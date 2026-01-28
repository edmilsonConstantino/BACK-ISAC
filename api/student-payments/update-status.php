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
$payment_id = $data['payment_id'] ?? null;
$status = $data['status'] ?? null;

if (!$payment_id || !$status) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required fields']);
    exit;
}

$query = "UPDATE student_payments SET status = ? WHERE id = ?";
$stmt = $pdo->prepare($query);
$stmt->execute([$status, $payment_id]);

echo json_encode(['message' => 'Status updated']);
?>
