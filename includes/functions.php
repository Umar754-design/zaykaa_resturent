<?php
// ===================================================
// Core Functions & Utility Library - Zayka Platform
// ===================================================

require_once __DIR__ . '/../config/database.php';

/**
 * Safe session initialization
 */
function init_session() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}
init_session();

/**
 * Returns currently logged-in user array or null
 */
function current_user() {
    init_session();
    if (!isset($_SESSION['user_id'])) {
        return null;
    }
    
    static $cachedUser = null;
    if ($cachedUser !== null && $cachedUser['id'] == $_SESSION['user_id']) {
        return $cachedUser;
    }

    global $pdo;
    $stmt = $pdo->prepare("SELECT id, name, email, phone, role, avatar, wallet_balance, referral_code, status FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    
    if (!$user || $user['status'] === 'suspended') {
        unset($_SESSION['user_id'], $_SESSION['user_role'], $_SESSION['user_name']);
        return null;
    }

    $cachedUser = $user;
    return $user;
}

/**
 * Check if user is logged in
 */
function is_logged_in() {
    return current_user() !== null;
}

/**
 * Check if current user has specific role or roles
 */
function has_role($roles) {
    $user = current_user();
    if (!$user) return false;
    
    if (is_array($roles)) {
        return in_array($user['role'], $roles);
    }
    return $user['role'] === $roles;
}

/**
 * Route protection guard
 */
function require_role($roles, $redirectUrl = null) {
    if (!is_logged_in()) {
        $loginUrl = ($redirectUrl) ? $redirectUrl : 'login.php';
        if (is_array($roles) && in_array('restaurant', $roles)) {
            $loginUrl = 'restaurant/login.php';
        } elseif (is_array($roles) && in_array('delivery', $roles)) {
            $loginUrl = 'delivery/login.php';
        } elseif (is_array($roles) && in_array('admin', $roles)) {
            $loginUrl = 'admin/login.php';
        }
        set_flash('error', 'Please log in to continue.');
        header("Location: " . $loginUrl);
        exit;
    }

    if (!has_role($roles)) {
        http_response_code(403);
        die("<div style='font-family:sans-serif;text-align:center;padding:60px;'>
            <h1>403 - Access Denied</h1>
            <p>You do not have permission to access this portal.</p>
            <p><a href='/index.php'>Return to Homepage</a></p>
        </div>");
    }
}

/**
 * Set flash alert message
 */
function set_flash($type, $message) {
    init_session();
    $_SESSION['flash'] = [
        'type' => $type, // 'success', 'error', 'warning', 'info'
        'message' => $message
    ];
}

/**
 * Retrieve and clear flash message
 */
function get_flash() {
    init_session();
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/**
 * Render flash alert banner if exists
 */
function render_flash_message() {
    $flash = get_flash();
    if (!$flash) return '';

    $bgColor = '#e6f4ea';
    $textColor = '#137333';
    $borderColor = '#ceead6';
    $icon = '✓';

    if ($flash['type'] === 'error') {
        $bgColor = '#fce8e6';
        $textColor = '#c5221f';
        $borderColor = '#fad2cf';
        $icon = '✕';
    } elseif ($flash['type'] === 'warning') {
        $bgColor = '#fef7e0';
        $textColor = '#b06000';
        $borderColor = '#feefc3';
        $icon = '⚠';
    }

    return "<div class='flash-alert' style='padding:14px 20px;margin:15px auto;max-width:1200px;border-radius:10px;background:{$bgColor};color:{$textColor};border:1px solid {$borderColor};display:flex;align-items:center;gap:12px;font-weight:500;font-size:14px;box-shadow:0 2px 8px rgba(0,0,0,0.05);'>
        <span style='font-size:16px;font-weight:bold;'>{$icon}</span>
        <span>" . htmlspecialchars($flash['message']) . "</span>
    </div>";
}

/**
 * CSRF token generation & validation
 */
function csrf_token() {
    init_session();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf_token($token) {
    init_session();
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Format currency in Indian Rupees (INR)
 */
function format_price($amount) {
    return '₹' . number_format((float)$amount, 2, '.', ',');
}

/**
 * Fetch platform configuration setting from database with static memory cache
 */
function get_setting($key, $default = null) {
    static $settings = [];

    if (isset($settings[$key])) {
        return $settings[$key];
    }

    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM platform_settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $val = $stmt->fetchColumn();
        if ($val !== false) {
            $settings[$key] = $val;
            return $val;
        }
    } catch (Exception $e) {
        // Fallback
    }

    return $default;
}

/**
 * Calculate distance between two coordinates in kilometers using Haversine formula
 */
function calculate_distance($lat1, $lon1, $lat2, $lon2) {
    $earthRadius = 6371; // km

    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);

    $a = sin($dLat / 2) * sin($dLat / 2) +
         cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
         sin($dLon / 2) * sin($dLon / 2);

    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    $distance = $earthRadius * $c;

    return round($distance, 1);
}

/**
 * Calculate dynamic delivery fee based on distance
 */
function calculate_delivery_fee($distanceKm) {
    $baseFee = (float)get_setting('base_delivery_fee', 30.00);
    $perKmFee = (float)get_setting('per_km_fee', 10.00);
    
    if ($distanceKm <= 3.0) {
        return $baseFee;
    }
    
    $extraKm = $distanceKm - 3.0;
    return round($baseFee + ($extraKm * $perKmFee), 2);
}

/**
 * Comprehensive Order Financial Calculation Engine
 */
function calculate_order_financials($subtotal, $addonsTotal, $couponDiscount, $tipAmount, $distanceKm, $restaurantCommissionRate = 15.00) {
    $platformFee = (float)get_setting('platform_service_fee', 5.00);
    $taxRatePercent = (float)get_setting('gst_tax_rate_percent', 5.00);
    
    $deliveryFee = calculate_delivery_fee($distanceKm);
    
    // Taxable food items amount
    $foodTotal = $subtotal + $addonsTotal;
    $discountedFoodTotal = max(0, $foodTotal - $couponDiscount);
    
    // 5% GST on food
    $taxAmount = round(($discountedFoodTotal * $taxRatePercent) / 100, 2);
    
    // Final customer payable
    $totalPayable = round($discountedFoodTotal + $taxAmount + $deliveryFee + $platformFee + $tipAmount, 2);
    
    // Restaurant earnings: Food Total minus commission %
    $commissionRate = (float)$restaurantCommissionRate;
    $platformCommission = round(($foodTotal * $commissionRate) / 100, 2);
    $restaurantEarnings = round($foodTotal - $platformCommission, 2);
    
    // Delivery partner earnings
    $riderBasePay = (float)get_setting('rider_delivery_base_pay', 35.00);
    $riderPerKm = (float)get_setting('rider_per_km_pay', 8.00);
    $riderTripFee = $riderBasePay + (max(0, $distanceKm - 3.0) * $riderPerKm);
    $deliveryEarnings = round($riderTripFee + $tipAmount, 2);
    
    return [
        'subtotal'             => $subtotal,
        'addons_total'         => $addonsTotal,
        'coupon_discount'      => $couponDiscount,
        'tax_amount'           => $taxAmount,
        'delivery_fee'         => $deliveryFee,
        'platform_fee'         => $platformFee,
        'tip_amount'           => $tipAmount,
        'total_amount'         => $totalPayable,
        'restaurant_earnings'  => $restaurantEarnings,
        'platform_commission'  => $platformCommission,
        'delivery_earnings'    => $deliveryEarnings
    ];
}

/**
 * Generate unique order tracking number
 */
function generate_order_number() {
    return 'ZYK-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4));
}

/**
 * Generate 4-digit numeric delivery confirmation OTP
 */
function generate_otp() {
    return (string)rand(1000, 9999);
}

/**
 * Log order status transition to audit table
 */
function log_order_status($orderId, $status, $notes = '') {
    global $pdo;
    $stmt = $pdo->prepare("INSERT INTO order_status_logs (order_id, status, notes) VALUES (?, ?, ?)");
    $stmt->execute([$orderId, $status, $notes]);
}

/**
 * HTML Status badge helper
 */
function get_status_badge($status) {
    $badges = [
        'placed' => ['bg' => '#E8F0FE', 'color' => '#1967D2', 'label' => 'Order Placed'],
        'accepted' => ['bg' => '#E0F2FE', 'color' => '#0284C7', 'label' => 'Accepted'],
        'preparing' => ['bg' => '#FEF3C7', 'color' => '#D97706', 'label' => 'Preparing Food'],
        'ready_for_pickup' => ['bg' => '#EDE9FE', 'color' => '#7C3AED', 'label' => 'Ready for Pickup'],
        'picked_up' => ['bg' => '#E0E7FF', 'color' => '#4338CA', 'label' => 'Picked Up'],
        'out_for_delivery' => ['bg' => '#FFEDD5', 'color' => '#EA580C', 'label' => 'Out for Delivery'],
        'delivered' => ['bg' => '#DEF7EC', 'color' => '#046C4E', 'label' => 'Delivered'],
        'cancelled' => ['bg' => '#FDE8E8', 'color' => '#9B1C1C', 'label' => 'Cancelled'],
    ];

    $b = $badges[$status] ?? ['bg' => '#F3F4F6', 'color' => '#374151', 'label' => ucfirst(str_replace('_', ' ', $status))];
    return "<span style='display:inline-flex;align-items:center;padding:4px 12px;border-radius:20px;font-size:12px;font-weight:600;background:{$b['bg']};color:{$b['color']};white-space:nowrap;'>{$b['label']}</span>";
}

/**
 * Render order status badge (alias for get_status_badge)
 */
function render_order_badge($status) {
    return get_status_badge($status);
}


/**
 * Standard JSON response helper for API endpoints
 */
function json_response($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Returns the single main restaurant record (Zayka)
 */
function get_main_restaurant() {
    static $restaurant = null;
    if ($restaurant !== null) {
        return $restaurant;
    }

    global $pdo;
    try {
        $stmt = $pdo->query("SELECT * FROM restaurants WHERE id = 1 LIMIT 1");
        $restaurant = $stmt->fetch();
        if (!$restaurant) {
            $stmt = $pdo->query("SELECT * FROM restaurants ORDER BY id ASC LIMIT 1");
            $restaurant = $stmt->fetch();
        }
    } catch (Exception $e) {
        $restaurant = null;
    }

    return $restaurant;
}

