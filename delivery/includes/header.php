<?php
// ===================================================
// Delivery Partner Portal Header
// ===================================================
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

require_role(['delivery', 'admin']);
$user = current_user();

// Fetch rider profile
$rStmt = $pdo->prepare("SELECT * FROM delivery_partners WHERE user_id = ?");
$rStmt->execute([$user['id']]);
$rider = $rStmt->fetch();

if (!$rider && $user['role'] === 'admin') {
    $rider = $pdo->query("SELECT * FROM delivery_partners LIMIT 1")->fetch();
}

// Mandatory check: If approved rider has not verified current shift selfie, redirect to login
if ($user['role'] === 'delivery' && $rider) {
    if ($rider['kyc_status'] === 'pending') {
        session_destroy();
        init_session();
        set_flash('error', '⏳ Your Rider KYC verification is pending Admin approval.');
        header("Location: login.php");
        exit;
    } elseif ($rider['kyc_status'] === 'rejected') {
        session_destroy();
        init_session();
        set_flash('error', '❌ Your Rider KYC application was rejected by operations.');
        header("Location: login.php");
        exit;
    } elseif ($rider['kyc_status'] === 'approved' && empty($_SESSION['rider_selfie_verified'])) {
        $_SESSION['pending_rider_login'] = [
            'user_id'      => (int)$user['id'],
            'rider_id'     => (int)$rider['id'],
            'name'         => $user['name'],
            'email'        => $user['email'],
            'phone'        => $user['phone'],
            'selfie_image' => $rider['selfie_image'] ?? ''
        ];
        header("Location: login.php");
        exit;
    }
}

$page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delivery Partner Portal | Zaykaa</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;600;700&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/responsive.css">
</head>
<body style="background: #F8F9FA;">
    <div id="toastContainer"></div>

    <!-- Rider Topbar -->
    <header style="background: #1E293B; color: #fff; position: sticky; top: 0; z-index: 1000; box-shadow: var(--shadow-sm);">
        <div class="container rider-topbar-content" style="display: flex; justify-content: space-between; align-items: center; padding: 12px 20px;">
            <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                <a href="dashboard.php" style="font-family: 'Playfair Display', serif; font-size: 20px; font-weight: 700; color: #fff; text-decoration: none; display: flex; align-items: center; gap: 6px;">
                    <span>Zaykaa</span>
                    <span style="font-size: 11px; background: #059669; color: #fff; padding: 2px 8px; border-radius: 4px; font-family: 'Poppins', sans-serif;">RIDER</span>
                </a>
                <span style="opacity: 0.4;">|</span>
                
                <?php if (!empty($rider['current_selfie']) || !empty($rider['selfie_image'])): ?>
                    <img src="../<?php echo htmlspecialchars($rider['current_selfie'] ?: $rider['selfie_image']); ?>" 
                         alt="Rider Photo" 
                         style="width: 32px; height: 32px; border-radius: 50%; object-fit: cover; border: 2px solid #059669;">
                <?php endif; ?>

                <span style="font-size: 13px; font-weight: 600; color: #94A3B8;">
                    <?php echo htmlspecialchars($user['name']); ?>
                </span>

                <span style="font-size: 10px; padding: 2px 8px; border-radius: 10px; background: #DEF7EC; color: #046C4E; font-weight: 700;">
                    📸 Live Selfie Verified
                </span>
            </div>

            <div style="display: flex; align-items: center; gap: 14px; flex-wrap: wrap;">
                <!-- Online/Offline Duty Switch -->
                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; background: rgba(255,255,255,0.08); padding: 5px 14px; border-radius: 20px; font-size: 12px; font-weight: 600;">
                    <input type="checkbox" id="riderOnlineCheck" <?php echo (!empty($rider['is_online'])) ? 'checked' : ''; ?> 
                           onchange="toggleRiderDuty(this.checked)" style="accent-color: #10B981; width: 16px; height: 16px;">
                    <span id="dutyStatusLabel" style="color: <?php echo (!empty($rider['is_online'])) ? '#10B981' : '#94A3B8'; ?>;">
                        <?php echo (!empty($rider['is_online'])) ? '● On Duty (Online)' : '○ Off Duty (Offline)'; ?>
                    </span>
                </label>

                <a href="../logout.php" style="color: #F87171; font-size: 13px; font-weight: 600; text-decoration: none;">Logout</a>
            </div>
        </div>

        <!-- Subnav -->
        <nav class="rider-subnav" style="background: #0F172A; border-top: 1px solid rgba(255,255,255,0.08); overflow-x: auto; scrollbar-width: none;">
            <div class="container" style="display: flex; gap: 24px; padding: 0 20px; white-space: nowrap;">
                <a href="dashboard.php" style="padding: 10px 0; font-size: 13px; font-weight: 600; color: <?php echo ($page === 'dashboard.php') ? '#38BDF8' : '#fff'; ?>; text-decoration: none; border-bottom: <?php echo ($page === 'dashboard.php') ? '3px solid #38BDF8' : 'none'; ?>;">
                    🛵 Available Orders
                </a>
                <a href="active-order.php" style="padding: 10px 0; font-size: 13px; font-weight: 600; color: <?php echo ($page === 'active-order.php') ? '#38BDF8' : '#fff'; ?>; text-decoration: none; border-bottom: <?php echo ($page === 'active-order.php') ? '3px solid #38BDF8' : 'none'; ?>;">
                    📍 Active Delivery
                </a>
                <a href="earnings.php" style="padding: 10px 0; font-size: 13px; font-weight: 600; color: <?php echo ($page === 'earnings.php') ? '#38BDF8' : '#fff'; ?>; text-decoration: none; border-bottom: <?php echo ($page === 'earnings.php') ? '3px solid #38BDF8' : 'none'; ?>;">
                    💰 Earnings & Trips
                </a>
                <a href="profile.php" style="padding: 10px 0; font-size: 13px; font-weight: 600; color: <?php echo ($page === 'profile.php') ? '#38BDF8' : '#fff'; ?>; text-decoration: none; border-bottom: <?php echo ($page === 'profile.php') ? '3px solid #38BDF8' : 'none'; ?>;">
                    🪪 KYC & Vehicle
                </a>
            </div>
        </nav>
    </header>

    <?php echo render_flash_message(); ?>

    <script>
    function toggleRiderDuty(isOnline) {
        const formData = new FormData();
        formData.append('type', 'delivery_online');
        formData.append('is_online', isOnline ? '1' : '0');

        fetch('../api/toggle-status.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message, 'success');
                    const lbl = document.getElementById('dutyStatusLabel');
                    if (lbl) {
                        lbl.innerText = isOnline ? '● On Duty (Online)' : '○ Off Duty (Offline)';
                        lbl.style.color = isOnline ? '#10B981' : '#94A3B8';
                    }
                } else {
                    showToast(data.message, 'error');
                }
            });
    }
    </script>
