<?php
require __DIR__ . '/../config/database.php';

$stmt = $pdo->query("SHOW COLUMNS FROM menu_items LIKE 'serving%'");
$cols = $stmt->fetchAll();
echo "Found columns: " . count($cols) . PHP_EOL;
foreach ($cols as $c) {
    echo $c['Field'] . " - " . $c['Type'] . PHP_EOL;
}
