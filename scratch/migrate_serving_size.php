<?php
require __DIR__ . '/../config/database.php';

echo "Running safe migration for serving_size..." . PHP_EOL;

// 1. Check menu_items
$stmt = $pdo->query("SHOW COLUMNS FROM menu_items LIKE 'serving_size'");
if (!$stmt->fetch()) {
    $pdo->exec("ALTER TABLE menu_items ADD COLUMN serving_size VARCHAR(100) DEFAULT NULL AFTER discount_price");
    echo "Added serving_size column to menu_items table." . PHP_EOL;
} else {
    echo "serving_size column already exists in menu_items." . PHP_EOL;
}

// 2. Check order_items (optional but great for consistency)
$stmt = $pdo->query("SHOW COLUMNS FROM order_items LIKE 'serving_size'");
if (!$stmt->fetch()) {
    $pdo->exec("ALTER TABLE order_items ADD COLUMN serving_size VARCHAR(100) DEFAULT NULL AFTER item_name");
    echo "Added serving_size column to order_items table." . PHP_EOL;
} else {
    echo "serving_size column already exists in order_items." . PHP_EOL;
}

// 3. Populate existing dishes with realistic category-accurate serving sizes
$initialServings = [
    1 => '500 g, Serves 1 Person',   // Zaykaa Special Chicken Dum Biryani
    2 => '500 g, Serves 1 Person',   // Hyderabadi Mutton Dum Biryani
    3 => '500 g, Serves 1 Person',   // Shahi Paneer Dum Biryani
    4 => '4 Pieces',                 // Galouti Kebab Platter (4 Pcs)
    5 => '4 Pieces',                 // Tandoori Murgh (Half)
    6 => '450 ml, Serves 2',         // Butter Chicken Supreme
    7 => '2 Pieces',                 // Butter Garlic Naan
    8 => '300 ml',                   // Emerald Mint Mojito
    9 => '250 ml',                   // Royal Mango Kesar Shake
    10 => '450 ml, Serves 2',        // Paneer Tikka Butter Masala
    11 => '450 ml, Serves 2',        // Dal Makhani Heritage
    12 => '2 Pieces',                // Amritsari Kulcha with Chole (prompt example: 2 Pieces)
    13 => 'Serves 1 Person',         // Royal Maharaja Pure Veg Thali
    14 => '6 Pieces',                // Chicken Malai Tikka
    15 => '6 Pieces',                // Dahi Ke Kebab (Veg)
    16 => '500 g, Serves 1 Person',   // Kolkata Style Chicken Biryani
    17 => '1 Piece',                 // Burger - Crispy Zaykaa Special
    18 => '2 Pieces',                // Brownie - Sizzling Walnut Fudge
];

$updStmt = $pdo->prepare("UPDATE menu_items SET serving_size = ? WHERE id = ?");
foreach ($initialServings as $id => $serving) {
    $updStmt->execute([$serving, $id]);
}

echo "Updated initial serving sizes for existing menu items successfully!" . PHP_EOL;
