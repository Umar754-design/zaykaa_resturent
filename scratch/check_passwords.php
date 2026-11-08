<?php
require_once __DIR__ . '/../config/database.php';
$stmt = $pdo->query("SELECT email, role, password FROM users");
$users = $stmt->fetchAll();

$credentials = [
    'admin@zayka.com' => 'Admin@123',
    'restaurant@zayka.com' => 'Partner@123',
    'greenleaf@zayka.com' => 'Partner@123',
    'royaltandoor@zayka.com' => 'Partner@123',
    'delivery@zayka.com' => 'Rider@123',
    'amit.rider@zayka.com' => 'Rider@123',
    'customer@zayka.com' => 'Customer@123',
    'vikash@gmail.com' => 'Customer@123'
];

foreach ($users as $u) {
    $expected = $credentials[$u['email']] ?? null;
    $valid = $expected && password_verify($expected, $u['password']);
    echo "User: " . $u['email'] . " | Pass: " . ($expected ?: 'N/A') . " | Valid: " . ($valid ? 'YES' : 'NO') . "\n";
}
