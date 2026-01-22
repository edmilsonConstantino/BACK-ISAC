<?php
/**
 * COUNT REGISTRATIONS BY COURSE
 * Conta quantos estudantes já estão matriculados em um curso
 * 
 * 📁 LOCATION: api/registrations/count-by-course.php
 */

require_once '../../config/database.php';
require_once '../../auth/AuthMiddleware.php';

AuthMiddleware::validate();

$database = new Database();
$db = $database->getConnection();

if (!isset($_GET['course_id'])) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "course_id is required"
    ]);
    exit;
}

$course_id = $_GET['course_id'];

try {
    // Contar estudantes matriculados no curso (ativos)
    $query = "SELECT COUNT(*) as total 
              FROM registrations 
              WHERE course_id = :course_id 
              AND status IN ('active', 'suspended')";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':course_id', $course_id);
    $stmt->execute();
    
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $total = $result['total'];
    
    // Próximo número sequencial
    $next_number = $total + 1;
    
    http_response_code(200);
    echo json_encode([
        "success" => true,
        "total" => $total,
        "next_number" => $next_number
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Error: " . $e->getMessage()
    ]);
}
?>