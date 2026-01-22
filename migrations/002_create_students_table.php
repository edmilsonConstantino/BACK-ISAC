<?php
/**
 * MIGRATION 002 - Create students table (ENGLISH VERSION)
 * ✅ curso_id is now OPTIONAL (NULL allowed)
 * ✅ Includes LOGIN fields: username, password, last_login
 * 🔐 Login: username OR enrollment_number + password
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
    enrollment_number VARCHAR(50) UNIQUE NOT NULL COMMENT 'Student enrollment number (usado no login)',
    username VARCHAR(100) UNIQUE DEFAULT NULL COMMENT 'Username for login (optional alternative to enrollment_number)',
    bi_number VARCHAR(20) UNIQUE DEFAULT NULL COMMENT 'BI number (12 digits + 1 letter)',
    gender ENUM('M','F') DEFAULT NULL COMMENT 'Gender: M=Male, F=Female',
    
    -- 🔐 LOGIN CREDENTIALS
    password VARCHAR(255) DEFAULT NULL COMMENT 'Hashed password for login',
    last_login DATETIME DEFAULT NULL COMMENT 'Last successful login timestamp',
    
    -- Course (OPTIONAL - set during enrollment)
    curso_id VARCHAR(10) DEFAULT NULL COMMENT 'Course code (optional - assigned during enrollment)',
    curso VARCHAR(100) DEFAULT NULL COMMENT 'Course name (legacy field)',
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
    echo "   ✓ All field names in ENGLISH\n";
    echo "   ✓ Basic info: name, email, phone, birth_date, address\n";
    echo "   ✓ Identification: enrollment_number, username, bi_number, gender\n";
    echo "   🔐 LOGIN: username, password, last_login\n";
    echo "   🔑 Login method: username OR enrollment_number + password\n";
    echo "   ✓ Course: curso_id (OPTIONAL), curso, enrollment_year\n";
    echo "   ✓ Emergency: emergency_contact_1, emergency_contact_2\n";
    echo "   ✓ Other: notes, status\n";
    echo "   ✓ Foreign Key: students.curso_id → cursos.codigo (NULLABLE)\n";
    
    // Register migration as executed
    $stmt = $pdo->prepare("INSERT INTO migrations (migration) VALUES (?)");
    $stmt->execute([$migrationName]);
    echo "📝 Migration '{$migrationName}' registered!\n";
    
} catch (PDOException $e) {
    echo "❌ Error creating 'students' table: " . $e->getMessage() . "\n";
    exit(1);
}
?>