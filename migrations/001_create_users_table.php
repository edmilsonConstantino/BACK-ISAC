<?php
/**
 * MIGRAÇÃO 001 - Criar tabela users
 */

require_once __DIR__ . '/../config/bootstrap.php';

$migrationName = '001_create_users_table';

// Verifica se já foi executada
$stmt = $pdo->prepare("SELECT COUNT(*) FROM migrations WHERE migration = ?");
$stmt->execute([$migrationName]);
if ($stmt->fetchColumn() > 0) {
    echo "⚠️  Migração '{$migrationName}' já foi executada antes. Pulando...\n";
    exit(0);
}

// SQL para criar a tabela
$sql = "
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    email VARCHAR(150) UNIQUE NOT NULL,
    senha VARCHAR(255) NOT NULL,
    role ENUM('admin','docente','aluno','academic_admin') NOT NULL,
    avatar VARCHAR(10) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
";

try {
    // Executa a criação da tabela
    $pdo->exec($sql);
    echo "✅ Tabela 'users' criada com sucesso!\n";
    
    // Registra a migração como executada
    $stmt = $pdo->prepare("INSERT INTO migrations (migration) VALUES (?)");
    $stmt->execute([$migrationName]);
    echo "📝 Migração '{$migrationName}' registrada!\n";
    
} catch (PDOException $e) {
    echo "❌ Erro ao criar tabela 'users': " . $e->getMessage() . "\n";
    exit(1);
}