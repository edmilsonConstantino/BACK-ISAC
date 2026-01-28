<?php
/**
 * ============================================================
 * LOGOUT ENDPOINT
 * ============================================================
 * Revoga TODOS os refresh tokens do utilizador na tabela refresh_tokens.
 * Funciona para todos os tipos: admin, academic_admin, student, teacher.
 *
 * POST /auth/logout.php
 * Header: Authorization: Bearer {access_token}
 * ============================================================
 */

ini_set('display_errors', 0);
error_reporting(E_ALL);

// ============================================================
// CORS HEADERS
// ============================================================
$allowed_origins = [
    'http://localhost:3000',
    'http://localhost:5173',
    'http://localhost:8080',
    'http://127.0.0.1:3000',
    'http://127.0.0.1:5173',
    'http://127.0.0.1:8080'
];

$origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';

if (in_array($origin, $allowed_origins)) {
    header('Access-Control-Allow-Origin: ' . $origin);
} else {
    header('Access-Control-Allow-Origin: http://localhost:8080');
}

header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido. Use POST']);
    exit();
}

// ============================================================
// CARREGAR DEPENDÊNCIAS
// ============================================================
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/JWTHandler.php';

// ============================================================
// EXTRAIR TOKEN DO HEADER
// ============================================================
$headers = null;

if (isset($_SERVER['Authorization'])) {
    $headers = trim($_SERVER['Authorization']);
} elseif (isset($_SERVER['HTTP_AUTHORIZATION'])) {
    $headers = trim($_SERVER['HTTP_AUTHORIZATION']);
} elseif (function_exists('apache_request_headers')) {
    $requestHeaders = apache_request_headers();
    $requestHeaders = array_combine(
        array_map('ucwords', array_keys($requestHeaders)),
        array_values($requestHeaders)
    );
    if (isset($requestHeaders['Authorization'])) {
        $headers = trim($requestHeaders['Authorization']);
    }
}

$token = null;
if (!empty($headers) && preg_match('/Bearer\s(\S+)/', $headers, $matches)) {
    $token = $matches[1];
}

if (!$token) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Token não fornecido']);
    exit();
}

try {
    $jwt = new JWTHandler();
    $decoded = $jwt->validateToken($token);

    if (!$decoded) {
        // Mesmo com token expirado/inválido, aceitamos o logout
        http_response_code(200);
        echo json_encode(['success' => true, 'message' => 'Sessão terminada']);
        exit();
    }

    $user_id = $decoded->data->user_id;
    $user_type = $decoded->data->user_type ?? $decoded->data->role ?? null;

    $database = new Database();
    $db = $database->getConnection();

    // ========================================
    // REVOGAR TODOS OS REFRESH TOKENS DO UTILIZADOR
    // ========================================
    $type_map = [
        'admin' => 'admin',
        'academic_admin' => 'academic_admin',
        'student' => 'student',
        'teacher' => 'teacher'
    ];

    $mapped_type = $type_map[$user_type] ?? null;

    if ($mapped_type) {
        // Revogar tokens deste tipo
        $stmt = $db->prepare("
            UPDATE refresh_tokens
            SET revoked_at = NOW()
            WHERE user_id = :user_id
              AND user_type = :user_type
              AND revoked_at IS NULL
        ");
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':user_type', $mapped_type);
        $stmt->execute();
    } else {
        // user_type desconhecido (token antigo/bugado) — não revogar às cegas
        error_log("Logout sem user_type válido no token para user_id=$user_id");
    }

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Sessão terminada com sucesso'
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    error_log("Logout error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro interno do servidor']);
    exit();
}
