<?php
// ===================================================
// Restaurant Partner Dashboard - Zayka Platform
// ===================================================
include 'includes/header.php';

$restId = (int)$currentRestaurant['id'];

// 1. Compute Real DB Metrics
// Today's orders & revenue
$todayStmt = $pdo->prepare("SELECT COUNT(*) as total_orders, 
                                   COALESCE(SUM(restaurant_earnings), 0) as net_revenue,
                                   COALESCE(SUM(subtotal + addons_total), 0) as gross_sales 
                            FROM orders 
                            WHERE restaurant_id = ? AND DATE(created_at) = CURDATE() AND order_status != 'cancelled'");
$todayStmt->execute([$restId]);
$todayMetrics = $todayStmt->fetch();

// Order counts by status
$statusStmt = $pdo->prepare("SELECT 
    COUNT(CASE WHEN order_status IN ('placed', 'accepted', 'preparing') THEN 1 END) as pending_count,
    COUNT(CASE WHEN order_status = 'delivered' THEN 1 END) as completed_count,
    COUNT(CASE WHEN order_status = 'cancelled' THEN 1 END) as cancelled_count,
    COUNT(DISTINCT user_id) as total_customers,
    COUNT(*) as lifetime_orders
FROM orders WHERE restaurant_id = ?");
$statusStmt->execute([$restId]);
$statusMetrics = $statusStmt->fetch();

// Recent 8 Orders
$recStmt = $pdo->prepare("SELECT o.*, u.name as customer_name, u.phone as customer_phone 
                          FROM orders o 
                          JOIN users u ON o.user_id = u.id 
                          WHERE o.restaurant_id = ? 
                          ORDER BY o.created_at DESC LIMIT 8");
$recStmt->execute([$restId]);
$recentOrders = $recStmt->fetchAll();
?>

<div style="padding: 30px 0 60px;">
    <div class="container">
        <!-- Dashboard Top Banner -->
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 15px;">
            <div>
                <h1 style="font-family: 'Playfair Display', serif; font-size: 28px; color: var(--primary-green); margin: 0 0 4px;">
                    Kitchen Control Center
                </h1>
                <p style="color: #666; font-size: 14px; margin: 0;">
                    Live performance metrics for <strong><?php echo htmlspecialchars($currentRestaurant['name']); ?></strong>
                </p>
            </div>
            
            <div style="display: flex; gap: 10px;">
                <a href="orders.php" style="padding: 10px 20px; background: #DC2626; color: #fff; border-radius: 20px; font-weight: 700; text-decoration: none; font-size: 13px; display: flex; align-items: center; gap: 6px; box-shadow: var(--shadow-sm);">
                    <span>🔥 View Live Orders Board</span>
                </a>
                <a href="menu-item-edit.php" style="padding: 10px 20px; background: var(--primary-green); color: #fff; border-radius: 20px; font-weight: 600; text-decoration: none; font-size: 13px;">
                    + Add New Dish
                </a>
            </div>
        </div>

        <!-- Metric KPI Cards (Real DB Data) -->
        <div class="dashboard-grid">
            <div class="stat-kpi-card">
                <span class="kpi-title">Today's Orders</span>
                <span class="kpi-value"><?php echo (int)$todayMetrics['total_orders']; ?></span>
                <span class="kpi-sub">Lifetime: <?php echo (int)$statusMetrics['lifetime_orders']; ?> orders</span>
            </div>

            <div class="stat-kpi-card gold">
                <span class="kpi-title">Today's Net Revenue</span>
                <span class="kpi-value"><?php echo format_price($todayMetrics['net_revenue']); ?></span>
                <span class="kpi-sub">Gross Sales: <?php echo format_price($todayMetrics['gross_sales']); ?></span>
            </div>

            <div class="stat-kpi-card blue">
                <span class="kpi-title">Pending / In Kitchen</span>
                <span class="kpi-value" style="color: #2563EB;"><?php echo (int)$statusMetrics['pending_count']; ?></span>
                <span class="kpi-sub">Active orders requiring attention</span>
            </div>

            <div class="stat-kpi-card purple">
                <span class="kpi-title">Completed Orders</span>
                <span class="kpi-value" style="color: #046C4E;"><?php echo (int)$statusMetrics['completed_count']; ?></span>
                <span class="kpi-sub">Delivered successfully</span>
            </div>

            <div class="stat-kpi-card">
                <span class="kpi-title">Customer Rating</span>
                <span class="kpi-value">★ <?php echo number_format($currentRestaurant['rating'], 1); ?></span>
                <span class="kpi-sub">Based on <?php echo $currentRestaurant['total_ratings']; ?> customer ratings</span>
            </div>

            <div class="stat-kpi-card">
                <span class="kpi-title">Total Customers</span>
                <span class="kpi-value"><?php echo (int)$statusMetrics['total_customers']; ?></span>
                <span class="kpi-sub">Unique diner base</span>
            </div>
        </div>

        <!-- Recent Orders Table -->
        <div style="background: #FFFFFF; border-radius: 16px; border: 1px solid var(--light-border); padding: 24px; box-shadow: var(--shadow-sm);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 18px;">
                <h3 style="font-family: 'Playfair Display', serif; font-size: 20px; color: var(--primary-green); margin: 0;">
                    Recent Customer Orders
                </h3>
                <a href="orders.php" style="font-size: 13px; color: var(--primary-green); font-weight: 600; text-decoration: none;">View All Orders →</a>
            </div>

            <div class="table-responsive">
                <table class="zayka-table">
                    <thead>
                        <tr>
                            <th>Order #</th>
                            <th>Customer</th>
                            <th>Date & Time</th>
                            <th>Items & Total</th>
                            <th>Status</th>
                            <th>Payment</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($recentOrders)): ?>
                            <?php foreach ($recentOrders as $ord): 
                                // Fetch items
                                $itStmt = $pdo->prepare("SELECT item_name, quantity FROM order_items WHERE order_id = ?");
                                $itStmt->execute([$ord['id']]);
                                $its = $itStmt->fetchAll();
                                $summaryStr = implode(', ', array_map(function($i) { return $i['quantity'] . 'x ' . $i['item_name']; }, $its));
                            ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($ord['order_number']); ?></strong>
                                    <?php if ($ord['special_instructions']): ?>
                                        <div style="font-size: 11px; color: #D97706;">📝 "<?php echo htmlspecialchars($ord['special_instructions']); ?>"</div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="font-weight: 600;"><?php echo htmlspecialchars($ord['customer_name']); ?></div>
                                    <div style="font-size: 12px; color: #777;">📞 <?php echo htmlspecialchars($ord['customer_phone']); ?></div>
                                </td>
                                <td style="font-size: 12px; color: #666;">
                                    <?php echo date('d M, h:i A', strtotime($ord['created_at'])); ?>
                                </td>
                                <td>
                                    <div style="font-weight: 700; color: var(--primary-green);"><?php echo format_price($ord['total_amount']); ?></div>
                                    <div style="font-size: 11px; color: #666; max-width: 250px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                        <?php echo htmlspecialchars($summaryStr); ?>
                                    </div>
                                </td>
                                <td><?php echo get_status_badge($ord['order_status']); ?></td>
                                <td style="font-size: 12px;">
                                    <strong><?php echo strtoupper($ord['payment_method']); ?></strong><br>
                                    <span style="color: <?php echo ($ord['payment_status'] === 'paid') ? '#16A34A' : '#DC2626'; ?>; font-weight: 600;">
                                        <?php echo ucfirst($ord['payment_status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($ord['order_status'] === 'placed'): ?>
                                        <button onclick="acceptOrder(<?php echo $ord['id']; ?>)" style="padding: 6px 12px; background: #059669; color: #fff; border-radius: 6px; font-weight: 600; font-size: 12px; cursor: pointer; border: none;">
                                            Accept ✓
                                        </button>
                                    <?php elseif ($ord['order_status'] === 'accepted'): ?>
                                        <button onclick="updateOrderStatus(<?php echo $ord['id']; ?>, 'preparing')" style="padding: 6px 12px; background: #D97706; color: #fff; border-radius: 6px; font-weight: 600; font-size: 12px; cursor: pointer; border: none;">
                                            Start Prep 🍳
                                        </button>
                                    <?php elseif ($ord['order_status'] === 'preparing'): ?>
                                        <button onclick="updateOrderStatus(<?php echo $ord['id']; ?>, 'ready_for_pickup')" style="padding: 6px 12px; background: #7C3AED; color: #fff; border-radius: 6px; font-weight: 600; font-size: 12px; cursor: pointer; border: none;">
                                            Mark Ready 📦
                                        </button>
                                    <?php else: ?>
                                        <a href="../order-status.php?id=<?php echo $ord['id']; ?>" target="_blank" style="font-size: 12px; color: var(--primary-green); font-weight: 600; text-decoration: underline;">
                                            View Track
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" style="text-align: center; padding: 40px; color: #888;">
                                    No orders received today. Ensure restaurant status is set to "Open".
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

<script>
function acceptOrder(id) {
    const prepTime = prompt('Estimated preparation time in minutes:', '25');
    if (prepTime) {
        const formData = new FormData();
        formData.append('action', 'update_status');
        formData.append('order_id', id);
        formData.append('status', 'accepted');
        formData.append('prep_time', prepTime);

        fetch('../api/orders.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message, 'success');
                    setTimeout(() => window.location.reload(), 600);
                } else {
                    showToast(data.message, 'error');
                }
            });
    }
}

function updateOrderStatus(id, status) {
    const formData = new FormData();
    formData.append('action', 'update_status');
    formData.append('order_id', id);
    formData.append('status', status);

    fetch('../api/orders.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                showToast(data.message, 'success');
                setTimeout(() => window.location.reload(), 600);
            } else {
                showToast(data.message, 'error');
            }
        });
}
</script>

<?php include 'includes/footer.php'; ?>
