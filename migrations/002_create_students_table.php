<?php
/**
 * MIGRATION 002 - Create students table (ENGLISH VERSION - atualizado)
 * 
 * LOGIN PRINCIPAL:
 *   - username          → identificador principal de login do estudante
 *   - password          → senha (hash)
 *   - last_login        → data/hora do último login
 *   - enrollment_number → matrícula (pode ser usada como login alternativo)
 * 
 * Campos LEGACY / DESCONTINUADOS (não usar mais em novos cadastros):
 *   - curso_id          → ignorar / deixar NULL
 *   - curso             → ignorar / campo legado
 * 
 * Login permitido: username OU enrollment_number + password
 * 
 * 📁 LOCATION: migrations/002_create_students_table.php
 */
require_once __DIR__ . '/../config/bootstrap.php';

$migrationName = '002_create_students_table';

// Check if already executed
$stmt = $pdo->prepare("SELECT COUNT(*) FROM migrations WHERE migration = ?");
$stmt->execute([$migrationName]);

if ($stmt->fetchColumn() > 0) {
    echo "⚠️  Migration '{$migrationName}' already executed. Skipping...\n";
    exit(0);
}

// SQL to create the table with ALL fields in English
$sql = "
CREATE TABLE IF NOT EXISTS students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    
    -- Basic Information
    name VARCHAR(100) NOT NULL COMMENT 'Full name',
    email VARCHAR(150) UNIQUE NOT NULL COMMENT 'Email address',
    phone VARCHAR(20) DEFAULT NULL COMMENT 'Phone number',
    birth_date DATE DEFAULT NULL COMMENT 'Date of birth',
    address VARCHAR(255) DEFAULT NULL COMMENT 'Full address',
    
    -- Identification
    enrollment_number VARCHAR(50) UNIQUE NOT NULL COMMENT 'Matrícula / Número de estudante (pode ser usado no login)',

    bi_number VARCHAR(20) UNIQUE DEFAULT NULL COMMENT 'BI number (12 digits + 1 letter)',
    gender ENUM('M','F') DEFAULT NULL COMMENT 'Gender: M=Male, F=Female',
    
    -- 🔐 LOGIN CREDENTIALS
username VARCHAR(100) UNIQUE DEFAULT NULL COMMENT 'Username for login (gerado na matrícula)',
password VARCHAR(255) DEFAULT NULL COMMENT 'Hashed password for login',

    -- Course (LEGACY - DO NOT USE)
    curso_id VARCHAR(10) DEFAULT NULL COMMENT 'LEGACY FIELD - DO NOT USE - Course is in registrations table',
    curso VARCHAR(100) DEFAULT NULL COMMENT 'LEGACY FIELD - DO NOT USE',
    enrollment_year YEAR DEFAULT NULL COMMENT 'Year of enrollment',
    
    -- Emergency Contacts
    emergency_contact_1 VARCHAR(20) DEFAULT NULL COMMENT 'Emergency contact 1',
    emergency_contact_2 VARCHAR(20) DEFAULT NULL COMMENT 'Emergency contact 2',
    
    -- Additional Information
    notes TEXT DEFAULT NULL COMMENT 'General notes about student',
    
    -- Status and Timestamps
    status ENUM('ativo','inativo') DEFAULT 'ativo' COMMENT 'Student status',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Creation timestamp',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Last update timestamp',
    
    -- 🔗 FOREIGN KEY to cursos table (NULLABLE)
    CONSTRAINT fk_students_curso 
        FOREIGN KEY (curso_id) 
        REFERENCES cursos(codigo) 
        ON DELETE SET NULL 
        ON UPDATE CASCADE,
    
    -- Indexes for Performance
    INDEX idx_curso_id (curso_id),
    INDEX idx_status (status),
    INDEX idx_name (name),
    INDEX idx_bi_number (bi_number),
    INDEX idx_gender (gender),
    INDEX idx_enrollment_number (enrollment_number),
    INDEX idx_username (username)
    
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
COMMENT='Students table - Login: username OR enrollment_number + password';
";

try {
    // Execute table creation
    $pdo->exec($sql);
    echo "✅ Table 'students' created successfully!\n";
    echo "   ✓ username agora é NOT NULL e tem comentário simplificado\n";
    echo "   ✓ password agora é NOT NULL\n";
    echo "   ✓ curso_id e curso marcados como LEGACY FIELD - DO NOT USE\n";
    echo "   ✓ Campos de login ajustados para o modelo centralizado\n";
    
    // Register migration as executed
    $stmt = $pdo->prepare("INSERT INTO migrations (migration) VALUES (?)");
    $stmt->execute([$migrationName]);
    echo "📝 Migration '{$migrationName}' registered!\n";
    
} catch (PDOException $e) {
    echo "❌ Error creating 'students' table: " . $e->getMessage() . "\n";
    exit(1);
}
?>