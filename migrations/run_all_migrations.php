<?php
/**
 * EXECUTAR TODAS AS MIGRAÇÕES
 * ============================================================
 * Executa todas as migrações na ordem correta de dependências
 *
 * USO: php migrations/run_all_migrations.php
 *
 * 📁 LOCAL: migrations/run_all_migrations.php
 */

echo "╔══════════════════════════════════════════════════════════════════╗\n";
echo "║        🗄️  EXECUTANDO TODAS AS MIGRAÇÕES DO SISTEMA 🗄️           ║\n";
echo "║                    ISAC - Sistema Acadêmico                      ║\n";
echo "╚══════════════════════════════════════════════════════════════════╝\n\n";

// ============================================================
// ORDEM CORRETA - Respeita TODAS as dependências
// ============================================================
$migrations = [
    // ══════════════════════════════════════════════════════════
    // FASE 1: Tabelas Base (sem dependências)
    // ══════════════════════════════════════════════════════════
    '000_create_migrations_table.php',          // Controle de migrações
    '001_create_users_table.php',               // Admins/usuários do sistema
    '003_create_teachers_table.php',            // Professores
    '005_create_courses_table.php',             // Cursos (base)

    // ══════════════════════════════════════════════════════════
    // FASE 2: Categorias e Níveis de Cursos
    // ══════════════════════════════════════════════════════════
    '010_create_categorias_curso_table.php',    // Categorias de cursos
    '011_create_curso_niveis_table.php',        // Níveis de cursos (FK: cursos)
    '012_add_categoria_to_cursos.php',          // Adiciona FK categoria aos cursos

    // ══════════════════════════════════════════════════════════
    // FASE 3: Estudantes e Turmas
    // ══════════════════════════════════════════════════════════
    '002_create_students_table.php',            // Estudantes (FK: cursos)
    '004_create_turmas_table.php',              // Turmas (FK: professores, cursos)
    '005_create_turma_estudantes_table.php',    // Turma-Estudantes (FK: turmas, students)
    '006_create_registrations_table.php',       // Matrículas (FK: students, cursos, turmas)

    // ══════════════════════════════════════════════════════════
    // FASE 4: Sistema de Pagamentos
    // ══════════════════════════════════════════════════════════
    '013_create_course_fees_table.php',         // Taxas de cursos (FK: cursos)
    '014_create_payment_types_table.php',       // Tipos de pagamento
    '015_create_student_payments_table.php',    // Pagamentos de estudantes (FK: students)
    '016_create_student_payment_plans_table.php', // Planos de pagamento (FK: students)

    // ══════════════════════════════════════════════════════════
    // FASE 5: Autenticação e Segurança
    // ══════════════════════════════════════════════════════════
    '017_create_password_resets_table.php',     // Reset de senhas
    '018_create_refresh_tokens_table.php',      // Refresh tokens JWT

    // ══════════════════════════════════════════════════════════
    // FASE 6: Atualizações e Padronização
    // ══════════════════════════════════════════════════════════
    '019_add_tipo_nivel_to_categorias.php',     // Adiciona tipo_nivel às categorias
    '020_standardize_categorias_to_english.php', // Padroniza categorias para inglês
];

$totalMigrations = count($migrations);
$executedMigrations = 0;
$failedMigrations = 0;
$skippedMigrations = 0;
$alreadyExecuted = 0;

echo "📋 Total de migrações a executar: {$totalMigrations}\n\n";

