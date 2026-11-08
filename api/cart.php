<?php
// ===================================================
// Cart Management API - Zayka Platform
// ===================================================

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

$action = $_REQUEST['action'] ?? 'get';

if (!isset($_SESSION['cart'])) {
    $mainRest = get_main_restaurant();
    $_SESSION['cart'] = [
        'restaurant_id' => $mainRest['id'] ?? 1,
        'restaurant_name' => $mainRest['name'] ?? 'Zaykaa Café & Restaurant',
        'items' => [],
        'coupon' => null,
        'tip' => 0.00,
        'instructions' => ''
    ];
} else {
    if (empty($_SESSION['cart']['restaurant_id'])) {
        $mainRest = get_main_restaurant();
        $_SESSION['cart']['restaurant_id'] = $mainRest['id'] ?? 1;
        $_SESSION['cart']['restaurant_name'] = $mainRest['name'] ?? 'Zaykaa Café & Restaurant';
    }
}

/**
 * Helper to compute full cart summary
 */
function get_cart_summary() {
    $cart = &$_SESSION['cart'];
    $subtotal = 0.00;
    $addonsTotal = 0.00;
    $itemCount = 0;

    foreach ($cart['items'] as $cartKey => $item) {
        $itemPrice = (float)$item['price'];
        $qty = (int)$item['quantity'];
        $itemSubtotal = $itemPrice * $qty;
        $subtotal += $itemSubtotal;
        $itemCount += $qty;

        $itemAddonsSum = 0.00;
        if (!empty($item['addons'])) {
            foreach ($item['addons'] as $addon) {
                $itemAddonsSum += (float)$addon['price'] * $qty;
            }
        }
        $addonsTotal += $itemAddonsSum;
    }

    $foodTotal = $subtotal + $addonsTotal;
    $discount = 0.00;

    // Validate and recompute coupon
    if (!empty($cart['coupon'])) {
        global $pdo;
        $stmt = $pdo->prepare("SELECT * FROM coupons WHERE code = ? AND is_active = 1 AND valid_from <= CURDATE() AND valid_until >= CURDATE()");
        $stmt->execute([$cart['coupon']['code']]);
        $couponData = $stmt->fetch();

        if ($couponData && $foodTotal >= (float)$couponData['min_order_amount']) {
            if ($couponData['discount_type'] === 'percentage') {
                $calcDiscount = ($foodTotal * (float)$couponData['discount_value']) / 100;
                if (!empty($couponData['max_discount'])) {
                    $calcDiscount = min($calcDiscount, (float)$couponData['max_discount']);
                }
                $discount = round($calcDiscount, 2);
            } else {
                $discount = min($foodTotal, (float)$couponData['discount_value']);
            }
            $cart['coupon']['discount'] = $discount;
        } else {
            $cart['coupon'] = null; // Minimum order condition not met
        }
    }

    $tip = (float)($cart['tip'] ?? 0.00);
    $distanceKm = 3.5; // Default reference distance
    $financials = calculate_order_financials($subtotal, $addonsTotal, $discount, $tip, $distanceKm);

    return [
        'restaurant_id'    => $cart['restaurant_id'],
        'restaurant_name'  => $cart['restaurant_name'],
        'items'            => array_values($cart['items']),
        'item_count'       => $itemCount,
        'subtotal'         => $financials['subtotal'],
        'subtotal_fmt'     => format_price($financials['subtotal']),
        'addons_total'     => $financials['addons_total'],
        'addons_total_fmt' => format_price($financials['addons_total']),
        'tax_amount'       => $financials['tax_amount'],
        'tax_amount_fmt'   => format_price($financials['tax_amount']),
        'delivery_fee'     => $financials['delivery_fee'],
        'delivery_fee_fmt' => format_price($financials['delivery_fee']),
        'platform_fee'     => $financials['platform_fee'],
        'platform_fee_fmt' => format_price($financials['platform_fee']),
        'coupon'           => $cart['coupon'],
        'discount'         => $discount,
        'discount_fmt'     => format_price($discount),
        'tip'              => $tip,
        'tip_fmt'          => format_price($tip),
        'total_amount'     => $financials['total_amount'],
        'total_amount_fmt' => format_price($financials['total_amount']),
        'instructions'     => $cart['instructions'] ?? ''
    ];
}

