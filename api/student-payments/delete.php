<?php
header('Content-Type: application/json');
require_once '../../config/database.php';

$method = $_SERVER['REQUEST_METHOD'];
if ($method !== 'DELETE') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$payment_id = $data['payment_id'] ?? null;

if (!$payment_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing payment_id']);
    exit;
}

$query = "DELETE FROM student_payments WHERE id = ?";
$stmt = $pdo->prepare($query);
$stmt->execute([$payment_id]);

echo json_encode(['message' => 'Payment deleted']);
?>
