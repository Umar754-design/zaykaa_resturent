<?php
// ===================================================
// Admin Platform Control Center Dashboard
// Business Analytics, Revenue Trends & Operations HQ
// ===================================================
include 'includes/admin-header.php';

// 1. Calculate Comprehensive Real DB Metrics
// Total Platform Gross Revenue & Commission
$revStmt = $pdo->query("SELECT 
    COALESCE(SUM(total_amount), 0) as total_gross_revenue,
    COALESCE(SUM(platform_commission), 0) as total_commission,
    COALESCE(SUM(delivery_fee), 0) as total_delivery_fees,
    COALESCE(SUM(coupon_discount), 0) as total_coupon_discounts,
    COALESCE(SUM(CASE WHEN payment_status = 'refunded' THEN total_amount ELSE 0 END), 0) as total_refunds,
    COUNT(*) as total_orders,
    COUNT(CASE WHEN DATE(created_at) = CURDATE() THEN 1 END) as today_orders,
    COUNT(CASE WHEN order_status IN ('placed', 'accepted', 'preparing', 'ready_for_pickup', 'picked_up', 'out_for_delivery') THEN 1 END) as pending_orders,
    COUNT(CASE WHEN order_status = 'delivered' THEN 1 END) as delivered_orders,
    COUNT(CASE WHEN order_status = 'cancelled' THEN 1 END) as cancelled_orders
FROM orders");
$stats = $revStmt->fetch();

// Entities Counts
$custCount = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'customer'")->fetchColumn();
$restCount = (int)$pdo->query("SELECT COUNT(*) FROM restaurants WHERE is_approved = 1")->fetchColumn();
$pendingRestCount = (int)$pdo->query("SELECT COUNT(*) FROM restaurants WHERE is_approved = 0")->fetchColumn();
$riderCount = (int)$pdo->query("SELECT COUNT(*) FROM delivery_partners WHERE kyc_status = 'approved'")->fetchColumn();
$onlineRiders = (int)$pdo->query("SELECT COUNT(*) FROM delivery_partners WHERE is_online = 1")->fetchColumn();

// Net Platform Profit: Commission
$netPlatformProfit = (float)$stats['total_commission'];
$aov = ($stats['total_orders'] > 0) ? ((float)$stats['total_gross_revenue'] / (int)$stats['total_orders']) : 0;

// ---------------------------------------------------
// 2. Business Analytics & Graph Datasets
// ---------------------------------------------------

// A. 7-Day Trend (Daily)
$sevenDays = [];
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $sevenDays[$d] = [
        'date' => $d,
        'label' => date('d M', strtotime($d)),
        'revenue' => 0.0,
        'commission' => 0.0,
        'orders' => 0
    ];
}
$stmt7 = $pdo->query("
    SELECT DATE(created_at) as odate,
           COALESCE(SUM(total_amount), 0) as rev,
           COALESCE(SUM(platform_commission), 0) as comm,
           COUNT(*) as cnt
    FROM orders
    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
    GROUP BY DATE(created_at)
");
while ($r = $stmt7->fetch(PDO::FETCH_ASSOC)) {
    if (isset($sevenDays[$r['odate']])) {
        $sevenDays[$r['odate']]['revenue'] = round((float)$r['rev'], 2);
        $sevenDays[$r['odate']]['commission'] = round((float)$r['comm'], 2);
        $sevenDays[$r['odate']]['orders'] = (int)$r['cnt'];
    }
}
$data7Days = array_values($sevenDays);

// B. 30-Day Trend (Daily)
$thirtyDays = [];
for ($i = 29; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $thirtyDays[$d] = [
        'date' => $d,
        'label' => date('d M', strtotime($d)),
        'revenue' => 0.0,
        'commission' => 0.0,
        'orders' => 0
    ];
}
$stmt30 = $pdo->query("
    SELECT DATE(created_at) as odate,
           COALESCE(SUM(total_amount), 0) as rev,
           COALESCE(SUM(platform_commission), 0) as comm,
           COUNT(*) as cnt
    FROM orders
    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)
    GROUP BY DATE(created_at)
");
while ($r = $stmt30->fetch(PDO::FETCH_ASSOC)) {
    if (isset($thirtyDays[$r['odate']])) {
        $thirtyDays[$r['odate']]['revenue'] = round((float)$r['rev'], 2);
        $thirtyDays[$r['odate']]['commission'] = round((float)$r['comm'], 2);
        $thirtyDays[$r['odate']]['orders'] = (int)$r['cnt'];
    }
}
$data30Days = array_values($thirtyDays);

// C. Yearly Trend (12 Months of Current Year)
$months = [];
$currentYear = date('Y');
for ($m = 1; $m <= 12; $m++) {
    $mKey = str_pad($m, 2, '0', STR_PAD_LEFT);
    $label = date('M', mktime(0, 0, 0, $m, 1, (int)$currentYear));
    $months[$mKey] = [
        'month' => $mKey,
        'label' => $label,
        'revenue' => 0.0,
        'commission' => 0.0,
        'orders' => 0
    ];
}
$stmtYear = $pdo->query("
    SELECT DATE_FORMAT(created_at, '%m') as omonth,
           COALESCE(SUM(total_amount), 0) as rev,
           COALESCE(SUM(platform_commission), 0) as comm,
           COUNT(*) as cnt
    FROM orders
    WHERE YEAR(created_at) = YEAR(CURDATE())
    GROUP BY DATE_FORMAT(created_at, '%m')
");
while ($r = $stmtYear->fetch(PDO::FETCH_ASSOC)) {
    if (isset($months[$r['omonth']])) {
        $months[$r['omonth']]['revenue'] = round((float)$r['rev'], 2);
        $months[$r['omonth']]['commission'] = round((float)$r['comm'], 2);
        $months[$r['omonth']]['orders'] = (int)$r['cnt'];
    }
}
$dataYearly = array_values($months);

// D. Category-wise Revenue Breakdown
$catRaw = $pdo->query("
    SELECT 
        COALESCE(c.name, 'Zaykaa Specials') as cat_name,
        COALESCE(SUM(oi.total), 0) as total_sales,
        COALESCE(SUM(oi.quantity), 0) as total_qty
    FROM order_items oi
    JOIN menu_items m ON oi.item_id = m.id
    LEFT JOIN categories c ON m.category_id = c.id
    GROUP BY c.id, c.name
    ORDER BY total_sales DESC
    LIMIT 6
")->fetchAll(PDO::FETCH_ASSOC);

$catLabels = [];
$catSales = [];
$totalCategorySales = 0;
foreach ($catRaw as $crow) {
    $catLabels[] = $crow['cat_name'];
    $catSales[] = (float)$crow['total_sales'];
    $totalCategorySales += (float)$crow['total_sales'];
}

// E. Order Status Breakdown
$statusCounts = $pdo->query("
    SELECT order_status, COUNT(*) as cnt
    FROM orders
    GROUP BY order_status
")->fetchAll(PDO::FETCH_KEY_PAIR);

$deliveredCount = (int)($statusCounts['delivered'] ?? 0);
$activeCount = (int)($stats['pending_orders'] ?? 0);
$cancelledCount = (int)($statusCounts['cancelled'] ?? 0);
$totalOrdersCount = (int)$stats['total_orders'];
$fulfillmentRate = ($totalOrdersCount > 0) ? round(($deliveredCount / $totalOrdersCount) * 100, 1) : 100;

// F. Top 5 Bestselling Dishes
$topDishes = $pdo->query("
    SELECT 
        oi.item_name,
        COALESCE(SUM(oi.total), 0) as total_revenue,
        COALESCE(SUM(oi.quantity), 0) as total_qty
    FROM order_items oi
    GROUP BY oi.item_id, oi.item_name
    ORDER BY total_revenue DESC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

// Live Active Orders (Latest 8 across all restaurants)
$liveStmt = $pdo->query("SELECT o.*, r.name as rest_name, u.name as customer_name, dp.vehicle_number 
                         FROM orders o 
                         JOIN restaurants r ON o.restaurant_id = r.id 
                         JOIN users u ON o.user_id = u.id 
                         LEFT JOIN delivery_partners dp ON o.delivery_partner_id = dp.id 
                         ORDER BY o.created_at DESC LIMIT 8");
$liveOrders = $liveStmt->fetchAll();
?>

<style>
/* Business Analytics & Chart Dashboard Styles */
.analytics-card {
    background: #FFFFFF;
    border-radius: 16px;
    border: 1px solid var(--light-border);
    padding: 24px;
    box-shadow: var(--shadow-sm);
    margin-bottom: 24px;
    position: relative;
}

.analytics-card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 16px;
    margin-bottom: 20px;
    padding-bottom: 16px;
    border-bottom: 1px solid #F1EDE2;
}

.analytics-title-group h2 {
    font-family: 'Playfair Display', serif;
    font-size: 22px;
    color: #0B192C;
    margin: 0 0 4px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.analytics-title-group p {
    font-size: 13px;
    color: #64748B;
    margin: 0;
}

.chart-controls-group {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}

.btn-toggle-group {
    display: inline-flex;
    background: #F1F5F9;
    padding: 3px;
    border-radius: 10px;
    border: 1px solid #E2E8F0;
}

.chart-toggle-btn {
    padding: 6px 14px;
    font-size: 12px;
    font-weight: 600;
    border-radius: 8px;
    border: none;
    background: transparent;
    color: #475569;
    cursor: pointer;
    transition: all 0.2s ease;
}

.chart-toggle-btn.active {
    background: #0B192C;
    color: #FFFFFF;
    box-shadow: 0 2px 6px rgba(11, 25, 44, 0.2);
}

.metric-pill-btn {
    padding: 6px 14px;
    font-size: 12px;
    font-weight: 600;
    border-radius: 20px;
    border: 1px solid #CBD5E1;
    background: #FFFFFF;
    color: #334155;
    cursor: pointer;
    transition: all 0.2s ease;
}

.metric-pill-btn.active {
    background: #059669;
    color: #FFFFFF;
    border-color: #059669;
    box-shadow: 0 2px 6px rgba(5, 150, 105, 0.25);
}

.chart-summary-strip {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 14px;
    margin-bottom: 20px;
}

.chart-mini-stat {
    background: #F8FAFC;
    border: 1px solid #E2E8F0;
    border-radius: 12px;
    padding: 12px 16px;
    display: flex;
    flex-direction: column;
}

.chart-mini-stat .mini-label {
    font-size: 11px;
    font-weight: 600;
    color: #64748B;
    text-transform: uppercase;
    letter-spacing: 0.4px;
    margin-bottom: 4px;
}

.chart-mini-stat .mini-val {
    font-size: 20px;
    font-weight: 700;
    color: #0B192C;
}

.chart-canvas-container {
    position: relative;
    width: 100%;
    height: 350px;
}

.charts-grid-2col {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 24px;
    margin-bottom: 24px;
}

@media (max-width: 900px) {
    .charts-grid-2col {
        grid-template-columns: 1fr;
    }
}

.donut-legend-list {
    margin-top: 18px;
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.donut-legend-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 13px;
    padding: 6px 10px;
    background: #F8FAFC;
    border-radius: 8px;
}

.donut-legend-left {
    display: flex;
    align-items: center;
    gap: 8px;
    font-weight: 500;
    color: #334155;
}

.donut-color-dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    display: inline-block;
}

.donut-legend-right {
    font-weight: 700;
    color: #0B192C;
}

.fulfillment-center-badge {
    text-align: center;
    margin-top: 14px;
    padding: 10px;
    background: #ECFDF5;
    border-radius: 10px;
    border: 1px solid #A7F3D0;
}
</style>

<div style="padding: 30px 0 60px;">
    <div class="container">
        
        <!-- Dashboard Top Header -->
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 15px;">
            <div>
                <h1 style="font-family: 'Playfair Display', serif; font-size: 28px; color: #0B192C; margin: 0 0 4px;">
                    Platform Overview & Executive Control
                </h1>
                <p style="color: #64748B; font-size: 14px; margin: 0;">
                    Real-time aggregated metrics across all restaurants, riders, and customers
                </p>
            </div>
            
            <div style="display: flex; gap: 10px;">
                <a href="orders.php" style="padding: 10px 20px; background: #0B192C; color: #fff; border-radius: 20px; font-weight: 700; text-decoration: none; font-size: 13px;">
                    ⚡ Master Orders Console
                </a>
                <a href="finance.php" style="padding: 10px 20px; background: #059669; color: #fff; border-radius: 20px; font-weight: 700; text-decoration: none; font-size: 13px;">
                    💰 Financial Balance Sheet
                </a>
            </div>
        </div>

        <!-- KPI Grid 1: Revenue & Financials -->
        <div class="dashboard-grid">
            <div class="stat-kpi-card gold">
                <span class="kpi-title">Platform Gross GMV</span>
                <span class="kpi-value"><?php echo format_price($stats['total_gross_revenue']); ?></span>
                <span class="kpi-sub">Total food volume transacted</span>
            </div>

            <div class="stat-kpi-card" style="border-left-color: #059669;">
                <span class="kpi-title">Net Platform Commission</span>
                <span class="kpi-value" style="color: #059669;"><?php echo format_price($netPlatformProfit); ?></span>
                <span class="kpi-sub">Average 15% commission retained</span>
            </div>

            <div class="stat-kpi-card blue">
                <span class="kpi-title">Total Orders Placed</span>
                <span class="kpi-value"><?php echo (int)$stats['total_orders']; ?></span>
                <span class="kpi-sub">Today's orders: <strong><?php echo (int)$stats['today_orders']; ?></strong></span>
            </div>

            <div class="stat-kpi-card" style="border-left-color: #8B5CF6;">
                <span class="kpi-title">Average Order Value (AOV)</span>
                <span class="kpi-value" style="color: #8B5CF6;"><?php echo format_price($aov); ?></span>
                <span class="kpi-sub">Basket size per successful checkout</span>
            </div>
        </div>

        <!-- KPI Grid 2: Platform Community & Fleet -->
        <div class="dashboard-grid" style="margin-bottom: 24px;">
            <div class="stat-kpi-card">
                <span class="kpi-title">Registered Customers</span>
                <span class="kpi-value"><?php echo $custCount; ?></span>
                <span class="kpi-sub">Active dining accounts</span>
            </div>

            <div class="stat-kpi-card">
                <span class="kpi-title">Partner Restaurants</span>
                <span class="kpi-value"><?php echo $restCount; ?></span>
                <span class="kpi-sub"><?php echo $pendingRestCount; ?> pending approval</span>
            </div>

            <div class="stat-kpi-card">
                <span class="kpi-title">Delivery Fleet</span>
                <span class="kpi-value"><?php echo $riderCount; ?></span>
                <span class="kpi-sub"><strong style="color: #059669;"><?php echo $onlineRiders; ?> riders</strong> online now</span>
            </div>

            <div class="stat-kpi-card" style="border-left-color: #DC2626;">
                <span class="kpi-title">Total Refunds & Discounts</span>
                <span class="kpi-value" style="color: #DC2626;"><?php echo format_price($stats['total_refunds'] + $stats['total_coupon_discounts']); ?></span>
                <span class="kpi-sub">Refunds: <?php echo format_price($stats['total_refunds']); ?></span>
            </div>
        </div>

        <!-- ===================================================
             MAIN BUSINESS PERFORMANCE & REVENUE GRAPH
             =================================================== -->
        <div class="analytics-card">
            <div class="analytics-card-header">
                <div class="analytics-title-group">
                    <h2>
                        <span>📈 Business Performance & Revenue Analytics</span>
                    </h2>
                    <p>Track gross transaction volume, order velocity, and platform profits in real-time</p>
                </div>

                <div class="chart-controls-group">
                    <!-- Metric Switcher -->
                    <div style="display: flex; gap: 6px;">
                        <button type="button" class="metric-pill-btn active" id="btnMetricRev" onclick="switchMetric('revenue')">Gross Revenue (₹)</button>
                        <button type="button" class="metric-pill-btn" id="btnMetricOrd" onclick="switchMetric('orders')">Order Volume</button>
                        <button type="button" class="metric-pill-btn" id="btnMetricComm" onclick="switchMetric('commission')">Net Commission (₹)</button>
                    </div>

                    <!-- Timeframe Filter Buttons -->
                    <div class="btn-toggle-group">
                        <button type="button" class="chart-toggle-btn active" id="btnTf7" onclick="switchTimeframe('7d')">Last 7 Days</button>
                        <button type="button" class="chart-toggle-btn" id="btnTf30" onclick="switchTimeframe('30d')">Last 30 Days</button>
                        <button type="button" class="chart-toggle-btn" id="btnTfYear" onclick="switchTimeframe('year')">Yearly (<?php echo $currentYear; ?>)</button>
                    </div>
                </div>
            </div>

            <!-- Dynamic Graph Header Metrics Strip -->
            <div class="chart-summary-strip">
                <div class="chart-mini-stat">
                    <span class="mini-label" id="summaryLabel1">Selected Period Revenue</span>
                    <span class="mini-val" id="summaryVal1" style="color: #059669;">₹0.00</span>
                </div>
                <div class="chart-mini-stat">
                    <span class="mini-label">Total Orders in Period</span>
                    <span class="mini-val" id="summaryVal2" style="color: #0B192C;">0 Orders</span>
                </div>
                <div class="chart-mini-stat">
                    <span class="mini-label">Period Avg Daily Sales</span>
                    <span class="mini-val" id="summaryVal3" style="color: #C9A24D;">₹0.00 / day</span>
                </div>
                <div class="chart-mini-stat">
                    <span class="mini-label">Platform Commission in Period</span>
                    <span class="mini-val" id="summaryVal4" style="color: #7C3AED;">₹0.00</span>
                </div>
            </div>

            <!-- Chart Canvas -->
            <div class="chart-canvas-container">
                <canvas id="businessTimelineChart"></canvas>
            </div>
        </div>

        <!-- ===================================================
             SECONDARY 2-COLUMN GRAPHS (CATEGORY & FULFILLMENT)
             =================================================== -->
        <div class="charts-grid-2col">
            
            <!-- Category Revenue Share (Doughnut Chart) -->
            <div class="analytics-card" style="margin-bottom: 0;">
                <div class="analytics-card-header">
                    <div class="analytics-title-group">
                        <h2 style="font-size: 19px;">🍽️ Sales by Food Category</h2>
                        <p>Revenue distribution across cuisines and meal types</p>
                    </div>
                </div>

                <div style="position: relative; height: 260px;">
                    <canvas id="categoryDonutChart"></canvas>
                </div>

                <!-- Category Legend List -->
                <div class="donut-legend-list">
                    <?php 
                    $catColors = ['#D97706', '#059669', '#DC2626', '#8B5CF6', '#0284C7', '#64748B'];
                    foreach ($catRaw as $cidx => $crow): 
                        $pct = ($totalCategorySales > 0) ? round(($crow['total_sales'] / $totalCategorySales) * 100, 1) : 0;
                        $color = $catColors[$cidx % count($catColors)];
                    ?>
                        <div class="donut-legend-item">
                            <div class="donut-legend-left">
                                <span class="donut-color-dot" style="background: <?php echo $color; ?>;"></span>
                                <span><?php echo htmlspecialchars($crow['cat_name']); ?></span>
                                <span style="font-size: 11px; color: #888;">(<?php echo $crow['total_qty']; ?> sold)</span>
                            </div>
                            <div class="donut-legend-right">
                                <span><?php echo format_price($crow['total_sales']); ?></span>
                                <span style="font-size: 11px; color: #059669; margin-left: 6px;">(<?php echo $pct; ?>%)</span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Order Status & Operational Fulfillment -->
            <div class="analytics-card" style="margin-bottom: 0;">
                <div class="analytics-card-header">
                    <div class="analytics-title-group">
                        <h2 style="font-size: 19px;">⚡ Fulfillment & Order Health</h2>
                        <p>Kitchen prep, transit completion, and cancellation rates</p>
                    </div>
                </div>

                <div style="position: relative; height: 260px;">
                    <canvas id="orderStatusChart"></canvas>
                </div>

                <div class="fulfillment-center-badge">
                    <div style="font-size: 12px; font-weight: 700; color: #047857; text-transform: uppercase;">
                        Operational Success Rate
                    </div>
                    <div style="font-size: 24px; font-weight: 800; color: #065F46;">
                        <?php echo $fulfillmentRate; ?>%
                    </div>
                    <div style="font-size: 11px; color: #047857;">
                        <?php echo $deliveredCount; ?> successfully delivered out of <?php echo $totalOrdersCount; ?> total orders
                    </div>
                </div>

                <div class="donut-legend-list" style="margin-top: 12px;">
                    <div class="donut-legend-item">
                        <div class="donut-legend-left">
                            <span class="donut-color-dot" style="background: #10B981;"></span>
                            <span>Delivered Successfully</span>
                        </div>
                        <span class="donut-legend-right"><?php echo $deliveredCount; ?></span>
                    </div>
                    <div class="donut-legend-item">
                        <div class="donut-legend-left">
                            <span class="donut-color-dot" style="background: #3B82F6;"></span>
                            <span>Active / In Kitchen / In Transit</span>
                        </div>
                        <span class="donut-legend-right"><?php echo $activeCount; ?></span>
                    </div>
                    <div class="donut-legend-item">
                        <div class="donut-legend-left">
                            <span class="donut-color-dot" style="background: #EF4444;"></span>
                            <span>Cancelled / Failed</span>
                        </div>
                        <span class="donut-legend-right"><?php echo $cancelledCount; ?></span>
                    </div>
                </div>
            </div>

        </div>

        <!-- ===================================================
             TOP 5 BESTSELLING DISHES (HORIZONTAL BAR CHART)
             =================================================== -->
        <div class="analytics-card" style="margin-top: 24px;">
            <div class="analytics-card-header">
                <div class="analytics-title-group">
                    <h2 style="font-size: 20px;">🏆 Top 5 Highest Grossing Dishes</h2>
                    <p>Menu items generating the highest sales revenue on the platform</p>
                </div>
                <a href="menu.php" style="font-size: 13px; color: #0284C7; font-weight: 600; text-decoration: none;">Manage Menu & Serving Portions →</a>
            </div>

            <div style="position: relative; height: 260px;">
                <canvas id="topDishesChart"></canvas>
            </div>
        </div>

        <!-- ===================================================
             MASTER ORDERS LIVE CONTROL TABLE
             =================================================== -->
        <div style="background: #FFFFFF; border-radius: 16px; border: 1px solid var(--light-border); padding: 24px; box-shadow: var(--shadow-sm); margin-top: 24px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 18px;">
                <div>
                    <h3 style="font-family: 'Playfair Display', serif; font-size: 20px; color: #0B192C; margin: 0 0 4px;">
                        Live Orders Stream (Across All Restaurants)
                    </h3>
                    <p style="font-size: 13px; color: #64748B; margin: 0;">Instant centralized view of real-time orders</p>
                </div>
                <a href="orders.php" style="font-size: 13px; color: #0284C7; font-weight: 600; text-decoration: none;">View All Master Orders →</a>
            </div>

            <div class="table-responsive">
                <table class="zayka-table">
                    <thead>
                        <tr>
                            <th>Order #</th>
                            <th>Restaurant</th>
                            <th>Customer</th>
                            <th>Time</th>
                            <th>Total</th>
                            <th>Status</th>
                            <th>Payment</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($liveOrders)): ?>
                            <?php foreach ($liveOrders as $ord): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($ord['order_number']); ?></strong></td>
                                <td>
                                    <strong style="color: var(--primary-green);"><?php echo htmlspecialchars($ord['rest_name']); ?></strong>
                                </td>
                                <td><?php echo htmlspecialchars($ord['customer_name']); ?></td>
                                <td style="font-size: 12px; color: #64748B;"><?php echo date('d M, h:i A', strtotime($ord['created_at'])); ?></td>
                                <td style="font-weight: 700; color: #059669;"><?php echo format_price($ord['total_amount']); ?></td>
                                <td><?php echo get_status_badge($ord['order_status']); ?></td>
                                <td style="font-size: 12px;">
                                    <strong><?php echo strtoupper($ord['payment_method']); ?></strong> (<?php echo ucfirst($ord['payment_status']); ?>)
                                </td>
                                <td>
                                    <a href="../order-status.php?id=<?php echo $ord['id']; ?>" target="_blank" 
                                       style="padding: 5px 12px; background: #F1F5F9; border: 1px solid #CBD5E1; border-radius: 6px; font-size: 12px; font-weight: 600; text-decoration: none; color: #334155;">
                                        Radar Track
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" style="text-align: center; color: #888; padding: 30px;">
                                    No orders placed yet.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

<!-- ===================================================
     CHART.JS INTERACTIVE SCRIPTS & DATA BINDING
     =================================================== -->
<script>
// Aggregated Chart Datasets passed from PHP
const dataSets = {
    '7d': <?php echo json_encode($data7Days); ?>,
    '30d': <?php echo json_encode($data30Days); ?>,
    'year': <?php echo json_encode($dataYearly); ?>
};

const categoryData = {
    labels: <?php echo json_encode($catLabels); ?>,
    values: <?php echo json_encode($catSales); ?>
};

const orderStatusData = {
    labels: ['Delivered', 'Active / In-Transit', 'Cancelled'],
    values: [<?php echo $deliveredCount; ?>, <?php echo $activeCount; ?>, <?php echo $cancelledCount; ?>]
};

const topDishesData = {
    labels: <?php echo json_encode(array_column($topDishes, 'item_name')); ?>,
    revenues: <?php echo json_encode(array_map('floatval', array_column($topDishes, 'total_revenue'))); ?>,
    quantities: <?php echo json_encode(array_map('intval', array_column($topDishes, 'total_qty'))); ?>
};

// State Variables
let currentTimeframe = '7d';
let currentMetric = 'revenue'; // 'revenue', 'orders', 'commission'
let timelineChart = null;

// Currency Formatter
function formatINR(val) {
    return '₹' + Number(val).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

// ---------------------------------------------------
// 1. Initialize Main Timeline Business Chart
// ---------------------------------------------------
function initTimelineChart() {
    const ctx = document.getElementById('businessTimelineChart');
    if (!ctx) return;

    const data = dataSets[currentTimeframe];
    const labels = data.map(d => d.label);
    const metricValues = data.map(d => d[currentMetric]);

    // Create gradient
    const chartCtx = ctx.getContext('2d');
    const gradient = chartCtx.createLinearGradient(0, 0, 0, 320);
    gradient.addColorStop(0, 'rgba(5, 150, 105, 0.28)');
    gradient.addColorStop(1, 'rgba(5, 150, 105, 0.00)');

    timelineChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: getMetricLabel(),
                data: metricValues,
                borderColor: '#059669',
                backgroundColor: gradient,
                borderWidth: 3,
                fill: true,
                tension: 0.35,
                pointBackgroundColor: '#059669',
                pointBorderColor: '#FFFFFF',
                pointBorderWidth: 2,
                pointRadius: 4,
                pointHoverRadius: 7
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#0B192C',
                    titleColor: '#FFFFFF',
                    bodyColor: '#FBBF24',
                    padding: 12,
                    displayColors: false,
                    callbacks: {
                        label: function(context) {
                            if (currentMetric === 'orders') {
                                return context.parsed.y + ' Orders Placed';
                            }
                            return formatINR(context.parsed.y);
                        }
                    }
                }
            },
            scales: {
                x: {
                    grid: { display: false },
                    ticks: { color: '#64748B', font: { family: 'Poppins', size: 11 } }
                },
                y: {
                    grid: { color: '#F1F5F9' },
                    ticks: {
                        color: '#64748B',
                        font: { family: 'Poppins', size: 11 },
                        callback: function(value) {
                            if (currentMetric === 'orders') return value;
                            return '₹' + (value >= 1000 ? (value / 1000) + 'k' : value);
                        }
                    }
                }
            }
        }
    });

    updateSummaryStrip();
}

