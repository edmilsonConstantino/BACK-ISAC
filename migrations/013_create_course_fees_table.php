<?php
/**
 * MIGRAÇÃO 013 - Criar tabela course_fees (preços do curso)
 *
 * 📁 LOCAL: migrations/013_create_course_fees_table.php
 */
require_once __DIR__ . '/../config/bootstrap.php';

$migrationName = '013_create_course_fees_table';

// Verifica se já foi executada
$stmt = $pdo->prepare("SELECT COUNT(*) FROM migrations WHERE migration = ?");
$stmt->execute([$migrationName]);

if ($stmt->fetchColumn() > 0) {
    echo "⚠️  Migração '{$migrationName}' já foi executada antes. Pulando...\n";
    exit(0);
}

$sql = "
CREATE TABLE IF NOT EXISTS course_fees (
    id INT AUTO_INCREMENT PRIMARY KEY,

    -- curso
    curso_id VARCHAR(50) NOT NULL,

    -- valores
    matricula_valor DECIMAL(10,2) DEFAULT 0.00,
    mensalidade_valor DECIMAL(10,2) NOT NULL DEFAULT 0.00,

    -- duração financeira (quantos meses de propinas)
    meses_total INT NOT NULL DEFAULT 6,

    -- controle
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    data_atualizacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- constraints
    CONSTRAINT uq_course_fees_curso UNIQUE (curso_id),

    CONSTRAINT fk_course_fees_curso
      FOREIGN KEY (curso_id)
      REFERENCES cursos(codigo)
      ON DELETE CASCADE
      ON UPDATE CASCADE,

    INDEX idx_course_fees_curso (curso_id),
    INDEX idx_course_fees_ativo (ativo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

try {
    $pdo->exec($sql);
    echo "✅ Tabela 'course_fees' criada com sucesso!\n";

    $stmt = $pdo->prepare("INSERT INTO migrations (migration) VALUES (?)");
    $stmt->execute([$migrationName]);
    echo "📝 Migração '{$migrationName}' registrada!\n";

} catch (PDOException $e) {
    echo "❌ Erro ao criar tabela 'course_fees': " . $e->getMessage() . "\n";
    exit(1);
}
