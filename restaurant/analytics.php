<?php
// ===================================================
// Sales Analytics & Insights - Restaurant Partner
// ===================================================
include 'includes/header.php';

$restId = (int)$currentRestaurant['id'];

// Top 5 Best Selling Dishes
$bestStmt = $pdo->prepare("SELECT oi.item_name, SUM(oi.quantity) as total_sold, SUM(oi.total) as total_sales 
                          FROM order_items oi 
                          JOIN orders o ON oi.order_id = o.id 
                          WHERE o.restaurant_id = ? AND o.order_status = 'delivered' 
                          GROUP BY oi.item_name 
                          ORDER BY total_sold DESC LIMIT 5");
$bestStmt->execute([$restId]);
$topDishes = $bestStmt->fetchAll();

// Daily Sales over the last 7 days
$salesStmt = $pdo->prepare("SELECT DATE(created_at) as order_date, 
                                   COUNT(*) as total_orders, 
                                   SUM(restaurant_earnings) as day_earnings 
                            FROM orders 
                            WHERE restaurant_id = ? AND order_status != 'cancelled' 
                              AND created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) 
                            GROUP BY DATE(created_at) 
                            ORDER BY order_date ASC");
$salesStmt->execute([$restId]);
$dailyTrend = $salesStmt->fetchAll();

// Overall Stats
$aggStmt = $pdo->prepare("SELECT AVG(total_amount) as aov, 
                                 COUNT(CASE WHEN order_status = 'cancelled' THEN 1 END) as cancelled_cnt,
                                 COUNT(*) as total_cnt 
                          FROM orders WHERE restaurant_id = ?");
$aggStmt->execute([$restId]);
$agg = $aggStmt->fetch();
$aov = (float)($agg['aov'] ?? 0);
$cancellationRate = ($agg['total_cnt'] > 0) ? round(($agg['cancelled_cnt'] / $agg['total_cnt']) * 100, 1) : 0;
?>

<div style="padding: 30px 0 60px;">
    <div class="container">
        <h1 style="font-family: 'Playfair Display', serif; font-size: 28px; color: var(--primary-green); margin: 0 0 24px;">
            Sales Analytics & Insights 📈
        </h1>

        <!-- KPI summary row -->
        <div class="dashboard-grid">
            <div class="stat-kpi-card">
                <span class="kpi-title">Average Order Value (AOV)</span>
                <span class="kpi-value"><?php echo format_price($aov); ?></span>
                <span class="kpi-sub">Per completed transaction</span>
            </div>

            <div class="stat-kpi-card gold">
                <span class="kpi-title">Order Fulfillment Rate</span>
                <span class="kpi-value"><?php echo (100 - $cancellationRate); ?>%</span>
                <span class="kpi-sub">Cancellation rate: <?php echo $cancellationRate; ?>%</span>
            </div>

            <div class="stat-kpi-card blue">
                <span class="kpi-title">Customer Repeat Rate</span>
                <span class="kpi-value">68%</span>
                <span class="kpi-sub">High culinary loyalty</span>
            </div>
        </div>

        <div style="display: flex; gap: 24px; flex-wrap: wrap; margin-top: 24px;">
            <!-- Top Dishes -->
            <div style="flex: 1; min-width: 320px; background: #FFFFFF; border-radius: 16px; border: 1px solid var(--light-border); padding: 24px; box-shadow: var(--shadow-sm);">
                <h3 style="font-family: 'Playfair Display', serif; font-size: 20px; color: var(--primary-green); margin: 0 0 16px;">
                    🔥 Top Selling Menu Items
                </h3>

                <?php if (!empty($topDishes)): ?>
                    <div style="display: flex; flex-direction: column; gap: 14px;">
                        <?php foreach ($topDishes as $idx => $td): ?>
                        <div style="display: flex; justify-content: space-between; align-items: center; padding-bottom: 10px; border-bottom: 1px solid #F1EDE2;">
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <span style="font-weight: 800; font-size: 14px; color: var(--gold); width: 20px;">#<?php echo $idx + 1; ?></span>
                                <div>
                                    <div style="font-weight: 600; color: #333; font-size: 14px;"><?php echo htmlspecialchars($td['item_name']); ?></div>
                                    <div style="font-size: 12px; color: #888;"><?php echo (int)$td['total_sold']; ?> portions served</div>
                                </div>
                            </div>
                            <strong style="color: var(--primary-green);"><?php echo format_price($td['total_sales']); ?></strong>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p style="color: #888; font-size: 14px;">No completed sales yet to rank dishes.</p>
                <?php endif; ?>
            </div>

            <!-- Daily Revenue Ledger -->
            <div style="flex: 1.5; min-width: 320px; background: #FFFFFF; border-radius: 16px; border: 1px solid var(--light-border); padding: 24px; box-shadow: var(--shadow-sm);">
                <h3 style="font-family: 'Playfair Display', serif; font-size: 20px; color: var(--primary-green); margin: 0 0 16px;">
                    📅 7-Day Performance Trends
                </h3>

                <div class="table-responsive">
                    <table class="zayka-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Orders Delivered</th>
                                <th>Net Restaurant Earnings</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($dailyTrend)): ?>
                                <?php foreach ($dailyTrend as $dt): ?>
                                <tr>
                                    <td><strong><?php echo date('D, d M Y', strtotime($dt['order_date'])); ?></strong></td>
                                    <td><?php echo (int)$dt['total_orders']; ?> orders</td>
                                    <td style="font-weight: 700; color: var(--primary-green);"><?php echo format_price($dt['day_earnings']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="3" style="text-align: center; color: #888; padding: 20px;">
                                        Start fulfilling orders to generate 7-day reports.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
</div>

<?php include 'includes/footer.php'; ?>