function getMetricLabel() {
    if (currentMetric === 'revenue') return 'Gross Revenue (₹)';
    if (currentMetric === 'orders') return 'Orders Count';
    return 'Net Commission (₹)';
}

function updateSummaryStrip() {
    const data = dataSets[currentTimeframe];
    const totalRev = data.reduce((acc, cur) => acc + cur.revenue, 0);
    const totalOrders = data.reduce((acc, cur) => acc + cur.orders, 0);
    const totalComm = data.reduce((acc, cur) => acc + cur.commission, 0);
    const dayCount = data.length || 1;
    const avgDaily = totalRev / dayCount;

    document.getElementById('summaryVal1').textContent = formatINR(totalRev);
    document.getElementById('summaryVal2').textContent = totalOrders + ' Orders';
    document.getElementById('summaryVal3').textContent = formatINR(avgDaily) + ' / day';
    document.getElementById('summaryVal4').textContent = formatINR(totalComm);
}

function switchTimeframe(tf) {
    currentTimeframe = tf;

    document.querySelectorAll('.chart-toggle-btn').forEach(btn => btn.classList.remove('active'));
    if (tf === '7d') document.getElementById('btnTf7').classList.add('active');
    if (tf === '30d') document.getElementById('btnTf30').classList.add('active');
    if (tf === 'year') document.getElementById('btnTfYear').classList.add('active');

    const data = dataSets[tf];
    timelineChart.data.labels = data.map(d => d.label);
    timelineChart.data.datasets[0].data = data.map(d => d[currentMetric]);
    timelineChart.update();

    updateSummaryStrip();
}

