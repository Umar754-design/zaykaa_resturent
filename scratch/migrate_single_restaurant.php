<?php
require_once __DIR__ . '/../config/database.php';

echo "Updating database to single-restaurant platform (Zayka ID 1)...\n";

// Update all menu items to restaurant_id = 1
$pdo->exec("UPDATE menu_items SET restaurant_id = 1");
echo "Updated menu_items to restaurant_id = 1. Affected rows: " . $pdo->query("SELECT COUNT(*) FROM menu_items WHERE restaurant_id = 1")->fetchColumn() . "\n";

// Update all menu categories to restaurant_id = 1
$pdo->exec("UPDATE menu_categories SET restaurant_id = 1");
echo "Updated menu_categories to restaurant_id = 1.\n";

// Update all coupons to restaurant_id = 1 or NULL (NULL applies platform-wide)
$pdo->exec("UPDATE coupons SET restaurant_id = 1 WHERE restaurant_id IS NOT NULL");
echo "Updated coupons.\n";

// Update any existing orders to restaurant_id = 1
$pdo->exec("UPDATE orders SET restaurant_id = 1");
echo "Updated orders to restaurant_id = 1.\n";

echo "Database migration complete!\n";
