<?php
/**
 * MIGRAÇÃO 017 - Criar tabela password_resets
 *
 * Tabela para gestão de recuperação de senha.
 * Funciona para qualquer tipo de utilizador (admin, professor, estudante).
 * O campo user_type indica em qual tabela procurar o email.
 */

require_once __DIR__ . '/../config/bootstrap.php';

$migrationName = '017_create_password_resets_table';

// Verifica se já foi executada
$stmt = $pdo->prepare("SELECT COUNT(*) FROM migrations WHERE migration = ?");
$stmt->execute([$migrationName]);
if ($stmt->fetchColumn() > 0) {
    echo "⚠️  Migração '{$migrationName}' já foi executada antes. Pulando...\n";
    exit(0);
}

$sql = "
CREATE TABLE IF NOT EXISTS password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(150) NOT NULL,
    token VARCHAR(255) NOT NULL,
    user_type ENUM('admin','academic_admin','student','teacher') NOT NULL,
    expires_at DATETIME NOT NULL,
    used TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_token (token),
    INDEX idx_email (email),
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB;
";

try {
    $pdo->exec($sql);
    echo "✅ Tabela 'password_resets' criada com sucesso!\n";

    $stmt = $pdo->prepare("INSERT INTO migrations (migration) VALUES (?)");
    $stmt->execute([$migrationName]);
    echo "📝 Migração '{$migrationName}' registrada!\n";

} catch (PDOException $e) {
    echo "❌ Erro ao criar tabela 'password_resets': " . $e->getMessage() . "\n";
    exit(1);
}