// Only run action dispatcher if cart.php is invoked directly or with cart actions
$cartActions = ['get', 'add', 'update_qty', 'remove', 'clear', 'apply_coupon', 'remove_coupon', 'set_tip', 'set_instructions'];
if (empty($isHelperCall) && in_array($action, array_merge($cartActions, ['']))) {
    switch ($action) {
    case 'add':
        $itemId = (int)($_POST['item_id'] ?? 0);
        $qty = max(1, (int)($_POST['quantity'] ?? 1));
        $addonsSelected = $_POST['addons'] ?? []; // array of addon ids or objects

        if (!$itemId) {
            json_response(['success' => false, 'message' => 'Invalid food item.'], 400);
        }

        $stmt = $pdo->prepare("SELECT m.*, r.name as restaurant_name, r.status as restaurant_status 
                               FROM menu_items m 
                               LEFT JOIN restaurants r ON m.restaurant_id = r.id 
                               WHERE m.id = ? AND m.is_available = 1");
        $stmt->execute([$itemId]);
        $item = $stmt->fetch();

        if (!$item) {
            json_response(['success' => false, 'message' => 'Item is currently unavailable.'], 404);
        }

        $mainRest = get_main_restaurant();
        $_SESSION['cart']['restaurant_id'] = $mainRest['id'] ?? 1;
        $_SESSION['cart']['restaurant_name'] = $mainRest['name'] ?? 'Zaykaa Café & Restaurant';

        // Process Addons
        $processedAddons = [];
        $addonsKeyPart = '';
        if (!empty($addonsSelected) && is_array($addonsSelected)) {
            $placeholders = implode(',', array_fill(0, count($addonsSelected), '?'));
            $addonStmt = $pdo->prepare("SELECT id, name, price FROM item_addons WHERE id IN ($placeholders) AND item_id = ? AND is_available = 1");
            $params = array_merge(array_values($addonsSelected), [$itemId]);
            $addonStmt->execute($params);
            $fetchedAddons = $addonStmt->fetchAll();

            foreach ($fetchedAddons as $fa) {
                $processedAddons[] = [
                    'id' => $fa['id'],
                    'name' => $fa['name'],
                    'price' => (float)$fa['price']
                ];
                $addonsKeyPart .= '_' . $fa['id'];
            }
        }

        $cartKey = 'item_' . $itemId . ($addonsKeyPart ? $addonsKeyPart : '');
        $finalPrice = !empty($item['discount_price']) ? (float)$item['discount_price'] : (float)$item['price'];

        if (isset($_SESSION['cart']['items'][$cartKey])) {
            $_SESSION['cart']['items'][$cartKey]['quantity'] += $qty;
        } else {
            $_SESSION['cart']['items'][$cartKey] = [
                'cart_key'     => $cartKey,
                'item_id'      => $itemId,
                'name'         => $item['name'],
                'price'        => $finalPrice,
                'serving_size' => $item['serving_size'] ?? '',
                'is_veg'       => (int)$item['is_veg'],
                'image'        => $item['image'],
                'quantity'     => $qty,
                'addons'       => $processedAddons
            ];
        }

        json_response([
            'success' => true,
            'message' => $item['name'] . ' added to cart!',
            'cart' => get_cart_summary()
        ]);
        break;

    case 'update_qty':
        $cartKey = $_POST['cart_key'] ?? '';
        $delta = (int)($_POST['delta'] ?? 0); // +1 or -1

        if (isset($_SESSION['cart']['items'][$cartKey])) {
            $_SESSION['cart']['items'][$cartKey]['quantity'] += $delta;
            if ($_SESSION['cart']['items'][$cartKey]['quantity'] <= 0) {
                unset($_SESSION['cart']['items'][$cartKey]);
            }
            if (empty($_SESSION['cart']['items'])) {
                $_SESSION['cart']['restaurant_id'] = null;
                $_SESSION['cart']['restaurant_name'] = null;
                $_SESSION['cart']['coupon'] = null;
            }
            json_response([
                'success' => true,
                'message' => 'Cart updated',
                'cart' => get_cart_summary()
            ]);
        }
        json_response(['success' => false, 'message' => 'Item not found in cart.'], 404);
        break;

    case 'remove':
        $cartKey = $_POST['cart_key'] ?? '';
        if (isset($_SESSION['cart']['items'][$cartKey])) {
            unset($_SESSION['cart']['items'][$cartKey]);
            if (empty($_SESSION['cart']['items'])) {
                $_SESSION['cart']['restaurant_id'] = null;
                $_SESSION['cart']['restaurant_name'] = null;
                $_SESSION['cart']['coupon'] = null;
            }
            json_response([
                'success' => true,
                'message' => 'Item removed from cart',
                'cart' => get_cart_summary()
            ]);
        }
        json_response(['success' => false, 'message' => 'Item not found.'], 404);
        break;

    case 'clear':
        $_SESSION['cart'] = [
            'restaurant_id' => null,
            'restaurant_name' => null,
            'items' => [],
            'coupon' => null,
            'tip' => 0.00,
            'instructions' => ''
        ];
        json_response(['success' => true, 'message' => 'Cart cleared', 'cart' => get_cart_summary()]);
        break;

    case 'apply_coupon':
        $code = strtoupper(trim($_POST['code'] ?? ''));
        if (!$code) {
            json_response(['success' => false, 'message' => 'Please enter a coupon code.'], 400);
        }

        $stmt = $pdo->prepare("SELECT * FROM coupons WHERE code = ? AND is_active = 1 AND valid_from <= CURDATE() AND valid_until >= CURDATE()");
        $stmt->execute([$code]);
        $coupon = $stmt->fetch();

        if (!$coupon) {
            json_response(['success' => false, 'message' => 'Invalid or expired coupon code.'], 400);
        }

        $summary = get_cart_summary();
        $foodTotal = $summary['subtotal'] + $summary['addons_total'];

        if ($foodTotal < (float)$coupon['min_order_amount']) {
            json_response([
                'success' => false,
                'message' => 'Minimum order amount for this coupon is ' . format_price($coupon['min_order_amount']) . '.'
            ], 400);
        }

        if (!empty($coupon['restaurant_id']) && $coupon['restaurant_id'] != $_SESSION['cart']['restaurant_id']) {
            json_response(['success' => false, 'message' => 'This coupon is not valid for this restaurant.'], 400);
        }

        $_SESSION['cart']['coupon'] = [
            'id' => $coupon['id'],
            'code' => $coupon['code'],
            'title' => $coupon['title'],
            'discount' => 0.00
        ];

        json_response([
            'success' => true,
            'message' => 'Coupon ' . $code . ' applied successfully!',
            'cart' => get_cart_summary()
        ]);
        break;

    case 'remove_coupon':
        $_SESSION['cart']['coupon'] = null;
        json_response(['success' => true, 'message' => 'Coupon removed', 'cart' => get_cart_summary()]);
        break;

    case 'set_tip':
        $tip = max(0.00, (float)($_POST['tip'] ?? 0.00));
        $_SESSION['cart']['tip'] = round($tip, 2);
        json_response(['success' => true, 'message' => 'Tip updated', 'cart' => get_cart_summary()]);
        break;

    case 'set_instructions':
        $instructions = trim($_POST['instructions'] ?? '');
        $_SESSION['cart']['instructions'] = substr($instructions, 0, 500);
        json_response(['success' => true, 'message' => 'Instructions saved', 'cart' => get_cart_summary()]);
        break;

    case 'get':
    default:
        json_response(['success' => true, 'cart' => get_cart_summary()]);
        break;
    }
}

