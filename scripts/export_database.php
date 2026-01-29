<?php
/**
 * EXPORTAR BANCO DE DADOS COMPLETO
 * ============================================================
 * Cria um ficheiro SQL com toda a estrutura e dados
 *
 * USO: php scripts/export_database.php
 *
 * O ficheiro será salvo em: backups/isac_backup_YYYYMMDD_HHMMSS.sql
 */

require_once __DIR__ . '/../config/database.php';

echo "╔══════════════════════════════════════════════════════════════════╗\n";
echo "║           📦 EXPORTAR BANCO DE DADOS ISAC 📦                     ║\n";
echo "╚══════════════════════════════════════════════════════════════════╝\n\n";

// Criar pasta de backups se não existir
$backupDir = __DIR__ . '/../backups';
if (!is_dir($backupDir)) {
    mkdir($backupDir, 0755, true);
    echo "📁 Pasta 'backups' criada\n";
}

// Nome do arquivo de backup
$timestamp = date('Ymd_His');
$backupFile = "{$backupDir}/isac_backup_{$timestamp}.sql";

// Configurações do banco
$host = 'localhost';
$dbname = 'isacc';  // Ajuste conforme seu config/database.php
$username = 'root';
$password = '';

echo "🔗 Conectando ao banco de dados...\n";

try {
    $pdo = new PDO(
        "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
        $username,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    echo "✅ Conexão estabelecida!\n\n";
} catch (PDOException $e) {
    die("❌ Erro de conexão: " . $e->getMessage() . "\n");
}

// Iniciar conteúdo SQL
$sql = "-- ============================================================\n";
$sql .= "-- BACKUP DO BANCO DE DADOS ISAC\n";
$sql .= "-- Data: " . date('Y-m-d H:i:s') . "\n";
$sql .= "-- Banco: {$dbname}\n";
$sql .= "-- ============================================================\n\n";

$sql .= "SET FOREIGN_KEY_CHECKS = 0;\n";
$sql .= "SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\n";
$sql .= "SET AUTOCOMMIT = 0;\n";
$sql .= "START TRANSACTION;\n\n";

// Criar banco se não existir
$sql .= "-- Criar banco de dados\n";
$sql .= "CREATE DATABASE IF NOT EXISTS `{$dbname}` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;\n";
$sql .= "USE `{$dbname}`;\n\n";

// Obter todas as tabelas
$tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
$totalTables = count($tables);

echo "📋 Encontradas {$totalTables} tabelas para exportar\n\n";

foreach ($tables as $index => $table) {
    $num = $index + 1;
    echo "  [{$num}/{$totalTables}] Exportando: {$table}...\n";

    // DROP TABLE
    $sql .= "-- ============================================================\n";
    $sql .= "-- Tabela: {$table}\n";
    $sql .= "-- ============================================================\n";
    $sql .= "DROP TABLE IF EXISTS `{$table}`;\n";

    // CREATE TABLE
    $createTable = $pdo->query("SHOW CREATE TABLE `{$table}`")->fetch(PDO::FETCH_ASSOC);
    $sql .= $createTable['Create Table'] . ";\n\n";

    // INSERT dados
    $rows = $pdo->query("SELECT * FROM `{$table}`")->fetchAll(PDO::FETCH_ASSOC);

    if (count($rows) > 0) {
        $columns = array_keys($rows[0]);
        $columnList = '`' . implode('`, `', $columns) . '`';

        $sql .= "-- Dados da tabela {$table}\n";

        foreach ($rows as $row) {
            $values = array_map(function($value) use ($pdo) {
                if ($value === null) {
                    return 'NULL';
                }
                return $pdo->quote($value);
            }, array_values($row));

            $valueList = implode(', ', $values);
            $sql .= "INSERT INTO `{$table}` ({$columnList}) VALUES ({$valueList});\n";
        }

        $sql .= "\n";
        echo "      ✓ " . count($rows) . " registros exportados\n";
    } else {
        echo "      ✓ Tabela vazia\n";
    }
}

$sql .= "SET FOREIGN_KEY_CHECKS = 1;\n";
$sql .= "COMMIT;\n";
$sql .= "\n-- FIM DO BACKUP\n";

// Salvar arquivo
file_put_contents($backupFile, $sql);

$fileSize = round(filesize($backupFile) / 1024, 2);

echo "\n";
echo "╔══════════════════════════════════════════════════════════════════╗\n";
echo "║                    ✅ EXPORTAÇÃO CONCLUÍDA!                      ║\n";
echo "╚══════════════════════════════════════════════════════════════════╝\n";
echo "\n";
echo "📄 Arquivo: {$backupFile}\n";
echo "📊 Tamanho: {$fileSize} KB\n";
echo "📋 Tabelas: {$totalTables}\n";
echo "\n";
echo "┌─────────────────────────────────────────────────────────────────┐\n";
echo "│ 🚀 COMO USAR NO OUTRO COMPUTADOR:                              │\n";
echo "├─────────────────────────────────────────────────────────────────┤\n";
echo "│                                                                 │\n";
echo "│ OPÇÃO 1 - Via linha de comando:                                │\n";
echo "│   mysql -u root -p < backups/isac_backup_{$timestamp}.sql      │\n";
echo "│                                                                 │\n";
echo "│ OPÇÃO 2 - Via phpMyAdmin:                                      │\n";
echo "│   1. Abra phpMyAdmin no navegador                              │\n";
echo "│   2. Clique em 'Importar' no menu superior                     │\n";
echo "│   3. Selecione o arquivo .sql                                  │\n";
echo "│   4. Clique em 'Executar'                                      │\n";
echo "│                                                                 │\n";
echo "│ OPÇÃO 3 - Via script PHP:                                      │\n";
echo "│   php scripts/import_database.php                              │\n";
echo "│                                                                 │\n";
echo "└─────────────────────────────────────────────────────────────────┘\n";