function switchMetric(metric) {
    currentMetric = metric;

    document.querySelectorAll('.metric-pill-btn').forEach(btn => btn.classList.remove('active'));
    if (metric === 'revenue') document.getElementById('btnMetricRev').classList.add('active');
    if (metric === 'orders') document.getElementById('btnMetricOrd').classList.add('active');
    if (metric === 'commission') document.getElementById('btnMetricComm').classList.add('active');

    const data = dataSets[currentTimeframe];
    timelineChart.data.datasets[0].label = getMetricLabel();
    timelineChart.data.datasets[0].data = data.map(d => d[metric]);

    if (metric === 'commission') {
        timelineChart.data.datasets[0].borderColor = '#7C3AED';
        timelineChart.data.datasets[0].pointBackgroundColor = '#7C3AED';
    } else if (metric === 'orders') {
        timelineChart.data.datasets[0].borderColor = '#0284C7';
        timelineChart.data.datasets[0].pointBackgroundColor = '#0284C7';
    } else {
        timelineChart.data.datasets[0].borderColor = '#059669';
        timelineChart.data.datasets[0].pointBackgroundColor = '#059669';
    }

    timelineChart.update();
}

// ---------------------------------------------------
// 2. Category Doughnut Chart
// ---------------------------------------------------
function initCategoryChart() {
    const ctx = document.getElementById('categoryDonutChart');
    if (!ctx) return;

    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: categoryData.labels,
            datasets: [{
                data: categoryData.values,
                backgroundColor: ['#D97706', '#059669', '#DC2626', '#8B5CF6', '#0284C7', '#64748B'],
                borderWidth: 3,
                borderColor: '#FFFFFF',
                hoverOffset: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '72%',
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#0B192C',
                    padding: 12,
                    callbacks: {
                        label: function(context) {
                            return context.label + ': ' + formatINR(context.parsed);
                        }
                    }
                }
            }
        }
    });
}

