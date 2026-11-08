<?php
// ===================================================
// Live Order Tracking & Geolocation API - Zayka Platform
// ===================================================

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

$orderId = (int)($_GET['order_id'] ?? 0);
if (!$orderId) {
    json_response(['success' => false, 'message' => 'Order ID required'], 400);
}

$stmt = $pdo->prepare("SELECT o.*, 
                              r.name as rest_name, r.address as rest_address, r.latitude as rest_lat, r.longitude as rest_lng, r.phone as rest_phone,
                              u.name as rider_name, u.phone as rider_phone, dp.vehicle_number, dp.vehicle_type, dp.rating as rider_rating,
                              dp.current_lat as rider_base_lat, dp.current_lng as rider_base_lng
                       FROM orders o
                       JOIN restaurants r ON o.restaurant_id = r.id
                       LEFT JOIN delivery_partners dp ON o.delivery_partner_id = dp.id
                       LEFT JOIN users u ON dp.user_id = u.id
                       WHERE o.id = ?");
$stmt->execute([$orderId]);
$order = $stmt->fetch();

if (!$order) {
    json_response(['success' => false, 'message' => 'Order not found'], 404);
}

// Decode delivery address JSON
$dest = json_decode($order['delivery_address_json'], true);
$destLat = (float)($dest['latitude'] ?? 23.798200);
$destLng = (float)($dest['longitude'] ?? 86.432000);

$restLat = (float)$order['rest_lat'];
$restLng = (float)$order['rest_lng'];

// Compute live rider coordinates simulation
$riderLat = $restLat;
$riderLng = $restLng;

if (!empty($order['delivery_partner_id'])) {
    if ($order['order_status'] === 'picked_up') {
        // Rider just at the restaurant
        $riderLat = $restLat;
        $riderLng = $restLng;
    } elseif ($order['order_status'] === 'out_for_delivery') {
        // Compute time elapsed since status moved to out_for_delivery
        $logStmt = $pdo->prepare("SELECT created_at FROM order_status_logs WHERE order_id = ? AND status = 'out_for_delivery' ORDER BY id DESC LIMIT 1");
        $logStmt->execute([$orderId]);
        $timeStr = $logStmt->fetchColumn();
        
        $elapsedSec = $timeStr ? (time() - strtotime($timeStr)) : 60;
        // Total expected transit: 12 minutes (720s)
        $progressFraction = min(0.92, max(0.15, $elapsedSec / 720));
        
        $riderLat = $restLat + (($destLat - $restLat) * $progressFraction);
        $riderLng = $restLng + (($destLng - $restLng) * $progressFraction);
    } elseif ($order['order_status'] === 'delivered') {
        $riderLat = $destLat;
        $riderLng = $destLng;
    }
}

// Map stage progression
$stageProgress = [
    'placed'            => 15,
    'accepted'          => 30,
    'preparing'         => 50,
    'ready_for_pickup'  => 65,
    'picked_up'         => 80,
    'out_for_delivery'  => 90,
    'delivered'         => 100,
    'cancelled'         => 0
];

json_response([
    'success' => true,
    'order_id' => $order['id'],
    'order_number' => $order['order_number'],
    'status' => $order['order_status'],
    'status_badge' => get_status_badge($order['order_status']),
    'progress_percent' => $stageProgress[$order['order_status']] ?? 10,
    'delivery_otp' => $order['delivery_otp'],
    'estimated_delivery_time' => $order['estimated_delivery_time'],
    'restaurant' => [
        'name' => $order['rest_name'],
        'address' => $order['rest_address'],
        'phone' => $order['rest_phone'],
        'lat' => $restLat,
        'lng' => $restLng
    ],
    'destination' => [
        'address' => $dest['address_line'] . ', ' . ($dest['landmark'] ? $dest['landmark'] . ', ' : '') . $dest['city'],
        'lat' => $destLat,
        'lng' => $destLng
    ],
    'rider' => $order['rider_name'] ? [
        'name' => $order['rider_name'],
        'phone' => $order['rider_phone'],
        'vehicle' => $order['vehicle_type'] . ' (' . $order['vehicle_number'] . ')',
        'rating' => $order['rider_rating'],
        'lat' => round($riderLat, 6),
        'lng' => round($riderLng, 6)
    ] : null
]);
