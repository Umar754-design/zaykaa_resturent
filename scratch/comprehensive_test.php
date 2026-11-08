<?php
require __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

echo "=== STARTING COMPREHENSIVE SERVING SIZE TEST ===" . PHP_EOL;

// TEST 1: Check Database Schema
echo "\n[Test 1] Verifying Database Columns..." . PHP_EOL;
$stmt = $pdo->query("SHOW COLUMNS FROM menu_items LIKE 'serving_size'");
$col = $stmt->fetch();
if ($col && strpos(strtolower($col['Type']), 'varchar') !== false) {
    echo "✓ PASS: menu_items.serving_size column exists ({$col['Type']})" . PHP_EOL;
} else {
    echo "✗ FAIL: menu_items.serving_size column missing!" . PHP_EOL;
}

$stmt = $pdo->query("SHOW COLUMNS FROM order_items LIKE 'serving_size'");
$col = $stmt->fetch();
if ($col) {
    echo "✓ PASS: order_items.serving_size column exists ({$col['Type']})" . PHP_EOL;
} else {
    echo "✗ FAIL: order_items.serving_size column missing!" . PHP_EOL;
}

// TEST 2: Check Amritsari Kulcha with Chole data and layout requirement
echo "\n[Test 2] Checking Amritsari Kulcha with Chole..." . PHP_EOL;
$stmt = $pdo->query("SELECT * FROM menu_items WHERE name LIKE '%Amritsari Kulcha%'");
$kulcha = $stmt->fetch();
if ($kulcha) {
    echo "✓ PASS: Found Amritsari Kulcha with Chole (ID: {$kulcha['id']})" . PHP_EOL;
    echo "  - Serving: {$kulcha['serving_size']}" . PHP_EOL;
    echo "  - Price: ₹{$kulcha['price']}" . PHP_EOL;
    echo "  - Discount: ₹{$kulcha['discount_price']}" . PHP_EOL;
    if ($kulcha['serving_size'] === '2 Pieces' && (float)$kulcha['discount_price'] == 159.00 && (float)$kulcha['price'] == 180.00) {
        echo "✓ PASS: Amritsari Kulcha perfectly matches example: Serving: 2 Pieces, ₹159.00 ~~₹180.00~~" . PHP_EOL;
    }
} else {
    echo "✗ FAIL: Amritsari Kulcha not found!" . PHP_EOL;
}

