<?php
// ===================================================
// Quick Status Toggle API - Zayka Platform
// ===================================================

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

$user = current_user();
if (!$user) {
    json_response(['success' => false, 'message' => 'Unauthorized'], 401);
}

$type = $_POST['type'] ?? '';

switch ($type) {
    case 'restaurant_status':
        if (!in_array($user['role'], ['restaurant', 'admin'])) {
            json_response(['success' => false, 'message' => 'Permission denied'], 403);
        }
        $restaurantId = (int)($_POST['restaurant_id'] ?? 0);
        $status = $_POST['status'] ?? 'open';
        if (!in_array($status, ['open', 'closed', 'busy'])) {
            json_response(['success' => false, 'message' => 'Invalid status value'], 400);
        }

        if ($user['role'] === 'restaurant') {
            $stmt = $pdo->prepare("UPDATE restaurants SET status = ? WHERE id = ? AND owner_id = ?");
            $stmt->execute([$status, $restaurantId, $user['id']]);
        } else {
            $stmt = $pdo->prepare("UPDATE restaurants SET status = ? WHERE id = ?");
            $stmt->execute([$status, $restaurantId]);
        }

        json_response(['success' => true, 'message' => 'Restaurant status updated to ' . ucfirst($status), 'status' => $status]);
        break;

    case 'delivery_online':
        if ($user['role'] !== 'delivery') {
            json_response(['success' => false, 'message' => 'Permission denied'], 403);
        }
        $isOnline = (int)($_POST['is_online'] ?? 0);
        $stmt = $pdo->prepare("UPDATE delivery_partners SET is_online = ? WHERE user_id = ?");
        $stmt->execute([$isOnline, $user['id']]);

        json_response(['success' => true, 'message' => 'Duty status updated to ' . ($isOnline ? 'Online' : 'Offline'), 'is_online' => $isOnline]);
        break;

    case 'menu_item_availability':
        if (!in_array($user['role'], ['restaurant', 'admin'])) {
            json_response(['success' => false, 'message' => 'Permission denied'], 403);
        }
        $itemId = (int)($_POST['item_id'] ?? 0);
        $isAvailable = (int)($_POST['is_available'] ?? 0);

        if ($user['role'] === 'restaurant') {
            $stmt = $pdo->prepare("UPDATE menu_items m JOIN restaurants r ON m.restaurant_id = r.id 
                                   SET m.is_available = ? WHERE m.id = ? AND r.owner_id = ?");
            $stmt->execute([$isAvailable, $itemId, $user['id']]);
        } else {
            $stmt = $pdo->prepare("UPDATE menu_items SET is_available = ? WHERE id = ?");
            $stmt->execute([$isAvailable, $itemId]);
        }

        json_response(['success' => true, 'message' => 'Item availability updated', 'is_available' => $isAvailable]);
        break;

    case 'favorite':
        $restaurantId = (int)($_POST['restaurant_id'] ?? 0);
        if (!$restaurantId) {
            json_response(['success' => false, 'message' => 'Invalid restaurant ID'], 400);
        }

        $checkStmt = $pdo->prepare("SELECT id FROM favorites WHERE user_id = ? AND restaurant_id = ?");
        $checkStmt->execute([$user['id'], $restaurantId]);
        $existing = $checkStmt->fetch();

        if ($existing) {
            $del = $pdo->prepare("DELETE FROM favorites WHERE id = ?");
            $del->execute([$existing['id']]);
            json_response(['success' => true, 'is_favorite' => false, 'message' => 'Removed from favorites']);
        } else {
            $ins = $pdo->prepare("INSERT INTO favorites (user_id, restaurant_id) VALUES (?, ?)");
            $ins->execute([$user['id'], $restaurantId]);
            json_response(['success' => true, 'is_favorite' => true, 'message' => 'Saved to favorites!']);
        }
        break;

    default:
        json_response(['success' => false, 'message' => 'Invalid toggle type'], 400);
        break;
}
