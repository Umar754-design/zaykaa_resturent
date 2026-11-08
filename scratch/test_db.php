<?php
require_once __DIR__ . '/../config/database.php';
$stmt = $pdo->query("SELECT id, name, email, role, status FROM users");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($users, JSON_PRETTY_PRINT);
