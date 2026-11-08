<?php
require __DIR__ . '/../config/database.php';

$stmt = $pdo->query("SELECT m.id, m.name, c.id as cat_id, c.name as cat_name, c.slug as cat_slug, m.price, m.discount_price 
                     FROM menu_items m 
                     LEFT JOIN categories c ON m.category_id = c.id 
                     ORDER BY m.id ASC");
while ($r = $stmt->fetch()) {
    echo "ID: {$r['id']} | Cat: [{$r['cat_id']}] {$r['cat_name']} ({$r['cat_slug']}) | Name: {$r['name']} | Price: ₹{$r['price']} | Offer: ₹{$r['discount_price']}" . PHP_EOL;
}
