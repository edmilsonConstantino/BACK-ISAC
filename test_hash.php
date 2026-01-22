<?php
// gerar_hash.php
$senha = '8456@';
$hash = password_hash($senha, PASSWORD_BCRYPT);
echo "Senha: {$senha}\n";
echo "Hash: {$hash}\n\n";
echo "Execute este SQL:\n";
echo "UPDATE users SET senha = '{$hash}' WHERE email = 'academic@isac.ac.mz';\n";
?>