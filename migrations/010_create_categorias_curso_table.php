<?php
/**
 * MIGRATION 010 - Criar tabela categorias_curso
 * 
 * 📁 LOCAL: API-LOGIN/migrations/010_create_categorias_curso_table.php
 */
require_once __DIR__ . '/../config/bootstrap.php';

$migrationName = '010_create_categorias_curso_table';

$stmt = $pdo->prepare("SELECT COUNT(*) FROM migrations WHERE migration = ?");
$stmt->execute([$migrationName]);

if ($stmt->fetchColumn() > 0) {
    echo "⚠️  Migração '{$migrationName}' já executada. Pulando...\n";
    exit(0);
}

try {
    echo "📚 Criando tabela categorias_curso...\n";
    
    $sql = "CREATE TABLE IF NOT EXISTS categorias_curso (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nome VARCHAR(100) NOT NULL UNIQUE COMMENT 'Nome da categoria (ex: Idiomas)',
        descricao TEXT NULL COMMENT 'Descrição da categoria',
        tem_niveis BOOLEAN DEFAULT FALSE COMMENT 'Se cursos desta categoria têm níveis',
        status ENUM('ativo', 'inativo') DEFAULT 'ativo',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        
        INDEX idx_nome (nome),
        INDEX idx_status (status)
        
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
    COMMENT='Categorias de cursos (Idiomas, Profissionais, etc)';";
    
    $pdo->exec($sql);
    echo "✅ Tabela 'categorias_curso' criada!\n";
    
    // Inserir categorias padrão
    echo "📥 Inserindo categorias padrão...\n";
    
    $categorias = [
        ['nome' => 'Idiomas', 'descricao' => 'Cursos de línguas estrangeiras', 'tem_niveis' => 1],
        ['nome' => 'Profissionais', 'descricao' => 'Cursos profissionalizantes', 'tem_niveis' => 0],
        ['nome' => 'Informática', 'descricao' => 'Cursos de tecnologia', 'tem_niveis' => 1]
    ];
    
    $insertSql = "INSERT INTO categorias_curso (nome, descricao, tem_niveis) VALUES (?, ?, ?)";
    $insertStmt = $pdo->prepare($insertSql);
    
    foreach ($categorias as $cat) {
        $insertStmt->execute([$cat['nome'], $cat['descricao'], $cat['tem_niveis']]);
        echo "   ✓ {$cat['nome']}\n";
    }
    
    // Registrar migration
    $stmt = $pdo->prepare("INSERT INTO migrations (migration) VALUES (?)");
    $stmt->execute([$migrationName]);
    
    echo "✅ Migração concluída!\n\n";
    
} catch (PDOException $e) {
    echo "❌ ERRO: " . $e->getMessage() . "\n";
    exit(1);
}
?>