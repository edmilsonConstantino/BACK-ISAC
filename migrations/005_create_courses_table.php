<?php
/**
 * ================================================
 * MIGRAÇÃO 005 - CRIAR TABELA DE CURSOS
 * ================================================
 * ✅ Cria tabela cursos
 * ✅ Adiciona FK em turmas APENAS se turmas já existir
 * Baseada nos campos de CreateCourseModal.tsx
 */

require_once __DIR__ . '/../config/bootstrap.php';

$migrationName = '005_create_courses_table';

// Verifica se já foi executada
$stmt = $pdo->prepare("SELECT COUNT(*) FROM migrations WHERE migration = ?");
$stmt->execute([$migrationName]);

if ($stmt->fetchColumn() > 0) {
    echo "⚠️  Migração '{$migrationName}' já foi executada antes. Pulando...\n";
    exit(0);
}

try {
    echo "📚 Iniciando migração: Criar tabela de cursos...\n";
    
    // ==================== CRIAR TABELA CURSOS ====================
    $sql = "CREATE TABLE IF NOT EXISTS cursos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        
        -- Identificação (do modal)
        nome VARCHAR(255) NOT NULL COMMENT 'Nome do curso',
        codigo VARCHAR(50) NOT NULL UNIQUE COMMENT 'Código único do curso',
        
        -- Tipo e Duração (do modal)
        tipo_curso ENUM('tecnico', 'tecnico_superior', 'tecnico_profissional', 'curta_duracao') 
            NOT NULL DEFAULT 'tecnico_superior' 
            COMMENT 'Tipo do curso',
        duracao_valor INT NOT NULL DEFAULT 2 
            COMMENT 'Duração (anos para superior, meses para outros)',
        
        -- Regime (do modal)
        regime ENUM('laboral', 'pos_laboral', 'ambos') 
            NOT NULL DEFAULT 'laboral' 
            COMMENT 'Regime de aulas',
        
        -- Financeiro (do modal)
        mensalidade DECIMAL(10,2) NOT NULL DEFAULT 0.00 
            COMMENT 'Mensalidade em MZN',
        taxa_matricula DECIMAL(10,2) NOT NULL DEFAULT 0.00 
            COMMENT 'Taxa de matrícula em MZN',
        propina_fixa BOOLEAN DEFAULT TRUE 
            COMMENT 'Se a propina é fixa (sem variações)',
        permite_bolsa BOOLEAN DEFAULT TRUE 
            COMMENT 'Se permite bolsa de estudo',
        
        -- Status e Observações (do modal)
        status ENUM('ativo', 'inativo') DEFAULT 'ativo',
        observacoes TEXT NULL COMMENT 'Informações adicionais',
        
        -- Controle (timestamps)
        data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        data_atualizacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        
        -- Índices
        INDEX idx_codigo (codigo),
        INDEX idx_status (status),
        INDEX idx_tipo_curso (tipo_curso)
        
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
    COMMENT='Tabela de cursos oferecidos - estrutura do CreateCourseModal';";
    
    $pdo->exec($sql);
    echo "✅ Tabela 'cursos' criada com sucesso!\n";
    echo "   ✓ Campos: nome, código, tipo, duração, regime\n";
    echo "   ✓ Financeiro: mensalidade, taxa_matricula, propina_fixa, permite_bolsa\n";
    echo "   ✓ Status e observações\n\n";
    
    // ==================== NÃO INSERIR CURSOS ====================
    echo "ℹ️  Tabela criada vazia - cursos serão criados pelo frontend\n\n";
    
    // ==================== VERIFICAR SE TURMAS EXISTE ====================
    echo "🔍 Verificando se tabela 'turmas' existe...\n";
    
    $checkTurmas = $pdo->query("SHOW TABLES LIKE 'turmas'")->fetch();
    
    if ($checkTurmas) {
        echo "✅ Tabela 'turmas' encontrada!\n";
        echo "🔗 Adicionando relacionamento: turmas → cursos...\n";
        
        // Verificar se coluna já existe
        $checkColumn = $pdo->query("SHOW COLUMNS FROM turmas LIKE 'curso_id'")->fetch();
        
        if (!$checkColumn) {
            $pdo->exec("
                ALTER TABLE turmas 
                ADD COLUMN curso_id INT NULL AFTER disciplina,
                ADD FOREIGN KEY (curso_id) REFERENCES cursos(id) ON DELETE SET NULL,
                ADD INDEX idx_curso_id (curso_id)
            ");
            echo "✅ Coluna 'curso_id' adicionada à tabela 'turmas'\n";
            echo "✅ Foreign Key criada: turmas.curso_id → cursos.id\n\n";
        } else {
            echo "⚠️  Coluna 'curso_id' já existe em 'turmas'\n\n";
        }
    } else {
        echo "⚠️  Tabela 'turmas' ainda não existe\n";
        echo "ℹ️  FK será adicionada quando turmas for criada\n\n";
    }
    
    // ==================== REGISTRAR MIGRAÇÃO ====================
    $stmt = $pdo->prepare("INSERT INTO migrations (migration) VALUES (?)");
    $stmt->execute([$migrationName]);
    
    echo "✅ ========================================\n";
    echo "✅ MIGRAÇÃO 005 CONCLUÍDA COM SUCESSO!\n";
    echo "✅ ========================================\n\n";
    echo "📊 Resumo:\n";
    echo "   - Tabela 'cursos' criada\n";
    
    if ($checkTurmas && !$checkColumn) {
        echo "   - Coluna 'curso_id' adicionada em 'turmas'\n";
        echo "   - FK turmas → cursos criada\n";
    } elseif (!$checkTurmas) {
        echo "   - FK será adicionada quando 'turmas' for criada\n";
    }
    
    echo "   - Migração registrada\n";
    echo "   - Cursos devem ser criados pelo frontend\n\n";
    
} catch (PDOException $e) {
    echo "\n❌ ERRO na migração: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
    exit(1);
}
?>