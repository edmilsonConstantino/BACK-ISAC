<?php
/**
 * MIGRAÇÃO 018 - Criar tabela refresh_tokens
 *
 * Tabela centralizada para gestão de refresh tokens de TODOS os tipos de utilizador.
 * Permite invalidação individual, rotação segura e auditoria.
 */

require_once __DIR__ . '/../config/bootstrap.php';

$migrationName = '018_create_refresh_tokens_table';

// Verifica se já foi executada
$stmt = $pdo->prepare("SELECT COUNT(*) FROM migrations WHERE migration = ?");
$stmt->execute([$migrationName]);
if ($stmt->fetchColumn() > 0) {
    echo "⚠️  Migração '{$migrationName}' já foi executada antes. Pulando...\n";
    exit(0);
}

$sql = "
CREATE TABLE IF NOT EXISTS refresh_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    user_type ENUM('admin','academic_admin','student','teacher') NOT NULL,
    token_hash VARCHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    revoked_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY uq_token_hash (token_hash),
    INDEX idx_user (user_id, user_type),
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB;
";

try {
    $pdo->exec($sql);
    echo "✅ Tabela 'refresh_tokens' criada com sucesso!\n";

    $stmt = $pdo->prepare("INSERT INTO migrations (migration) VALUES (?)");
    $stmt->execute([$migrationName]);
    echo "📝 Migração '{$migrationName}' registrada!\n";

} catch (PDOException $e) {
    echo "❌ Erro ao criar tabela 'refresh_tokens': " . $e->getMessage() . "\n";
    exit(1);
}
