<?php
// ===================================================
// Core Functions & Utility Library - Zayka Platform
// ===================================================

if (ob_get_level() === 0) {
    ob_start();
}

require_once __DIR__ . '/../config/database.php';

/**
 * Safe session initialization
 */
function init_session() {
    if (session_status() === PHP_SESSION_NONE) {
        $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
}
init_session();

/**
 * Call right after a successful login to prevent session fixation.
 */
function login_session_regenerate() {
    init_session();
    session_regenerate_id(true);
    unset($_SESSION['csrf_token']);
}

/**
 * Login brute-force protection: max 5 failed attempts per email+IP in 15 minutes.
 */
define('LOGIN_MAX_ATTEMPTS', 5);
define('LOGIN_LOCK_MINUTES', 15);

function login_attempt_key($scope, $email) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'cli';
    return hash('sha256', $scope . '|' . strtolower(trim((string) $email)) . '|' . $ip);
}

function login_attempts_table() {
    global $pdo;
    static $ready = false;
    if (!$ready) {
        $pdo->exec("CREATE TABLE IF NOT EXISTS `login_attempts` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `attempt_key` CHAR(64) NOT NULL,
            `attempted_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_key_time` (`attempt_key`, `attempted_at`)
        ) ENGINE=InnoDB");
        $ready = true;
    }
    return $pdo;
}

