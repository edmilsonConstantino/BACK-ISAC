<?php
/**
 * MIGRAÇÃO 004 - Criar tabela turmas (ATUALIZADA)
 *
 * - Adiciona curso_id (obrigatório)
 * - disciplina vira opcional (NULL)
 * - status inclui 'cancelado' (compatível com a API)
 * - suporta --force para recriar (DEV)
 *
 * 📁 LOCAL: migrations/004_create_turmas_table.php
 */
require_once __DIR__ . '/../config/bootstrap.php';

$migrationName = '004_create_turmas_table';

// ✅ Permite rodar de novo em DEV: php migrations/004_create_turmas_table.php --force
$force = isset($argv) && in_array('--force', $argv, true);

// Verifica se já foi executada
$stmt = $pdo->prepare("SELECT COUNT(*) FROM migrations WHERE migration = ?");
$stmt->execute([$migrationName]);
$alreadyRan = $stmt->fetchColumn() > 0;

if ($alreadyRan && !$force) {
    echo "⚠️  Migração '{$migrationName}' já foi executada antes. Pulando...\n";
    echo "👉 Se quiser recriar em DEV: php migrations/004_create_turmas_table.php --force\n";
    exit(0);
}

try {
    // 🔥 Se for --force: apaga registro e dropa tabela antes de recriar
    if ($force) {
        echo "🧨 --force ativo: removendo registro da migração e dropando tabela 'turmas'...\n";

        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
        $pdo->exec("DROP TABLE IF EXISTS turmas;");
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");

        $del = $pdo->prepare("DELETE FROM migrations WHERE migration = ?");
        $del->execute([$migrationName]);

        echo "✅ Reset feito. Recriando tabela...\n";
    }

    // SQL para criar a tabela
    $sql = "
    CREATE TABLE IF NOT EXISTS turmas (
        id INT AUTO_INCREMENT PRIMARY KEY,

        -- Código gerado no backend (CURSO-ANO-SEQ)
        codigo VARCHAR(50) UNIQUE NOT NULL,

        -- Nome da turma
        nome VARCHAR(150) NOT NULL,

        -- Curso base (obrigatório)
        curso_id VARCHAR(20) NOT NULL,

        -- Disciplina NÃO é obrigatória no momento (pode ser usada depois)
        disciplina VARCHAR(150) DEFAULT NULL,

        professor_id INT DEFAULT NULL,
        semestre VARCHAR(20) DEFAULT NULL,

        ano_letivo INT NOT NULL,
        duracao_meses INT NOT NULL DEFAULT 6,

        capacidade_maxima INT DEFAULT 30,
        vagas_ocupadas INT DEFAULT 0,

        sala VARCHAR(50) DEFAULT NULL,

        dias_semana VARCHAR(100) NOT NULL DEFAULT '',
        horario_inicio TIME NOT NULL DEFAULT '00:00:00',
        horario_fim TIME NOT NULL DEFAULT '00:00:00',

        data_inicio DATE DEFAULT NULL,
        data_fim DATE DEFAULT NULL,

        carga_horaria INT DEFAULT NULL,
        creditos INT DEFAULT NULL,
        observacoes TEXT DEFAULT NULL,

        status ENUM('ativo', 'inativo', 'concluido', 'cancelado') DEFAULT 'ativo',

        data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        data_atualizacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

        -- Foreign Key professor
        CONSTRAINT fk_turmas_professor
            FOREIGN KEY (professor_id)
            REFERENCES professores(id)
            ON DELETE SET NULL
            ON UPDATE CASCADE,

        -- Índices
        INDEX idx_codigo (codigo),
        INDEX idx_curso_id (curso_id),
        INDEX idx_disciplina (disciplina),
        INDEX idx_status (status),
        INDEX idx_ano_letivo (ano_letivo)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";

    $pdo->exec($sql);
    echo "✅ Tabela 'turmas' criada com sucesso!\n";

    // Registra a migração como executada
    $ins = $pdo->prepare("INSERT INTO migrations (migration) VALUES (?)");
    $ins->execute([$migrationName]);
    echo "📝 Migração '{$migrationName}' registrada!\n";

} catch (PDOException $e) {
    echo "❌ Erro ao criar tabela 'turmas': " . $e->getMessage() . "\n";
    exit(1);
}
