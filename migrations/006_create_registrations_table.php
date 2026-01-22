<?php
/**
 * MIGRATION 006 - Create registrations table (Matrículas)
 * 
 * 📁 LOCATION: migrations/006_create_registrations_table.php
 */

require_once __DIR__ . '/../config/bootstrap.php';

$migrationName = '006_create_registrations_table';

// Check if already executed
$stmt = $pdo->prepare("SELECT COUNT(*) FROM migrations WHERE migration = ?");
$stmt->execute([$migrationName]);
if ($stmt->fetchColumn() > 0) {
    echo "⚠️  Migration '{$migrationName}' already executed. Skipping...\n";
    exit(0);
}

$sql = "
CREATE TABLE IF NOT EXISTS registrations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    
    -- Vínculo com estudante (OBRIGATÓRIO)
    student_id INT NOT NULL COMMENT 'ID do estudante',
    
    -- Vínculo com curso (OBRIGATÓRIO)
    course_id VARCHAR(10) NOT NULL COMMENT 'Código do curso',
    
    -- Vínculo com turma (OPCIONAL - estudante pode estar matriculado sem turma definida)
    class_id INT DEFAULT NULL COMMENT 'ID da turma',
    
    -- Dados da matrícula
    enrollment_number VARCHAR(50) UNIQUE NOT NULL COMMENT 'Número único da matrícula (ex: MAT2025001)',
    period VARCHAR(10) NOT NULL COMMENT 'Período letivo (ex: 2025/1)',
    enrollment_date DATE NOT NULL COMMENT 'Data da matrícula',
    
    -- Status da matrícula
    status ENUM('active', 'suspended', 'cancelled', 'completed') DEFAULT 'active' COMMENT 'Status da matrícula',
    
    -- Status de pagamento
    payment_status ENUM('paid', 'pending', 'overdue') DEFAULT 'pending' COMMENT 'Status do pagamento',
    
    -- Valores financeiros (em MZN)
    enrollment_fee DECIMAL(10, 2) DEFAULT 0.00 COMMENT 'Taxa de matrícula',
    monthly_fee DECIMAL(10, 2) DEFAULT 0.00 COMMENT 'Valor da mensalidade',
    
    -- Credenciais de acesso ao sistema
    username VARCHAR(50) UNIQUE NOT NULL COMMENT 'Usuário para login no sistema',
    password VARCHAR(255) NOT NULL COMMENT 'Senha hash para login',
    
    -- Observações
    observations TEXT DEFAULT NULL COMMENT 'Observações sobre a matrícula',
    
    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- 🔗 FOREIGN KEYS
    CONSTRAINT fk_registration_student 
        FOREIGN KEY (student_id) 
        REFERENCES students(id) 
        ON DELETE RESTRICT 
        ON UPDATE CASCADE,
    
    CONSTRAINT fk_registration_course 
        FOREIGN KEY (course_id) 
        REFERENCES cursos(codigo) 
        ON DELETE RESTRICT 
        ON UPDATE CASCADE,
    
    CONSTRAINT fk_registration_class 
        FOREIGN KEY (class_id) 
        REFERENCES turmas(id) 
        ON DELETE SET NULL 
        ON UPDATE CASCADE,
    
    -- 📊 INDEXES para performance
    INDEX idx_student (student_id),
    INDEX idx_course (course_id),
    INDEX idx_class (class_id),
    INDEX idx_period (period),
    INDEX idx_status (status),
    INDEX idx_payment_status (payment_status),
    INDEX idx_enrollment_number (enrollment_number),
    INDEX idx_username (username),
    
    -- ✅ UNIQUE constraint para evitar matrículas duplicadas no mesmo período
    UNIQUE KEY unique_student_course_period (student_id, course_id, period)
    
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
COMMENT='Matrículas dos estudantes nos cursos';
";

try {
    // Execute table creation
    $pdo->exec($sql);
    echo "✅ Table 'registrations' created successfully!\n";
    echo "   ✓ student_id → students.id (REQUIRED)\n";
    echo "   ✓ course_id → cursos.codigo (REQUIRED)\n";
    echo "   ✓ class_id → turmas.id (OPTIONAL)\n";
    echo "   ✓ enrollment_number (UNIQUE)\n";
    echo "   ✓ username (UNIQUE)\n";
    echo "   ✓ password (HASHED)\n";
    echo "   ✓ Unique constraint: student + course + period\n";
    
    // Register migration as executed
    $stmt = $pdo->prepare("INSERT INTO migrations (migration) VALUES (?)");
    $stmt->execute([$migrationName]);
    echo "📝 Migration '{$migrationName}' registered!\n";
    
} catch (PDOException $e) {
    echo "❌ Error creating 'registrations' table: " . $e->getMessage() . "\n";
    exit(1);
}
?>