<?php
// ===================================================
// Earnings & Payout Ledger - Restaurant Partner
// ===================================================
include 'includes/header.php';

$restId = (int)$currentRestaurant['id'];

// Financial totals from orders
$finStmt = $pdo->prepare("SELECT 
    COALESCE(SUM(subtotal + addons_total), 0) as gross_food_sales,
    COALESCE(SUM(platform_commission), 0) as total_platform_commission,
    COALESCE(SUM(restaurant_earnings), 0) as net_payable_earnings 
FROM orders WHERE restaurant_id = ? AND order_status = 'delivered'");
$finStmt->execute([$restId]);
$financials = $finStmt->fetch();

// Payout history
$pStmt = $pdo->prepare("SELECT * FROM payouts WHERE entity_type = 'restaurant' AND entity_id = ? ORDER BY created_at DESC");
$pStmt->execute([$restId]);
$payouts = $pStmt->fetchAll();

// Handle payout request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_payout'])) {
    $amount = (float)($_POST['amount'] ?? 0);
    $bankInfo = trim($_POST['bank_info'] ?? '');

    if ($amount > 500 && $bankInfo) {
        $ins = $pdo->prepare("INSERT INTO payouts (entity_type, entity_id, amount, status, notes) VALUES ('restaurant', ?, ?, 'pending', ?)");
        $ins->execute([$restId, $amount, "Bank Details: $bankInfo"]);
        set_flash('success', 'Payout request of ' . format_price($amount) . ' submitted to platform finance team.');
        header("Location: payouts.php");
        exit;
    } else {
        set_flash('error', 'Minimum payout request is ₹500 and bank details are required.');
    }
}
?>

<div style="padding: 30px 0 60px;">
    <div class="container" style="max-width: 900px;">
        <h1 style="font-family: 'Playfair Display', serif; font-size: 28px; color: var(--primary-green); margin: 0 0 24px;">
            Earnings & Payout Ledger 💰
        </h1>

        <!-- Financial Breakdown Cards -->
        <div class="dashboard-grid">
            <div class="stat-kpi-card">
                <span class="kpi-title">Gross Food Sales</span>
                <span class="kpi-value"><?php echo format_price($financials['gross_food_sales']); ?></span>
                <span class="kpi-sub">Total food value prepared</span>
            </div>

            <div class="stat-kpi-card" style="border-left-color: #DC2626;">
                <span class="kpi-title">Platform Commission (<?php echo (float)$currentRestaurant['commission_rate']; ?>%)</span>
                <span class="kpi-value" style="color: #DC2626;">-<?php echo format_price($financials['total_platform_commission']); ?></span>
                <span class="kpi-sub">Platform fee & server hosting</span>
            </div>

            <div class="stat-kpi-card gold">
                <span class="kpi-title">Net Restaurant Earnings</span>
                <span class="kpi-value" style="color: #046C4E;"><?php echo format_price($financials['net_payable_earnings']); ?></span>
                <span class="kpi-sub">Total earned from delivered meals</span>
            </div>
        </div>

        <div style="display: flex; gap: 24px; flex-wrap: wrap; margin-top: 24px;">
            <!-- Request Payout Card -->
            <div style="flex: 1; min-width: 280px; background: #fff; border-radius: 16px; border: 1px solid var(--light-border); padding: 24px; box-shadow: var(--shadow-sm);">
                <h3 style="font-family: 'Playfair Display', serif; font-size: 20px; color: var(--primary-green); margin: 0 0 16px;">
                    Request Direct Bank Transfer
                </h3>

                <form method="POST">
                    <input type="hidden" name="request_payout" value="1">
                    
                    <div style="margin-bottom: 14px;">
                        <label style="font-size: 13px; font-weight: 600; color: #444; display: block; margin-bottom: 4px;">Transfer Amount (₹) *</label>
                        <input type="number" step="50" name="amount" min="500" placeholder="Minimum ₹500" required 
                               style="width: 100%; padding: 10px; border: 1px solid var(--light-border); border-radius: 8px; font-size: 14px;">
                    </div>

                    <div style="margin-bottom: 18px;">
                        <label style="font-size: 13px; font-weight: 600; color: #444; display: block; margin-bottom: 4px;">Bank Account / UPI ID *</label>
                        <textarea name="bank_info" placeholder="Account Number, IFSC Code, Bank Name, or UPI ID" required 
                                  style="width: 100%; height: 75px; padding: 10px; border: 1px solid var(--light-border); border-radius: 8px; font-size: 13px;"></textarea>
                    </div>

                    <button type="submit" style="width: 100%; padding: 12px; background: var(--primary-green); color: #fff; border-radius: 20px; font-weight: 700; border: none; cursor: pointer;">
                        Submit Payout Request 💸
                    </button>
                </form>
            </div>

            <!-- Payout History -->
            <div style="flex: 1.5; min-width: 320px; background: #fff; border-radius: 16px; border: 1px solid var(--light-border); padding: 24px; box-shadow: var(--shadow-sm);">
                <h3 style="font-family: 'Playfair Display', serif; font-size: 20px; color: var(--primary-green); margin: 0 0 16px;">
                    Payout Requests History
                </h3>

                <?php if (!empty($payouts)): ?>
                    <div style="display: flex; flex-direction: column; gap: 12px;">
                        <?php foreach ($payouts as $p): ?>
                        <div style="display: flex; justify-content: space-between; align-items: center; padding-bottom: 12px; border-bottom: 1px solid #F1EDE2;">
                            <div>
                                <div style="font-weight: 700; color: #333; font-size: 15px;"><?php echo format_price($p['amount']); ?></div>
                                <div style="font-size: 12px; color: #777; margin-top: 2px;"><?php echo htmlspecialchars($p['notes']); ?></div>
                                <div style="font-size: 11px; color: #999;"><?php echo date('d M Y, h:i A', strtotime($p['created_at'])); ?></div>
                            </div>
                            <span style="font-size: 11px; font-weight: 700; padding: 3px 10px; border-radius: 12px; background: <?php echo ($p['status'] === 'processed') ? '#DEF7EC' : '#FEF3C7'; ?>; color: <?php echo ($p['status'] === 'processed') ? '#046C4E' : '#B45309'; ?>;">
                                <?php echo strtoupper($p['status']); ?>
                            </span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p style="color: #888; font-size: 14px; text-align: center; padding: 30px 0;">
                        No payout transfers requested yet.
                    </p>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>

<?php include 'includes/footer.php'; ?>
