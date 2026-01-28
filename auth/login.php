<?php
/**
 * ============================================================
 * LOGIN ENDPOINT - VERSÃO DEBUG CORRIGIDA
 * ============================================================
 * ✅ Fix: Invalid parameter number
 * 
 * 📁 LOCAL: API-LOGIN/auth/login.php
 */

// ============================================================
// DEBUG MODE (só em development)
// ============================================================
$app_env = getenv('APP_ENV') ?: 'production';
$is_debug = ($app_env === 'development');

if ($is_debug) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    error_reporting(E_ALL);
}

$logFile = __DIR__ . '/debug.log';

function debugLog($message) {
    global $logFile, $is_debug;
    if (!$is_debug) return;

    $timestamp = date('Y-m-d H:i:s');

    if (is_array($message) || is_object($message)) {
        $message = print_r($message, true);
    }

    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
}

// Iniciar log
debugLog("=== LOGIN DEBUG " . date('Y-m-d H:i:s') . " ===");
debugLog([
    'timestamp' => date('Y-m-d H:i:s'),
    'request_uri' => $_SERVER['REQUEST_URI'],
    'request_method' => $_SERVER['REQUEST_METHOD'],
    'content_type' => $_SERVER['CONTENT_TYPE'] ?? 'not set'
]);

// ============================================================
// 2️⃣ CORS HEADERS
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

// ============================================================
// 3️⃣ HANDLE PREFLIGHT
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    debugLog("Requisição OPTIONS - retornando 200");
    http_response_code(200);
    exit();
}

// ============================================================
// 4️⃣ APENAS POST
// ============================================================
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    debugLog("Método inválido: " . $_SERVER['REQUEST_METHOD']);
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Método não permitido. Use POST'
    ]);
    exit();
}

// ============================================================
// 5️⃣ CARREGAR ARQUIVOS
// ============================================================
try {
    require_once __DIR__ . '/../config/database.php';
    debugLog("✅ database.php carregado com sucesso");
} catch (Throwable $e) {
    debugLog("❌ ERRO ao carregar database.php: " . $e->getMessage());
    http_response_code(500);
    $err = ['success' => false, 'message' => 'Erro ao carregar configuração'];
    if ($is_debug) $err['debug'] = $e->getMessage();
    echo json_encode($err);
    exit();
}

try {
    require_once __DIR__ . '/JWTHandler.php';
    debugLog("✅ JWTHandler.php carregado com sucesso");
} catch (Throwable $e) {
    debugLog("❌ ERRO ao carregar JWTHandler.php: " . $e->getMessage());
    http_response_code(500);
    $err = ['success' => false, 'message' => 'Erro ao carregar JWT'];
    if ($is_debug) $err['debug'] = $e->getMessage();
    echo json_encode($err);
    exit();
}

// ============================================================
// 6️⃣ RECEBER DADOS
// ============================================================
$input = file_get_contents('php://input');
debugLog("📥 INPUT RECEBIDO: " . $input);

$data = json_decode($input, true);
debugLog("📦 DADOS DECODIFICADOS: " . print_r($data, true));

if (!$data) {
    debugLog("❌ Erro ao decodificar JSON");
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Dados inválidos'
    ]);
    exit();
}


$identifier = '';

if (isset($data['identifier'])) {
    $identifier = trim($data['identifier']);
} elseif (isset($data['email'])) {
    $identifier = trim($data['email']);
} elseif (isset($data['username'])) {
    $identifier = trim($data['username']);
} elseif (isset($data['enrollment_number'])) {
    $identifier = trim($data['enrollment_number']);
} elseif (isset($data['codigo'])) {
    $identifier = trim($data['codigo']);
}

$senha = isset($data['senha']) ? $data['senha'] : (isset($data['password']) ? $data['password'] : '');

debugLog("🔑 Credenciais: identifier='$identifier', senha_length=" . strlen($senha));

if (empty($identifier) || empty($senha)) {
    debugLog("❌ Campos vazios");
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Email/username/código e senha são obrigatórios'
    ]);
    exit();
}

if (strlen($senha) < 5) {
    debugLog("❌ Senha muito curta");
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Senha deve ter pelo menos 5 caracteres'
    ]);
    exit();
}

