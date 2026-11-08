<?php
require __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

// Prepare simulated customer session
$user = $pdo->query("SELECT * FROM users WHERE role = 'customer' LIMIT 1")->fetch();
$_SESSION['user_id'] = $user['id'];
$_SESSION['role'] = 'customer';

// Put item in cart
$_SESSION['cart'] = [
    'restaurant_id' => 1,
    'restaurant_name' => 'Zaykaa Café & Restaurant',
    'items' => [
        'item_12' => [
            'cart_key' => 'item_12',
            'item_id' => 12,
            'name' => 'Amritsari Kulcha with Chole',
            'price' => 159.00,
            'serving_size' => '2 Pieces',
            'is_veg' => 1,
            'image' => 'assets/images/categories/roti.png',
            'quantity' => 2,
            'addons' => []
        ]
    ],
    'coupon' => null,
    'tip' => 0.00,
    'instructions' => 'Extra pickles please'
];

$addr = $pdo->prepare("SELECT * FROM user_addresses WHERE user_id = ? LIMIT 1");
$addr->execute([$user['id']]);
$address = $addr->fetch();

if (!$address) {
    $pdo->prepare("INSERT INTO user_addresses (user_id, label, address_line, landmark, city, pincode, is_default) VALUES (?, 'Home', 'Main Street, Flat 4B', 'Near Clock Tower', 'Dhanbad', '828305', 1)")->execute([$user['id']]);
    $addressId = $pdo->lastInsertId();
} else {
    $addressId = $address['id'];
}

$_REQUEST['action'] = 'place_order';
$_POST['address_id'] = $addressId;
$_POST['payment_method'] = 'cod';

ob_start();
require __DIR__ . '/../api/orders.php';
$orderJson = ob_get_clean();
$orderData = json_decode($orderJson, true);

if (!empty($orderData['success']) && !empty($orderData['order_id'])) {
    $orderId = (int)$orderData['order_id'];
    echo "✓ Order placed successfully! Order ID: {$orderId}, Order Number: {$orderData['order_number']}" . PHP_EOL;

    // Check order_items table for serving_size
    $oiStmt = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ?");
    $oiStmt->execute([$orderId]);
    $oi = $oiStmt->fetch();

    echo "  Item Name: {$oi['item_name']}" . PHP_EOL;
    echo "  Saved Serving Size: {$oi['serving_size']}" . PHP_EOL;
    echo "  Price: ₹{$oi['price']} | Quantity: {$oi['quantity']} | Total: ₹{$oi['total']}" . PHP_EOL;

    if ($oi['serving_size'] === '2 Pieces') {
        echo "✓ PASS: serving_size successfully saved in order_items table!" . PHP_EOL;
    } else {
        echo "✗ FAIL: serving_size not saved properly in order_items" . PHP_EOL;
    }

    // Clean up test order
    $pdo->prepare("DELETE FROM orders WHERE id = ?")->execute([$orderId]);
    echo "✓ Cleaned up test order" . PHP_EOL;
} else {
    echo "Order error: $orderJson" . PHP_EOL;
}
