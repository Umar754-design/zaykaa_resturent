<?php
// ===================================================
// Admin Control Center Header
// ===================================================
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

require_role(['admin']);
$user = current_user();
$page = basename($_SERVER['PHP_SELF']);
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
