<?php
/**
 * MIGRAÇÃO 014 - Criar tabela payment_types
 * 📁 LOCAL: migrations/014_create_payment_types_table.php
 */
require_once __DIR__ . '/../config/bootstrap.php';

$migrationName = '014_create_payment_types_table';

$stmt = $pdo->prepare("SELECT COUNT(*) FROM migrations WHERE migration = ?");
$stmt->execute([$migrationName]);

if ($stmt->fetchColumn() > 0) {
    echo "⚠️  Migração '{$migrationName}' já foi executada antes. Pulando...\n";
    exit(0);
}

try {
    // ✅ DEV: drop para permitir recriar
    $pdo->exec("DROP TABLE IF EXISTS payment_types;");

    $sql = "
    CREATE TABLE payment_types (
      id INT AUTO_INCREMENT PRIMARY KEY,
      codigo VARCHAR(30) NOT NULL UNIQUE,
      nome VARCHAR(80) NOT NULL,
      ativo TINYINT(1) NOT NULL DEFAULT 1,
      data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      data_atualizacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      INDEX idx_payment_types_ativo (ativo)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    $pdo->exec($sql);

    // seed básico
    $pdo->exec("
      INSERT INTO payment_types (codigo, nome, ativo) VALUES
        ('cash', 'Dinheiro', 1),
        ('mpesa', 'M-Pesa', 1),
        ('transfer', 'Transferência', 1),
        ('card', 'Cartão', 1),
        ('other', 'Outro', 1);
    ");

    $stmt = $pdo->prepare("INSERT INTO migrations (migration) VALUES (?)");
    $stmt->execute([$migrationName]);

    echo "✅ payment_types criada e seed aplicada!\n";
    echo "📝 Migração '{$migrationName}' registrada!\n";

} catch (PDOException $e) {
    echo "❌ Erro na migração '{$migrationName}': " . $e->getMessage() . "\n";
    exit(1);
}
