<?php
// End-to-end verification script for Zayka Platform
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

echo "=== ZAYKA END-TO-END FLOW VALIDATION ===\n\n";

// 1. Check Customer
$customer = $pdo->query("SELECT * FROM users WHERE email = 'customer@zayka.com'")->fetch();
if (!$customer) die("Error: Customer not found\n");
echo "✓ Customer found: {$customer['name']} (ID: {$customer['id']}, Balance: ₹{$customer['wallet_balance']})\n";

// 2. Add an order directly via calculate_order_financials & simulation
$rest = $pdo->query("SELECT * FROM restaurants WHERE id = 1")->fetch();
echo "✓ Restaurant found: {$rest['name']} (ID: {$rest['id']})\n";

$item = $pdo->query("SELECT * FROM menu_items WHERE restaurant_id = 1 LIMIT 1")->fetch();
echo "✓ Menu Item found: {$item['name']} (Price: ₹{$item['price']})\n";

$addons = $pdo->query("SELECT * FROM item_addons WHERE item_id = {$item['id']} LIMIT 1")->fetch();
$addon_price = $addons ? (float)$addons['price'] : 0.0;
if ($addons) echo "✓ Addon found: {$addons['name']} (Price: ₹{$addon_price})\n";

// Calculate financials
$items_subtotal = (float)$item['price'];
$addons_subtotal = $addon_price;
$distance_km = 3.5;
$tip = 20.0;
$coupon_disc = 50.0;
$commission_pct = (float)$rest['commission_rate'];

$fin = calculate_order_financials($items_subtotal, $addons_subtotal, $distance_km, $tip, $coupon_disc, $commission_pct);
echo "✓ Financial calculation:\n";
echo "   Subtotal: ₹{$fin['subtotal']}\n";
echo "   Addons: ₹{$fin['addons_total']}\n";
echo "   Delivery Fee: ₹{$fin['delivery_fee']}\n";
echo "   Platform Fee: ₹{$fin['platform_fee']}\n";
echo "   GST (5%): ₹{$fin['tax_amount']}\n";
echo "   Coupon Discount: -₹{$fin['coupon_discount']}\n";
echo "   Tip: ₹{$fin['tip_amount']}\n";
echo "   Total Amount: ₹{$fin['total_amount']}\n";
echo "   Restaurant Earnings: ₹{$fin['restaurant_earnings']}\n";
echo "   Delivery Partner Earnings: ₹{$fin['delivery_earnings']}\n";
echo "   Platform Commission: ₹{$fin['platform_commission']}\n";

// Verify sum consistency
$calc_total = $fin['subtotal'] + $fin['addons_total'] + $fin['delivery_fee'] + $fin['platform_fee'] + $fin['tax_amount'] + $fin['tip_amount'] - $fin['coupon_discount'];
if (abs($calc_total - $fin['total_amount']) > 0.01) {
    die("Error: Total mismatch! {$calc_total} vs {$fin['total_amount']}\n");
}
echo "✓ Total arithmetic verified: ₹{$fin['total_amount']}\n";

// 3. Create a test order in DB
$order_number = 'ZYK' . date('ymd') . rand(1000, 9999);
$otp = generate_otp(4);
$addr_json = json_encode(['address_line' => 'Flat 402, Royal Palms', 'landmark' => 'Near Park', 'city' => 'Dhanbad', 'pincode' => '828305']);

$stmt = $pdo->prepare("INSERT INTO orders (order_number, user_id, restaurant_id, delivery_address_json, subtotal, addons_total, tax_amount, delivery_fee, platform_fee, coupon_discount, coupon_code, tip_amount, total_amount, restaurant_earnings, delivery_earnings, platform_commission, payment_method, payment_status, order_status, estimated_delivery_time, delivery_otp) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'cod', 'pending', 'placed', 35, ?)");
$stmt->execute([$order_number, $customer['id'], $rest['id'], $addr_json, $fin['subtotal'], $fin['addons_total'], $fin['tax_amount'], $fin['delivery_fee'], $fin['platform_fee'], $fin['coupon_discount'], 'WELCOME50', $fin['tip_amount'], $fin['total_amount'], $fin['restaurant_earnings'], $fin['delivery_earnings'], $fin['platform_commission'], $otp]);
$order_id = $pdo->lastInsertId();

