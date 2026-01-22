<?php
/**
 * EXECUTAR TODAS AS MIGRAÇÕES
 * 
 * Este script executa todas as migrações do sistema de uma só vez
 * na ordem correta (respeitando dependências).
 * 
 * 📁 LOCAL: migrations/run_all_migrations.php
 */

echo "╔══════════════════════════════════════════════════════════╗\n";
echo "║      🗄️  EXECUTANDO TODAS AS MIGRAÇÕES DO SISTEMA 🗄️     ║\n";
echo "╚══════════════════════════════════════════════════════════╝\n\n";

// Lista de migrações na ordem correta de execução
$migrations = [
    '000_create_migrations_table.php',   // 1️⃣ Controle de migrações
    '001_create_users_table.php',        // 2️⃣ Usuários do sistema
    '002_create_students_table.php',   // 3️⃣ Estudantes
    '003_create_professores_table.php',  // 4️⃣ Professores
    '004_create_turmas_table.php'        // 5️⃣ Turmas (depende de professores)
];

$totalMigrations = count($migrations);
$executedMigrations = 0;
$failedMigrations = 0;

foreach ($migrations as $index => $migrationFile) {
    $migrationNumber = $index + 1;
    $migrationPath = __DIR__ . '/' . $migrationFile;
    
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "📦 [{$migrationNumber}/{$totalMigrations}] Executando: {$migrationFile}\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    
    if (!file_exists($migrationPath)) {
        echo "❌ ERRO: Arquivo não encontrado: {$migrationPath}\n";
        $failedMigrations++;
        continue;
    }
    
    // Executa a migração e captura a saída
    ob_start();
    $exitCode = 0;
    
    try {
        include $migrationPath;
    } catch (Exception $e) {
        echo "❌ ERRO ao executar migração: " . $e->getMessage() . "\n";
        $exitCode = 1;
    }
    
    $output = ob_get_clean();
    echo $output;
    
    if ($exitCode === 0) {
        $executedMigrations++;
    } else {
        $failedMigrations++;
    }
    
    echo "\n";
}

// Resumo final
echo "╔══════════════════════════════════════════════════════════╗\n";
echo "║                    📊 RESUMO FINAL                       ║\n";
echo "╚══════════════════════════════════════════════════════════╝\n";
echo "✅ Migrações executadas com sucesso: {$executedMigrations}/{$totalMigrations}\n";

if ($failedMigrations > 0) {
    echo "❌ Migrações com falha: {$failedMigrations}\n";
    echo "\n⚠️  Algumas migrações falharam. Verifique os erros acima.\n";
    exit(1);
} else {
    echo "\n🎉 Todas as migrações foram executadas com sucesso!\n";
    echo "🚀 Seu banco de dados está pronto para uso!\n";
    
    echo "\n📋 Tabelas criadas:\n";
    echo "   ✓ migrations (controle)\n";
    echo "   ✓ users (usuários do sistema)\n";
    echo "   ✓ students\n";
    echo "   ✓ professores\n";
    echo "   ✓ turmas\n";
    
    exit(0);
}