<?php
/**
 * MIGRAÇÃO 000 - Tabela de Controle de Migrações
 * 
 * Esta tabela guarda quais migrações já foram executadas
 * para evitar executar a mesma migração duas vezes.
 */

require_once __DIR__ . '/../config/bootstrap.php';

$sql = "
CREATE TABLE IF NOT EXISTS migrations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    migration VARCHAR(255) NOT NULL UNIQUE,
    executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
";

try {
    $pdo->exec($sql);
    echo "✅ Tabela 'migrations' (controle) criada com sucesso!\n";
} catch (PDOException $e) {
    echo "❌ Erro ao criar tabela 'migrations': " . $e->getMessage() . "\n";
    exit(1);
}