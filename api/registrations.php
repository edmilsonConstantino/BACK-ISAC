<?php
// ✅ CORS PRIMEIRO (antes de require_once)
header("Access-Control-Allow-Origin: http://localhost:8080");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Headers: Authorization, Content-Type");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Content-Type: application/json; charset=UTF-8");

// ✅ Preflight sai ANTES de qualquer validação/include
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

/**
 * REGISTRATIONS API - ATUALIZADO (PASSO 3)
 * ✅ Credenciais salvas APENAS na tabela students
 * ✅ Geração de username/password acontece SOMENTE na PRIMEIRA matrícula
 * ✅ Matrículas seguintes: reutiliza credenciais existentes (não sobrescreve)
 * 
 * 📁 LOCATION: api/registrations.php
 */

require_once '../config/database.php';
require_once '../auth/AuthMiddleware.php';

AuthMiddleware::validate();

$database = new Database();
$db = $database->getConnection();

$method = $_SERVER['REQUEST_METHOD'];

switch($method) {
    // ==================== GET - LIST REGISTRATIONS ====================
    case 'GET':
        try {
            if(isset($_GET['id'])) {
                $query = "SELECT 
                            r.*,
                            s.name as student_name,
                            s.email as student_email,
                            c.nome as course_name,
                            t.nome as class_name
                          FROM registrations r
                          INNER JOIN students s ON r.student_id = s.id
                          INNER JOIN cursos c ON r.course_id = c.codigo
                          LEFT JOIN turmas t ON r.class_id = t.id
                          WHERE r.id = :id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':id', $_GET['id']);
            } else if(isset($_GET['student_id'])) {
                $query = "SELECT 
                            r.*,
                            s.name as student_name,
                            s.email as student_email,
                            c.nome as course_name,
                            t.nome as class_name
                          FROM registrations r
                          INNER JOIN students s ON r.student_id = s.id
                          INNER JOIN cursos c ON r.course_id = c.codigo
                          LEFT JOIN turmas t ON r.class_id = t.id
                          WHERE r.student_id = :student_id
                          ORDER BY r.created_at DESC";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':student_id', $_GET['student_id']);
            } else {
                $query = "SELECT 
                            r.*,
                            s.name as student_name,
                            s.email as student_email,
                            c.nome as course_name,
                            t.nome as class_name
                          FROM registrations r
                          INNER JOIN students s ON r.student_id = s.id
                          INNER JOIN cursos c ON r.course_id = c.codigo
                          LEFT JOIN turmas t ON r.class_id = t.id
                          ORDER BY r.created_at DESC";
                $stmt = $db->prepare($query);
            }
            
            $stmt->execute();
            $registrations = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            http_response_code(200);
            echo json_encode($registrations);
            
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode([
                "success" => false,
                "message" => "Error fetching registrations: " . $e->getMessage()
            ]);
        }
        break;

    // ==================== POST - CREATE REGISTRATION ====================
    case 'POST':
        $data = json_decode(file_get_contents("php://input"));
        
        // 🔍 VALIDATE REQUIRED FIELDS (básicos da matrícula)
        if(empty($data->student_id)) {
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "Student ID is required"]);
            exit;
        }
        
        if(empty($data->course_id)) {
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "Course ID is required"]);
            exit;
        }
        
        if(empty($data->enrollment_number)) {
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "Enrollment number is required"]);
            exit;
        }
        
        if(empty($data->period)) {
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "Period is required"]);
            exit;
        }
        
        if(empty($data->enrollment_date)) {
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "Enrollment date is required"]);
            exit;
        }
        
        try {
            $db->beginTransaction();
            
            // 1️⃣ VERIFICAR SE ESTUDANTE EXISTE
            $checkStudent = "SELECT COUNT(*) FROM students WHERE id = :student_id";
            $stmtCheck = $db->prepare($checkStudent);
            $stmtCheck->bindParam(':student_id', $data->student_id);
            $stmtCheck->execute();
            
            if($stmtCheck->fetchColumn() == 0) {
                $db->rollBack();
                http_response_code(400);
                echo json_encode(["success" => false, "message" => "Student not found"]);
                exit;
            }
            
            // 2️⃣ VERIFICAR SE CURSO EXISTE
            $checkCourse = "SELECT COUNT(*) FROM cursos WHERE codigo = :course_id AND status = 'ativo'";
            $stmtCheck = $db->prepare($checkCourse);
            $stmtCheck->bindParam(':course_id', $data->course_id);
            $stmtCheck->execute();
            
            if($stmtCheck->fetchColumn() == 0) {
                $db->rollBack();
                http_response_code(400);
                echo json_encode(["success" => false, "message" => "Course not found or inactive"]);
                exit;
            }
            
            // 3️⃣ VERIFICAR DUPLICAÇÃO DE MATRÍCULA
            $checkDuplicate = "SELECT COUNT(*) FROM registrations 
                              WHERE student_id = :student_id 
                              AND course_id = :course_id 
                              AND period = :period";
            $stmtCheck = $db->prepare($checkDuplicate);
            $stmtCheck->execute([
                ':student_id' => $data->student_id,
                ':course_id'  => $data->course_id,
                ':period'     => $data->period
            ]);
            
            if($stmtCheck->fetchColumn() > 0) {
                $db->rollBack();
                http_response_code(400);
                echo json_encode([
                    "success" => false, 
                    "message" => "Student already enrolled in this course for this period"
                ]);
                exit;
            }
            
            // ========================================
            // VERIFICAR SE ESTUDANTE JÁ TEM CREDENCIAIS
            // ========================================
            $checkCredentials = "SELECT username, password FROM students WHERE id = :student_id";
            $stmtCheck = $db->prepare($checkCredentials);
            $stmtCheck->bindParam(':student_id', $data->student_id);
            $stmtCheck->execute();
            $studentCredentials = $stmtCheck->fetch(PDO::FETCH_ASSOC);

            // Se NÃO tiver credenciais → Gerar (primeira matrícula)
            if (empty($studentCredentials['username']) || empty($studentCredentials['password'])) {
                
                // Validar que frontend enviou username/password
                if (empty($data->username) || empty($data->password)) {
                    $db->rollBack();
                    http_response_code(400);
                    echo json_encode([
                        "success" => false,
                        "message" => "Username and password are required for first enrollment"
                    ]);
                    exit;
                }
                
                // Hash da senha
                $password_hash = password_hash($data->password, PASSWORD_DEFAULT);
                
                // Atualizar tabela students com credenciais
                $updateCredentials = "UPDATE students 
                                     SET username = :username, 
                                         password = :password 
                                     WHERE id = :student_id";
                
                $stmtUpdate = $db->prepare($updateCredentials);
                $stmtUpdate->bindParam(':username', $data->username);
                $stmtUpdate->bindParam(':password', $password_hash);
                $stmtUpdate->bindParam(':student_id', $data->student_id);
                
                if (!$stmtUpdate->execute()) {
                    $db->rollBack();
                    http_response_code(503);
                    echo json_encode([
                        "success" => false,
                        "message" => "Failed to create credentials"
                    ]);
                    exit;
                }
                
                // echo "✅ Credenciais criadas para primeira matrícula\n"; // Debug (remova em produção)
            } // else { 
                // echo "✅ Estudante já possui credenciais existentes\n"; // Debug (remova em produção)
            // }

            // ========================================
            // INSERIR MATRÍCULA NA TABELA REGISTRATIONS (sem username/password)
            // ========================================
            $query = "INSERT INTO registrations 
                      (student_id, course_id, class_id, enrollment_number, period, 
                       enrollment_date, status, payment_status, enrollment_fee, 
                       monthly_fee, observations) 
                      VALUES 
                      (:student_id, :course_id, :class_id, :enrollment_number, :period,
                       :enrollment_date, :status, :payment_status, :enrollment_fee,
                       :monthly_fee, :observations)";
            
            $stmt = $db->prepare($query);
            
            $stmt->bindParam(':student_id', $data->student_id);
            $stmt->bindParam(':course_id', $data->course_id);
            
            $class_id = $data->class_id ?? null;
            $stmt->bindParam(':class_id', $class_id);
            
            $stmt->bindParam(':enrollment_number', $data->enrollment_number);
            $stmt->bindParam(':period', $data->period);
            $stmt->bindParam(':enrollment_date', $data->enrollment_date);
            
            $status = $data->status ?? 'active';
            $stmt->bindParam(':status', $status);
            
            $payment_status = $data->payment_status ?? 'pending';
            $stmt->bindParam(':payment_status', $payment_status);
            
            $enrollment_fee = $data->enrollment_fee ?? 0.00;
            $stmt->bindParam(':enrollment_fee', $enrollment_fee);
            
            $monthly_fee = $data->monthly_fee ?? 0.00;
            $stmt->bindParam(':monthly_fee', $monthly_fee);
            
            $observations = $data->observations ?? null;
            $stmt->bindParam(':observations', $observations);
            
            if(!$stmt->execute()) {
                $db->rollBack();
                http_response_code(503);
                echo json_encode(["success" => false, "message" => "Could not create registration"]);
                exit;
            }
            
            $registration_id = $db->lastInsertId();
            
            $db->commit();
            
            http_response_code(201);
            echo json_encode([
                "success" => true,
                "message" => empty($studentCredentials['username']) || empty($studentCredentials['password'])
                    ? "Registration created successfully and student credentials set" 
                    : "Registration created successfully (existing credentials reused)",
                "id" => $registration_id
            ]);
            
        } catch (PDOException $e) {
            if($db->inTransaction()) {
                $db->rollBack();
            }
            
            http_response_code(500);
            
            if(strpos($e->getMessage(), 'Duplicate entry') !== false) {
                if(strpos($e->getMessage(), 'enrollment_number') !== false) {
                    echo json_encode(["success" => false, "message" => "Enrollment number already exists"]);
                } else if(strpos($e->getMessage(), 'username') !== false) {
                    echo json_encode(["success" => false, "message" => "Username already exists"]);
                } else {
                    echo json_encode(["success" => false, "message" => "Duplicate record"]);
                }
            } else {
                echo json_encode(["success" => false, "message" => "Error: " . $e->getMessage()]);
            }
        }
        break;

    // ==================== PUT e DELETE (sem alterações) ====================
    case 'PUT':
        $data = json_decode(file_get_contents("php://input"));
        
        if(empty($data->id)) {
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "Registration ID is required"]);
            exit;
        }
        
        try {
            $query = "UPDATE registrations SET 
                      class_id = :class_id,
                      period = :period,
                      status = :status,
                      payment_status = :payment_status,
                      enrollment_fee = :enrollment_fee,
                      monthly_fee = :monthly_fee,
                      observations = :observations
                      WHERE id = :id";
            
            $stmt = $db->prepare($query);
            
            $stmt->bindParam(':id', $data->id);
            $stmt->bindParam(':class_id', $data->class_id);
            $stmt->bindParam(':period', $data->period);
            $stmt->bindParam(':status', $data->status);
            $stmt->bindParam(':payment_status', $data->payment_status);
            $stmt->bindParam(':enrollment_fee', $data->enrollment_fee);
            $stmt->bindParam(':monthly_fee', $data->monthly_fee);
            $stmt->bindParam(':observations', $data->observations);
            
            if($stmt->execute()) {
                http_response_code(200);
                echo json_encode(["success" => true, "message" => "Registration updated successfully"]);
            } else {
                http_response_code(503);
                echo json_encode(["success" => false, "message" => "Could not update registration"]);
            }
            
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(["success" => false, "message" => "Error: " . $e->getMessage()]);
        }
        break;

    case 'DELETE':
        $data = json_decode(file_get_contents("php://input"));
        
        if(empty($data->id)) {
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "Registration ID is required"]);
            exit;
        }
        
        try {
            $query = "UPDATE registrations SET status = 'cancelled' WHERE id = :id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':id', $data->id);
            
            if($stmt->execute()) {
                http_response_code(200);
                echo json_encode(["success" => true, "message" => "Registration cancelled successfully"]);
            } else {
                http_response_code(503);
                echo json_encode(["success" => false, "message" => "Could not cancel registration"]);
            }
            
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(["success" => false, "message" => "Error: " . $e->getMessage()]);
        }
        break;

    default:
        http_response_code(405);
        echo json_encode(["success" => false, "message" => "Method not allowed"]);
        break;
}
?>