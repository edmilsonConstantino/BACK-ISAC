<?php
/**
 * MIGRAÇÃO 003 - Criar tabela professores
 * ✅ Includes LOGIN fields: username, password, last_login
 * 🔐 Login: username + password
 * 
 * 📁 LOCAL: migrations/003_create_teachers_table.php
 */
require_once __DIR__ . '/../config/bootstrap.php';

$migrationName = '003_create_teachers_table';

// Verifica se já foi executada
$stmt = $pdo->prepare("SELECT COUNT(*) FROM migrations WHERE migration = ?");
$stmt->execute([$migrationName]);

if ($stmt->fetchColumn() > 0) {
    echo "⚠️  Migração '{$migrationName}' já foi executada antes. Pulando...\n";
    exit(0);
}

// SQL para criar a tabela (com TODOS os campos que a API usa)
$sql = "
CREATE TABLE IF NOT EXISTS professores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    
    -- Informações Básicas
    nome VARCHAR(100) NOT NULL,
    email VARCHAR(150) UNIQUE NOT NULL,
    telefone VARCHAR(20) DEFAULT NULL,
    especialidade VARCHAR(100) DEFAULT NULL,
    data_nascimento DATE DEFAULT NULL,
    endereco VARCHAR(255) DEFAULT NULL,
    
    -- 🔐 CREDENCIAIS DE LOGIN
    username VARCHAR(100) UNIQUE DEFAULT NULL COMMENT 'Username para login',
    password VARCHAR(255) DEFAULT NULL COMMENT 'Senha hash para login',
    last_login DATETIME DEFAULT NULL COMMENT 'Último login bem-sucedido',
    
    -- Informações Contratuais
    tipo_contrato ENUM('tempo_integral', 'meio_periodo', 'freelancer', 'substituto') DEFAULT 'tempo_integral',
    data_inicio DATE DEFAULT NULL,
    salario DECIMAL(10,2) DEFAULT NULL,
    
    -- Contatos e Observações
    contato_emergencia VARCHAR(150) DEFAULT NULL,
    observacoes TEXT DEFAULT NULL,
    
    -- Status e Timestamps
    status ENUM('ativo', 'inativo', 'licenca') DEFAULT 'ativo',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Índices para performance
    INDEX idx_username (username),
    INDEX idx_status (status),
    INDEX idx_nome (nome)
    
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Professores table - Login: username + password';
";

try {
    // Executa a criação da tabela
    $pdo->exec($sql);
    echo "✅ Tabela 'professores' criada com sucesso!\n";
    echo "   ✓ Informações básicas: nome, email, telefone, etc\n";
    echo "   🔐 LOGIN: username, password, last_login\n";
    echo "   🔑 Método de login: username + password\n";
    echo "   ✓ Informações contratuais completas\n";
    
    // Registra a migração como executada
    $stmt = $pdo->prepare("INSERT INTO migrations (migration) VALUES (?)");
    $stmt->execute([$migrationName]);
    echo "📝 Migração '{$migrationName}' registrada!\n";
    
} catch (PDOException $e) {
    echo "❌ Erro ao criar tabela 'professores': " . $e->getMessage() . "\n";
    exit(1);
}
?>