<?php
/**
 * IMPORTAR BANCO DE DADOS
 * ============================================================
 * Importa um ficheiro SQL de backup
 *
 * USO: php scripts/import_database.php [arquivo.sql]
 *
 * Se não especificar arquivo, usa o backup mais recente da pasta backups/
 */

echo "╔══════════════════════════════════════════════════════════════════╗\n";
echo "║           📥 IMPORTAR BANCO DE DADOS ISAC 📥                     ║\n";
echo "╚══════════════════════════════════════════════════════════════════╝\n\n";

// Configurações do banco
$host = 'localhost';
$dbname = 'isac_academic';
$username = 'root';
$password = '';

// Determinar arquivo a importar
$backupDir = __DIR__ . '/../backups';
$sqlFile = null;

if (isset($argv[1])) {
    // Arquivo especificado como argumento
    $sqlFile = $argv[1];
    if (!file_exists($sqlFile)) {
        $sqlFile = $backupDir . '/' . $argv[1];
    }
} else {
    // Buscar backup mais recente
    if (is_dir($backupDir)) {
        $files = glob($backupDir . '/isac_backup_*.sql');
        if (!empty($files)) {
            usort($files, function($a, $b) {
                return filemtime($b) - filemtime($a);
            });
            $sqlFile = $files[0];
        }
    }
}

if (!$sqlFile || !file_exists($sqlFile)) {
    echo "❌ Nenhum arquivo SQL encontrado!\n\n";
    echo "📋 USO:\n";
    echo "   php scripts/import_database.php                    # Usa backup mais recente\n";
    echo "   php scripts/import_database.php backup.sql         # Especifica arquivo\n";
    echo "   php scripts/import_database.php backups/file.sql   # Caminho completo\n";
    echo "\n";
    echo "💡 Primeiro execute: php scripts/export_database.php (no computador de origem)\n";
    exit(1);
}

echo "📄 Arquivo: {$sqlFile}\n";
echo "📊 Tamanho: " . round(filesize($sqlFile) / 1024, 2) . " KB\n\n";

// Confirmar importação
echo "⚠️  ATENÇÃO: Isto vai SUBSTITUIR todas as tabelas existentes!\n";
echo "   Deseja continuar? (s/n): ";

$handle = fopen("php://stdin", "r");
$line = fgets($handle);
fclose($handle);

if (trim(strtolower($line)) !== 's') {
    echo "\n❌ Importação cancelada pelo utilizador.\n";
    exit(0);
}

echo "\n🔗 Conectando ao MySQL...\n";

try {
    // Primeiro conectar sem selecionar banco (para poder criá-lo)
    $pdo = new PDO(
        "mysql:host={$host};charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::MYSQL_ATTR_LOCAL_INFILE => true
        ]
    );
    echo "✅ Conexão estabelecida!\n\n";
} catch (PDOException $e) {
    die("❌ Erro de conexão: " . $e->getMessage() . "\n");
}

echo "📥 Importando banco de dados...\n";

// Ler o arquivo SQL
$sql = file_get_contents($sqlFile);

// Dividir em comandos individuais
$commands = array_filter(
    array_map('trim', explode(';', $sql)),
    function($cmd) {
        return !empty($cmd) && !preg_match('/^--/', $cmd);
    }
);

$total = count($commands);
$executed = 0;
$errors = 0;

echo "📋 {$total} comandos SQL a executar...\n\n";

foreach ($commands as $command) {
    if (empty(trim($command))) continue;

    try {
        $pdo->exec($command);
        $executed++;

        // Mostrar progresso a cada 10 comandos
        if ($executed % 10 === 0 || $executed === $total) {
            $percent = round(($executed / $total) * 100);
            echo "\r   Progresso: {$executed}/{$total} ({$percent}%)";
        }
    } catch (PDOException $e) {
        $errors++;
        // Ignorar erros de "já existe" mas logar outros
        if (strpos($e->getMessage(), 'already exists') === false &&
            strpos($e->getMessage(), 'Duplicate') === false) {
            echo "\n   ⚠️  Aviso: " . substr($e->getMessage(), 0, 100) . "\n";
        }
    }
}

echo "\n\n";
echo "╔══════════════════════════════════════════════════════════════════╗\n";
echo "║                    ✅ IMPORTAÇÃO CONCLUÍDA!                      ║\n";
echo "╚══════════════════════════════════════════════════════════════════╝\n";
echo "\n";
echo "📊 Comandos executados: {$executed}\n";
if ($errors > 0) {
    echo "⚠️  Avisos/erros ignorados: {$errors}\n";
}
echo "\n";
echo "🚀 O banco de dados está pronto para uso!\n";
echo "\n";
echo "✨ Próximos passos:\n";
echo "   1. Verifique se o config/database.php tem as credenciais corretas\n";
echo "   2. Inicie o Apache e MySQL no XAMPP\n";
echo "   3. Acesse o sistema!\n";
