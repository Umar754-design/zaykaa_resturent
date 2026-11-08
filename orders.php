<?php
// ===================================================
// Customer Order History - Zayka Platform
// ===================================================
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

$user = current_user();
if (!$user) {
    header("Location: login.php?redirect=orders.php");
    exit;
}

// Fetch user orders
$stmt = $pdo->prepare("SELECT o.*, r.name as rest_name, r.logo as rest_logo, r.id as rest_id 
                       FROM orders o 
                       JOIN restaurants r ON o.restaurant_id = r.id 
                       WHERE o.user_id = ? 
                       ORDER BY o.created_at DESC");
$stmt->execute([$user['id']]);
$orders = $stmt->fetchAll();

include 'includes/header.php';
?>

<div style="background: #FAF8F2; min-height: 85vh; padding: 40px 0 80px;">
    <div class="container">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 15px;">
            <div>
                <h1 style="font-family: 'Playfair Display', serif; font-size: 32px; color: var(--primary-green); margin: 0 0 6px;">
                    My Orders 📦
                </h1>
                <p style="color: #666; margin: 0; font-size: 14px;">
                    Track live deliveries, reorder favourites, and download invoices
                </p>
            </div>
            <a href="menu.php" style="padding: 10px 22px; background: var(--primary-green); color: #fff; border-radius: 20px; font-weight: 600; text-decoration: none; font-size: 13px;">
                + View Menu / Order
            </a>
        </div>

        <?php if (!empty($orders)): ?>
            <div style="display: flex; flex-direction: column; gap: 20px;">
                <?php foreach ($orders as $ord): 
                    // Fetch items for this order
                    $itStmt = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ?");
                    $itStmt->execute([$ord['id']]);
                    $items = $itStmt->fetchAll();
                ?>
                <div style="background: #FFFFFF; border-radius: 16px; border: 1px solid var(--light-border); padding: 24px; box-shadow: var(--shadow-sm); display: flex; flex-direction: column; gap: 16px;">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 12px; border-bottom: 1px solid #F1EDE2; padding-bottom: 14px;">
                        <div>
                            <div style="font-size: 12px; color: #888; font-weight: 700; text-transform: uppercase;">
                                Order #<?php echo htmlspecialchars($ord['order_number']); ?> • <?php echo date('d M Y, h:i A', strtotime($ord['created_at'])); ?>
                            </div>
                            <h3 style="font-family: 'Playfair Display', serif; font-size: 20px; color: var(--primary-green); margin: 4px 0 0;">
                                <?php echo htmlspecialchars($ord['rest_name'] ?? 'Zaykaa Café & Restaurant'); ?>
                            </h3>
                        </div>

                        <div style="display: flex; align-items: center; gap: 12px;">
                            <?php echo get_status_badge($ord['order_status']); ?>
                            <span style="font-size: 18px; font-weight: 700; color: var(--primary-green);">
                                <?php echo format_price($ord['total_amount']); ?>
                            </span>
                        </div>
                    </div>

                    <!-- Items Summary -->
                    <div style="display: flex; flex-wrap: wrap; gap: 10px; font-size: 13px; color: #555;">
                        <?php foreach ($items as $it): ?>
                            <span style="background: #FAF8F2; border: 1px solid var(--light-border); padding: 4px 10px; border-radius: 6px;">
                                <strong><?php echo (int)$it['quantity']; ?>x</strong> <?php echo htmlspecialchars($it['item_name']); ?>
                                <?php if (!empty($it['serving_size'])): ?>
                                    <small style="color: #666; font-size: 11px;">(<?php echo htmlspecialchars($it['serving_size']); ?>)</small>
                                <?php endif; ?>
                            </span>
                        <?php endforeach; ?>
                    </div>

                    <!-- Actions Row -->
                    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; border-top: 1px dashed var(--light-border); padding-top: 14px; margin-top: 4px;">
                        <div style="font-size: 12px; color: #666;">
                            Payment: <strong><?php echo strtoupper($ord['payment_method']); ?></strong> (<?php echo ucfirst($ord['payment_status']); ?>)
                        </div>

                        <div class="order-action-btns-wrap" style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                            <a href="order-status.php?id=<?php echo $ord['id']; ?>" 
                               style="padding: 8px 16px; background: var(--primary-green); color: #fff; border-radius: 20px; font-weight: 600; font-size: 12px; text-decoration: none;">
                                📍 Track Order
                            </a>
                            <a href="invoice.php?id=<?php echo $ord['id']; ?>" target="_blank"
                               style="padding: 8px 16px; background: #FAF8F2; border: 1px solid var(--light-border); border-radius: 20px; font-weight: 600; font-size: 12px; text-decoration: none; color: #333;">
                                🧾 Invoice
                            </a>
                            <?php if ($ord['order_status'] === 'delivered'): ?>
                                <a href="review.php?order_id=<?php echo $ord['id']; ?>" 
                                   style="padding: 8px 16px; background: #FEF3C7; border: 1px solid #FCD34D; border-radius: 20px; font-weight: 600; font-size: 12px; text-decoration: none; color: #B45309;">
                                    ⭐ Rate Order
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div style="background: #FFFFFF; border-radius: 16px; border: 1px solid var(--light-border); text-align: center; padding: 60px 20px; max-width: 600px; margin: 40px auto;">
                <div style="font-size: 54px; margin-bottom: 12px;">📦</div>
                <h3 style="font-family: 'Playfair Display', serif; color: var(--primary-green); font-size: 22px; margin: 0 0 8px;">No Orders Placed Yet</h3>
                <p style="color: #666; font-size: 14px; margin-bottom: 24px;">When you place orders, they will show up here with live status tracking.</p>
                <a href="menu.php" style="display: inline-block; padding: 12px 28px; background: var(--primary-green); color: #fff; border-radius: 24px; font-weight: 600; text-decoration: none;">
                    Order Now
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
