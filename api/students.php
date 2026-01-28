<?php
/**
 * STUDENTS API - ENGLISH VERSION - ATUALIZADO
 * 
 * ✅ Listagem geral: inclui curso da última matrícula ativa + mensalidade + username
 * ✅ Detalhe por ID: retorna todas as matrículas do estudante + username/password
 * ✅ POST: cadastro simples → SEM username e password
 * 
 * 📁 LOCATION: api/students.php
 */

require_once '../config/database.php';
require_once '../auth/AuthMiddleware.php';

AuthMiddleware::validate();

$database = new Database();
$db = $database->getConnection();

$method = $_SERVER['REQUEST_METHOD'];

switch($method) {
    // ==================== GET - LIST STUDENTS / GET ONE ====================
    case 'GET':
        try {
            if(isset($_GET['id'])) {
                // Detalhe de um estudante + TODAS as matrículas
                $studentQuery = "
                    SELECT 
                        s.id, s.name, s.email, s.username, s.password, s.phone, s.birth_date, s.address,
                        s.enrollment_number, s.bi_number, s.gender, s.curso_id, s.curso,
                        s.enrollment_year, s.emergency_contact_1, s.emergency_contact_2,
                        s.notes, s.status, s.last_login, s.created_at
                    FROM students s
                    WHERE s.id = :id
                ";
                $stmt = $db->prepare($studentQuery);
                $stmt->bindParam(':id', $_GET['id']);
                $stmt->execute();
                $student = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$student) {
                    http_response_code(404);
                    echo json_encode(["success" => false, "message" => "Student not found"]);
                    exit;
                }

                $regQuery = "
                    SELECT 
                        r.id, r.course_id, c.nome AS course_name,
                        r.period, r.enrollment_date, r.status, r.payment_status,
                        r.enrollment_fee, r.monthly_fee, r.observations,
                        r.class_id, t.nome AS class_name
                    FROM registrations r
                    LEFT JOIN cursos c ON r.course_id = c.codigo
                    LEFT JOIN turmas t ON r.class_id = t.id
                    WHERE r.student_id = :student_id
                    ORDER BY r.created_at DESC
                ";
                $stmtReg = $db->prepare($regQuery);
                $stmtReg->bindParam(':student_id', $_GET['id']);
                $stmtReg->execute();
                $registrations = $stmtReg->fetchAll(PDO::FETCH_ASSOC);

                $student['registrations'] = $registrations;

                http_response_code(200);
                echo json_encode($student);
            } else {
                // ✅ LISTAGEM GERAL - Incluindo username para verificação
                $query = "
                    SELECT 
                        s.id,
                        s.name,
                        s.email,
                        s.phone,
                        s.bi_number,
                        s.gender,
                        s.address,
                        s.enrollment_number,
                        s.username,
                        s.status,
                        s.created_at,
                        r.course_id,
                        c.nome AS className,
                        c.mensalidade AS monthlyFee
                    FROM students s
                    LEFT JOIN (
                        SELECT 
                            student_id, 
                            course_id, 
                            enrollment_number,
                            ROW_NUMBER() OVER (PARTITION BY student_id ORDER BY id DESC) AS rn
                        FROM registrations 
                        WHERE status = 'active'
                    ) r ON s.id = r.student_id AND r.rn = 1
                    LEFT JOIN cursos c ON r.course_id = c.codigo
                    ORDER BY s.name ASC
                ";

                $stmt = $db->prepare($query);
                $stmt->execute();
                $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                http_response_code(200);
                echo json_encode($students);
            }
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode([
                "success" => false,
                "message" => "Error fetching students: " . $e->getMessage()
            ]);
        }
        break;

    // ==================== POST - CREATE STUDENT (sem credenciais) ====================
    case 'POST':
        $data = json_decode(file_get_contents("php://input"));
        
        // Validações obrigatórias
        if (empty($data->name)) {
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "Name is required"]);
            exit;
        }
        
        if (empty($data->email) || !filter_var($data->email, FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "Valid email is required"]);
            exit;
        }
        
        if (empty($data->bi_number)) {
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "BI number is required"]);
            exit;
        }
        
        if (!preg_match('/^\d{12}[A-Z]$/i', $data->bi_number)) {
            http_response_code(400);
            echo json_encode([
                "success" => false, 
                "message" => "Invalid BI format. Must be 12 digits + 1 letter (e.g., 110100123456P)"
            ]);
            exit;
        }
        
        if (empty($data->gender) || !in_array($data->gender, ['M', 'F'])) {
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "Gender must be M or F"]);
            exit;
        }

        try {
            // Verifica se curso foi informado (opcional)
            if (!empty($data->curso_id)) {
                $checkCurso = "SELECT COUNT(*) FROM cursos WHERE codigo = :curso_id AND status = 'ativo'";
                $stmtCheck = $db->prepare($checkCurso);
                $stmtCheck->bindParam(':curso_id', $data->curso_id);
                $stmtCheck->execute();
                if ($stmtCheck->fetchColumn() == 0) {
                    http_response_code(400);
                    echo json_encode(["success" => false, "message" => "Invalid or inactive course"]);
                    exit;
                }
            }

            // INSERT SIMPLES - sem username e password
            $query = "
                INSERT INTO students 
                (name, email, phone, birth_date, address, enrollment_number, 
                 bi_number, gender, status) 
                VALUES 
                (:name, :email, :phone, :birth_date, :address, :enrollment_number,
                 :bi_number, :gender, :status)
            ";

            $stmt = $db->prepare($query);

            // Campos obrigatórios
            $stmt->bindParam(':name', $data->name);
            $stmt->bindParam(':email', $data->email);
            $stmt->bindParam(':bi_number', $data->bi_number);
            $stmt->bindParam(':gender', $data->gender);

            // Campos opcionais / default
            $phone = $data->phone ?? null;
            $stmt->bindParam(':phone', $phone);

            $birth_date = $data->birth_date ?? null;
            $stmt->bindParam(':birth_date', $birth_date);

            $address = $data->address ?? null;
            $stmt->bindParam(':address', $address);

            $enrollment_number = $data->enrollment_number ?? null;
            $stmt->bindParam(':enrollment_number', $enrollment_number);

            $status = $data->status ?? 'ativo';
            $stmt->bindParam(':status', $status);

            if ($stmt->execute()) {
                $student_id = $db->lastInsertId();
                http_response_code(201);
                echo json_encode([
                    "success" => true,
                    "message" => "Student profile created successfully (credentials will be set on first enrollment)",
                    "id" => $student_id
                ]);
            } else {
                http_response_code(503);
                echo json_encode(["success" => false, "message" => "Could not create student"]);
            }
            
        } catch (PDOException $e) {
            http_response_code(500);
            
            if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                if (strpos($e->getMessage(), 'email') !== false) {
                    echo json_encode(["success" => false, "message" => "Email already registered"]);
                } else if (strpos($e->getMessage(), 'bi_number') !== false) {
                    echo json_encode(["success" => false, "message" => "BI number already registered"]);
                } else {
                    echo json_encode(["success" => false, "message" => "Duplicate record"]);
                }
            } else {
                echo json_encode(["success" => false, "message" => "Error: " . $e->getMessage()]);
            }
        }
        break;

    // ==================== PUT - UPDATE STUDENT ====================
    case 'PUT':
        $data = json_decode(file_get_contents("php://input"));
        
        if (empty($data->id)) {
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "Student ID is required"]);
            exit;
        }

        try {
            // Construir query dinâmica baseada nos campos fornecidos
            $updates = [];
            $params = [':id' => $data->id];

            if (isset($data->name)) {
                $updates[] = "name = :name";
                $params[':name'] = $data->name;
            }
            if (isset($data->email)) {
                if (!filter_var($data->email, FILTER_VALIDATE_EMAIL)) {
                    http_response_code(400);
                    echo json_encode(["success" => false, "message" => "Invalid email format"]);
                    exit;
                }
                $updates[] = "email = :email";
                $params[':email'] = $data->email;
            }
            if (isset($data->phone)) {
                $updates[] = "phone = :phone";
                $params[':phone'] = $data->phone;
            }
            if (isset($data->birth_date)) {
                $updates[] = "birth_date = :birth_date";
                $params[':birth_date'] = $data->birth_date;
            }
            if (isset($data->address)) {
                $updates[] = "address = :address";
                $params[':address'] = $data->address;
            }
            if (isset($data->bi_number)) {
                $updates[] = "bi_number = :bi_number";
                $params[':bi_number'] = $data->bi_number;
            }
            if (isset($data->gender)) {
                $updates[] = "gender = :gender";
                $params[':gender'] = $data->gender;
            }
            if (isset($data->status)) {
                $updates[] = "status = :status";
                $params[':status'] = $data->status;
            }
            if (isset($data->username)) {
                $updates[] = "username = :username";
                $params[':username'] = $data->username;
            }
            if (isset($data->password)) {
                $updates[] = "password = :password";
                $params[':password'] = $data->password;
            }

            if (empty($updates)) {
                http_response_code(400);
                echo json_encode(["success" => false, "message" => "No fields to update"]);
                exit;
            }

            $query = "UPDATE students SET " . implode(', ', $updates) . " WHERE id = :id";
            $stmt = $db->prepare($query);

            if ($stmt->execute($params)) {
                http_response_code(200);
                echo json_encode([
                    "success" => true,
                    "message" => "Student updated successfully"
                ]);
            } else {
                http_response_code(503);
                echo json_encode(["success" => false, "message" => "Could not update student"]);
            }
            
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(["success" => false, "message" => "Error: " . $e->getMessage()]);
        }
        break;

    // ==================== DELETE - REMOVE STUDENT ====================
    case 'DELETE':
        if (empty($_GET['id'])) {
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "Student ID is required"]);
            exit;
        }

        try {
            // Verificar se estudante tem matrículas ativas
            $checkQuery = "SELECT COUNT(*) FROM registrations WHERE student_id = :id AND status = 'active'";
            $checkStmt = $db->prepare($checkQuery);
            $checkStmt->bindParam(':id', $_GET['id']);
            $checkStmt->execute();
            
            if ($checkStmt->fetchColumn() > 0) {
                http_response_code(400);
                echo json_encode([
                    "success" => false, 
                    "message" => "Cannot delete student with active enrollments. Cancel enrollments first."
                ]);
                exit;
            }

            $query = "DELETE FROM students WHERE id = :id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':id', $_GET['id']);

            if ($stmt->execute()) {
                if ($stmt->rowCount() > 0) {
                    http_response_code(200);
                    echo json_encode([
                        "success" => true,
                        "message" => "Student deleted successfully"
                    ]);
                } else {
                    http_response_code(404);
                    echo json_encode(["success" => false, "message" => "Student not found"]);
                }
            } else {
                http_response_code(503);
                echo json_encode(["success" => false, "message" => "Could not delete student"]);
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