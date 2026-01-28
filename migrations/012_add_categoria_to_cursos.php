<?php
/**
 * MIGRATION 012 - Adicionar categoria_id em cursos
 * 
 * 📁 LOCAL: API-LOGIN/migrations/012_add_categoria_to_cursos.php
 */
require_once __DIR__ . '/../config/bootstrap.php';

$migrationName = '012_add_categoria_to_cursos';

$stmt = $pdo->prepare("SELECT COUNT(*) FROM migrations WHERE migration = ?");
$stmt->execute([$migrationName]);

if ($stmt->fetchColumn() > 0) {
    echo "⚠️  Migração '{$migrationName}' já executada. Pulando...\n";
    exit(0);
}

try {
    echo "🔧 Adicionando categoria_id em cursos...\n";
    
    // Verificar se coluna já existe
    $checkColumn = $pdo->query("SHOW COLUMNS FROM cursos LIKE 'categoria_id'")->fetch();
    
    if (!$checkColumn) {
        $sql = "ALTER TABLE cursos 
                ADD COLUMN categoria_id INT NULL AFTER codigo,
                ADD FOREIGN KEY (categoria_id) REFERENCES categorias_curso(id) ON DELETE SET NULL,
                ADD INDEX idx_categoria_id (categoria_id)";
        
        $pdo->exec($sql);
        echo "✅ Coluna categoria_id adicionada!\n";
    } else {
        echo "⚠️  Coluna categoria_id já existe\n";
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