// ---------------------------------------------------
// 3. Operational Fulfillment Doughnut Chart
// ---------------------------------------------------
function initOrderStatusChart() {
    const ctx = document.getElementById('orderStatusChart');
    if (!ctx) return;

    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: orderStatusData.labels,
            datasets: [{
                data: orderStatusData.values,
                backgroundColor: ['#10B981', '#3B82F6', '#EF4444'],
                borderWidth: 3,
                borderColor: '#FFFFFF',
                hoverOffset: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '72%',
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#0B192C',
                    padding: 12,
                    callbacks: {
                        label: function(context) {
                            return context.label + ': ' + context.parsed + ' orders';
                        }
                    }
                }
            }
        }
    });
}

// ---------------------------------------------------
// 4. Top 5 Bestselling Dishes (Horizontal Bar Chart)
// ---------------------------------------------------
function initTopDishesChart() {
    const ctx = document.getElementById('topDishesChart');
    if (!ctx) return;

    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: topDishesData.labels,
            datasets: [{
                label: 'Revenue (₹)',
                data: topDishesData.revenues,
                backgroundColor: '#C9A24D',
                borderRadius: 8,
                barThickness: 22
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#0B192C',
                    padding: 12,
                    callbacks: {
                        label: function(context) {
                            const idx = context.dataIndex;
                            const qty = topDishesData.quantities[idx] || 0;
                            return 'Revenue: ' + formatINR(context.parsed.x) + ' (' + qty + ' units sold)';
                        }
                    }
                }
            },
            scales: {
                x: {
                    grid: { color: '#F1F5F9' },
                    ticks: {
                        color: '#64748B',
                        font: { family: 'Poppins', size: 11 },
                        callback: function(value) {
                            return '₹' + (value >= 1000 ? (value / 1000) + 'k' : value);
                        }
                    }
                },
                y: {
                    grid: { display: false },
                    ticks: {
                        color: '#0B192C',
                        font: { family: 'Poppins', size: 12, weight: '600' }
                    }
                }
            }
        }
    });
}

// Initialize all charts when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    initTimelineChart();
    initCategoryChart();
    initOrderStatusChart();
    initTopDishesChart();
});
</script>

<?php include 'includes/admin-footer.php'; ?>