// ============================================================
// 8️⃣ CONECTAR BANCO E BUSCAR
// ============================================================
try {
    $database = new Database();
    $db = $database->getConnection();
    debugLog("✅ Conexão com banco estabelecida");
    
    $user = null;
    $user_type = null;
    
    // ========================================
    // 🔍 BUSCAR ADMIN
    // ========================================
    debugLog("🔍 Buscando ADMIN com identifier: $identifier");
    
    $query = "SELECT id, nome, email, senha, role, status, created_at
              FROM users
              WHERE email = :identifier
              LIMIT 1";

    $stmt = $db->prepare($query);
    $stmt->bindParam(':identifier', $identifier);
    $stmt->execute();

    if ($stmt->rowCount() > 0) {
        debugLog("✅ ADMIN encontrado!");
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        $user_type = $user['role']; // 'admin' ou 'academic_admin'

        // Verificar se a conta está activa
        if ($user['status'] === 'inactive') {
            debugLog("❌ Conta ADMIN inactiva");
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'message' => 'Conta desactivada. Contacte o administrador.'
            ]);
            exit();
        }

        if (password_verify($senha, $user['senha'])) {
            debugLog("✅ Senha ADMIN correta");

            // Atualizar last_login
            try {
                $updateQuery = "UPDATE users SET last_login = NOW() WHERE id = :id";
                $updateStmt = $db->prepare($updateQuery);
                $updateStmt->bindParam(':id', $user['id']);
                $updateStmt->execute();
                debugLog("✅ last_login admin atualizado");
            } catch (PDOException $e) {
                debugLog("⚠️ Erro ao atualizar last_login admin: " . $e->getMessage());
            }

            // Remover status da resposta
            unset($user['status']);

            goto login_success;
        } else {
            debugLog("❌ Senha ADMIN incorreta");
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'message' => 'Email ou senha incorretos'
            ]);
            exit();
        }
    }
    
    debugLog("ℹ️ ADMIN não encontrado, tentando STUDENT");
    
    // ========================================
    // 🔍 BUSCAR STUDENT (✅ FIX: dois placeholders)
    // ========================================
    $query = "SELECT id, name as nome, email, enrollment_number, password as senha, 
                     status, created_at 
              FROM students 
              WHERE (enrollment_number = :id1 OR email = :id2) 
              AND status = 'ativo'
              LIMIT 1";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id1', $identifier);
    $stmt->bindParam(':id2', $identifier);
    $stmt->execute();
    
    debugLog("Query STUDENT executada, rows: " . $stmt->rowCount());
    
    if ($stmt->rowCount() > 0) {
        debugLog("✅ STUDENT encontrado!");
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        $user['role'] = 'student';
        $user_type = 'student';
        
        if (password_verify($senha, $user['senha'])) {
            debugLog("✅ Senha STUDENT correta");
            
            // Atualizar last_login
            try {
                $updateQuery = "UPDATE students SET last_login = NOW() WHERE id = :id";
                $updateStmt = $db->prepare($updateQuery);
                $updateStmt->bindParam(':id', $user['id']);
                $updateStmt->execute();
                debugLog("✅ last_login atualizado");
            } catch (PDOException $e) {
                debugLog("⚠️ Erro ao atualizar last_login: " . $e->getMessage());
            }
            
            goto login_success;
        } else {
            debugLog("❌ Senha STUDENT incorreta");
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'message' => 'Código ou senha incorretos'
            ]);
            exit();
        }
    }
    
    debugLog("ℹ️ STUDENT não encontrado, tentando TEACHER");
    
    // ========================================
    // 🔍 BUSCAR TEACHER (✅ FIX: dois placeholders)
    // ========================================
    $query = "SELECT id, nome, email, username, password as senha, 
                     status, created_at 
              FROM professores 
              WHERE (username = :id1 OR email = :id2)
              AND status = 'ativo'
              LIMIT 1";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id1', $identifier);
    $stmt->bindParam(':id2', $identifier);
    $stmt->execute();
    
    debugLog("Query TEACHER executada, rows: " . $stmt->rowCount());
    
    if ($stmt->rowCount() > 0) {
        debugLog("✅ TEACHER encontrado!");
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        $user['role'] = 'teacher';
        $user_type = 'teacher';
        
        if (password_verify($senha, $user['senha'])) {
            debugLog("✅ Senha TEACHER correta");
            
            // Atualizar last_login
            try {
                $updateQuery = "UPDATE professores SET last_login = NOW() WHERE id = :id";
                $updateStmt = $db->prepare($updateQuery);
                $updateStmt->bindParam(':id', $user['id']);
                $updateStmt->execute();
                debugLog("✅ last_login atualizado");
            } catch (PDOException $e) {
                debugLog("⚠️ Erro ao atualizar last_login: " . $e->getMessage());
            }
            
            goto login_success;
        } else {
            debugLog("❌ Senha TEACHER incorreta");
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'message' => 'Username ou senha incorretos'
            ]);
            exit();
        }
    }
    
    // ❌ Nenhum usuário encontrado
    debugLog("❌ Nenhum usuário encontrado em nenhuma tabela");
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Credenciais inválidas'
    ]);
    exit();
    
    // ========================================
    // ✅ LOGIN BEM-SUCEDIDO
    // ========================================
    login_success:
    
    debugLog("🎉 Gerando tokens JWT...");

    // user_type já é o valor real: admin, academic_admin, student, teacher
    $refresh_user_type = $user_type;

    $jwt = new JWTHandler();
    $access_token = $jwt->generateToken(
        $user['id'],
        $user['email'],
        $user['role'],
        $refresh_user_type
    );
    $refresh_token = $jwt->generateRefreshToken($user['id'], $refresh_user_type);

    debugLog("✅ Tokens gerados!");

    // Salvar refresh token na tabela refresh_tokens (TODOS os tipos)
    try {
        $token_hash = hash('sha256', $refresh_token);
        $expires_at = date('Y-m-d H:i:s', strtotime('+7 days'));

        $insert_query = "INSERT INTO refresh_tokens (user_id, user_type, token_hash, expires_at)
                         VALUES (:user_id, :user_type, :token_hash, :expires_at)";
        $insert_stmt = $db->prepare($insert_query);
        $insert_stmt->bindParam(':user_id', $user['id']);
        $insert_stmt->bindParam(':user_type', $refresh_user_type);
        $insert_stmt->bindParam(':token_hash', $token_hash);
        $insert_stmt->bindParam(':expires_at', $expires_at);
        $insert_stmt->execute();
        debugLog("✅ Refresh token salvo na tabela refresh_tokens");

        // Limitar a 5 sessões activas por utilizador/tipo
        // Revoga os tokens mais antigos acima do limite
        $max_sessions = 5;
        $cleanup = $db->prepare("
            UPDATE refresh_tokens
            SET revoked_at = NOW()
            WHERE user_id = :uid
              AND user_type = :ut
              AND revoked_at IS NULL
              AND id NOT IN (
                  SELECT id FROM (
                      SELECT id FROM refresh_tokens
                      WHERE user_id = :uid2
                        AND user_type = :ut2
                        AND revoked_at IS NULL
                      ORDER BY created_at DESC
                      LIMIT $max_sessions
                  ) AS recent
              )
        ");
        $cleanup->bindParam(':uid', $user['id']);
        $cleanup->bindParam(':ut', $refresh_user_type);
        $cleanup->bindParam(':uid2', $user['id']);
        $cleanup->bindParam(':ut2', $refresh_user_type);
        $cleanup->execute();
        $revoked_count = $cleanup->rowCount();
        if ($revoked_count > 0) {
            debugLog("🧹 $revoked_count sessões antigas revogadas (limite: $max_sessions)");
        }
    } catch (PDOException $e) {
        debugLog("⚠️ Erro ao salvar refresh token: " . $e->getMessage());
    }
    
    // Remover campos internos da resposta
    unset($user['senha']);
    unset($user['password']);
    unset($user['status']);
    unset($user['created_at']);
    
    debugLog("✅ LOGIN COMPLETO - user_id: " . $user['id'] . ", role: " . $user['role']);
    
    // RESPOSTA
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Login realizado com sucesso',
        'data' => [
            'user' => $user,
            'access_token' => $access_token,
            'refresh_token' => $refresh_token,
            'token_type' => 'Bearer',
            'expires_in' => 3600
        ]
    ], JSON_UNESCAPED_UNICODE);
    
    debugLog("📤 JSON retornado com sucesso");
    exit();
    
} catch (PDOException $e) {
    debugLog("❌ ERRO PDO: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    http_response_code(500);
    $err = ['success' => false, 'message' => 'Erro ao processar requisição'];
    if ($is_debug) $err['debug'] = ['error' => $e->getMessage(), 'line' => $e->getLine()];
    echo json_encode($err);
    exit();
    
} catch (Throwable $e) {
    debugLog("❌ ERRO GERAL: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    http_response_code(500);
    $err = ['success' => false, 'message' => 'Erro inesperado'];
    if ($is_debug) $err['debug'] = ['error' => $e->getMessage(), 'line' => $e->getLine()];
    echo json_encode($err);
    exit();
}
?>