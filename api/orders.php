<?php
// ===================================================
// Orders API - Zayka Platform
// Multi-role lifecycle: Customer, Restaurant, Delivery, Admin
// ===================================================

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

$action = $_REQUEST['action'] ?? '';
$user = current_user();

switch ($action) {
    case 'place_order':
        if (!$user) {
            json_response(['success' => false, 'message' => 'Please login to place your order.', 'redirect' => 'login.php'], 401);
        }

        if (empty($_SESSION['cart']['items'])) {
            json_response(['success' => false, 'message' => 'Your cart is empty.'], 400);
        }

        $restaurant = get_main_restaurant();
        $restaurantId = (int)($restaurant['id'] ?? 1);

        if (!$restaurant || $restaurant['status'] === 'closed') {
            json_response(['success' => false, 'message' => 'This restaurant is currently closed.'], 400);
        }

        $addressId = (int)($_POST['address_id'] ?? 0);
        $addrStmt = $pdo->prepare("SELECT * FROM user_addresses WHERE id = ? AND user_id = ?");
        $addrStmt->execute([$addressId, $user['id']]);
        $address = $addrStmt->fetch();

        if (!$address) {
            json_response(['success' => false, 'message' => 'Please select a valid delivery address.'], 400);
        }

        // Calculate distance between restaurant and customer
        $distanceKm = calculate_distance(
            (float)$restaurant['latitude'], (float)$restaurant['longitude'],
            (float)$address['latitude'], (float)$address['longitude']
        );

        if ($distanceKm > (float)$restaurant['delivery_radius_km']) {
            json_response([
                'success' => false,
                'message' => "Selected address is {$distanceKm} km away. Maximum delivery radius for this restaurant is {$restaurant['delivery_radius_km']} km."
            ], 400);
        }

        // Calculate final financial figures
        require_once __DIR__ . '/cart.php';
        $summary = get_cart_summary();
        $paymentMethod = $_POST['payment_method'] ?? 'cod';
        if (!in_array($paymentMethod, ['cod', 'online', 'wallet'])) {
            $paymentMethod = 'cod';
        }

        $paymentStatus = 'pending';
        $transactionId = null;

        if ($paymentMethod === 'wallet') {
            if ((float)$user['wallet_balance'] < (float)$summary['total_amount']) {
                json_response(['success' => false, 'message' => 'Insufficient wallet balance.'], 400);
            }
            $paymentStatus = 'paid';
            $transactionId = 'WLT_' . strtoupper(uniqid());
        } elseif ($paymentMethod === 'online') {
            $paymentStatus = 'paid'; // Simulation of instantaneous success
            $transactionId = 'TXN_ONLINE_' . strtoupper(uniqid());
        }

        // Begin DB transaction
        $pdo->beginTransaction();

        try {
            $orderNumber = generate_order_number();
            $deliveryOtp = generate_otp();
            $instructions = trim($_POST['instructions'] ?? $_SESSION['cart']['instructions'] ?? '');
            $addressJson = json_encode([
                'label' => $address['label'],
                'address_line' => $address['address_line'],
                'landmark' => $address['landmark'],
                'city' => $address['city'],
                'pincode' => $address['pincode'],
                'latitude' => $address['latitude'],
                'longitude' => $address['longitude']
            ]);

            $financials = calculate_order_financials(
                $summary['subtotal'],
                $summary['addons_total'],
                $summary['discount'],
                $summary['tip'],
                $distanceKm,
                (float)$restaurant['commission_rate']
            );

            $insStmt = $pdo->prepare("INSERT INTO orders (
                order_number, user_id, restaurant_id, address_id, delivery_address_json,
                subtotal, addons_total, tax_amount, delivery_fee, platform_fee,
                coupon_discount, coupon_code, tip_amount, total_amount,
                restaurant_earnings, delivery_earnings, platform_commission,
                payment_method, payment_status, payment_transaction_id,
                order_status, special_instructions, estimated_delivery_time, delivery_otp
            ) VALUES (
                ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?,
                ?, ?, ?, ?,
                ?, ?, ?,
                ?, ?, ?,
                'placed', ?, ?, ?
            )");

            $insStmt->execute([
                $orderNumber, $user['id'], $restaurantId, $address['id'], $addressJson,
                $financials['subtotal'], $financials['addons_total'], $financials['tax_amount'], $financials['delivery_fee'], $financials['platform_fee'],
                $financials['coupon_discount'], ($summary['coupon']['code'] ?? null), $financials['tip_amount'], $financials['total_amount'],
                $financials['restaurant_earnings'], $financials['delivery_earnings'], $financials['platform_commission'],
                $paymentMethod, $paymentStatus, $transactionId,
                $instructions, (int)$restaurant['delivery_time_min'] + 10, $deliveryOtp
            ]);

            $orderId = $pdo->lastInsertId();

            // Insert items and addons
            $itemStmt = $pdo->prepare("INSERT INTO order_items (order_id, item_id, item_name, serving_size, price, quantity, total) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $addonStmt = $pdo->prepare("INSERT INTO order_item_addons (order_item_id, addon_name, price) VALUES (?, ?, ?)");

            foreach ($_SESSION['cart']['items'] as $cartItem) {
                $itemTotal = (float)$cartItem['price'] * (int)$cartItem['quantity'];
                $itemStmt->execute([
                    $orderId, $cartItem['item_id'], $cartItem['name'],
                    $cartItem['serving_size'] ?? null,
                    $cartItem['price'], $cartItem['quantity'], $itemTotal
                ]);
                $orderItemId = $pdo->lastInsertId();

                if (!empty($cartItem['addons'])) {
                    foreach ($cartItem['addons'] as $add) {
                        $addonStmt->execute([$orderItemId, $add['name'], $add['price']]);
                    }
                }
            }

            // Deduct wallet if paid via wallet
            if ($paymentMethod === 'wallet') {
                $wStmt = $pdo->prepare("UPDATE users SET wallet_balance = wallet_balance - ? WHERE id = ?");
                $wStmt->execute([$financials['total_amount'], $user['id']]);

                $wtStmt = $pdo->prepare("INSERT INTO wallet_transactions (user_id, type, amount, description, reference_order_id) VALUES (?, 'debit', ?, ?, ?)");
                $wtStmt->execute([$user['id'], $financials['total_amount'], "Payment for Order #$orderNumber", $orderId]);
            }

            // Record initial status log
            log_order_status($orderId, 'placed', 'Order placed successfully by customer');

            // Increment coupon usage count if applied
            if (!empty($summary['coupon']['id'])) {
                $pdo->prepare("UPDATE coupons SET used_count = used_count + 1 WHERE id = ?")->execute([$summary['coupon']['id']]);
            }

            $pdo->commit();

            // Clear Cart
            $_SESSION['cart'] = [
                'restaurant_id' => null,
                'restaurant_name' => null,
                'items' => [],
                'coupon' => null,
                'tip' => 0.00,
                'instructions' => ''
            ];

            json_response([
                'success' => true,
                'message' => 'Order placed successfully!',
                'order_id' => $orderId,
                'order_number' => $orderNumber,
                'redirect' => 'order-status.php?id=' . $orderId
            ]);
        } catch (Exception $e) {
            $pdo->rollBack();
            json_response(['success' => false, 'message' => 'Failed to place order: ' . $e->getMessage()], 500);
        }
        break;

    case 'update_status':
        if (!$user) {
            json_response(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $orderId = (int)($_POST['order_id'] ?? 0);
        $newStatus = $_POST['status'] ?? '';
        $notes = trim($_POST['notes'] ?? '');
        $prepTime = (int)($_POST['prep_time'] ?? 0);

        $allowedStatuses = ['placed', 'accepted', 'preparing', 'ready_for_pickup', 'picked_up', 'out_for_delivery', 'delivered', 'cancelled'];
        if (!in_array($newStatus, $allowedStatuses)) {
            json_response(['success' => false, 'message' => 'Invalid status code.'], 400);
        }

        $stmt = $pdo->prepare("SELECT o.*, r.owner_id as rest_owner_id, dp.user_id as rider_user_id 
                               FROM orders o 
                               JOIN restaurants r ON o.restaurant_id = r.id 
                               LEFT JOIN delivery_partners dp ON o.delivery_partner_id = dp.id 
                               WHERE o.id = ?");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch();

        if (!$order) {
            json_response(['success' => false, 'message' => 'Order not found.'], 404);
        }

        // Authorization check
        $canUpdate = false;
        if ($user['role'] === 'admin') {
            $canUpdate = true;
        } elseif ($user['role'] === 'restaurant' && $order['rest_owner_id'] == $user['id']) {
            if (in_array($newStatus, ['accepted', 'preparing', 'ready_for_pickup', 'cancelled'])) {
                $canUpdate = true;
            }
        } elseif ($user['role'] === 'delivery' && $order['rider_user_id'] == $user['id']) {
            if (in_array($newStatus, ['picked_up', 'out_for_delivery', 'delivered'])) {
                $canUpdate = true;
            }
        }

        if (!$canUpdate) {
            json_response(['success' => false, 'message' => 'Permission denied for this action.'], 403);
        }

        // If delivered, verify OTP
        if ($newStatus === 'delivered' && $user['role'] !== 'admin') {
            $inputOtp = trim($_POST['otp'] ?? '');
            if ($inputOtp !== $order['delivery_otp']) {
                json_response(['success' => false, 'message' => 'Incorrect delivery OTP! Please ask customer for the 4-digit code.'], 400);
            }
        }

        $pdo->beginTransaction();
        try {
            $sql = "UPDATE orders SET order_status = ?, updated_at = NOW()";
            $params = [$newStatus];

            if ($prepTime > 0) {
                $sql .= ", estimated_delivery_time = ?";
                $params[] = $prepTime;
            }

            if ($newStatus === 'delivered') {
                $sql .= ", payment_status = 'paid'";
                // Credit restaurant earnings
                $pdo->prepare("UPDATE users SET wallet_balance = wallet_balance + ? WHERE id = ?")
                    ->execute([$order['restaurant_earnings'], $order['rest_owner_id']]);
                
                // Credit rider earnings if assigned
                if (!empty($order['delivery_partner_id'])) {
                    $pdo->prepare("UPDATE delivery_partners SET earnings_balance = earnings_balance + ?, total_deliveries = total_deliveries + 1 WHERE id = ?")
                        ->execute([$order['delivery_earnings'], $order['delivery_partner_id']]);
                }
            }

            if ($newStatus === 'cancelled') {
                $cancelledBy = $user['role'];
                $sql .= ", cancelled_by = ?, cancellation_reason = ?";
                $params[] = $cancelledBy;
                $params[] = $notes ?: 'Cancelled by ' . ucfirst($cancelledBy);

                // If paid online or wallet, refund to customer wallet
                if ($order['payment_status'] === 'paid') {
                    $pdo->prepare("UPDATE users SET wallet_balance = wallet_balance + ? WHERE id = ?")
                        ->execute([$order['total_amount'], $order['user_id']]);
                    $pdo->prepare("INSERT INTO wallet_transactions (user_id, type, amount, description, reference_order_id) VALUES (?, 'credit', ?, ?, ?)")
                        ->execute([$order['user_id'], $order['total_amount'], "Refund for Cancelled Order #" . $order['order_number'], $order['id']]);
                    $sql .= ", payment_status = 'refunded'";
                }
            }

            $sql .= " WHERE id = ?";
            $params[] = $orderId;

            $upd = $pdo->prepare($sql);
            $upd->execute($params);

            log_order_status($orderId, $newStatus, $notes ?: 'Status updated to ' . ucfirst(str_replace('_', ' ', $newStatus)));

            $pdo->commit();
            json_response(['success' => true, 'message' => 'Order status updated to ' . ucfirst(str_replace('_', ' ', $newStatus)) . '!']);
        } catch (Exception $e) {
            $pdo->rollBack();
            json_response(['success' => false, 'message' => 'Update failed: ' . $e->getMessage()], 500);
        }
        break;

    case 'accept_delivery':
        if (!$user || $user['role'] !== 'delivery') {
            json_response(['success' => false, 'message' => 'Only delivery partners can accept delivery assignments.'], 403);
        }

        $orderId = (int)($_POST['order_id'] ?? 0);
        $stmt = $pdo->prepare("SELECT id FROM delivery_partners WHERE user_id = ?");
        $stmt->execute([$user['id']]);
        $rider = $stmt->fetch();

        if (!$rider) {
            json_response(['success' => false, 'message' => 'Delivery profile not found.'], 404);
        }

        $orderStmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? AND delivery_partner_id IS NULL AND order_status IN ('accepted', 'preparing', 'ready_for_pickup')");
        $orderStmt->execute([$orderId]);
        $order = $orderStmt->fetch();

        if (!$order) {
            json_response(['success' => false, 'message' => 'Order is no longer available for assignment.'], 400);
        }

        $upd = $pdo->prepare("UPDATE orders SET delivery_partner_id = ?, updated_at = NOW() WHERE id = ?");
        $upd->execute([$rider['id'], $orderId]);

        log_order_status($orderId, $order['order_status'], "Delivery partner {$user['name']} accepted the order assignment");

        json_response(['success' => true, 'message' => 'Delivery order assigned to you successfully!']);
        break;

    case 'cancel_order':
        if (!$user) {
            json_response(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $orderId = (int)($_POST['order_id'] ?? 0);
        $reason = trim($_POST['reason'] ?? 'Customer requested cancellation');

        $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch();

        if (!$order) {
            json_response(['success' => false, 'message' => 'Order not found.'], 404);
        }

        if ($user['role'] === 'customer' && $order['user_id'] != $user['id']) {
            json_response(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        if ($user['role'] === 'customer' && !in_array($order['order_status'], ['placed', 'accepted'])) {
            json_response(['success' => false, 'message' => 'Order cannot be cancelled at this stage as food preparation has started.'], 400);
        }

        $pdo->beginTransaction();
        try {
            $upd = $pdo->prepare("UPDATE orders SET order_status = 'cancelled', cancelled_by = ?, cancellation_reason = ?, updated_at = NOW() WHERE id = ?");
            $upd->execute([$user['role'], $reason, $orderId]);

            // Refund if paid
            if ($order['payment_status'] === 'paid') {
                $pdo->prepare("UPDATE users SET wallet_balance = wallet_balance + ? WHERE id = ?")->execute([$order['total_amount'], $order['user_id']]);
                $pdo->prepare("UPDATE orders SET payment_status = 'refunded' WHERE id = ?")->execute([$orderId]);
                $pdo->prepare("INSERT INTO wallet_transactions (user_id, type, amount, description, reference_order_id) VALUES (?, 'credit', ?, ?, ?)")
                    ->execute([$order['user_id'], $order['total_amount'], "Refund for Order #" . $order['order_number'], $orderId]);
            }

            log_order_status($orderId, 'cancelled', "Order cancelled by {$user['role']}: $reason");
            $pdo->commit();

            json_response(['success' => true, 'message' => 'Order cancelled successfully. Any payment made has been credited to your wallet.']);
        } catch (Exception $e) {
            $pdo->rollBack();
            json_response(['success' => false, 'message' => 'Cancellation error: ' . $e->getMessage()], 500);
        }
        break;

    case 'poll_status':
        $orderId = (int)($_GET['order_id'] ?? 0);
        if (!$orderId) {
            json_response(['success' => false, 'message' => 'Order ID required.'], 400);
        }

        $stmt = $pdo->prepare("SELECT o.*, r.name as restaurant_name, r.phone as restaurant_phone, r.latitude as rest_lat, r.longitude as rest_lng,
                                      u.name as rider_name, u.phone as rider_phone, dp.vehicle_number, dp.current_lat as rider_lat, dp.current_lng as rider_lng
                               FROM orders o
                               JOIN restaurants r ON o.restaurant_id = r.id
                               LEFT JOIN delivery_partners dp ON o.delivery_partner_id = dp.id
                               LEFT JOIN users u ON dp.user_id = u.id
                               WHERE o.id = ?");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch();

        if (!$order) {
            json_response(['success' => false, 'message' => 'Order not found.'], 404);
        }

        $logsStmt = $pdo->prepare("SELECT * FROM order_status_logs WHERE order_id = ? ORDER BY created_at ASC");
        $logsStmt->execute([$orderId]);
        $logs = $logsStmt->fetchAll();

        json_response([
            'success' => true,
            'order' => [
                'id' => $order['id'],
                'order_number' => $order['order_number'],
                'status' => $order['order_status'],
                'status_badge' => get_status_badge($order['order_status']),
                'delivery_otp' => $order['delivery_otp'],
                'estimated_delivery_time' => $order['estimated_delivery_time'],
                'restaurant' => [
                    'name' => $order['restaurant_name'],
                    'phone' => $order['restaurant_phone'],
                    'lat' => (float)$order['rest_lat'],
                    'lng' => (float)$order['rest_lng']
                ],
                'rider' => $order['rider_name'] ? [
                    'name' => $order['rider_name'],
                    'phone' => $order['rider_phone'],
                    'vehicle' => $order['vehicle_number'],
                    'lat' => (float)$order['rider_lat'],
                    'lng' => (float)$order['rider_lng']
                ] : null,
                'logs' => $logs
            ]
        ]);
        break;

    default:
        json_response(['success' => false, 'message' => 'Invalid action.'], 400);
        break;
}
