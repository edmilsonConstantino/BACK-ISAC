<?php
/**
 * MIGRATION 011 - Criar tabela curso_niveis
 * 
 * 📁 LOCAL: API-LOGIN/migrations/011_create_curso_niveis_table.php
 */
require_once __DIR__ . '/../config/bootstrap.php';

$migrationName = '011_create_curso_niveis_table';

$stmt = $pdo->prepare("SELECT COUNT(*) FROM migrations WHERE migration = ?");
$stmt->execute([$migrationName]);

if ($stmt->fetchColumn() > 0) {
    echo "⚠️  Migração '{$migrationName}' já executada. Pulando...\n";
    exit(0);
}

try {
    echo "📚 Criando tabela curso_niveis...\n";
    
    $sql = "CREATE TABLE IF NOT EXISTS curso_niveis (
        id INT AUTO_INCREMENT PRIMARY KEY,
        curso_id INT NOT NULL COMMENT 'ID do curso',
        nivel INT NOT NULL COMMENT 'Número do nível (1, 2, 3...)',
        nome VARCHAR(100) NOT NULL COMMENT 'Nome do nível (ex: Nível 1, Básico)',
        descricao TEXT NULL COMMENT 'Descrição do nível',
        duracao_meses INT NOT NULL DEFAULT 4 COMMENT 'Duração em meses',
        ordem INT NOT NULL COMMENT 'Ordem de execução',
        prerequisito_nivel_id INT NULL COMMENT 'ID do nível anterior (se houver)',
        status ENUM('ativo', 'inativo') DEFAULT 'ativo',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        
        FOREIGN KEY (curso_id) REFERENCES cursos(id) ON DELETE CASCADE,
        FOREIGN KEY (prerequisito_nivel_id) REFERENCES curso_niveis(id) ON DELETE SET NULL,
        
        UNIQUE KEY unique_curso_nivel (curso_id, nivel),
        INDEX idx_curso_id (curso_id),
        INDEX idx_ordem (ordem)
        
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
    COMMENT='Níveis de cursos (ex: Inglês Nível 1, 2, 3...)';";
    
    $pdo->exec($sql);
    echo "✅ Tabela 'curso_niveis' criada!\n";
    
    // Registrar migration
    $stmt = $pdo->prepare("INSERT INTO migrations (migration) VALUES (?)");
    $stmt->execute([$migrationName]);
    
    echo "✅ Migração concluída!\n\n";
    
} catch (PDOException $e) {
    echo "❌ ERRO: " . $e->getMessage() . "\n";
    exit(1);
}
?>