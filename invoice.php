<?php
// ===================================================
// Printable / Downloadable Tax Invoice - Zayka Platform
// ===================================================
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

$user = current_user();
if (!$user) {
    die("Access denied. Please login.");
}

$orderId = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT o.*, r.name as rest_name, r.address as rest_address, r.city as rest_city, r.phone as rest_phone, r.email as rest_email,
                              u.name as customer_name, u.email as customer_email, u.phone as customer_phone
                       FROM orders o
                       JOIN restaurants r ON o.restaurant_id = r.id
                       JOIN users u ON o.user_id = u.id
                       WHERE o.id = ?");
$stmt->execute([$orderId]);
$order = $stmt->fetch();

if (!$order) {
    die("Invoice not found.");
}

if ($user['role'] === 'customer' && $order['user_id'] != $user['id']) {
    die("Unauthorized.");
}

// Fetch items
$itStmt = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ?");
$itStmt->execute([$orderId]);
$items = $itStmt->fetchAll();

$dest = json_decode($order['delivery_address_json'], true);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tax Invoice - <?php echo htmlspecialchars($order['order_number']); ?></title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; margin: 0; padding: 40px; color: #333; background: #fff; font-size: 14px; }
        .invoice-card { max-width: 800px; margin: 0 auto; border: 1px solid #ddd; padding: 40px; border-radius: 8px; }
        .header-row { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #063F2B; padding-bottom: 20px; margin-bottom: 30px; }
        .logo-text { font-size: 28px; font-weight: bold; color: #063F2B; }
        .invoice-table-wrap { width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; }
        .invoice-table { width: 100%; border-collapse: collapse; margin: 25px 0; min-width: 480px; }
        .invoice-table th { background: #F8F5EC; color: #063F2B; padding: 12px; text-align: left; border-bottom: 1px solid #ddd; font-size: 13px; }
        .invoice-table td { padding: 12px; border-bottom: 1px solid #eee; }
        .totals-table { width: 350px; margin-left: auto; border-collapse: collapse; }
        .totals-table td { padding: 8px 12px; text-align: right; }
        .totals-table tr.grand-total td { font-size: 16px; font-weight: bold; color: #063F2B; border-top: 2px solid #063F2B; }
        .print-btn { display: inline-block; padding: 10px 24px; background: #063F2B; color: #fff; text-decoration: none; border-radius: 6px; font-weight: bold; cursor: pointer; border: none; margin-bottom: 20px; }
        @media (max-width: 600px) {
            body { padding: 16px; }
            .invoice-card { padding: 16px; border: 1px solid #eee; }
            .header-row { flex-direction: column; gap: 16px; }
            .totals-table { width: 100%; margin-left: 0; }
        }
        @media print {
            .no-print { display: none !important; }
            body { padding: 0; }
            .invoice-card { border: none; padding: 0; }
        }
    </style>
</head>
<body>
    <div style="max-width: 800px; margin: 0 auto;" class="no-print">
        <button onclick="window.print()" class="print-btn">🖨️ Print / Save as PDF</button>
    </div>

    <div class="invoice-card">
        <div class="header-row">
            <div>
                <div class="logo-text">Zaykaa 🍃</div>
                <div style="color: #666; font-size: 12px; margin-top: 4px;">Food Delivery Platform</div>
                <div style="color: #888; font-size: 12px;">GSTIN: 20AAACZ1234F1Z5</div>
            </div>
            <div style="text-align: right;">
                <h2 style="margin: 0; color: #063F2B; font-size: 20px;">TAX INVOICE</h2>
                <div style="font-weight: bold; font-size: 15px; margin-top: 6px;">#<?php echo htmlspecialchars($order['order_number']); ?></div>
                <div style="color: #666; font-size: 12px; margin-top: 2px;">Date: <?php echo date('d M Y, h:i A', strtotime($order['created_at'])); ?></div>
            </div>
        </div>

        <div style="display: flex; justify-content: space-between; gap: 40px; margin-bottom: 25px;">
            <div style="flex: 1;">
                <div style="font-size: 11px; font-weight: bold; color: #888; text-transform: uppercase;">Sold By (Restaurant):</div>
                <div style="font-weight: bold; font-size: 15px; color: #063F2B; margin: 4px 0;"><?php echo htmlspecialchars($order['rest_name']); ?></div>
                <div style="color: #666; font-size: 12px; line-height: 1.4;">
                    <?php echo htmlspecialchars($order['rest_address'] . ', ' . $order['rest_city']); ?><br>
                    Phone: <?php echo htmlspecialchars($order['rest_phone']); ?>
                </div>
            </div>

            <div style="flex: 1; text-align: right;">
                <div style="font-size: 11px; font-weight: bold; color: #888; text-transform: uppercase;">Delivered To:</div>
                <div style="font-weight: bold; font-size: 15px; color: #333; margin: 4px 0;"><?php echo htmlspecialchars($order['customer_name']); ?></div>
                <div style="color: #666; font-size: 12px; line-height: 1.4;">
                    <?php echo htmlspecialchars($dest['address_line'] ?? ''); ?><br>
                    <?php echo htmlspecialchars(($dest['city'] ?? '') . ' - ' . ($dest['pincode'] ?? '')); ?><br>
                    Phone: <?php echo htmlspecialchars($order['customer_phone']); ?>
                </div>
            </div>
        </div>

        <div class="invoice-table-wrap">
            <table class="invoice-table">
                <thead>
                    <tr>
                        <th style="width: 50%;">Item Description</th>
                        <th style="text-align: center;">Qty</th>
                        <th style="text-align: right;">Unit Price</th>
                        <th style="text-align: right;">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $it): ?>
                    <tr>
                        <td>
                            <strong style="color: #063F2B;"><?php echo htmlspecialchars($it['item_name']); ?></strong>
                            <?php if (!empty($it['serving_size'])): ?>
                                <div style="font-size: 11px; color: #666; margin-top: 2px;">Serving: <?php echo htmlspecialchars($it['serving_size']); ?></div>
                            <?php endif; ?>
                        </td>
                        <td style="text-align: center;"><?php echo (int)$it['quantity']; ?></td>
                        <td style="text-align: right;"><?php echo format_price($it['price']); ?></td>
                        <td style="text-align: right; font-weight: bold;"><?php echo format_price($it['total']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <table class="totals-table">
            <tr>
                <td style="color: #666;">Item Subtotal:</td>
                <td><?php echo format_price($order['subtotal']); ?></td>
            </tr>
            <?php if ($order['coupon_discount'] > 0): ?>
            <tr>
                <td style="color: #16A34A;">Coupon Discount (<?php echo htmlspecialchars($order['coupon_code'] ?? ''); ?>):</td>
                <td style="color: #16A34A; font-weight: bold;">-<?php echo format_price($order['coupon_discount']); ?></td>
            </tr>
            <?php endif; ?>
            <tr>
                <td style="color: #666;">Taxes & GST (5%):</td>
                <td><?php echo format_price($order['tax_amount']); ?></td>
            </tr>
            <tr>
                <td style="color: #666;">Delivery Fee:</td>
                <td><?php echo format_price($order['delivery_fee']); ?></td>
            </tr>
            <tr>
                <td style="color: #666;">Platform Convenience Fee:</td>
                <td><?php echo format_price($order['platform_fee']); ?></td>
            </tr>
            <?php if ($order['tip_amount'] > 0): ?>
            <tr>
                <td style="color: #D97706;">Rider Tip:</td>
                <td><?php echo format_price($order['tip_amount']); ?></td>
            </tr>
            <?php endif; ?>
            <tr class="grand-total">
                <td>Total Amount Paid:</td>
                <td><?php echo format_price($order['total_amount']); ?></td>
            </tr>
        </table>

        <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee; display: flex; justify-content: space-between; font-size: 12px; color: #777;">
            <div>
                Payment Method: <strong><?php echo strtoupper($order['payment_method']); ?></strong> (<?php echo ucfirst($order['payment_status']); ?>)<br>
                <?php if ($order['payment_transaction_id']): ?>
                    Transaction ID: <?php echo htmlspecialchars($order['payment_transaction_id']); ?>
                <?php endif; ?>
            </div>
            <div style="text-align: right;">
                This is a computer-generated tax invoice.<br>
                Thank you for ordering with Zaykaa!
            </div>
        </div>
    </div>
</body>
</html>