foreach ($migrations as $index => $migrationFile) {
    $migrationNumber = $index + 1;
    $migrationPath = __DIR__ . '/' . $migrationFile;

    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "📦 [{$migrationNumber}/{$totalMigrations}] Executando: {$migrationFile}\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

    // Verificar se o arquivo existe
    if (!file_exists($migrationPath)) {
        echo "⚠️  AVISO: Arquivo não encontrado: {$migrationFile}\n";
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
    } catch (Error $e) {
        echo "❌ ERRO FATAL ao executar migração: " . $e->getMessage() . "\n";
        echo "📍 Arquivo: {$migrationFile}\n";
        echo "📍 Linha: " . $e->getLine() . "\n";
        $exitCode = 1;
    }

    $output = ob_get_clean();
    echo $output;

    // Verificar resultado
    if ($exitCode === 0 && strpos($output, '❌') === false && strpos($output, 'ERRO') === false) {
        // Verificar se já foi executada antes
        if (strpos($output, 'já existe') !== false || strpos($output, 'already exists') !== false) {
            $alreadyExecuted++;
            echo "   ℹ️  Migração já havia sido executada anteriormente.\n";
        } else {
            $executedMigrations++;
        }
    } else {
        $failedMigrations++;
        echo "\n🛑 Parando execução devido a erro na migração!\n";
        echo "💡 Corrija o erro acima antes de continuar.\n";
        break; // Para na primeira falha
    }

    echo "\n";
}

// ============================================================
// RESUMO FINAL
// ============================================================
echo "\n";
echo "╔══════════════════════════════════════════════════════════════════╗\n";
echo "║                       📊 RESUMO FINAL                            ║\n";
echo "╚══════════════════════════════════════════════════════════════════╝\n";

echo "📈 Total de migrações: {$totalMigrations}\n";
echo "✅ Executadas com sucesso: {$executedMigrations}\n";

if ($alreadyExecuted > 0) {
    echo "🔄 Já executadas anteriormente: {$alreadyExecuted}\n";
}

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

    echo "\n┌─────────────────────────────────────────────────────────────────┐\n";
    echo "│ 📋 TABELAS CRIADAS NA ORDEM CORRETA:                           │\n";
    echo "├─────────────────────────────────────────────────────────────────┤\n";
    echo "│ FASE 1 - Base:                                                 │\n";
    echo "│   • migrations (controle de versão)                            │\n";
    echo "│   • users (admins do sistema)                                  │\n";
    echo "│   • professores (docentes)                                     │\n";
    echo "│   • cursos (base acadêmica)                                    │\n";
    echo "├─────────────────────────────────────────────────────────────────┤\n";
    echo "│ FASE 2 - Categorias:                                           │\n";
    echo "│   • categorias_curso (tipos de cursos)                         │\n";
    echo "│   • curso_niveis (níveis por curso)                            │\n";
    echo "│   • cursos.categoria_id (FK adicionada)                        │\n";
    echo "├─────────────────────────────────────────────────────────────────┤\n";
    echo "│ FASE 3 - Estudantes:                                           │\n";
    echo "│   • students (estudantes)                                      │\n";
    echo "│   • turmas (classes)                                           │\n";
    echo "│   • turma_estudantes (relação N:N)                             │\n";
    echo "│   • registrations (matrículas)                                 │\n";
    echo "├─────────────────────────────────────────────────────────────────┤\n";
    echo "│ FASE 4 - Pagamentos:                                           │\n";
    echo "│   • course_fees (taxas de cursos)                              │\n";
    echo "│   • payment_types (tipos de pagamento)                         │\n";
    echo "│   • student_payments (pagamentos)                              │\n";
    echo "│   • student_payment_plans (planos de pagamento)                │\n";
    echo "├─────────────────────────────────────────────────────────────────┤\n";
    echo "│ FASE 5 - Segurança:                                            │\n";
    echo "│   • password_resets (recuperação de senha)                     │\n";
    echo "│   • refresh_tokens (tokens JWT)                                │\n";
    echo "├─────────────────────────────────────────────────────────────────┤\n";
    echo "│ FASE 6 - Padronização:                                         │\n";
    echo "│   • categorias_curso (tipo_nivel adicionado)                   │\n";
    echo "│   • categorias_curso (padronizado para inglês)                 │\n";
    echo "└─────────────────────────────────────────────────────────────────┘\n";

    echo "\n🔐 SISTEMA DE LOGIN CONFIGURADO:\n";
    echo "   • Estudantes: enrollment_number + password\n";
    echo "   • Professores: username + password\n";
    echo "   • Admins: email + senha\n";

    echo "\n✨ Próximos passos:\n";
    echo "   1. Execute: php seeds/seed_users.php (criar admin padrão)\n";
    echo "   2. Inicie o servidor PHP\n";
    echo "   3. Acesse o sistema!\n";

    exit(0);
}
