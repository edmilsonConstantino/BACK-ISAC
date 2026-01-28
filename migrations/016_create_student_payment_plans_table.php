<?php
/**
 * MIGRAÇÃO 016 - Criar tabela student_payment_plans
 * 📁 LOCAL: migrations/016_create_student_payment_plans_table.php
 */
require_once __DIR__ . '/../config/bootstrap.php';

$migrationName = '016_create_student_payment_plans_table';

$stmt = $pdo->prepare("SELECT COUNT(*) FROM migrations WHERE migration = ?");
$stmt->execute([$migrationName]);

if ($stmt->fetchColumn() > 0) {
    echo "⚠️  Migração '{$migrationName}' já foi executada antes. Pulando...\n";
    exit(0);
}

try {
    // ⚠️ Ordem importa (plans depende de payments via payment_id)
    $pdo->exec("DROP TABLE IF EXISTS student_payment_plans;");

    $sql = "
    CREATE TABLE student_payment_plans (
      id INT AUTO_INCREMENT PRIMARY KEY,

      student_id INT NOT NULL,
      curso_id VARCHAR(50) NOT NULL,

      month_reference CHAR(7) NOT NULL, -- YYYY-MM
      due_date DATE NOT NULL,
      amount_due DECIMAL(10,2) NOT NULL DEFAULT 0.00,

      status ENUM('pending','paid','overdue','partial') NOT NULL DEFAULT 'pending',

      payment_id INT DEFAULT NULL,

      observacoes TEXT DEFAULT NULL,
      data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      data_atualizacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

      CONSTRAINT uq_plan UNIQUE (student_id, curso_id, month_reference),

      CONSTRAINT fk_plan_student
        FOREIGN KEY (student_id) REFERENCES students(id)
        ON DELETE CASCADE ON UPDATE CASCADE,

      CONSTRAINT fk_plan_course
        FOREIGN KEY (curso_id) REFERENCES cursos(codigo)
        ON DELETE RESTRICT ON UPDATE CASCADE,

      CONSTRAINT fk_plan_payment
        FOREIGN KEY (payment_id) REFERENCES student_payments(id)
        ON DELETE SET NULL ON UPDATE CASCADE,

      INDEX idx_plan_student (student_id),
      INDEX idx_plan_course (curso_id),
      INDEX idx_plan_status (status),
      INDEX idx_plan_due (due_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    $pdo->exec($sql);

    $stmt = $pdo->prepare("INSERT INTO migrations (migration) VALUES (?)");
    $stmt->execute([$migrationName]);

    echo "✅ student_payment_plans criada com sucesso!\n";
    echo "📝 Migração '{$migrationName}' registrada!\n";

} catch (PDOException $e) {
    echo "❌ Erro na migração '{$migrationName}': " . $e->getMessage() . "\n";
    exit(1);
}
