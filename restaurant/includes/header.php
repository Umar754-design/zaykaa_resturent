<?php
// ===================================================
// Restaurant Partner Portal Header
// ===================================================
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

require_role(['restaurant', 'admin']);
$user = current_user();

// Fetch restaurant managed by this user (if admin, pick first)
if ($user['role'] === 'admin') {
    $rStmt = $pdo->query("SELECT * FROM restaurants LIMIT 1");
} else {
    $rStmt = $pdo->prepare("SELECT * FROM restaurants WHERE owner_id = ?");
    $rStmt->execute([$user['id']]);
}
$currentRestaurant = $rStmt->fetch();

if (!$currentRestaurant) {
    die("<div style='padding:50px; text-align:center;'>No restaurant associated with this partner account. <a href='../logout.php'>Logout</a></div>");
}

$page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($currentRestaurant['name']); ?> | Partner Dashboard</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;600;700&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/responsive.css">
</head>
<body style="background: #F8F9FA;">
    <div id="toastContainer"></div>

    <!-- Partner Topbar -->
    <header style="background: #063F2B; color: #fff; position: sticky; top: 0; z-index: 1000; box-shadow: var(--shadow-sm);">
        <div class="container partner-topbar-content" style="display: flex; justify-content: space-between; align-items: center; padding: 12px 20px;">
            <div style="display: flex; align-items: center; gap: 15px; flex-wrap: wrap;">
                <a href="dashboard.php" style="font-family: 'Playfair Display', serif; font-size: 20px; font-weight: 700; color: #fff; text-decoration: none; display: flex; align-items: center; gap: 6px;">
                    <span>Zaykaa</span>
                    <span style="font-size: 13px; background: var(--gold); color: #fff; padding: 2px 8px; border-radius: 4px; font-family: 'Poppins', sans-serif;">PARTNER</span>
                </a>
                <span style="opacity: 0.4;">|</span>
                <span style="font-weight: 600; font-size: 14px; color: var(--light-gold);">
                    <?php echo htmlspecialchars($currentRestaurant['name']); ?>
                </span>
            </div>

            <!-- Status Selector & User -->
            <div style="display: flex; align-items: center; gap: 16px; flex-wrap: wrap;">
                <!-- Open/Closed/Busy Switch -->
                <div style="display: flex; align-items: center; gap: 8px; background: rgba(255,255,255,0.1); padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600;">
                    <span>Status:</span>
                    <select onchange="updateRestaurantStatus(this.value)" 
                            style="background: transparent; color: #fff; border: none; outline: none; font-weight: 700; cursor: pointer;">
                        <option value="open" <?php echo ($currentRestaurant['status'] === 'open') ? 'selected' : ''; ?> style="color:#000;">🟢 Open (Accepting Orders)</option>
                        <option value="busy" <?php echo ($currentRestaurant['status'] === 'busy') ? 'selected' : ''; ?> style="color:#000;">🟡 Busy (Extra Prep Time)</option>
                        <option value="closed" <?php echo ($currentRestaurant['status'] === 'closed') ? 'selected' : ''; ?> style="color:#000;">🔴 Closed</option>
                    </select>
                </div>

                <div style="font-size: 13px; font-weight: 600;">
                    <?php echo htmlspecialchars($user['name']); ?>
                </div>
                <a href="../logout.php" style="color: #F87171; font-size: 13px; font-weight: 600; text-decoration: none;">Logout</a>
            </div>
        </div>

        <!-- Sub-Navigation Bar -->
        <nav class="partner-subnav" style="background: #0B5138; border-top: 1px solid rgba(255,255,255,0.1); overflow-x: auto; scrollbar-width: none;">
            <div class="container" style="display: flex; gap: 24px; padding: 0 20px; white-space: nowrap;">
                <a href="dashboard.php" style="padding: 10px 0; font-size: 13px; font-weight: 600; color: <?php echo ($page === 'dashboard.php') ? 'var(--light-gold)' : '#fff'; ?>; text-decoration: none; border-bottom: <?php echo ($page === 'dashboard.php') ? '3px solid var(--gold)' : 'none'; ?>;">
                    📊 Dashboard
                </a>
                <a href="orders.php" style="padding: 10px 0; font-size: 13px; font-weight: 600; color: <?php echo ($page === 'orders.php') ? 'var(--light-gold)' : '#fff'; ?>; text-decoration: none; border-bottom: <?php echo ($page === 'orders.php') ? '3px solid var(--gold)' : 'none'; ?>; display: flex; align-items: center; gap: 6px;">
                    <span>📋 Live Orders</span>
                    <span id="partnerLiveCount" style="background: #DC2626; color: #fff; font-size: 10px; padding: 1px 6px; border-radius: 10px;">0</span>
                </a>
                <a href="menu.php" style="padding: 10px 0; font-size: 13px; font-weight: 600; color: <?php echo ($page === 'menu.php' || $page === 'menu-item-edit.php') ? 'var(--light-gold)' : '#fff'; ?>; text-decoration: none; border-bottom: <?php echo ($page === 'menu.php' || $page === 'menu-item-edit.php') ? '3px solid var(--gold)' : 'none'; ?>;">
                    🍽️ Menu & Add-ons
                </a>
                <a href="analytics.php" style="padding: 10px 0; font-size: 13px; font-weight: 600; color: <?php echo ($page === 'analytics.php') ? 'var(--light-gold)' : '#fff'; ?>; text-decoration: none; border-bottom: <?php echo ($page === 'analytics.php') ? '3px solid var(--gold)' : 'none'; ?>;">
                    📈 Sales Analytics
                </a>
                <a href="payouts.php" style="padding: 10px 0; font-size: 13px; font-weight: 600; color: <?php echo ($page === 'payouts.php') ? 'var(--light-gold)' : '#fff'; ?>; text-decoration: none; border-bottom: <?php echo ($page === 'payouts.php') ? '3px solid var(--gold)' : 'none'; ?>;">
                    💰 Payouts
                </a>
                <a href="profile.php" style="padding: 10px 0; font-size: 13px; font-weight: 600; color: <?php echo ($page === 'profile.php') ? 'var(--light-gold)' : '#fff'; ?>; text-decoration: none; border-bottom: <?php echo ($page === 'profile.php') ? '3px solid var(--gold)' : 'none'; ?>;">
                    ⚙️ Settings & Hours
                </a>
                <a href="../restaurant.php?id=<?php echo $currentRestaurant['id']; ?>" target="_blank" style="padding: 10px 0; font-size: 13px; font-weight: 600; color: rgba(255,255,255,0.7); text-decoration: none;">
                    ↗️ View Storefront
                </a>
            </div>
        </nav>
    </header>

    <?php echo render_flash_message(); ?>

    <script>
    function updateRestaurantStatus(newStatus) {
        const formData = new FormData();
        formData.append('type', 'restaurant_status');
        formData.append('restaurant_id', <?php echo $currentRestaurant['id']; ?>);
        formData.append('status', newStatus);

        fetch('../api/toggle-status.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message, 'success');
                } else {
                    showToast(data.message, 'error');
                }
            });
    }
    </script>
