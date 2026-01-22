<?php
/**
 * MIGRAÇÃO 005 - Criar tabela turma_estudantes (relacionamento turmas ↔ estudantes)
 * 
 * 📁 LOCAL: migrations/005_create_turma_estudantes_table.php
 */
require_once __DIR__ . '/../config/bootstrap.php';

$migrationName = '005_create_turma_estudantes_table';

// Verifica se já foi executada
$stmt = $pdo->prepare("SELECT COUNT(*) FROM migrations WHERE migration = ?");
$stmt->execute([$migrationName]);
if ($stmt->fetchColumn() > 0) {
    echo "⚠️  Migração '{$migrationName}' já foi executada antes. Pulando...\n";
    exit(0);
}

// SQL para criar a tabela intermediária
$sql = "
CREATE TABLE IF NOT EXISTS turma_estudantes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    turma_id INT NOT NULL,
    estudante_id INT NOT NULL,
    data_matricula DATETIME DEFAULT CURRENT_TIMESTAMP,
    status ENUM('ativo', 'inativo', 'transferido', 'concluido') DEFAULT 'ativo',
    nota_final DECIMAL(5,2) DEFAULT NULL,
    frequencia DECIMAL(5,2) DEFAULT NULL,
    observacoes TEXT DEFAULT NULL,
    data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    data_atualizacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Foreign Keys
    CONSTRAINT fk_turma_estudantes_turma 
        FOREIGN KEY (turma_id) 
        REFERENCES turmas(id) 
        ON DELETE CASCADE 
        ON UPDATE CASCADE,
    
    CONSTRAINT fk_turma_estudantes_estudante 
        FOREIGN KEY (estudante_id) 
        REFERENCES students(id) 
        ON DELETE CASCADE 
        ON UPDATE CASCADE,
    
    -- Constraint única: um estudante não pode estar 2x na mesma turma
    CONSTRAINT unique_turma_estudante UNIQUE (turma_id, estudante_id),
    
    -- Índices para melhorar performance
    INDEX idx_turma_id (turma_id),
    INDEX idx_estudante_id (estudante_id),
    INDEX idx_status (status),
    INDEX idx_data_matricula (data_matricula)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

try {
    // Executa a criação da tabela
    $pdo->exec($sql);
    echo "✅ Tabela 'turma_estudantes' criada com sucesso!\n";
    
    // Registra a migração como executada
    $stmt = $pdo->prepare("INSERT INTO migrations (migration) VALUES (?)");
    $stmt->execute([$migrationName]);
    echo "📝 Migração '{$migrationName}' registrada!\n";
    
} catch (PDOException $e) {
    echo "❌ Erro ao criar tabela 'turma_estudantes': " . $e->getMessage() . "\n";
    exit(1);
}