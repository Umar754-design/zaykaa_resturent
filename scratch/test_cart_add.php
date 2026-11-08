<?php
require __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$_REQUEST['action'] = 'add';
$_POST['action'] = 'add';
$_POST['item_id'] = 12; // Amritsari Kulcha
$_POST['quantity'] = 2;
$_SESSION = [];

ob_start();
require __DIR__ . '/../api/cart.php';
$cartJson = ob_get_clean();
$data = json_decode($cartJson, true);

echo "Cart Add Success: " . ($data['success'] ? 'YES' : 'NO') . PHP_EOL;
echo "Item count: " . $data['cart']['item_count'] . PHP_EOL;
echo "Item name: " . $data['cart']['items'][0]['name'] . PHP_EOL;
echo "Item serving size: " . $data['cart']['items'][0]['serving_size'] . PHP_EOL;
echo "Item price: ₹" . $data['cart']['items'][0]['price'] . PHP_EOL;
