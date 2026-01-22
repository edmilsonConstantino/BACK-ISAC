<?php
// debug_login.php - CRIAR NA PASTA: API-LOGIN/auth/
// Acesse: http://localhost/API-LOGIN/auth/debug_login.php

// CORS Headers
header('Access-Control-Allow-Origin: http://localhost:5173');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: POST, OPTIONS, GET');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Debug Login</title></head><body>";
echo "<h1>🔍 DEBUG DO SISTEMA DE LOGIN</h1>";
echo "<hr>";

// 1. Testar conexão com banco
echo "<h2>1️⃣ TESTE DE CONEXÃO COM BANCO</h2>";
try {
    require_once '../config/database.php';
    $database = new Database();
    $db = $database->getConnection();
    echo "✅ <strong>Conexão com banco: OK!</strong><br>";
} catch (Exception $e) {
    echo "❌ <strong>ERRO na conexão:</strong> " . $e->getMessage() . "<br>";
    exit();
}

// 2. Verificar se usuários existem
echo "<hr><h2>2️⃣ USUÁRIOS NO BANCO</h2>";
try {
    $query = "SELECT id, nome, email, role, created_at FROM users ORDER BY role";
    $stmt = $db->prepare($query);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        echo "<table border='1' cellpadding='10' style='border-collapse: collapse;'>";
        echo "<tr style='background: #333; color: white;'>
                <th>ID</th>
                <th>Nome</th>
                <th>Email</th>
                <th>Role</th>
                <th>Criado em</th>
              </tr>";
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo "<tr>";
            echo "<td>{$row['id']}</td>";
            echo "<td>{$row['nome']}</td>";
            echo "<td>{$row['email']}</td>";
            echo "<td><strong>{$row['role']}</strong></td>";
            echo "<td>{$row['created_at']}</td>";
            echo "</tr>";
        }
        echo "</table>";
        echo "<br>✅ <strong>Total de usuários:</strong> " . $stmt->rowCount();
    } else {
        echo "⚠️ <strong>NENHUM usuário encontrado no banco!</strong>";
    }
} catch (Exception $e) {
    echo "❌ <strong>ERRO ao buscar usuários:</strong> " . $e->getMessage();
}

// 3. Testar senha específica
echo "<hr><h2>3️⃣ TESTE DE VERIFICAÇÃO DE SENHA</h2>";
try {
    $email_teste = 'admin@example.com';
    $senha_teste = '8456@';
    
    $query = "SELECT senha FROM users WHERE email = :email LIMIT 1";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':email', $email_teste);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        $hash_banco = $user['senha'];
        
        echo "📧 <strong>Email testado:</strong> {$email_teste}<br>";
        echo "🔑 <strong>Senha testada:</strong> {$senha_teste}<br>";
        echo "🔐 <strong>Hash no banco:</strong> " . substr($hash_banco, 0, 50) . "...<br><br>";
        
        if (password_verify($senha_teste, $hash_banco)) {
            echo "✅ <strong style='color: green; font-size: 18px;'>SENHA CORRETA! 🎉</strong><br>";
        } else {
            echo "❌ <strong style='color: red; font-size: 18px;'>SENHA INCORRETA!</strong><br>";
            echo "<br>⚠️ <strong>PROBLEMA IDENTIFICADO:</strong><br>";
            echo "O hash no banco NÃO corresponde à senha '8456@'<br><br>";
            
            // Gerar hash correto
            $hash_correto = password_hash($senha_teste, PASSWORD_DEFAULT);
            echo "🔧 <strong>SOLUÇÃO:</strong> Execute este SQL:<br>";
            echo "<pre style='background: #f0f0f0; padding: 10px; border: 1px solid #ccc;'>";
            echo "UPDATE users SET senha = '{$hash_correto}' WHERE email = '{$email_teste}';";
            echo "</pre>";
        }
    } else {
        echo "❌ <strong>Usuário '{$email_teste}' NÃO ENCONTRADO!</strong><br>";
        echo "<br>Execute o script SQL para criar o usuário.";
    }
} catch (Exception $e) {
    echo "❌ <strong>ERRO:</strong> " . $e->getMessage();
}

// 4. Testar recebimento de dados POST
echo "<hr><h2>4️⃣ TESTE DE REQUISIÇÃO POST</h2>";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    echo "📨 <strong>Dados recebidos:</strong><br>";
    echo "<pre style='background: #f0f0f0; padding: 10px; border: 1px solid #ccc;'>";
    print_r($data);
    echo "</pre>";
    
    if (isset($data['email']) && isset($data['senha'])) {
        echo "✅ Email e senha foram recebidos corretamente!<br>";
        echo "📧 Email: {$data['email']}<br>";
        echo "🔑 Senha: " . str_repeat('*', strlen($data['senha'])) . "<br>";
    } else {
        echo "❌ Email ou senha NÃO foram recebidos!";
    }
} else {
    echo "ℹ️ <em>Faça uma requisição POST para testar</em>";
}

// 5. Informações do servidor
echo "<hr><h2>5️⃣ INFORMAÇÕES DO SERVIDOR</h2>";
echo "🖥️ <strong>PHP Version:</strong> " . phpversion() . "<br>";
echo "📁 <strong>Diretório atual:</strong> " . __DIR__ . "<br>";
echo "🌐 <strong>REQUEST_METHOD:</strong> " . $_SERVER['REQUEST_METHOD'] . "<br>";

echo "</body></html>";
?>