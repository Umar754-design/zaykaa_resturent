<?php
// ===================================================
// Rider Earnings & Trip History - Delivery Partner
// ===================================================
include 'includes/header.php';

$riderId = (int)$rider['id'];

// Completed deliveries
$stmt = $pdo->prepare("SELECT o.*, r.name as rest_name 
                       FROM orders o 
                       JOIN restaurants r ON o.restaurant_id = r.id 
                       WHERE o.delivery_partner_id = ? AND o.order_status = 'delivered' 
                       ORDER BY o.updated_at DESC");
$stmt->execute([$riderId]);
$completedTrips = $stmt->fetchAll();

// Total earnings and tips
$totStmt = $pdo->prepare("SELECT COALESCE(SUM(delivery_earnings), 0) as total_earned,
                                 COALESCE(SUM(tip_amount), 0) as total_tips,
                                 COUNT(*) as total_trips 
                          FROM orders WHERE delivery_partner_id = ? AND order_status = 'delivered'");
$totStmt->execute([$riderId]);
$stats = $totStmt->fetch();
?>

<div style="padding: 30px 0 60px;">
    <div class="container" style="max-width: 900px;">
        <h1 style="font-family: 'Playfair Display', serif; font-size: 28px; color: #1E293B; margin: 0 0 24px;">
            Earnings & Delivery Trips 💰
        </h1>

        <!-- KPI Breakdown -->
        <div class="dashboard-grid">
            <div class="stat-kpi-card">
                <span class="kpi-title">Total Trip Payouts</span>
                <span class="kpi-value" style="color: #059669;"><?php echo format_price($stats['total_earned']); ?></span>
                <span class="kpi-sub">Includes base pay & per-km bonuses</span>
            </div>

            <div class="stat-kpi-card gold">
                <span class="kpi-title">Tips Received</span>
                <span class="kpi-value" style="color: #D97706;"><?php echo format_price($stats['total_tips']); ?></span>
                <span class="kpi-sub">100% direct customer appreciation</span>
            </div>

            <div class="stat-kpi-card blue">
                <span class="kpi-title">Completed Trips</span>
                <span class="kpi-value" style="color: #2563EB;"><?php echo (int)$stats['total_trips']; ?></span>
                <span class="kpi-sub">Successfully handed over</span>
            </div>
        </div>

        <!-- Trips History Table -->
        <div style="background: #FFFFFF; border-radius: 16px; border: 1px solid var(--light-border); padding: 24px; box-shadow: var(--shadow-sm); margin-top: 24px;">
            <h3 style="font-family: 'Playfair Display', serif; font-size: 20px; color: #1E293B; margin: 0 0 16px;">
                Completed Delivery Trips
            </h3>

            <div class="table-responsive">
                <table class="zayka-table">
                    <thead>
                        <tr>
                            <th>Order #</th>
                            <th>Restaurant</th>
                            <th>Delivered At</th>
                            <th>Tip</th>
                            <th>Trip Payout</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($completedTrips)): ?>
                            <?php foreach ($completedTrips as $trip): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($trip['order_number']); ?></strong></td>
                                <td><?php echo htmlspecialchars($trip['rest_name']); ?></td>
                                <td style="font-size: 12px; color: #666;">
                                    <?php echo date('d M Y, h:i A', strtotime($trip['updated_at'])); ?>
                                </td>
                                <td>
                                    <?php if ($trip['tip_amount'] > 0): ?>
                                        <span style="color: #D97706; font-weight: bold;">+<?php echo format_price($trip['tip_amount']); ?></span>
                                    <?php else: ?>
                                        <span style="color: #999;">-</span>
                                    <?php endif; ?>
                                </td>
                                <td style="font-weight: 700; color: #059669;">
                                    <?php echo format_price($trip['delivery_earnings']); ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" style="text-align: center; color: #888; padding: 30px;">
                                    No completed trips yet. Start accepting orders to build your earnings!
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

<?php include 'includes/footer.php'; ?>
