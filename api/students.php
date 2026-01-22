<?php
/**
 * STUDENTS API - ENGLISH VERSION
 * ✅ curso_id is now OPTIONAL (set during enrollment)
 * ✅ username and password added for authentication
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
    // ==================== GET - LIST STUDENTS ====================
    case 'GET':
        try {
            if(isset($_GET['id'])) {
                // Não retornar senha na busca
                $query = "SELECT id, name, email, username, phone, birth_date, address, 
                          enrollment_number, bi_number, gender, curso_id, curso, 
                          enrollment_year, emergency_contact_1, emergency_contact_2, 
                          notes, status, last_login, created_at 
                          FROM students WHERE id = :id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':id', $_GET['id']);
            } else {
                $query = "SELECT id, name, email, username, phone, birth_date, address, 
                          enrollment_number, bi_number, gender, curso_id, curso, 
                          enrollment_year, emergency_contact_1, emergency_contact_2, 
                          notes, status, last_login, created_at 
                          FROM students ORDER BY name ASC";
                $stmt = $db->prepare($query);
            }
            
            $stmt->execute();
            $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            http_response_code(200);
            echo json_encode($students);
            
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode([
                "success" => false,
                "message" => "Error fetching students: " . $e->getMessage()
            ]);
        }
        break;

    // ==================== POST - CREATE STUDENT ====================
    case 'POST':
        $data = json_decode(file_get_contents("php://input"));
        
        // 🔍 VALIDATE REQUIRED FIELDS
        if(empty($data->name)) {
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "Name is required"]);
            exit;
        }
        
        if(empty($data->email) || !filter_var($data->email, FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "Valid email is required"]);
            exit;
        }
        
        if(empty($data->enrollment_number)) {
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "Enrollment number is required"]);
            exit;
        }
        
        if(empty($data->bi_number)) {
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "BI number is required"]);
            exit;
        }
        
        // 🆕 VALIDATE BI FORMAT (12 digits + 1 letter)
        if(!preg_match('/^\d{12}[A-Z]$/i', $data->bi_number)) {
            http_response_code(400);
            echo json_encode([
                "success" => false, 
                "message" => "Invalid BI format. Must be 12 digits + 1 letter (e.g., 110100123456P)"
            ]);
            exit;
        }
        
        if(empty($data->gender) || !in_array($data->gender, ['M', 'F'])) {
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "Gender must be M or F"]);
            exit;
        }
        
        // ✅ curso_id is now OPTIONAL - only validate if provided
        
        try {
            // ✅ Check if course exists (only if curso_id is provided)
            if(!empty($data->curso_id)) {
                $checkCurso = "SELECT COUNT(*) FROM cursos WHERE codigo = :curso_id AND status = 'ativo'";
                $stmtCheck = $db->prepare($checkCurso);
                $stmtCheck->bindParam(':curso_id', $data->curso_id);
                $stmtCheck->execute();
                
                if($stmtCheck->fetchColumn() == 0) {
                    http_response_code(400);
                    echo json_encode(["success" => false, "message" => "Invalid or inactive course"]);
                    exit;
                }
            }
            
            // ✅ HASH PASSWORD IF PROVIDED
            $password_hash = null;
            if(!empty($data->password)) {
                $password_hash = password_hash($data->password, PASSWORD_DEFAULT);
            }
            
            // INSERT WITH ALL FIELDS (English names) - curso_id OPTIONAL
            $query = "INSERT INTO students 
                      (name, email, username, password, phone, birth_date, address, 
                       enrollment_number, bi_number, gender, 
                       curso_id, curso, enrollment_year, 
                       emergency_contact_1, emergency_contact_2, notes, 
                       status) 
                      VALUES 
                      (:name, :email, :username, :password, :phone, :birth_date, :address, 
                       :enrollment_number, :bi_number, :gender, 
                       :curso_id, :curso, :enrollment_year, 
                       :emergency_contact_1, :emergency_contact_2, :notes, 
                       :status)";
            
            $stmt = $db->prepare($query);
            
            // Required fields
            $stmt->bindParam(':name', $data->name);
            $stmt->bindParam(':email', $data->email);
            $stmt->bindParam(':enrollment_number', $data->enrollment_number);
            $stmt->bindParam(':bi_number', $data->bi_number);
            $stmt->bindParam(':gender', $data->gender);
            
            // ✅ NEW: Login credentials
            $username = $data->username ?? null;
            $stmt->bindParam(':username', $username);
            $stmt->bindParam(':password', $password_hash);
            
            // Optional fields
            $curso_id = $data->curso_id ?? null; // ✅ CAN BE NULL
            $stmt->bindParam(':curso_id', $curso_id);
            
            $phone = $data->phone ?? null;
            $stmt->bindParam(':phone', $phone);
            
            $birth_date = $data->birth_date ?? null;
            $stmt->bindParam(':birth_date', $birth_date);
            
            $address = $data->address ?? null;
            $stmt->bindParam(':address', $address);
            
            $curso = $data->curso ?? null;
            $stmt->bindParam(':curso', $curso);
            
            $enrollment_year = $data->enrollment_year ?? date('Y');
            $stmt->bindParam(':enrollment_year', $enrollment_year);
            
            $emergency_contact_1 = $data->emergency_contact_1 ?? null;
            $stmt->bindParam(':emergency_contact_1', $emergency_contact_1);
            
            $emergency_contact_2 = $data->emergency_contact_2 ?? null;
            $stmt->bindParam(':emergency_contact_2', $emergency_contact_2);
            
            $notes = $data->notes ?? null;
            $stmt->bindParam(':notes', $notes);
            
            $status = $data->status ?? 'ativo';
            $stmt->bindParam(':status', $status);
            
            if($stmt->execute()) {
                $student_id = $db->lastInsertId();
                
                // 🔗 IF CLASS_ID PROVIDED, LINK STUDENT TO CLASS
                if(isset($data->class_id) && !empty($data->class_id) && $data->class_id > 0) {
                    try {
                        $queryClass = "INSERT INTO turma_estudantes 
                                      (turma_id, estudante_id, data_matricula, status) 
                                      VALUES (:class_id, :student_id, NOW(), 'ativo')";
                        
                        $stmtClass = $db->prepare($queryClass);
                        $stmtClass->bindParam(':class_id', $data->class_id);
                        $stmtClass->bindParam(':student_id', $student_id);
                        $stmtClass->execute();
                        
                        // Update occupied slots
                        $updateSlots = "UPDATE turmas SET 
                                       vagas_ocupadas = (
                                           SELECT COUNT(*) FROM turma_estudantes 
                                           WHERE turma_id = :class_id AND status = 'ativo'
                                       )
                                       WHERE id = :class_id";
                        
                        $stmtSlots = $db->prepare($updateSlots);
                        $stmtSlots->bindParam(':class_id', $data->class_id);
                        $stmtSlots->execute();
                        
                    } catch (PDOException $e) {
                        error_log("Error linking student to class: " . $e->getMessage());
                    }
                }
                
                http_response_code(201);
                echo json_encode([
                    "success" => true,
                    "message" => "Student created successfully",
                    "id" => $student_id
                ]);
            } else {
                http_response_code(503);
                echo json_encode(["success" => false, "message" => "Could not create student"]);
            }
            
        } catch (PDOException $e) {
            http_response_code(500);
            
            if(strpos($e->getMessage(), 'Duplicate entry') !== false) {
                if(strpos($e->getMessage(), 'email') !== false) {
                    echo json_encode(["success" => false, "message" => "Email already registered"]);
                } else if(strpos($e->getMessage(), 'enrollment_number') !== false) {
                    echo json_encode(["success" => false, "message" => "Enrollment number already exists"]);
                } else if(strpos($e->getMessage(), 'bi_number') !== false) {
                    echo json_encode(["success" => false, "message" => "BI number already registered"]);
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

    // ==================== PUT - UPDATE STUDENT ====================
    case 'PUT':
        $data = json_decode(file_get_contents("php://input"));
        
        if(empty($data->id)) {
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "Student ID is required"]);
            exit;
        }
        
        // Validate BI format if provided
        if(isset($data->bi_number) && !empty($data->bi_number)) {
            if(!preg_match('/^\d{12}[A-Z]$/i', $data->bi_number)) {
                http_response_code(400);
                echo json_encode([
                    "success" => false,
                    "message" => "Invalid BI format. Must be 12 digits + 1 letter"
                ]);
                exit;
            }
        }
        
        try {
            // ✅ BUILD DYNAMIC UPDATE QUERY (only update password if provided)
            $fields = [
                'name = :name',
                'email = :email',
                'username = :username',
                'phone = :phone',
                'birth_date = :birth_date',
                'address = :address',
                'enrollment_number = :enrollment_number',
                'bi_number = :bi_number',
                'gender = :gender',
                'curso_id = :curso_id',
                'curso = :curso',
                'enrollment_year = :enrollment_year',
                'emergency_contact_1 = :emergency_contact_1',
                'emergency_contact_2 = :emergency_contact_2',
                'notes = :notes',
                'status = :status'
            ];
            
            // Add password to update only if provided
            if(!empty($data->password)) {
                $fields[] = 'password = :password';
            }
            
            $query = "UPDATE students SET " . implode(', ', $fields) . " WHERE id = :id";
            
            $stmt = $db->prepare($query);
            
            $stmt->bindParam(':id', $data->id);
            $stmt->bindParam(':name', $data->name);
            $stmt->bindParam(':email', $data->email);
            $stmt->bindParam(':username', $data->username);
            $stmt->bindParam(':phone', $data->phone);
            $stmt->bindParam(':birth_date', $data->birth_date);
            $stmt->bindParam(':address', $data->address);
            $stmt->bindParam(':enrollment_number', $data->enrollment_number);
            $stmt->bindParam(':bi_number', $data->bi_number);
            $stmt->bindParam(':gender', $data->gender);
            $stmt->bindParam(':curso_id', $data->curso_id);
            $stmt->bindParam(':curso', $data->curso);
            $stmt->bindParam(':enrollment_year', $data->enrollment_year);
            $stmt->bindParam(':emergency_contact_1', $data->emergency_contact_1);
            $stmt->bindParam(':emergency_contact_2', $data->emergency_contact_2);
            $stmt->bindParam(':notes', $data->notes);
            $stmt->bindParam(':status', $data->status);
            
            // Bind password only if provided
            if(!empty($data->password)) {
                $password_hash = password_hash($data->password, PASSWORD_DEFAULT);
                $stmt->bindParam(':password', $password_hash);
            }
            
            if($stmt->execute()) {
                http_response_code(200);
                echo json_encode(["success" => true, "message" => "Student updated successfully"]);
            } else {
                http_response_code(503);
                echo json_encode(["success" => false, "message" => "Could not update student"]);
            }
            
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(["success" => false, "message" => "Error: " . $e->getMessage()]);
        }
        break;

    // ==================== DELETE - DELETE STUDENT ====================
    case 'DELETE':
        $data = json_decode(file_get_contents("php://input"));
        
        if(empty($data->id)) {
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "Student ID is required"]);
            exit;
        }
        
        try {
            // Soft delete
            $query = "UPDATE students SET status = 'inativo' WHERE id = :id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':id', $data->id);
            
            if($stmt->execute()) {
                http_response_code(200);
                echo json_encode(["success" => true, "message" => "Student removed successfully"]);
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