<?php
require_once __DIR__ . '/../config/database.php';

// Criar conexão PDO
$database = new Database();
$pdo = $database->getConnection();
