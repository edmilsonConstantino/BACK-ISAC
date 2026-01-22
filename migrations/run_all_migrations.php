<?php
/**
 * EXECUTAR TODAS AS MIGRAÇÕES
 * ✅ ORDEM CORRETA - Respeitando todas as dependências
 * 
 * 📁 LOCAL: migrations/run_all_migrations.php
 */

echo "╔══════════════════════════════════════════════════════════╗\n";
echo "║      🗄️  EXECUTANDO TODAS AS MIGRAÇÕES DO SISTEMA 🗄️     ║\n";
echo "╚══════════════════════════════════════════════════════════╝\n\n";

// ✅ ORDEM CORRETA - Respeita TODAS as dependências
$migrations = [
    '000_create_migrations_table.php',          // 1️⃣ Controle (sem dependências)
    '001_create_users_table.php',               // 2️⃣ Admins (sem dependências)
    '005_create_courses_table.php',             // 3️⃣ CURSOS (sem dependências) - PRIMEIRO!
    '003_create_teachers_table.php',            // 4️⃣ Professores (sem dependências)
    '002_create_students_table.php',            // 5️⃣ Estudantes (depende de cursos)
    '004_create_turmas_table.php',              // 6️⃣ Turmas (depende de professores e cursos)
    '007_create_turma_estudantes_table.php',    // 7️⃣ Turma-Estudantes (depende de turmas e students)
    '006_create_registrations_table.php'        // 8️⃣ Matrículas (depende de students e cursos)
];

$totalMigrations = count($migrations);
$executedMigrations = 0;
$failedMigrations = 0;
$skippedMigrations = 0;

foreach ($migrations as $index => $migrationFile) {
    $migrationNumber = $index + 1;
    $migrationPath = __DIR__ . '/' . $migrationFile;
    
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "📦 [{$migrationNumber}/{$totalMigrations}] Executando: {$migrationFile}\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    
    if (!file_exists($migrationPath)) {
        echo "⚠️  AVISO: Arquivo não encontrado: {$migrationPath}\n";
        echo "   Pulando esta migração...\n\n";
        $skippedMigrations++;
        continue;
    }
    
    // Executa a migração e captura a saída
    ob_start();
    $exitCode = 0;
    
    try {
        include $migrationPath;
    } catch (Exception $e) {
        echo "❌ ERRO ao executar migração: " . $e->getMessage() . "\n";
        echo "📍 Arquivo: {$migrationFile}\n";
        $exitCode = 1;
    }
    
    $output = ob_get_clean();
    echo $output;
    
    if ($exitCode === 0 && strpos($output, '❌') === false) {
        $executedMigrations++;
    } else {
        $failedMigrations++;
        echo "\n🛑 Parando execução devido a erro na migração!\n";
        break; // Para na primeira falha
    }
    
    echo "\n";
}

// Resumo final
echo "╔══════════════════════════════════════════════════════════╗\n";
echo "║                    📊 RESUMO FINAL                       ║\n";
echo "╚══════════════════════════════════════════════════════════╝\n";
echo "✅ Executadas com sucesso: {$executedMigrations}/{$totalMigrations}\n";

if ($skippedMigrations > 0) {
    echo "⏭️  Puladas (arquivo não encontrado): {$skippedMigrations}\n";
}

if ($failedMigrations > 0) {
    echo "❌ Com falha: {$failedMigrations}\n";
    echo "\n⚠️  Execução parada devido a erros!\n";
    echo "💡 Dica: Corrija o erro acima antes de continuar.\n";
    exit(1);
} else {
    echo "\n🎉 Todas as migrações foram executadas com sucesso!\n";
    echo "🚀 Seu banco de dados está pronto para uso!\n";
    
    echo "\n📋 Tabelas criadas na ordem correta:\n";
    echo "   1️⃣ migrations (controle)\n";
    echo "   2️⃣ users (admins)\n";
    echo "   3️⃣ cursos (base para tudo)\n";
    echo "   4️⃣ professores (com login)\n";
    echo "   5️⃣ students (com login + FK para cursos)\n";
    echo "   6️⃣ turmas (FK para professores e cursos)\n";
    echo "   7️⃣ turma_estudantes (FK para turmas e students)\n";
    echo "   8️⃣ registrations (FK para students e cursos)\n";
    
    echo "\n🔐 SISTEMA DE LOGIN CONFIGURADO:\n";
    echo "   • Estudantes: enrollment_number + password\n";
    echo "   • Professores: username + password\n";
    echo "   • Admins: email + senha\n";
    
    exit(0);
}
?>