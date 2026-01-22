<?php
// Configurações do banco de dados
define('DB_HOST', 'localhost');
define('DB_NAME', 'isacc');  // ← MUDOU AQUI
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Classe de conexão
class Database {
    private $conn = null;

    public function getConnection() {
        if ($this->conn === null) {
            try {
                $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
                
                $options = [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ];

                $this->conn = new PDO($dsn, DB_USER, DB_PASS, $options);
                
            } catch(PDOException $e) {
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'message' => 'Erro na conexão com o banco de dados'
                ]);
                exit();
            }
        }

        return $this->conn;
    }
}
?>