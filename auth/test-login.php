<?php
/**
 * TESTE DIRETO DE LOGIN
 * 📁 Salvar como: API-LOGIN/auth/test-login.php
 * 🌐 Acessar: http://localhost/api-login/auth/test-login.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');

echo json_encode(['step' => 1, 'message' => 'Iniciando teste']) . "\n";

// Teste 1: Verificar se database.php existe
$db_path = __DIR__ . '/../config/database.php';
if (!file_exists($db_path)) {
    echo json_encode(['error' => 'database.php não encontrado', 'path' => $db_path]) . "\n";
    exit();
}
echo json_encode(['step' => 2, 'message' => 'database.php encontrado']) . "\n";

// Teste 2: Incluir database.php
try {
    require_once $db_path;
    echo json_encode(['step' => 3, 'message' => 'database.php incluído com sucesso']) . "\n";
} catch (Exception $e) {
    echo json_encode(['error' => 'Erro ao incluir database.php', 'message' => $e->getMessage()]) . "\n";
    exit();
}

// Teste 3: Verificar se JWTHandler.php existe
$jwt_path = __DIR__ . '/JWTHandler.php';
if (!file_exists($jwt_path)) {
    echo json_encode(['error' => 'JWTHandler.php não encontrado', 'path' => $jwt_path]) . "\n";
    exit();
}
echo json_encode(['step' => 4, 'message' => 'JWTHandler.php encontrado']) . "\n";

// Teste 4: Verificar composer autoload
$autoload_path = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($autoload_path)) {
    echo json_encode(['error' => 'PROBLEMA CRÍTICO: vendor/autoload.php não encontrado!', 'path' => $autoload_path, 'solution' => 'Execute: composer install']) . "\n";
    exit();
}
echo json_encode(['step' => 5, 'message' => 'vendor/autoload.php encontrado']) . "\n";

// Teste 5: Incluir JWTHandler.php
try {
    require_once $jwt_path;
    echo json_encode(['step' => 6, 'message' => 'JWTHandler.php incluído com sucesso']) . "\n";
} catch (Exception $e) {
    echo json_encode(['error' => 'Erro ao incluir JWTHandler.php', 'message' => $e->getMessage()]) . "\n";
    exit();
}

// Teste 6: Conectar ao banco
try {
    $database = new Database();
    $db = $database->getConnection();
    echo json_encode(['step' => 7, 'message' => 'Conexão com banco estabelecida']) . "\n";
} catch (Exception $e) {
    echo json_encode(['error' => 'Erro ao conectar ao banco', 'message' => $e->getMessage()]) . "\n";
    exit();
}

// Teste 7: Verificar se tabela users existe
try {
    $query = "SELECT COUNT(*) as total FROM users";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo json_encode(['step' => 8, 'message' => 'Tabela users existe', 'total_users' => $result['total']]) . "\n";
} catch (Exception $e) {
    echo json_encode(['error' => 'Erro ao acessar tabela users', 'message' => $e->getMessage()]) . "\n";
    exit();
}

// Teste 8: Buscar um usuário de teste
try {
    $test_email = 'admin@isac.co.mz'; // Ajuste se necessário
    $query = "SELECT id, nome, email, role FROM users WHERE email = :email LIMIT 1";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':email', $test_email);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        echo json_encode(['step' => 9, 'message' => 'Usuário encontrado', 'user' => $user]) . "\n";
    } else {
        echo json_encode(['step' => 9, 'message' => 'Usuário não encontrado', 'searched_email' => $test_email]) . "\n";
    }
} catch (Exception $e) {
    echo json_encode(['error' => 'Erro ao buscar usuário', 'message' => $e->getMessage()]) . "\n";
    exit();
}

// Teste 9: Testar geração de JWT
try {
    $jwt = new JWTHandler();
    $test_token = $jwt->generateToken(1, 'test@test.com', 'admin');
    echo json_encode(['step' => 10, 'message' => 'JWT gerado com sucesso', 'token_length' => strlen($test_token)]) . "\n";
} catch (Exception $e) {
    echo json_encode(['error' => 'Erro ao gerar JWT', 'message' => $e->getMessage()]) . "\n";
    exit();
}

echo json_encode(['step' => 11, 'message' => '✅ TODOS OS TESTES PASSARAM!', 'status' => 'OK']) . "\n";
?>