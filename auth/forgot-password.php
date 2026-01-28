<?php
/**
 * ============================================================
 * FORGOT PASSWORD ENDPOINT
 * ============================================================
 * Gera um token de recuperação de senha e guarda na tabela password_resets.
 * Funciona para os 3 tipos de utilizador (admin, professor, estudante).
 *
 * POST /auth/forgot-password.php
 * Body: { "email": "user@example.com" }
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

if (!$data || empty($data['email'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Email é obrigatório']);
    exit();
}

$email = trim($data['email']);

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Email inválido']);
    exit();
}

try {
    $database = new Database();
    $db = $database->getConnection();

    $user_type = null;

    // ========================================
    // PROCURAR EM QUAL TABELA O EMAIL EXISTE
    // ========================================

    // 1. Tabela users (admin / academic_admin)
    $stmt = $db->prepare("SELECT id, email, role FROM users WHERE email = :email AND status = 'active' LIMIT 1");
    $stmt->bindParam(':email', $email);
    $stmt->execute();

    if ($stmt->rowCount() > 0) {
        $found = $stmt->fetch(PDO::FETCH_ASSOC);
        $user_type = $found['role']; // admin ou academic_admin
    }

    // 2. Tabela students
    if (!$user_type) {
        $stmt = $db->prepare("SELECT id, email FROM students WHERE email = :email AND status = 'ativo' LIMIT 1");
        $stmt->bindParam(':email', $email);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $user_type = 'student';
        }
    }

    // 3. Tabela professores
    if (!$user_type) {
        $stmt = $db->prepare("SELECT id, email FROM professores WHERE email = :email AND status = 'ativo' LIMIT 1");
        $stmt->bindParam(':email', $email);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $user_type = 'teacher';
        }
    }

    // ========================================
    // RESPOSTA GENÉRICA (segurança: não revelar se email existe)
    // ========================================
    if (!$user_type) {
        // Retornamos sucesso mesmo assim para não revelar se o email existe
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Se o email estiver registado, receberá instruções de recuperação'
        ]);
        exit();
    }

    // ========================================
    // INVALIDAR TOKENS ANTERIORES DO MESMO EMAIL
    // ========================================
    $stmt = $db->prepare("UPDATE password_resets SET used = 1 WHERE email = :email AND used = 0");
    $stmt->bindParam(':email', $email);
    $stmt->execute();

    // ========================================
    // GERAR TOKEN E GUARDAR
    // ========================================
    $token = bin2hex(random_bytes(32)); // 64 caracteres hex
    $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour'));

    $stmt = $db->prepare("
        INSERT INTO password_resets (email, token, user_type, expires_at)
        VALUES (:email, :token, :user_type, :expires_at)
    ");
    $stmt->bindParam(':email', $email);
    $stmt->bindParam(':token', $token);
    $stmt->bindParam(':user_type', $user_type);
    $stmt->bindParam(':expires_at', $expires_at);
    $stmt->execute();

    // ========================================
    // RESPOSTA
    // ========================================
    // Em produção, aqui enviarias um email com o link de reset.
    $response = [
        'success' => true,
        'message' => 'Se o email estiver registado, receberá instruções de recuperação'
    ];

    // Debug info: só em desenvolvimento (APP_ENV != production)
    $app_env = getenv('APP_ENV') ?: 'production';
    if ($app_env === 'development') {
        $response['debug'] = [
            'token' => $token,
            'expires_at' => $expires_at,
            'reset_url' => "http://localhost:8080/reset-password?token={$token}"
        ];
    }

    http_response_code(200);
    echo json_encode($response, JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    error_log("Forgot password error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro interno do servidor']);
    exit();
}
