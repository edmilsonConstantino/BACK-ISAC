<?php
// Teste o login via PHP
$url = 'http://localhost/API-LOGIN/auth/login.php';

$data = [
    'email' => 'admin@example.com',
    'senha' => '8456@'
];

$options = [
    'http' => [
        'header'  => "Content-Type: application/json\r\n",
        'method'  => 'POST',
        'content' => json_encode($data)
    ]
];

$context = stream_context_create($options);
$result = file_get_contents($url, false, $context);

echo "<h2>📡 Teste de Login</h2>";
echo "<pre>";
echo htmlspecialchars($result);
echo "</pre>";

$response = json_decode($result, true);

if ($response['success']) {
    echo "<p style='color:green'>✅ Login bem-sucedido!</p>";
    echo "<pre>";
    print_r($response);
    echo "</pre>";
} else {
    echo "<p style='color:red'>❌ " . $response['message'] . "</p>";
}
?>