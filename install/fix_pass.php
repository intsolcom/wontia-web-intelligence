<?php
$hash = password_hash('admin', PASSWORD_BCRYPT, ['cost' => 12]);
$dsn = 'mysql:host=mysql-prod;port=3306;dbname=wontia;charset=utf8mb4';
$pdo = new PDO($dsn, 'root', 'Admin2026!');
$pdo->query("UPDATE users SET password_hash = " . $pdo->quote($hash) . " WHERE username = 'admin'");
echo "Password updated. Hash: $hash\n";
