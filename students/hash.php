<?php
// Gerar senha criptografada para o usuário admin

$senhaTexto = '8456@';
$senhaCriptografada = password_hash($senhaTexto, PASSWORD_DEFAULT);

echo "<h2>🔐 Senha Criptografada Gerada</h2>";
echo "<p><strong>Senha original:</strong> " . htmlspecialchars($senhaTexto) . "</p>";
echo "<p><strong>Senha hash:</strong> <code>" . $senhaCriptografada . "</code></p>";

echo "<hr>";

echo "<h3>📋 SQL para inserir no banco:</h3>";
echo "<textarea style='width:100%; height:120px; font-family:monospace; padding:10px;'>";
echo "INSERT INTO users (nome, email, senha, role) VALUES \n";
echo "('admin', 'admin@example.com', '{$senhaCriptografada}', 'admin');";
echo "</textarea>";

echo "<hr>";
echo "<p><em>Copie o SQL acima e execute no phpMyAdmin</em></p>";
?>