<?php
// ===================================================
// Active Order Navigation & OTP Verification - Delivery Partner
// ===================================================
include 'includes/header.php';

$riderId = (int)$rider['id'];

// Fetch current active delivery assignment
$actStmt = $pdo->prepare("SELECT o.*, 
                                 r.name as rest_name, r.address as rest_address, r.city as rest_city, r.phone as rest_phone, r.latitude as rest_lat, r.longitude as rest_lng,
                                 u.name as customer_name, u.phone as customer_phone 
                          FROM orders o 
                          JOIN restaurants r ON o.restaurant_id = r.id 
                          JOIN users u ON o.user_id = u.id 
                          WHERE o.delivery_partner_id = ? 
                            AND o.order_status IN ('accepted', 'preparing', 'ready_for_pickup', 'picked_up', 'out_for_delivery') 
                          ORDER BY o.id DESC LIMIT 1");
$actStmt->execute([$riderId]);
$order = $actStmt->fetch();

if (!$order) {
    echo "<div class='container' style='padding:80px 20px; text-align:center;'>
        <h2>No Active Delivery In Progress</h2>
        <p>You currently do not have any active delivery trips assigned.</p>
        <a href='dashboard.php' style='display:inline-block; margin-top:20px; padding:10px 24px; background:#059669; color:#fff; border-radius:20px; text-decoration:none; font-weight:700;'>
            View Available Orders Pool →
        </a>
    </div>";
    include 'includes/footer.php';
    exit;
}

// Fetch order items
$itStmt = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ?");
$itStmt->execute([$order['id']]);
$items = $itStmt->fetchAll();

$dest = json_decode($order['delivery_address_json'], true);
?>

<div style="padding: 30px 0 60px;">
    <div class="container" style="max-width: 800px;">
        
        <!-- Header -->
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 15px;">
            <div>
                <span style="font-size: 11px; font-weight: 800; color: #059669; text-transform: uppercase;">ACTIVE DELIVERY JOURNEY</span>
                <h1 style="font-family: 'Playfair Display', serif; font-size: 28px; color: #1E293B; margin: 4px 0 0;">
                    Order #<?php echo htmlspecialchars($order['order_number']); ?>
                </h1>
            </div>
            <div>
                <?php echo get_status_badge($order['order_status']); ?>
            </div>
        </div>

        <!-- Stage 1: Pickup from Restaurant -->
        <div style="background: #FFFFFF; border-radius: 16px; border: 1.5px solid <?php echo in_array($order['order_status'], ['accepted', 'preparing', 'ready_for_pickup']) ? '#059669' : '#CBD5E1'; ?>; padding: 24px; margin-bottom: 20px; box-shadow: var(--shadow-sm);">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 14px;">
                <div>
                    <span style="font-size: 12px; font-weight: 700; color: #888; text-transform: uppercase;">STEP 1: RESTAURANT PICKUP</span>
                    <h3 style="font-family: 'Playfair Display', serif; font-size: 20px; color: var(--primary-green); margin: 4px 0 0;">
                        <?php echo htmlspecialchars($order['rest_name']); ?>
                    </h3>
                </div>
                <a href="tel:<?php echo htmlspecialchars($order['rest_phone']); ?>" 
                   style="padding: 8px 16px; background: #FAF8F2; border: 1px solid var(--light-border); border-radius: 20px; font-size: 13px; font-weight: 600; text-decoration: none; color: #333;">
                    📞 Call Kitchen
                </a>
            </div>

            <p style="font-size: 14px; color: #555; margin: 0 0 16px;">
                📍 <?php echo htmlspecialchars($order['rest_address'] . ', ' . $order['rest_city']); ?>
            </p>

            <!-- Items Checklist -->
            <div style="background: #FAF8F2; border-radius: 12px; padding: 14px; margin-bottom: 16px;">
                <div style="font-size: 12px; font-weight: 700; color: #666; text-transform: uppercase; margin-bottom: 8px;">Order Items Checklist:</div>
                <?php foreach ($items as $it): ?>
                    <div style="font-size: 13px; color: #333; margin-bottom: 4px;">
                        ✓ <strong><?php echo (int)$it['quantity']; ?>x</strong> <?php echo htmlspecialchars($it['item_name']); ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if (in_array($order['order_status'], ['accepted', 'preparing', 'ready_for_pickup'])): ?>
                <button onclick="confirmPickup(<?php echo $order['id']; ?>)" 
                        style="width: 100%; padding: 14px; background: #059669; color: #fff; border-radius: 25px; font-weight: 700; font-size: 15px; border: none; cursor: pointer; box-shadow: var(--shadow-sm);">
                    📦 Confirm Order Picked Up
                </button>
            <?php else: ?>
                <div style="color: #059669; font-weight: 700; font-size: 13px;">
                    ✓ Picked up from restaurant
                </div>
            <?php endif; ?>
        </div>

        <!-- Stage 2: Deliver to Customer & OTP Verification -->
        <div style="background: #FFFFFF; border-radius: 16px; border: 1.5px solid <?php echo in_array($order['order_status'], ['picked_up', 'out_for_delivery']) ? '#059669' : '#CBD5E1'; ?>; padding: 24px; box-shadow: var(--shadow-sm);">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 14px;">
                <div>
                    <span style="font-size: 12px; font-weight: 700; color: #888; text-transform: uppercase;">STEP 2: CUSTOMER DELIVERY</span>
                    <h3 style="font-family: 'Playfair Display', serif; font-size: 20px; color: #1E293B; margin: 4px 0 0;">
                        <?php echo htmlspecialchars($order['customer_name']); ?>
                    </h3>
                </div>
                <a href="tel:<?php echo htmlspecialchars($order['customer_phone']); ?>" 
                   style="padding: 8px 16px; background: #FAF8F2; border: 1px solid var(--light-border); border-radius: 20px; font-size: 13px; font-weight: 600; text-decoration: none; color: #333;">
                    📞 Call Customer
                </a>
            </div>

            <p style="font-size: 14px; color: #555; margin: 0 0 8px; line-height: 1.4;">
                📍 <strong>Destination:</strong> <?php echo htmlspecialchars($dest['address_line'] ?? ''); ?><br>
                <?php if (!empty($dest['landmark'])): ?>Landmark: <em><?php echo htmlspecialchars($dest['landmark']); ?></em><br><?php endif; ?>
                <?php echo htmlspecialchars($dest['city'] ?? '') . ' - ' . htmlspecialchars($dest['pincode'] ?? ''); ?>
            </p>

            <?php if ($order['special_instructions']): ?>
                <div style="font-size: 13px; color: #D97706; background: #FEF3C7; padding: 8px 12px; border-radius: 8px; margin-bottom: 16px; font-weight: 600;">
                    📝 Customer Note: "<?php echo htmlspecialchars($order['special_instructions']); ?>"
                </div>
            <?php endif; ?>

            <?php if ($order['order_status'] === 'picked_up'): ?>
                <button onclick="startDelivery(<?php echo $order['id']; ?>)" 
                        style="width: 100%; padding: 14px; background: #EA580C; color: #fff; border-radius: 25px; font-weight: 700; font-size: 15px; border: none; cursor: pointer;">
                    🛵 Start Transit (Out for Delivery)
                </button>
            <?php elseif ($order['order_status'] === 'out_for_delivery'): ?>
                <!-- OTP Verification Form -->
                <div style="background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 12px; padding: 20px; text-align: center; margin-top: 14px;">
                    <div style="font-size: 13px; font-weight: 700; color: #1E293B; margin-bottom: 6px;">ENTER 4-DIGIT CUSTOMER DELIVERY OTP:</div>
                    <p style="font-size: 12px; color: #64748B; margin: 0 0 14px;">Ask the customer for their delivery verification code to complete this trip.</p>
                    
                    <input type="text" id="deliveryOtpInput" maxlength="4" placeholder="••••" 
                           style="font-size: 28px; letter-spacing: 12px; text-align: center; width: 180px; padding: 10px; border-radius: 10px; border: 2px solid var(--primary-green); font-family: monospace; margin-bottom: 16px;">

                    <button onclick="verifyAndCompleteDelivery(<?php echo $order['id']; ?>)" 
                            style="display: block; width: 100%; padding: 14px; background: #059669; color: #fff; border-radius: 25px; font-weight: 700; font-size: 16px; border: none; cursor: pointer; box-shadow: var(--shadow-sm);">
                        ✓ Verify OTP & Mark Delivered
                    </button>
                </div>
            <?php endif; ?>
        </div>

    </div>
</div>

<script>
function confirmPickup(id) {
    const formData = new FormData();
    formData.append('action', 'update_status');
    formData.append('order_id', id);
    formData.append('status', 'picked_up');

    fetch('../api/orders.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                showToast('Order marked as Picked Up!', 'success');
                setTimeout(() => window.location.reload(), 600);
            } else {
                showToast(data.message, 'error');
            }
        });
}

function startDelivery(id) {
    const formData = new FormData();
    formData.append('action', 'update_status');
    formData.append('order_id', id);
    formData.append('status', 'out_for_delivery');

    fetch('../api/orders.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                showToast('Trip is now Out for Delivery!', 'success');
                setTimeout(() => window.location.reload(), 600);
            } else {
                showToast(data.message, 'error');
            }
        });
}

function verifyAndCompleteDelivery(id) {
    const otp = document.getElementById('deliveryOtpInput').value.trim();
    if (otp.length !== 4) {
        showToast('Please enter the full 4-digit OTP code.', 'warning');
        return;
    }

    const formData = new FormData();
    formData.append('action', 'update_status');
    formData.append('order_id', id);
    formData.append('status', 'delivered');
    formData.append('otp', otp);

    fetch('../api/orders.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                showToast('Delivery completed successfully! Trip earnings added to your wallet.', 'success');
                setTimeout(() => {
                    window.location.href = 'dashboard.php';
                }, 1000);
            } else {
                showToast(data.message, 'error');
            }
        });
}
</script>

<?php include 'includes/footer.php'; ?>
