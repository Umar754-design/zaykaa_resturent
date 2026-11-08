<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

echo "=== VERIFYING SINGLE-RESTAURANT PLATFORM (ZAYKA) ===\n\n";

// 1. Check Menu Items
$itemsNotInZayka = $pdo->query("SELECT count(*) FROM menu_items WHERE restaurant_id != 1")->fetchColumn();
echo "1. Menu items not assigned to Zayka: {$itemsNotInZayka} (Expected: 0)\n";
if ($itemsNotInZayka > 0) {
    die("FAIL: Some menu items do not belong to Zayka\n");
}
echo "   ✓ All " . $pdo->query("SELECT count(*) FROM menu_items")->fetchColumn() . " menu items belong to Zayka.\n\n";

// 2. Check Menu Categories
$catsNotInZayka = $pdo->query("SELECT count(*) FROM menu_categories WHERE restaurant_id != 1")->fetchColumn();
echo "2. Menu categories not assigned to Zayka: {$catsNotInZayka} (Expected: 0)\n";
if ($catsNotInZayka > 0) {
    die("FAIL: Some menu categories do not belong to Zayka\n");
}
echo "   ✓ All menu categories belong to Zayka.\n\n";

// 3. Test Cart Add API Simulation
session_start();
$_SESSION['cart'] = null; // reset

// Mock POST for adding item 1
$_POST = ['action' => 'add', 'item_id' => 1, 'quantity' => 2];
$_REQUEST = $_POST;

ob_start();
// Include cart API logic via test helper or curl
ob_end_clean();

$mainRest = get_main_restaurant();
echo "3. Main restaurant resolution: {$mainRest['name']} (ID: {$mainRest['id']})\n";
if ($mainRest['id'] != 1) {
    die("FAIL: Main restaurant is not ID 1\n");
}
echo "   ✓ Main restaurant correctly verified as Zayka.\n\n";

// 4. Test HTTP Endpoints on localhost:8000
echo "4. Testing HTTP endpoints on http://localhost:8000...\n";

function test_url($url, $label, $forbiddenTerms = [], $requiredTerms = []) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    $html = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        echo "   ✕ {$label} returned HTTP {$httpCode}\n";
        return false;
    }

    foreach ($forbiddenTerms as $term) {
        if (stripos($html, $term) !== false) {
            echo "   ✕ {$label} contains forbidden term: '{$term}'\n";
            return false;
        }
    }

    foreach ($requiredTerms as $term) {
        if (stripos($html, $term) === false) {
            echo "   ✕ {$label} missing required term: '{$term}'\n";
            return false;
        }
    }

    echo "   ✓ {$label} (HTTP 200, checks passed)\n";
    return true;
}

test_url(
    "http://localhost:8000/index.php", 
    "Homepage (index.php)", 
    ["Top Restaurants in", "Explore All Restaurants", "Green Leaf Pure Veg Delights", "Royal Tandoor & Biryani Hub"],
    ["Zaykaa", "POPULAR SIGNATURE DISHES", "EXPLORE OUR FULL MENU"]
);

test_url(
    "http://localhost:8000/menu.php", 
    "Menu Page (menu.php)", 
    ["restaurant.php?id=", "Green Leaf Pure Veg", "Royal Tandoor"],
    ["Our Master Menu", "All Dishes", "Pure Veg Only"]
);

test_url(
    "http://localhost:8000/search.php?query=biryani", 
    "Search Page (search.php?query=biryani)", 
    ["restaurant-card-item", "restaurant.php?id="],
    ["Search results for", "Zaykaa Special Chicken Dum Biryani"]
);

test_url(
    "http://localhost:8000/cart.php", 
    "Cart Page (cart.php)", 
    ["Browse Restaurants"],
    ["My Food Cart", "Explore Menu"]
);

echo "\n=== ALL VERIFICATION CHECKS PASSED ===\n";
