<?php
// ===================================================
// Location & Address Management API - Zayka Platform
// ===================================================

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

$action = $_REQUEST['action'] ?? '';
$user = current_user();

switch ($action) {
    case 'set_city':
        $city = trim($_POST['city'] ?? 'Dhanbad');
        $_SESSION['user_city'] = $city;
        json_response(['success' => true, 'city' => $city]);
        break;

    case 'get_city':
        $city = $_SESSION['user_city'] ?? 'Dhanbad';
        json_response(['success' => true, 'city' => $city]);
        break;

    case 'list_addresses':
        if (!$user) {
            json_response(['success' => false, 'message' => 'Please login'], 401);
        }
        $stmt = $pdo->prepare("SELECT * FROM user_addresses WHERE user_id = ? ORDER BY is_default DESC, id DESC");
        $stmt->execute([$user['id']]);
        $addresses = $stmt->fetchAll();
        json_response(['success' => true, 'addresses' => $addresses]);
        break;

    case 'add_address':
        if (!$user) {
            json_response(['success' => false, 'message' => 'Please login'], 401);
        }
        $label = $_POST['label'] ?? 'Home';
        $addressLine = trim($_POST['address_line'] ?? '');
        $landmark = trim($_POST['landmark'] ?? '');
        $city = trim($_POST['city'] ?? 'Dhanbad');
        $pincode = trim($_POST['pincode'] ?? '826001');
        $lat = (float)($_POST['latitude'] ?? 23.796000);
        $lng = (float)($_POST['longitude'] ?? 86.431000);
        $isDefault = !empty($_POST['is_default']) ? 1 : 0;

        if (!$addressLine) {
            json_response(['success' => false, 'message' => 'Address line is required.'], 400);
        }

        if ($isDefault) {
            $pdo->prepare("UPDATE user_addresses SET is_default = 0 WHERE user_id = ?")->execute([$user['id']]);
        }

        $stmt = $pdo->prepare("INSERT INTO user_addresses (user_id, label, address_line, landmark, city, pincode, latitude, longitude, is_default)
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$user['id'], $label, $addressLine, $landmark, $city, $pincode, $lat, $lng, $isDefault]);
        $newId = $pdo->lastInsertId();

        json_response([
            'success' => true,
            'message' => 'Address added successfully!',
            'address' => [
                'id' => $newId,
                'label' => $label,
                'address_line' => $addressLine,
                'landmark' => $landmark,
                'city' => $city,
                'pincode' => $pincode,
                'is_default' => $isDefault
            ]
        ]);
        break;

    default:
        json_response(['success' => false, 'message' => 'Invalid action'], 400);
        break;
}
