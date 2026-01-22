<?php
/**
 * TESTE DE CONEXÃO BÁSICA
 * 📁 Salvar como: API-LOGIN/test-connection.php
 * 🌐 Acessar: http://localhost/api-login/test-connection.php
 */

header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo json_encode([
    'status' => 'OK',
    'message' => 'Servidor PHP está funcionando',
    'php_version' => phpversion(),
    'current_dir' => __DIR__,
    'time' => date('Y-m-d H:i:s')
]);

// Testar se consegue escrever arquivo
$log_file = __DIR__ . '/test.log';
$written = file_put_contents($log_file, "Test at " . date('Y-m-d H:i:s') . "\n");

if ($written === false) {
    echo json_encode(['error' => 'Não consegue escrever arquivos']);
} else {
    echo json_encode(['log_written' => true, 'log_path' => $log_file]);
}
?>