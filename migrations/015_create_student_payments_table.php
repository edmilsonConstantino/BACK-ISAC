<?php
/**
 * MIGRAÇÃO 015 - Criar tabela student_payments
 * 📁 LOCAL: migrations/015_create_student_payments_table.php
 */
require_once __DIR__ . '/../config/bootstrap.php';

$migrationName = '015_create_student_payments_table';

$stmt = $pdo->prepare("SELECT COUNT(*) FROM migrations WHERE migration = ?");
$stmt->execute([$migrationName]);

if ($stmt->fetchColumn() > 0) {
    echo "⚠️  Migração '{$migrationName}' já foi executada antes. Pulando...\n";
    exit(0);
}

try {
    // ✅ DEV: drop para permitir recriar
    $pdo->exec("DROP TABLE IF EXISTS student_payments;");

    $sql = "
    CREATE TABLE student_payments (
      id INT AUTO_INCREMENT PRIMARY KEY,

      student_id INT NOT NULL,
      curso_id VARCHAR(50) NOT NULL,

      month_reference CHAR(7) NOT NULL, -- YYYY-MM

      amount_paid DECIMAL(10,2) NOT NULL DEFAULT 0.00,

      payment_type_id INT NOT NULL,

      status ENUM('paid','partial','reversed') NOT NULL DEFAULT 'paid',
      paid_date DATE NOT NULL,
      receipt_number VARCHAR(60) DEFAULT NULL,

      observacoes TEXT DEFAULT NULL,
      data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      data_atualizacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

      CONSTRAINT fk_sp_student
        FOREIGN KEY (student_id) REFERENCES students(id)
        ON DELETE CASCADE ON UPDATE CASCADE,

      CONSTRAINT fk_sp_course
        FOREIGN KEY (curso_id) REFERENCES cursos(codigo)
        ON DELETE RESTRICT ON UPDATE CASCADE,

      CONSTRAINT fk_sp_payment_type
        FOREIGN KEY (payment_type_id) REFERENCES payment_types(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,

      INDEX idx_sp_student (student_id),
      INDEX idx_sp_course (curso_id),
      INDEX idx_sp_month (month_reference),
      INDEX idx_sp_paid_date (paid_date),
      INDEX idx_sp_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    $pdo->exec($sql);

    $stmt = $pdo->prepare("INSERT INTO migrations (migration) VALUES (?)");
    $stmt->execute([$migrationName]);

    echo "✅ student_payments criada com sucesso!\n";
    echo "📝 Migração '{$migrationName}' registrada!\n";

} catch (PDOException $e) {
    echo "❌ Erro na migração '{$migrationName}': " . $e->getMessage() . "\n";
    exit(1);
}
