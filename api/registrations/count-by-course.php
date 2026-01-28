<?php
header("Access-Control-Allow-Origin: http://localhost:8080");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Headers: Authorization, Content-Type");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();

if (!isset($_GET['course_id'])) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "course_id required"]);
    exit;
}

try {
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM registrations WHERE course_id = ? AND status IN ('active','suspended')");
    $stmt->execute([$_GET['course_id']]);
    $total = (int)$stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    echo json_encode(["success" => true, "total" => $total, "next_number" => $total + 1]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}