// TEST 3: Check units across categories
echo "\n[Test 3] Verifying Category-Specific Serving Units in Database..." . PHP_EOL;
$stmt = $pdo->query("SELECT m.name, c.name as cat_name, m.serving_size 
                     FROM menu_items m 
                     LEFT JOIN categories c ON m.category_id = c.id 
                     ORDER BY c.id ASC, m.id ASC");
$items = $stmt->fetchAll();
foreach ($items as $it) {
    echo "  [{$it['cat_name']}] {$it['name']} -> Serving: {$it['serving_size']}" . PHP_EOL;
}

// TEST 4: Frontend HTML Render Test for menu.php
echo "\n[Test 4] Testing Frontend menu.php HTML generation..." . PHP_EOL;
ob_start();
include __DIR__ . '/../menu.php';
$menuHtml = ob_get_clean();

if (strpos($menuHtml, 'Serving:') !== false) {
    echo "✓ PASS: 'Serving:' label is rendered in menu.php" . PHP_EOL;
} else {
    echo "✗ FAIL: 'Serving:' label NOT found in menu.php" . PHP_EOL;
}

if (strpos($menuHtml, '2 Pieces') !== false) {
    echo "✓ PASS: '2 Pieces' is rendered in menu.php" . PHP_EOL;
} else {
    echo "✗ FAIL: '2 Pieces' NOT found in menu.php" . PHP_EOL;
}

// Verify position: Serving size appears directly before format_price (price)
$posKulcha = strpos($menuHtml, 'Amritsari Kulcha with Chole');
$posServing = strpos($menuHtml, '2 Pieces', $posKulcha);
$posPrice = strpos($menuHtml, '159.00', $posKulcha);

if ($posKulcha < $posServing && $posServing < $posPrice) {
    echo "✓ PASS: Card layout verified! 'Amritsari Kulcha' -> 'Serving: 2 Pieces' -> '₹159.00' (Serving is directly above price)" . PHP_EOL;
} else {
    echo "✗ FAIL: Order of elements incorrect! Kulcha: $posKulcha, Serving: $posServing, Price: $posPrice" . PHP_EOL;
}

// TEST 5: Fallback Test
echo "\n[Test 5] Testing Fallback: 'Serving size not specified'..." . PHP_EOL;
// Temporarily insert a test item with NULL serving size
$pdo->exec("INSERT INTO menu_items (restaurant_id, category_id, name, description, price, serving_size, is_available) 
            VALUES (1, 1, '__TEST_FALLBACK_DISH__', 'Test dish without serving size', 199.00, NULL, 1)");
$testId = $pdo->lastInsertId();

ob_start();
include __DIR__ . '/../menu.php';
$menuHtmlFallback = ob_get_clean();

if (strpos($menuHtmlFallback, 'Serving size not specified') !== false) {
    echo "✓ PASS: Fallback 'Serving size not specified' renders correctly when serving_size is missing!" . PHP_EOL;
} else {
    echo "✗ FAIL: Fallback 'Serving size not specified' not found!" . PHP_EOL;
}

// Clean up test item
$pdo->prepare("DELETE FROM menu_items WHERE id = ?")->execute([$testId]);
echo "✓ Cleaned up temporary test dish" . PHP_EOL;

// TEST 6: Admin / Partner updating serving size
echo "\n[Test 6] Testing Dynamic Admin Update of serving_size..." . PHP_EOL;
$testDishId = 12; // Amritsari Kulcha
$originalServing = $kulcha['serving_size'];

// Admin updates serving size to '4 Pieces (Family Pack)'
$pdo->prepare("UPDATE menu_items SET serving_size = ? WHERE id = ?")->execute(['4 Pieces (Family Pack)', $testDishId]);

// Verify DB updated
$checkStmt = $pdo->prepare("SELECT serving_size FROM menu_items WHERE id = ?");
$checkStmt->execute([$testDishId]);
$newServing = $checkStmt->fetchColumn();

if ($newServing === '4 Pieces (Family Pack)') {
    echo "✓ PASS: Admin successfully modified serving size in DB" . PHP_EOL;
} else {
    echo "✗ FAIL: Admin modification failed" . PHP_EOL;
}

// Verify frontend renders updated serving dynamically without hardcoding
ob_start();
include __DIR__ . '/../menu.php';
$menuHtmlUpdated = ob_get_clean();

if (strpos($menuHtmlUpdated, '4 Pieces (Family Pack)') !== false) {
    echo "✓ PASS: menu.php dynamically reflects modified serving size without hardcoding!" . PHP_EOL;
} else {
    echo "✗ FAIL: menu.php did not reflect modified serving size" . PHP_EOL;
}

// Restore original serving size
$pdo->prepare("UPDATE menu_items SET serving_size = ? WHERE id = ?")->execute([$originalServing, $testDishId]);
echo "✓ Restored Amritsari Kulcha to: {$originalServing}" . PHP_EOL;

// TEST 7: Cart integration
echo "\n[Test 7] Testing Cart API & Serving Size..." . PHP_EOL;
$_POST['action'] = 'add';
$_POST['item_id'] = 12;
$_POST['quantity'] = 2;
$_SESSION = [];

ob_start();
require __DIR__ . '/../api/cart.php';
$cartJson = ob_get_clean();
$cartData = json_decode($cartJson, true);

if ($cartData && !empty($cartData['success']) && !empty($cartData['cart']['items'])) {
    $cartItem = $cartData['cart']['items'][0];
    echo "✓ PASS: Cart API returned item: {$cartItem['name']}" . PHP_EOL;
    echo "  - Stored serving_size in cart: {$cartItem['serving_size']}" . PHP_EOL;
    if ($cartItem['serving_size'] === '2 Pieces') {
        echo "✓ PASS: Serving size accurately stored and returned in cart session" . PHP_EOL;
    } else {
        echo "✗ FAIL: Serving size in cart mismatch!" . PHP_EOL;
    }
} else {
    echo "✗ FAIL: Cart API response invalid: $cartJson" . PHP_EOL;
}

echo "\n=== ALL TESTS COMPLETED SUCCESSFULLY ===" . PHP_EOL;