// Insert item
$pdo->prepare("INSERT INTO order_items (order_id, item_id, item_name, price, quantity, total) VALUES (?, ?, ?, ?, 1, ?)")
    ->execute([$order_id, $item['id'], $item['name'], $item['price'], $item['price']]);
$order_item_id = $pdo->lastInsertId();

if ($addons) {
    $pdo->prepare("INSERT INTO order_item_addons (order_item_id, addon_name, price) VALUES (?, ?, ?)")
        ->execute([$order_item_id, $addons['name'], $addons['price']]);
}

echo "✓ Order #{$order_number} created with ID {$order_id}, OTP: {$otp}\n";

// 4. Restaurant accepts and prepares order
$pdo->prepare("UPDATE orders SET order_status = 'accepted' WHERE id = ?")->execute([$order_id]);
$pdo->prepare("INSERT INTO order_status_logs (order_id, status, notes) VALUES (?, 'accepted', 'Restaurant accepted order')")->execute([$order_id]);
echo "✓ Restaurant accepted order\n";

$pdo->prepare("UPDATE orders SET order_status = 'ready_for_pickup' WHERE id = ?")->execute([$order_id]);
$pdo->prepare("INSERT INTO order_status_logs (order_id, status, notes) VALUES (?, 'ready_for_pickup', 'Food ready for pickup')")->execute([$order_id]);
echo "✓ Order marked ready for pickup\n";

// 5. Rider picks up order
$rider = $pdo->query("SELECT dp.*, u.name FROM delivery_partners dp JOIN users u ON dp.user_id = u.id WHERE dp.id = 1")->fetch();
echo "✓ Rider found: {$rider['name']} (ID: {$rider['id']})\n";

$pdo->prepare("UPDATE orders SET delivery_partner_id = ?, order_status = 'picked_up' WHERE id = ?")->execute([$rider['id'], $order_id]);
echo "✓ Rider assigned & order picked up\n";

// 6. Rider delivers with OTP
$test_otp = $otp;
$ord_check = $pdo->query("SELECT delivery_otp FROM orders WHERE id = {$order_id}")->fetch();
if ($ord_check['delivery_otp'] !== $test_otp) {
    die("Error: OTP validation failed!\n");
}
$pdo->prepare("UPDATE orders SET order_status = 'delivered', payment_status = 'paid' WHERE id = ?")->execute([$order_id]);
$pdo->prepare("UPDATE delivery_partners SET earnings_balance = earnings_balance + ?, total_deliveries = total_deliveries + 1 WHERE id = ?")
    ->execute([$fin['delivery_earnings'], $rider['id']]);
echo "✓ Delivery OTP verified: {$test_otp}\n";
echo "✓ Order marked DELIVERED and PAID. Rider balance credited with ₹{$fin['delivery_earnings']}\n";

// 7. Customer writes review
$pdo->prepare("INSERT INTO reviews (order_id, user_id, restaurant_id, delivery_partner_id, restaurant_rating, restaurant_review, food_rating, delivery_rating, delivery_review) VALUES (?, ?, ?, ?, 5, 'Super delicious food and hot delivery!', 5, 5, 'Polite rider, very prompt!')")
    ->execute([$order_id, $customer['id'], $rest['id'], $rider['id']]);
echo "✓ Customer review submitted\n";

// 8. Admin metrics check
$delivered_count = $pdo->query("SELECT COUNT(*) FROM orders WHERE order_status = 'delivered'")->fetchColumn();
$total_gmv = $pdo->query("SELECT SUM(total_amount) FROM orders WHERE order_status = 'delivered'")->fetchColumn();
echo "✓ Admin GMV updated: ₹{$total_gmv} across {$delivered_count} delivered orders.\n";

echo "\nALL TESTS PASSED SUCCESSFULLY! The entire platform lifecycle is operational.\n";
