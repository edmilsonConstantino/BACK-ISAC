<?php
/**
 * SEED: Usuários do Sistema
 * Insere usuários iniciais com as senhas corretas do frontend
 */

require_once __DIR__ . '/../config/bootstrap.php';

echo "🌱 Iniciando seed da tabela users...\n";

try {
    // Verifica se já existem usuários cadastrados
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($row['count'] > 0) {
        echo "⚠️  Tabela 'users' já possui dados ({$row['count']} registros). Seed ignorado.\n";
        echo "💡 Dica: Se quiseres recriar os usuários, apaga os registros primeiro.\n";
        exit(0);
    }

    // Função para gerar hash seguro da senha
    function hashPassword($senha) {
        return password_hash($senha, PASSWORD_BCRYPT);
    }

    // Usuários do sistema (SENHAS CORRETAS DO FRONTEND)
    $users = [
        [
            'nome' => 'Administrador',
            'email' => 'admin@example.com',
            'senha' => hashPassword('8456@'),  // ← Senha correta!
            'role' => 'admin',
            'avatar' => '👨‍💼'
        ],
        [
            'nome' => 'Direção Académica',
            'email' => 'academic@isac.ac.mz',
            'senha' => hashPassword('8456@'),  // ← Senha correta!
            'role' => 'academic_admin',
            'avatar' => '👩‍💼'
        ],
        [
            'nome' => 'Professor Silva',
            'email' => 'professor@example.com',
            'senha' => hashPassword('senha123'),
            'role' => 'teacher',
            'avatar' => '👨‍🏫'
        ],
        [
            'nome' => 'João Aluno',
            'email' => 'aluno@example.com',
            'senha' => hashPassword('senha123'),
            'role' => 'student',
            'avatar' => '👨‍💻'
        ]
    ];

    // Inserir usuários no banco
    $stmt = $pdo->prepare("
        INSERT INTO users (nome, email, senha, role, avatar)
        VALUES (:nome, :email, :senha, :role, :avatar)
    ");

    foreach ($users as $user) {
        $stmt->execute($user);
        echo "✅ Usuário '{$user['nome']}' ({$user['role']}) inserido com sucesso!\n";
        echo "   📧 Email: {$user['email']}\n";
    }

    echo "\n🎉 Seed da tabela 'users' concluído!\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "✅ {count($users)} usuários criados com sucesso!\n\n";
    
    echo "🔑 CREDENCIAIS DE ACESSO:\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "👑 SUPER ADMIN:\n";
    echo "   Email: admin@example.com\n";
    echo "   Senha: 8456@\n\n";
    echo "📚 ACADEMIC ADMIN:\n";
    echo "   Email: academic@isac.ac.mz\n";
    echo "   Senha: 8456@\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

} catch (PDOException $e) {
    echo "❌ Erro ao popular tabela 'users': " . $e->getMessage() . "\n";
    exit(1);
}