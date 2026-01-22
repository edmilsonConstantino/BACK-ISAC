<?php
/**
 * MIGRAÇÃO 004 - Criar tabela turmas
 * 
 * 📁 LOCAL: migrations/004_create_turmas_table.php
 */
require_once __DIR__ . '/../config/bootstrap.php';

$migrationName = '004_create_turmas_table';

// Verifica se já foi executada
$stmt = $pdo->prepare("SELECT COUNT(*) FROM migrations WHERE migration = ?");
$stmt->execute([$migrationName]);

if ($stmt->fetchColumn() > 0) {
    echo "⚠️  Migração '{$migrationName}' já foi executada antes. Pulando...\n";
    exit(0);
}

// SQL para criar a tabela
$sql = "
CREATE TABLE IF NOT EXISTS turmas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(50) UNIQUE NOT NULL,
    nome VARCHAR(150) NOT NULL,
    disciplina VARCHAR(150) NOT NULL,
    professor_id INT DEFAULT NULL,
    semestre VARCHAR(20) DEFAULT NULL,
    ano_letivo INT NOT NULL,
    duracao_meses INT NOT NULL,
    capacidade_maxima INT DEFAULT 30,
    vagas_ocupadas INT DEFAULT 0,
    sala VARCHAR(50) DEFAULT NULL,
    dias_semana VARCHAR(100) NOT NULL,
    horario_inicio TIME NOT NULL,
    horario_fim TIME NOT NULL,
    data_inicio DATE DEFAULT NULL,
    data_fim DATE DEFAULT NULL,
    carga_horaria INT DEFAULT NULL,
    creditos INT DEFAULT NULL,
    observacoes TEXT DEFAULT NULL,
    status ENUM('ativo', 'inativo', 'concluido') DEFAULT 'ativo',
    data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    data_atualizacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Foreign Key
    CONSTRAINT fk_turmas_professor 
        FOREIGN KEY (professor_id) 
        REFERENCES professores(id) 
        ON DELETE SET NULL 
        ON UPDATE CASCADE,
    
    -- Índices
    INDEX idx_codigo (codigo),
    INDEX idx_disciplina (disciplina),
    INDEX idx_status (status),
    INDEX idx_ano_letivo (ano_letivo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

try {
    // Executa a criação da tabela
    $pdo->exec($sql);
    echo "✅ Tabela 'turmas' criada com sucesso!\n";
    
    // Registra a migração como executada
    $stmt = $pdo->prepare("INSERT INTO migrations (migration) VALUES (?)");
    $stmt->execute([$migrationName]);
    echo "📝 Migração '{$migrationName}' registrada!\n";
    
} catch (PDOException $e) {
    echo "❌ Erro ao criar tabela 'turmas': " . $e->getMessage() . "\n";
    exit(1);
}