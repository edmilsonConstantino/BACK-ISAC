<?php
header('Content-Type: application/json');
require_once '../../config/database.php';

$method = $_SERVER['REQUEST_METHOD'];
if ($method !== 'PUT') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$plan_id = $data['plan_id'] ?? null;

if (!$plan_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing plan_id']);
    exit;
}

$query = "UPDATE student_payment_plans SET status = 'paid', paid_at = NOW() WHERE id = ?";
$stmt = $pdo->prepare($query);
$stmt->execute([$plan_id]);

echo json_encode(['message' => 'Month marked as paid']);
?>