/** Returns minutes the caller must wait, or 0 if login may be attempted. */
function login_lock_minutes($scope, $email) {
    $db = login_attempts_table();
    $stmt = $db->prepare("SELECT COUNT(*), TIMESTAMPDIFF(SECOND, NOW(), MIN(attempted_at) + INTERVAL " . LOGIN_LOCK_MINUTES . " MINUTE) FROM login_attempts
        WHERE attempt_key = ? AND attempted_at > (NOW() - INTERVAL " . LOGIN_LOCK_MINUTES . " MINUTE)");
    $stmt->execute([login_attempt_key($scope, $email)]);
    [$count, $secondsLeft] = $stmt->fetch(PDO::FETCH_NUM);
    if ((int) $count < LOGIN_MAX_ATTEMPTS) {
        return 0;
    }
    return max(1, (int) ceil(((int) $secondsLeft) / 60));
}

function login_record_failure($scope, $email) {
    $db = login_attempts_table();
    $db->prepare("INSERT INTO login_attempts (attempt_key) VALUES (?)")->execute([login_attempt_key($scope, $email)]);
    if (random_int(1, 50) === 1) {
        $db->exec("DELETE FROM login_attempts WHERE attempted_at < (NOW() - INTERVAL 1 DAY)");
    }
}

function login_clear_failures($scope, $email) {
    login_attempts_table()->prepare("DELETE FROM login_attempts WHERE attempt_key = ?")->execute([login_attempt_key($scope, $email)]);
}

function login_lock_message($minutes) {
    return "Too many failed login attempts. Please try again in {$minutes} minute" . ($minutes > 1 ? 's' : '') . '.';
}

/**
 * Only allow redirects to local relative paths (blocks open redirects like //evil.com).
 */
function safe_redirect_target($target, $fallback = 'index.php') {
    $target = trim((string) $target);
    if ($target === '' || preg_match('#^[a-z][a-z0-9+.-]*:#i', $target)
        || strpos($target, '//') === 0 || strpos($target, chr(92)) !== false
        || preg_match('/[\r\n]/', $target)) {
        return $fallback;
    }
    return $target;
}

/**
 * JSON-encode a value so it is safe inside HTML attributes and <script> blocks.
 */
function js_value($value) {
    return json_encode($value, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
}

/**
 * For legacy GET links that change data: the link must carry ?csrf_token=...
 * Use csrf_query() when building such links.
 */
function csrf_query() {
    return 'csrf_token=' . urlencode(csrf_token());
}

function require_csrf_get() {
    if (!verify_csrf_token($_GET['csrf_token'] ?? null)) {
        set_flash('error', 'Security check failed. Please try the action again.');
        header('Location: ' . strtok($_SERVER['REQUEST_URI'] ?? 'index.php', '?'));
        exit;
    }
}

/**
 * Rider shift-selfie checks.
 * Face matching runs in the rider's browser, so the server cannot fully trust its result.
 * These checks make replay/upload tricks harder and keep every selfie for admin review.
 */
define('RIDER_SELFIE_CHALLENGE_TTL', 300);      // seconds the selfie step stays valid
define('RIDER_SELFIE_MAX_BYTES', 2 * 1024 * 1024);

function rider_selfie_table() {
    global $pdo;
    static $ready = false;
    if (!$ready) {
        $pdo->exec("CREATE TABLE IF NOT EXISTS `rider_shift_selfies` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `rider_id` INT NOT NULL,
            `image_path` VARCHAR(255) NOT NULL,
            `image_hash` CHAR(64) NOT NULL,
            `client_match_distance` DECIMAL(6,4) DEFAULT NULL,
            `client_match_score` INT DEFAULT NULL,
            `ip_address` VARCHAR(45) DEFAULT NULL,
            `user_agent` VARCHAR(255) DEFAULT NULL,
            `review_status` ENUM('pending','approved','flagged') NOT NULL DEFAULT 'pending',
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_rider` (`rider_id`, `created_at`),
            INDEX `idx_hash` (`image_hash`)
        ) ENGINE=InnoDB");
        $ready = true;
    }
    return $pdo;
}

/**
 * Validates a live-camera data URL. Returns ['ok'=>bool, 'error'=>string, 'jpeg'=>bytes, 'hash'=>sha256].
 */
function rider_validate_live_selfie($dataUrl, $pending) {
    $fail = function ($msg) { return ['ok' => false, 'error' => $msg]; };

    if (!is_string($dataUrl) || !preg_match('#^data:image/jpeg;base64,#', $dataUrl)) {
        return $fail('Please take a live selfie with the camera. Photo uploads are not accepted.');
    }
    $bytes = base64_decode(substr($dataUrl, strpos($dataUrl, ',') + 1), true);
    if ($bytes === false || strlen($bytes) < 8 * 1024 || strlen($bytes) > RIDER_SELFIE_MAX_BYTES) {
        return $fail('Selfie image is invalid or too small. Please retake it in good light.');
    }
    $info = @getimagesizefromstring($bytes);
    if (!$info || $info[2] !== IMAGETYPE_JPEG || $info[0] < 320 || $info[1] < 240) {
        return $fail('Selfie image could not be read. Please retake it with the live camera.');
    }

    $hash = hash('sha256', $bytes);

    // Reject the registered KYC photo itself or any previously used shift selfie (replay)
    $kycPath = dirname(__DIR__) . '/' . ltrim((string) ($pending['selfie_image'] ?? ''), '/');
    if (!empty($pending['selfie_image']) && is_file($kycPath) && hash_file('sha256', $kycPath) === $hash) {
        return $fail('That is your registration photo. Please take a new live selfie.');
    }
    $dup = rider_selfie_table()->prepare("SELECT 1 FROM rider_shift_selfies WHERE image_hash = ? LIMIT 1");
    $dup->execute([$hash]);
    if ($dup->fetchColumn()) {
        return $fail('This selfie was already used before. Please take a new live selfie.');
    }

    return ['ok' => true, 'jpeg' => $bytes, 'hash' => $hash];
}

function rider_log_shift_selfie($riderId, $path, $hash, $distance, $score) {
    rider_selfie_table()->prepare("INSERT INTO rider_shift_selfies
        (rider_id, image_path, image_hash, client_match_distance, client_match_score, ip_address, user_agent)
        VALUES (?, ?, ?, ?, ?, ?, ?)")
        ->execute([
            $riderId, $path, $hash, $distance, $score,
            $_SERVER['REMOTE_ADDR'] ?? null,
            substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
        ]);
}

/**
 * Who is the current user to this order? Returns 'admin', 'customer', 'restaurant', 'rider' or null.
 * $order needs user_id, restaurant_id and delivery_partner_id.
 */
function order_viewer_role($order, $user) {
    global $pdo;
    if (!$user || !$order) {
        return null;
    }
    if ($user['role'] === 'admin') {
        return 'admin';
    }
    if ((int) $order['user_id'] === (int) $user['id']) {
        return 'customer';
    }
    if ($user['role'] === 'restaurant') {
        $s = $pdo->prepare("SELECT 1 FROM restaurants WHERE id = ? AND owner_id = ?");
        $s->execute([$order['restaurant_id'], $user['id']]);
        if ($s->fetchColumn()) {
            return 'restaurant';
        }
    }
    if ($user['role'] === 'delivery' && !empty($order['delivery_partner_id'])) {
        $s = $pdo->prepare("SELECT 1 FROM delivery_partners WHERE id = ? AND user_id = ?");
        $s->execute([$order['delivery_partner_id'], $user['id']]);
        if ($s->fetchColumn()) {
            return 'rider';
        }
    }
    return null;
}

/**
 * Ensures delivery_partners.location_updated_at exists (live GPS freshness).
 */
function ensure_rider_location_column() {
    global $pdo;
    static $done = false;
    if ($done) {
        return;
    }
    $has = $pdo->query("SHOW COLUMNS FROM `delivery_partners` LIKE 'location_updated_at'")->fetchColumn();
    if (!$has) {
        $pdo->exec("ALTER TABLE `delivery_partners` ADD COLUMN `location_updated_at` DATETIME DEFAULT NULL AFTER `current_lng`");
    }
    $done = true;
}

/**
 * Admin notifications (bell in the admin panel).
 */
function admin_notification_types() {
    return [
        'order_new'        => ['icon' => '🛒', 'label' => 'New order'],
        'order_cancelled'  => ['icon' => '❌', 'label' => 'Order cancelled'],
        'rider_registered' => ['icon' => '🛵', 'label' => 'Rider registration'],
        'rider_kyc'        => ['icon' => '🪪', 'label' => 'Rider KYC submitted'],
        'rider_selfie'     => ['icon' => '📸', 'label' => 'Shift selfie to check'],
        'partner_registered' => ['icon' => '🍳', 'label' => 'Restaurant sign-up'],
        'customer_new'     => ['icon' => '👤', 'label' => 'New customer'],
        'support_ticket'   => ['icon' => '💬', 'label' => 'Support ticket'],
        'contact_message'  => ['icon' => '📩', 'label' => 'Contact message'],
        'payout_request'   => ['icon' => '💰', 'label' => 'Payout request'],
        'review_low'       => ['icon' => '⭐', 'label' => 'Low rating'],
    ];
}

function ensure_admin_notifications_table() {
    global $pdo;
    static $done = false;
    if ($done) {
        return;
    }
    $pdo->exec("CREATE TABLE IF NOT EXISTS `admin_notifications` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `type` VARCHAR(30) NOT NULL,
        `title` VARCHAR(150) NOT NULL,
        `message` VARCHAR(255) DEFAULT NULL,
        `link` VARCHAR(255) DEFAULT NULL,
        `is_read` TINYINT(1) NOT NULL DEFAULT 0,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX `idx_unread` (`is_read`, `id`)
    ) ENGINE=InnoDB");
    $done = true;
}

/**
 * Records an admin notification. Never throws — a failed notification must not break the user's action.
 * $link is relative to the admin folder (e.g. 'orders.php').
 */
function notify_admin($type, $title, $message = '', $link = null) {
    global $pdo;
    try {
        // CREATE TABLE would implicitly commit an open transaction, so only ensure outside one
        if (!$pdo->inTransaction()) {
            ensure_admin_notifications_table();
        }
        $pdo->prepare("INSERT INTO admin_notifications (type, title, message, link) VALUES (?, ?, ?, ?)")
            ->execute([
                substr((string) $type, 0, 30),
                mb_substr((string) $title, 0, 150),
                mb_substr((string) $message, 0, 255),
                $link ? substr((string) $link, 0, 255) : null,
            ]);
    } catch (Throwable $e) {
        error_log('notify_admin failed: ' . $e->getMessage());
    }
}

/**
 * HTML-escape helper for output.
 */
function e($value) {
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

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
/**
 * Absolute URL path inside this app, e.g. app_url('admin/login.php') -> /Zayka/admin/login.php
 * (works whether the app lives at the web root or in a sub-folder).
 */
function app_url($path = '') {
    static $base = null;
    if ($base === null) {
        $slash = chr(92); // backslash (Windows paths)
        $docRoot = rtrim(str_replace($slash, '/', realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: ''), '/');
        $appRoot = str_replace($slash, '/', realpath(dirname(__DIR__)));
        $base = ($docRoot !== '' && stripos($appRoot, $docRoot) === 0) ? substr($appRoot, strlen($docRoot)) : '';
        $base = '/' . trim($base, '/');
        $base = $base === '/' ? '' : $base;
    }
    return $base . '/' . ltrim($path, '/');
}

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
        header("Location: " . (preg_match('#^(https?:)?/#', $loginUrl) ? $loginUrl : app_url($loginUrl)));
        exit;
    }

    if (!has_role($roles)) {
        http_response_code(403);
        die("<div style='font-family:sans-serif;text-align:center;padding:60px;'>
            <h1>403 - Access Denied</h1>
            <p>You do not have permission to access this portal.</p>
            <p><a href='" . app_url('index.php') . "'>Return to Homepage</a></p>
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
    return is_string($token) && isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function csrf_field() {
    return '<input type="hidden" name="csrf_token" value="' . csrf_token() . '">';
}

/**
 * Rejects any state-changing request (POST/PUT/PATCH/DELETE) without a valid CSRF token.
 * Token is accepted from the `csrf_token` form field or the `X-CSRF-Token` header.
 */
function enforce_csrf() {
    if (php_sapi_name() === 'cli') {
        return;
    }
    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    if (!in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
        return;
    }
    $token = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
    if (verify_csrf_token($token)) {
        return;
    }

    $isApi = strpos($_SERVER['SCRIPT_NAME'] ?? '', '/api/') !== false
        || !empty($_SERVER['HTTP_X_CSRF_TOKEN'])
        || stripos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false;
    http_response_code(419);
    if ($isApi) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'Session expired. Please refresh the page and try again.']);
        exit;
    }
    set_flash('error', 'Your session expired. Please try again.');
    $back = $_SERVER['REQUEST_URI'] ?? '/';
    header('Location: ' . $back);
    exit;
}

/**
 * Output filter: adds the CSRF token to every POST form and patches fetch()
 * so same-origin non-GET requests carry the X-CSRF-Token header.
 */
function csrf_output_filter($html) {
    if (stripos($html, '<form') === false && stripos($html, '</head>') === false) {
        return $html;
    }
    foreach (headers_list() as $h) {
        if (stripos($h, 'Content-Type:') === 0 && stripos($h, 'text/html') === false) {
            return $html;
        }
    }
    $token = htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8');
    $field = '<input type="hidden" name="csrf_token" value="' . $token . '">';
    $html = preg_replace_callback(
        '/<form\b[^>]*\bmethod\s*=\s*["\']?post["\']?[^>]*>/i',
        function ($m) use ($field) { return $m[0] . $field; },
        $html
    );
    $script = '<meta name="csrf-token" content="' . $token . '"><script>(function(){'
        . 'var t=document.querySelector(\'meta[name="csrf-token"]\').content,f=window.fetch;'
        . 'window.fetch=function(i,o){o=o||{};var m=(o.method||(i&&i.method)||"GET").toUpperCase();'
        . 'var u=typeof i==="string"?i:(i&&i.url)||"";var same=!/^https?:\/\//i.test(u)||u.indexOf(location.origin)===0;'
        . 'if(m!=="GET"&&m!=="HEAD"&&same){var h=new Headers(o.headers||(i&&i.headers)||{});h.set("X-CSRF-Token",t);o.headers=h;}'
        . 'return f.call(this,i,o);};})();</script>';
    $count = 0;
    $html = preg_replace('/<\/head>/i', $script . '</head>', $html, 1, $count);
    return $html;
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

// ---------------------------------------------------
// Global request protection (runs on every page/API)
// ---------------------------------------------------
if (php_sapi_name() !== 'cli') {
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    ob_start('csrf_output_filter');
    enforce_csrf();
}
