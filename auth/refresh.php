<?php
/**
 * ============================================================
 * REFRESH TOKEN ENDPOINT
 * ============================================================
 * Renova o access_token usando um refresh_token válido.
 * Usa a tabela refresh_tokens para validação de TODOS os utilizadores.
 * Implementa rotação: o refresh token antigo é revogado e um novo é emitido.
 *
 * POST /auth/refresh.php
 * Body: { "refresh_token": "eyJ0eXAiOiJKV1Q..." }
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
// RECEBER DADOS
// ============================================================
$data = json_decode(file_get_contents('php://input'), true);

if (!$data || empty($data['refresh_token'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Refresh token é obrigatório']);
    exit();
}

$refresh_token = trim($data['refresh_token']);

try {
    $jwt = new JWTHandler();

    // ========================================
    // 1. VALIDAR JWT DO REFRESH TOKEN
    // ========================================
    $decoded = $jwt->validateToken($refresh_token);

    if (!$decoded) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Refresh token inválido ou expirado']);
        exit();
    }

    if (!isset($decoded->data->type) || $decoded->data->type !== 'refresh') {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Token fornecido não é um refresh token']);
        exit();
    }

    $user_id = $decoded->data->user_id;
    $user_type = $decoded->data->user_type ?? null;

    $database = new Database();
    $db = $database->getConnection();

    // ========================================
    // 2. VERIFICAR TOKEN NA TABELA refresh_tokens
    // ========================================
    $token_hash = hash('sha256', $refresh_token);

    $stmt = $db->prepare("
        SELECT id, user_id, user_type
        FROM refresh_tokens
        WHERE token_hash = :hash
          AND revoked_at IS NULL
          AND expires_at > NOW()
        LIMIT 1
    ");
    $stmt->bindParam(':hash', $token_hash);
    $stmt->execute();

    if ($stmt->rowCount() === 0) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Refresh token revogado ou não encontrado']);
        exit();
    }

    $tokenRow = $stmt->fetch(PDO::FETCH_ASSOC);

    // Cruzar user_id do JWT com o da tabela (segurança extra)
    if ((int)$tokenRow['user_id'] !== (int)$user_id) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Refresh token inválido']);
        exit();
    }

    // Cruzar user_type do JWT com o da tabela (protege contra tokens de versões antigas)
    $jwt_user_type = $decoded->data->user_type ?? null;
    if ($jwt_user_type && $jwt_user_type !== $tokenRow['user_type']) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Refresh token inválido']);
        exit();
    }

    $user_type = $tokenRow['user_type'];

    // ========================================
    // 3. BUSCAR DADOS DO UTILIZADOR
    // ========================================
    $user = null;

    switch ($user_type) {
        case 'admin':
        case 'academic_admin':
            $stmt = $db->prepare("SELECT id, nome, email, role FROM users WHERE id = :id AND status = 'active' LIMIT 1");
            $stmt->bindParam(':id', $user_id);
            $stmt->execute();
            if ($stmt->rowCount() > 0) {
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
            }
            break;

        case 'student':
            $stmt = $db->prepare("SELECT id, name as nome, email FROM students WHERE id = :id AND status = 'ativo' LIMIT 1");
            $stmt->bindParam(':id', $user_id);
            $stmt->execute();
            if ($stmt->rowCount() > 0) {
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                $user['role'] = 'student';
            }
            break;

        case 'teacher':
            $stmt = $db->prepare("SELECT id, nome, email FROM professores WHERE id = :id AND status = 'ativo' LIMIT 1");
            $stmt->bindParam(':id', $user_id);
            $stmt->execute();
            if ($stmt->rowCount() > 0) {
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                $user['role'] = 'teacher';
            }
            break;
    }

    if (!$user) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Utilizador não encontrado ou inactivo']);
        exit();
    }

    // ========================================
    // 4. ROTAÇÃO: REVOGAR TOKEN ANTIGO, EMITIR NOVO
    // ========================================
    $db->beginTransaction();

    // Revogar o token antigo
    $stmt = $db->prepare("UPDATE refresh_tokens SET revoked_at = NOW() WHERE id = :id");
    $stmt->bindParam(':id', $tokenRow['id']);
    $stmt->execute();

    // Gerar novos tokens
    $new_access_token = $jwt->generateToken($user['id'], $user['email'], $user['role'], $user_type);
    $new_refresh_token = $jwt->generateRefreshToken($user['id'], $user_type);
    $new_token_hash = hash('sha256', $new_refresh_token);
    $new_expires = date('Y-m-d H:i:s', strtotime('+7 days'));

    // Guardar novo refresh token
    $stmt = $db->prepare("
        INSERT INTO refresh_tokens (user_id, user_type, token_hash, expires_at)
        VALUES (:user_id, :user_type, :token_hash, :expires_at)
    ");
    $stmt->bindParam(':user_id', $user['id']);
    $stmt->bindParam(':user_type', $user_type);
    $stmt->bindParam(':token_hash', $new_token_hash);
    $stmt->bindParam(':expires_at', $new_expires);
    $stmt->execute();

    $db->commit();

    // ========================================
    // 5. RESPOSTA
    // ========================================
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Token renovado com sucesso',
        'data' => [
            'user' => $user,
            'access_token' => $new_access_token,
            'refresh_token' => $new_refresh_token,
            'token_type' => 'Bearer',
            'expires_in' => 3600
        ]
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log("Refresh token error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro interno do servidor']);
    exit();
}
