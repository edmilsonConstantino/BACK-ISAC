<?php
/**
 * MIGRATION 020 - Standardize categorias_curso columns and enums to English
 *
 * Changes:
 *   nome            → name
 *   descricao       → description
 *   tem_niveis      → has_levels
 *   tipo_nivel      → level_type  (numerado/nomeado → numbered/named)
 *   niveis_predefinidos → predefined_levels
 *   status          → status      (ativo/inativo → active/inactive)
 *
 * LOCAL: API-LOGIN/migrations/020_standardize_categorias_to_english.php
 */
require_once __DIR__ . '/../config/bootstrap.php';

$migrationName = '020_standardize_categorias_to_english';

$stmt = $pdo->prepare("SELECT COUNT(*) FROM migrations WHERE migration = ?");
$stmt->execute([$migrationName]);

if ($stmt->fetchColumn() > 0) {
    echo "Migration '{$migrationName}' already executed. Skipping...\n";
    exit(0);
}

try {
    echo "Standardizing categorias_curso to English...\n";

    // 1. Rename columns
    $pdo->exec("ALTER TABLE categorias_curso
        CHANGE COLUMN nome name VARCHAR(100) NOT NULL COMMENT 'Category name',
        CHANGE COLUMN descricao description TEXT NULL COMMENT 'Category description',
        CHANGE COLUMN tem_niveis has_levels TINYINT(1) DEFAULT 0 COMMENT 'Whether courses in this category have levels'
    ");
    echo "  Renamed: nome→name, descricao→description, tem_niveis→has_levels\n";

    // 2. Add new English enum column for level_type, migrate data, drop old
    $pdo->exec("ALTER TABLE categorias_curso
        ADD COLUMN level_type ENUM('numbered','named') DEFAULT 'numbered'
        COMMENT 'Level type: numbered (1,2,3...) or named (Basic, Advanced)'
        AFTER has_levels
    ");
    $pdo->exec("UPDATE categorias_curso SET level_type = 'numbered' WHERE tipo_nivel = 'numerado' OR tipo_nivel IS NULL");
    $pdo->exec("UPDATE categorias_curso SET level_type = 'named' WHERE tipo_nivel = 'nomeado'");
    $pdo->exec("ALTER TABLE categorias_curso DROP COLUMN tipo_nivel");
    echo "  Migrated: tipo_nivel(numerado/nomeado) → level_type(numbered/named)\n";

    // 3. Rename niveis_predefinidos → predefined_levels
    $pdo->exec("ALTER TABLE categorias_curso
        CHANGE COLUMN niveis_predefinidos predefined_levels JSON NULL
        COMMENT 'Predefined level names for named type (e.g. [\"Basic\", \"Advanced\"])'
    ");
    echo "  Renamed: niveis_predefinidos → predefined_levels\n";

    // 4. Add new English enum column for status, migrate data, drop old
    $pdo->exec("ALTER TABLE categorias_curso
        ADD COLUMN status_new ENUM('active','inactive') DEFAULT 'active'
        COMMENT 'Category status'
        AFTER predefined_levels
    ");
    $pdo->exec("UPDATE categorias_curso SET status_new = 'active' WHERE status = 'ativo'");
    $pdo->exec("UPDATE categorias_curso SET status_new = 'inactive' WHERE status = 'inativo'");

    // Drop old indexes on status if they exist
    try {
        $pdo->exec("ALTER TABLE categorias_curso DROP INDEX idx_status");
    } catch (PDOException $e) {
        // Index may not exist, continue
    }

    $pdo->exec("ALTER TABLE categorias_curso DROP COLUMN status");
    $pdo->exec("ALTER TABLE categorias_curso CHANGE COLUMN status_new status ENUM('active','inactive') DEFAULT 'active' COMMENT 'Category status'");
    $pdo->exec("ALTER TABLE categorias_curso ADD INDEX idx_status (status)");
    echo "  Migrated: status(ativo/inativo) → status(active/inactive)\n";

    // 5. Rename name index
    try {
        $pdo->exec("ALTER TABLE categorias_curso DROP INDEX idx_nome");
    } catch (PDOException $e) {
        // Index may not exist
    }
    $pdo->exec("ALTER TABLE categorias_curso ADD INDEX idx_name (name)");
    echo "  Renamed index: idx_nome → idx_name\n";

    // Register migration
    $stmt = $pdo->prepare("INSERT INTO migrations (migration) VALUES (?)");
    $stmt->execute([$migrationName]);

    echo "Migration complete!\n\n";

} catch (PDOException $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
?>
