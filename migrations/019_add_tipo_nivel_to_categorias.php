<?php
/**
 * MIGRATION 019 - Adicionar tipo_nivel e niveis_predefinidos à tabela categorias_curso
 * + Renomear "Idiomas" para "Curso Linguístico"
 * + Configurar "Informática" como tipo nomeado (Básico / Avançado)
 *
 * LOCAL: API-LOGIN/migrations/019_add_tipo_nivel_to_categorias.php
 */
require_once __DIR__ . '/../config/bootstrap.php';

$migrationName = '019_add_tipo_nivel_to_categorias';

$stmt = $pdo->prepare("SELECT COUNT(*) FROM migrations WHERE migration = ?");
$stmt->execute([$migrationName]);

if ($stmt->fetchColumn() > 0) {
    echo "Migration '{$migrationName}' already executed. Skipping...\n";
    exit(0);
}

try {
    echo "Adding tipo_nivel and niveis_predefinidos to categorias_curso...\n";

    // 1. Adicionar coluna tipo_nivel
    $pdo->exec("ALTER TABLE categorias_curso
        ADD COLUMN tipo_nivel ENUM('numerado', 'nomeado') DEFAULT 'numerado'
        COMMENT 'Tipo de nível: numerado (1,2,3...) ou nomeado (Básico, Avançado)'
        AFTER tem_niveis");
    echo "  Column 'tipo_nivel' added.\n";

    // 2. Adicionar coluna niveis_predefinidos (JSON)
    $pdo->exec("ALTER TABLE categorias_curso
        ADD COLUMN niveis_predefinidos JSON NULL
        COMMENT 'Nomes dos níveis predefinidos para tipo nomeado (ex: [\"Básico\", \"Avançado\"])'
        AFTER tipo_nivel");
    echo "  Column 'niveis_predefinidos' added.\n";

    // 3. Renomear "Idiomas" para "Curso Linguístico"
    $stmt = $pdo->prepare("UPDATE categorias_curso SET nome = 'Curso Linguístico', descricao = 'Cursos de línguas (Inglês, Francês, Mandarim, etc.)' WHERE nome = 'Idiomas'");
    $stmt->execute();
    if ($stmt->rowCount() > 0) {
        echo "  Renamed 'Idiomas' -> 'Curso Linguístico'.\n";
    }

    // 4. Configurar "Curso Linguístico" como numerado (já é o default, mas explícito)
    $stmt = $pdo->prepare("UPDATE categorias_curso SET tipo_nivel = 'numerado' WHERE nome = 'Curso Linguístico'");
    $stmt->execute();

    // 5. Configurar "Informática" como nomeado com níveis Básico e Avançado
    $stmt = $pdo->prepare("UPDATE categorias_curso SET tipo_nivel = 'nomeado', niveis_predefinidos = ? WHERE nome = 'Informática'");
    $stmt->execute([json_encode(['Básico', 'Avançado'])]);
    if ($stmt->rowCount() > 0) {
        echo "  Set 'Informática' as nomeado with ['Básico', 'Avançado'].\n";
    }

    // 6. Configurar "Profissionais" como sem níveis (tem_niveis = 0, tipo_nivel irrelevante)
    $stmt = $pdo->prepare("UPDATE categorias_curso SET tipo_nivel = 'numerado' WHERE nome = 'Profissionais'");
    $stmt->execute();

    // Registrar migration
    $stmt = $pdo->prepare("INSERT INTO migrations (migration) VALUES (?)");
    $stmt->execute([$migrationName]);

    echo "Migration complete!\n\n";

} catch (PDOException $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
?>
