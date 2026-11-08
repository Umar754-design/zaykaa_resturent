<?php
// ===================================================
// Available Orders & Duty Dashboard - Delivery Partner
// ===================================================
include 'includes/header.php';

$riderId = (int)$rider['id'];

// Check for active order
$actStmt = $pdo->prepare("SELECT * FROM orders WHERE delivery_partner_id = ? AND order_status IN ('accepted', 'preparing', 'ready_for_pickup', 'picked_up', 'out_for_delivery') LIMIT 1");
$actStmt->execute([$riderId]);
$activeOrder = $actStmt->fetch();

// Fetch available unassigned orders
$poolStmt = $pdo->query("SELECT o.*, r.name as rest_name, r.address as rest_address, r.city as rest_city 
                         FROM orders o 
                         JOIN restaurants r ON o.restaurant_id = r.id 
                         WHERE o.delivery_partner_id IS NULL 
                           AND o.order_status IN ('accepted', 'preparing', 'ready_for_pickup') 
                         ORDER BY o.created_at ASC");
$availableOrders = $poolStmt->fetchAll();

// Today's completed trips & earnings
$todayStmt = $pdo->prepare("SELECT COUNT(*) as trip_count, COALESCE(SUM(delivery_earnings), 0) as today_earnings 
                            FROM orders 
                            WHERE delivery_partner_id = ? AND order_status = 'delivered' AND DATE(created_at) = CURDATE()");
$todayStmt->execute([$riderId]);
$todayStats = $todayStmt->fetch();
?>

<div style="padding: 30px 0 60px;">
    <div class="container">
        
        <!-- Active Delivery Alert Banner -->
        <?php if ($activeOrder): ?>
            <div style="background: #FEF3C7; border: 2px solid #F59E0B; border-radius: 16px; padding: 20px 24px; margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
                <div>
                    <div style="font-size: 11px; font-weight: 800; color: #B45309; text-transform: uppercase; letter-spacing: 1px;">ACTIVE TRIP IN PROGRESS</div>
                    <h3 style="margin: 4px 0; font-size: 18px; color: #92400E;">
                        Order #<?php echo htmlspecialchars($activeOrder['order_number']); ?> (<?php echo ucfirst(str_replace('_', ' ', $activeOrder['order_status'])); ?>)
                    </h3>
                    <p style="margin: 0; font-size: 13px; color: #78350F;">
                        Proceed to restaurant for pickup or deliver package to customer address.
                    </p>
                </div>
                <a href="active-order.php" style="padding: 12px 24px; background: #D97706; color: #fff; border-radius: 25px; font-weight: 700; text-decoration: none; font-size: 14px; box-shadow: var(--shadow-sm);">
                    Open Trip Navigation 🗺️ →
                </a>
            </div>
        <?php endif; ?>

        <!-- Dashboard Header -->
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 15px;">
            <div>
                <h1 style="font-family: 'Playfair Display', serif; font-size: 28px; color: #1E293B; margin: 0 0 4px;">
                    Rider Duty Dashboard 🛵
                </h1>
                <p style="color: #64748B; font-size: 14px; margin: 0;">
                    Accept nearby food pickups and earn guaranteed delivery pay plus tips
                </p>
            </div>

            <div style="font-size: 13px; color: #64748B;">
                Vehicle: <strong><?php echo htmlspecialchars($rider['vehicle_type'] . ' (' . $rider['vehicle_number'] . ')'); ?></strong>
            </div>
        </div>

        <!-- Real KPI Cards -->
        <div class="dashboard-grid">
            <div class="stat-kpi-card">
                <span class="kpi-title">Today's Earnings</span>
                <span class="kpi-value" style="color: #059669;"><?php echo format_price($todayStats['today_earnings']); ?></span>
                <span class="kpi-sub">Total wallet balance: <?php echo format_price($rider['earnings_balance']); ?></span>
            </div>

            <div class="stat-kpi-card gold">
                <span class="kpi-title">Today's Deliveries</span>
                <span class="kpi-value"><?php echo (int)$todayStats['trip_count']; ?> Trips</span>
                <span class="kpi-sub">Lifetime deliveries: <?php echo (int)$rider['total_deliveries']; ?></span>
            </div>

            <div class="stat-kpi-card blue">
                <span class="kpi-title">Rider Rating</span>
                <span class="kpi-value" style="color: #2563EB;">★ <?php echo number_format($rider['rating'], 1); ?></span>
                <span class="kpi-sub">Customer satisfaction score</span>
            </div>

            <div class="stat-kpi-card purple">
                <span class="kpi-title">KYC Document Status</span>
                <span class="kpi-value" style="font-size: 20px; color: <?php echo ($rider['kyc_status'] === 'approved') ? '#059669' : '#D97706'; ?>;">
                    <?php echo strtoupper($rider['kyc_status']); ?>
                </span>
                <span class="kpi-sub">Verified identity</span>
            </div>
        </div>

        <!-- Available Orders List -->
        <div style="margin-top: 30px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 18px;">
                <h3 style="font-family: 'Playfair Display', serif; font-size: 22px; color: #1E293B; margin: 0;">
                    Available Orders Pool (<?php echo count($availableOrders); ?>)
                </h3>
                <span style="font-size: 12px; color: #64748B;">Auto-refreshes for new dispatches</span>
            </div>

            <?php if (empty($rider['is_online'])): ?>
                <div style="background: #fff; border-radius: 16px; border: 1px solid var(--light-border); padding: 50px 20px; text-align: center; max-width: 500px; margin: 20px auto;">
                    <div style="font-size: 42px; margin-bottom: 12px;">⏸️</div>
                    <h3 style="color: #1E293B; margin: 0 0 6px;">You are Currently Offline</h3>
                    <p style="color: #64748B; font-size: 14px; margin: 0 0 16px;">Switch your duty toggle to "On Duty (Online)" in the header bar to see live orders.</p>
                </div>
            <?php elseif (!empty($availableOrders)): ?>
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 20px;">
                    <?php foreach ($availableOrders as $ord): 
                        $dest = json_decode($ord['delivery_address_json'], true);
                    ?>
                    <div style="background: #FFFFFF; border-radius: 16px; border: 1px solid var(--light-border); padding: 22px; box-shadow: var(--shadow-sm); display: flex; flex-direction: column; justify-content: space-between;">
                        <div>
                            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px; padding-bottom: 10px; border-bottom: 1px solid #F1EDE2;">
                                <div>
                                    <span style="font-size: 11px; font-weight: 700; color: #888; text-transform: uppercase;">Order #<?php echo htmlspecialchars($ord['order_number']); ?></span>
                                    <h4 style="margin: 2px 0 0; color: var(--primary-green); font-size: 17px;"><?php echo htmlspecialchars($ord['rest_name']); ?></h4>
                                </div>
                                <div style="text-align: right;">
                                    <div style="font-size: 18px; font-weight: 800; color: #059669;"><?php echo format_price($ord['delivery_earnings']); ?></div>
                                    <div style="font-size: 11px; color: #666;">Trip Pay <?php if ($ord['tip_amount'] > 0): ?>+ Tip<?php endif; ?></div>
                                </div>
                            </div>

                            <div style="display: flex; flex-direction: column; gap: 8px; font-size: 13px; color: #444; margin-bottom: 16px;">
                                <div style="display: flex; gap: 8px; align-items: flex-start;">
                                    <span>🍳</span>
                                    <div><strong>Pickup:</strong> <?php echo htmlspecialchars($ord['rest_address'] . ', ' . $ord['rest_city']); ?></div>
                                </div>
                                <div style="display: flex; gap: 8px; align-items: flex-start;">
                                    <span>🏠</span>
                                    <div><strong>Deliver:</strong> <?php echo htmlspecialchars($dest['address_line'] ?? ''); ?></div>
                                </div>
                            </div>
                        </div>

                        <button onclick="acceptOrderAssignment(<?php echo $ord['id']; ?>)" 
                                style="width: 100%; padding: 12px; background: #059669; color: #fff; border-radius: 20px; font-weight: 700; font-size: 14px; border: none; cursor: pointer; box-shadow: var(--shadow-sm);">
                            Accept Delivery 🛵
                        </button>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div style="background: #fff; border-radius: 16px; border: 1px solid var(--light-border); padding: 50px 20px; text-align: center; max-width: 500px; margin: 20px auto;">
                    <div style="font-size: 42px; margin-bottom: 12px;">☕</div>
                    <h3 style="color: #1E293B; margin: 0 0 6px;">No Orders Available Right Now</h3>
                    <p style="color: #64748B; font-size: 14px; margin: 0;">Stay parked near central restaurants in Dhanbad. New orders will pop up instantly.</p>
                </div>
            <?php endif; ?>
        </div>

    </div>
</div>

<script>
function acceptOrderAssignment(orderId) {
    const formData = new FormData();
    formData.append('action', 'accept_delivery');
    formData.append('order_id', orderId);

    fetch('../api/orders.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                showToast(data.message, 'success');
                setTimeout(() => {
                    window.location.href = 'active-order.php';
                }, 600);
            } else {
                showToast(data.message, 'error');
            }
        });
}

// Auto-refresh available orders pool every 6 seconds
setInterval(() => {
    window.location.reload();
}, 6000);
</script>

<?php include 'includes/footer.php'; ?>
