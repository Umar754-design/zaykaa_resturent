<?php
// ===================================================
// Admin Control Center Header
// ===================================================
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

require_role(['admin']);
$user = current_user();
$page = basename($_SERVER['PHP_SELF']);
ensure_admin_notifications_table();
$adminUnread = (int) $pdo->query("SELECT COUNT(*) FROM admin_notifications WHERE is_read = 0")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Zaykaa Admin Platform Control Center</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/responsive.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <style>
        .an-wrap { position: relative; }
        .an-bell { position: relative; width: 38px; height: 38px; border-radius: 50%; border: none; background: rgba(255,255,255,0.1); color: #fff; font-size: 18px; cursor: pointer; display: grid; place-items: center; }
        .an-bell:hover { background: rgba(255,255,255,0.18); }
        .an-bell.ring { animation: anRing 0.9s ease; }
        @keyframes anRing { 0%,100% { transform: rotate(0); } 20% { transform: rotate(-18deg); } 40% { transform: rotate(14deg); } 60% { transform: rotate(-10deg); } 80% { transform: rotate(6deg); } }
        .an-badge { position: absolute; top: -3px; right: -3px; min-width: 18px; height: 18px; padding: 0 5px; border-radius: 9px; background: #DC2626; color: #fff; font-size: 10px; font-weight: 700; display: grid; place-items: center; box-shadow: 0 0 0 2px #0B192C; }
        .an-badge[hidden] { display: none; }
        .an-panel { position: absolute; right: 0; top: calc(100% + 10px); width: min(380px, calc(100vw - 24px)); max-height: 70vh; display: none; flex-direction: column; background: #fff; color: #0F172A; border-radius: 14px; box-shadow: 0 18px 40px rgba(0,0,0,0.28); overflow: hidden; z-index: 2000; }
        .an-panel.open { display: flex; }
        .an-head { display: flex; justify-content: space-between; align-items: center; padding: 12px 16px; border-bottom: 1px solid #E2E8F0; }
        .an-head strong { font-size: 14px; }
        .an-head button { border: none; background: none; color: #2563EB; font-size: 12px; font-weight: 600; cursor: pointer; }
        .an-list { overflow-y: auto; }
        .an-item { display: flex; gap: 10px; padding: 11px 16px; border-bottom: 1px solid #F1F5F9; text-decoration: none; color: inherit; cursor: pointer; }
        .an-item:hover { background: #F8FAFC; }
        .an-item.unread { background: #F0F9FF; }
        .an-item.unread .an-title::after { content: ''; display: inline-block; width: 7px; height: 7px; border-radius: 50%; background: #2563EB; margin-left: 6px; vertical-align: middle; }
        .an-icon { flex-shrink: 0; width: 34px; height: 34px; border-radius: 10px; background: #F1F5F9; display: grid; place-items: center; font-size: 16px; }
        .an-title { font-size: 13px; font-weight: 600; line-height: 1.3; }
        .an-msg { font-size: 12px; color: #475569; margin-top: 2px; line-height: 1.35; }
        .an-when { font-size: 11px; color: #94A3B8; margin-top: 3px; }
        .an-empty { padding: 30px 16px; text-align: center; color: #94A3B8; font-size: 13px; }
        .an-foot { padding: 9px 16px; font-size: 11px; color: #64748B; background: #F8FAFC; display: flex; justify-content: space-between; align-items: center; gap: 8px; }
        .an-foot button { border: 1px solid #CBD5E1; background: #fff; border-radius: 8px; padding: 4px 8px; font-size: 11px; cursor: pointer; }
    </style>
</head>
<body style="background: #F4F6F8;">
    <div id="toastContainer"></div>

    <!-- Admin Topbar -->
    <header style="background: #0B192C; color: #fff; position: sticky; top: 0; z-index: 1000; box-shadow: var(--shadow-sm);">
        <div class="container admin-topbar-content" style="display: flex; justify-content: space-between; align-items: center; padding: 12px 20px;">
            <div style="display: flex; align-items: center; gap: 14px;">
                <a href="dashboard.php" style="font-family: 'Playfair Display', serif; font-size: 20px; font-weight: 700; color: #fff; text-decoration: none; display: flex; align-items: center; gap: 8px;">
                    <span>Zaykaa</span>
                    <span style="font-size: 11px; background: #DC2626; color: #fff; padding: 2px 8px; border-radius: 4px; font-family: 'Poppins', sans-serif;">ADMIN HQ</span>
                </a>
            </div>

            <div style="display: flex; align-items: center; gap: 16px; flex-wrap: wrap;">
                <div class="an-wrap">
                    <button type="button" class="an-bell" id="anBell" aria-label="Notifications" aria-haspopup="true" aria-expanded="false">
                        🔔<span class="an-badge" id="anBadge" <?php echo $adminUnread ? '' : 'hidden'; ?>><?php echo $adminUnread > 99 ? '99+' : $adminUnread; ?></span>
                    </button>
                    <div class="an-panel" id="anPanel" role="dialog" aria-label="Notifications">
                        <div class="an-head"><strong>Notifications</strong><button type="button" id="anMarkAll">Mark all as read</button></div>
                        <div class="an-list" id="anList"><div class="an-empty">Loading…</div></div>
                        <div class="an-foot"><span id="anFootText">Updates every 15 seconds</span><button type="button" id="anSoundBtn">🔊 Sound on</button></div>
                    </div>
                </div>
                <span style="font-size: 13px; color: #94A3B8;">
                    Logged in as <strong><?php echo htmlspecialchars($user['name']); ?></strong> (Superadmin)
                </span>
                <a href="../index.php" target="_blank" style="padding: 5px 12px; background: rgba(255,255,255,0.1); border-radius: 12px; font-size: 12px; color: #fff; text-decoration: none;">
                    🌐 View Storefront
                </a>
                <a href="../logout.php" style="color: #F87171; font-size: 13px; font-weight: 600; text-decoration: none;">Logout</a>
            </div>
        </div>

        <!-- Admin Navigation -->
        <nav class="admin-subnav" style="background: #1E3E62; border-top: 1px solid rgba(255,255,255,0.08); overflow-x: auto; scrollbar-width: none;">
            <div class="container" style="display: flex; gap: 20px; padding: 0 20px; white-space: nowrap;">
                <a href="dashboard.php" style="padding: 10px 0; font-size: 13px; font-weight: 600; color: <?php echo ($page === 'dashboard.php') ? '#FBBF24' : '#fff'; ?>; text-decoration: none; border-bottom: <?php echo ($page === 'dashboard.php') ? '3px solid #FBBF24' : 'none'; ?>;">
                    📊 Dashboard
                </a>
                <a href="orders.php" style="padding: 10px 0; font-size: 13px; font-weight: 600; color: <?php echo ($page === 'orders.php') ? '#FBBF24' : '#fff'; ?>; text-decoration: none; border-bottom: <?php echo ($page === 'orders.php') ? '3px solid #FBBF24' : 'none'; ?>;">
                    📋 Orders Control
                </a>
                <a href="restaurants.php" style="padding: 10px 0; font-size: 13px; font-weight: 600; color: <?php echo ($page === 'restaurants.php' || $page === 'restaurant-edit.php') ? '#FBBF24' : '#fff'; ?>; text-decoration: none; border-bottom: <?php echo ($page === 'restaurants.php' || $page === 'restaurant-edit.php') ? '3px solid #FBBF24' : 'none'; ?>;">
                    🍳 Restaurants
                </a>
                <a href="menu.php" style="padding: 10px 0; font-size: 13px; font-weight: 600; color: <?php echo ($page === 'menu.php' || $page === 'menu-edit.php' || $page === 'menu-add.php') ? '#FBBF24' : '#fff'; ?>; text-decoration: none; border-bottom: <?php echo ($page === 'menu.php' || $page === 'menu-edit.php' || $page === 'menu-add.php') ? '3px solid #FBBF24' : 'none'; ?>;">
                    🍽️ Menu Catalog
                </a>
                <a href="delivery-partners.php" style="padding: 10px 0; font-size: 13px; font-weight: 600; color: <?php echo ($page === 'delivery-partners.php') ? '#FBBF24' : '#fff'; ?>; text-decoration: none; border-bottom: <?php echo ($page === 'delivery-partners.php') ? '3px solid #FBBF24' : 'none'; ?>;">
                    🛵 Delivery Fleet
                </a>
                <a href="customers.php" style="padding: 10px 0; font-size: 13px; font-weight: 600; color: <?php echo ($page === 'customers.php') ? '#FBBF24' : '#fff'; ?>; text-decoration: none; border-bottom: <?php echo ($page === 'customers.php') ? '3px solid #FBBF24' : 'none'; ?>;">
                    👥 Customers
                </a>
                <a href="finance.php" style="padding: 10px 0; font-size: 13px; font-weight: 600; color: <?php echo ($page === 'finance.php') ? '#FBBF24' : '#fff'; ?>; text-decoration: none; border-bottom: <?php echo ($page === 'finance.php') ? '3px solid #FBBF24' : 'none'; ?>;">
                    💰 Finance & Payouts
                </a>
                <a href="coupons.php" style="padding: 10px 0; font-size: 13px; font-weight: 600; color: <?php echo ($page === 'coupons.php') ? '#FBBF24' : '#fff'; ?>; text-decoration: none; border-bottom: <?php echo ($page === 'coupons.php') ? '3px solid #FBBF24' : 'none'; ?>;">
                    🏷️ Coupons & Offers
                </a>
                <a href="support.php" style="padding: 10px 0; font-size: 13px; font-weight: 600; color: <?php echo ($page === 'support.php') ? '#FBBF24' : '#fff'; ?>; text-decoration: none; border-bottom: <?php echo ($page === 'support.php') ? '3px solid #FBBF24' : 'none'; ?>;">
                    💬 Support Desk
                </a>
                <a href="banners.php" style="padding: 10px 0; font-size: 13px; font-weight: 600; color: <?php echo ($page === 'banners.php') ? '#FBBF24' : '#fff'; ?>; text-decoration: none; border-bottom: <?php echo ($page === 'banners.php') ? '3px solid #FBBF24' : 'none'; ?>;">
                    🖼️ Banners
                </a>
                <a href="settings.php" style="padding: 10px 0; font-size: 13px; font-weight: 600; color: <?php echo ($page === 'settings.php') ? '#FBBF24' : '#fff'; ?>; text-decoration: none; border-bottom: <?php echo ($page === 'settings.php') ? '3px solid #FBBF24' : 'none'; ?>;">
                    ⚙️ Platform Settings
                </a>
            </div>
        </nav>
    </header>

    <?php echo render_flash_message(); ?>
