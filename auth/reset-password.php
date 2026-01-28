<?php
/**
 * ============================================================
 * RESET PASSWORD ENDPOINT
 * ============================================================
 * Valida o token de recuperação e actualiza a senha do utilizador.
 *
 * POST /auth/reset-password.php
 * Body: { "token": "abc123...", "password": "novaSenha123" }
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

// ============================================================
// RECEBER DADOS
// ============================================================
$data = json_decode(file_get_contents('php://input'), true);

if (!$data || empty($data['token']) || empty($data['password'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Token e nova senha são obrigatórios']);
    exit();
}

$token = trim($data['token']);
$new_password = $data['password'];

if (strlen($new_password) < 8) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'A senha deve ter pelo menos 8 caracteres']);
    exit();
}

if (!preg_match('/[A-Za-z]/', $new_password) || !preg_match('/[0-9]/', $new_password)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'A senha deve conter pelo menos uma letra e um número']);
    exit();
}

// Validar confirm_password se enviado pelo frontend
if (!empty($data['confirm_password']) && $data['confirm_password'] !== $new_password) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'As senhas não coincidem']);
    exit();
}

try {
    $database = new Database();
    $db = $database->getConnection();

    // ========================================
    // VALIDAR TOKEN
    // ========================================
    $stmt = $db->prepare("
        SELECT id, email, user_type, expires_at
        FROM password_resets
        WHERE token = :token AND used = 0
        LIMIT 1
    ");
    $stmt->bindParam(':token', $token);
    $stmt->execute();

    if ($stmt->rowCount() === 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Token inválido ou já utilizado']);
        exit();
    }

    $reset = $stmt->fetch(PDO::FETCH_ASSOC);

    // Verificar expiração
    if (strtotime($reset['expires_at']) < time()) {
        // Marcar como usado para não ser reutilizado
        $stmt = $db->prepare("UPDATE password_resets SET used = 1 WHERE id = :id");
        $stmt->bindParam(':id', $reset['id']);
        $stmt->execute();

        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Token expirado. Solicite uma nova recuperação']);
        exit();
    }

    // ========================================
    // ACTUALIZAR SENHA NA TABELA CORRECTA
    // ========================================
    $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
    $email = $reset['email'];
    $user_type = $reset['user_type'];

    $db->beginTransaction();

    switch ($user_type) {
        case 'admin':
        case 'academic_admin':
            $stmt = $db->prepare("UPDATE users SET senha = :password WHERE email = :email");
            break;

        case 'student':
            $stmt = $db->prepare("UPDATE students SET password = :password WHERE email = :email");
            break;

        case 'teacher':
            $stmt = $db->prepare("UPDATE professores SET password = :password WHERE email = :email");
            break;

        default:
            $db->rollBack();
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Tipo de utilizador inválido']);
            exit();
    }

    $stmt->bindParam(':password', $password_hash);
    $stmt->bindParam(':email', $email);
    $stmt->execute();

    // Marcar token como usado
    $stmt = $db->prepare("UPDATE password_resets SET used = 1 WHERE id = :id");
    $stmt->bindParam(':id', $reset['id']);
    $stmt->execute();

    // Invalidar refresh tokens (forçar re-login após mudar senha)
    // Buscar user_id para revogar tokens na tabela refresh_tokens
    $user_id_query = null;
    switch ($user_type) {
        case 'admin':
        case 'academic_admin':
            $user_id_query = $db->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
            break;
        case 'student':
            $user_id_query = $db->prepare("SELECT id FROM students WHERE email = :email LIMIT 1");
            break;
        case 'teacher':
            $user_id_query = $db->prepare("SELECT id FROM professores WHERE email = :email LIMIT 1");
            break;
    }

    if ($user_id_query) {
        $user_id_query->bindParam(':email', $email);
        $user_id_query->execute();
        $found_user = $user_id_query->fetch(PDO::FETCH_ASSOC);

        if ($found_user) {
            $stmt = $db->prepare("
                UPDATE refresh_tokens SET revoked_at = NOW()
                WHERE user_id = :user_id AND user_type = :user_type AND revoked_at IS NULL
            ");
            $stmt->bindParam(':user_id', $found_user['id']);
            $stmt->bindParam(':user_type', $user_type);
            $stmt->execute();
        }
    }

    $db->commit();

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Senha actualizada com sucesso. Faça login com a nova senha.'
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log("Reset password error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro interno do servidor']);
    exit();
}
