<?php
// ===================================================
// Live Order Management Board - Restaurant Partner
// ===================================================
include 'includes/header.php';

$restId = (int)$currentRestaurant['id'];
$activeTab = $_GET['tab'] ?? 'active';

// Fetch orders according to tab
$sql = "SELECT o.*, u.name as customer_name, u.phone as customer_phone,
               dp.vehicle_number, ru.name as rider_name, ru.phone as rider_phone
        FROM orders o 
        JOIN users u ON o.user_id = u.id 
        LEFT JOIN delivery_partners dp ON o.delivery_partner_id = dp.id 
        LEFT JOIN users ru ON dp.user_id = ru.id 
        WHERE o.restaurant_id = ?";

if ($activeTab === 'new') {
    $sql .= " AND o.order_status = 'placed'";
} elseif ($activeTab === 'preparing') {
    $sql .= " AND o.order_status IN ('accepted', 'preparing')";
} elseif ($activeTab === 'ready') {
    $sql .= " AND o.order_status = 'ready_for_pickup'";
} elseif ($activeTab === 'transit') {
    $sql .= " AND o.order_status IN ('picked_up', 'out_for_delivery')";
} elseif ($activeTab === 'completed') {
    $sql .= " AND o.order_status = 'delivered'";
} elseif ($activeTab === 'cancelled') {
    $sql .= " AND o.order_status = 'cancelled'";
} else { // active
    $sql .= " AND o.order_status IN ('placed', 'accepted', 'preparing', 'ready_for_pickup', 'picked_up', 'out_for_delivery')";
}

$sql .= " ORDER BY o.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute([$restId]);
$orders = $stmt->fetchAll();
?>

<div style="padding: 30px 0 60px;">
    <div class="container">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 15px;">
            <div>
                <h1 style="font-family: 'Playfair Display', serif; font-size: 28px; color: var(--primary-green); margin: 0 0 4px;">
                    Kitchen Live Orders Board 📋
                </h1>
                <p style="color: #666; font-size: 14px; margin: 0;">
                    Accept incoming tickets, notify riders, and update meal preparation
                </p>
            </div>
            
            <div style="display: flex; align-items: center; gap: 10px; font-size: 13px; font-weight: 600; color: #16A34A; background: #DEF7EC; padding: 6px 14px; border-radius: 20px;">
                <span>●</span> Live Polling Active (5s refresh)
            </div>
        </div>

        <!-- Filter Tabs -->
        <div style="display: flex; gap: 10px; overflow-x: auto; scrollbar-width: none; border-bottom: 1px solid var(--light-border); padding-bottom: 12px; margin-bottom: 24px;">
            <a href="orders.php?tab=active" class="filter-pill <?php echo ($activeTab === 'active') ? 'active' : ''; ?>">All Active</a>
            <a href="orders.php?tab=new" class="filter-pill <?php echo ($activeTab === 'new') ? 'active' : ''; ?>">🔥 New Orders</a>
            <a href="orders.php?tab=preparing" class="filter-pill <?php echo ($activeTab === 'preparing') ? 'active' : ''; ?>">🍳 In Preparation</a>
            <a href="orders.php?tab=ready" class="filter-pill <?php echo ($activeTab === 'ready') ? 'active' : ''; ?>">📦 Ready for Handover</a>
            <a href="orders.php?tab=transit" class="filter-pill <?php echo ($activeTab === 'transit') ? 'active' : ''; ?>">🛵 Out with Rider</a>
            <a href="orders.php?tab=completed" class="filter-pill <?php echo ($activeTab === 'completed') ? 'active' : ''; ?>">✓ Delivered</a>
            <a href="orders.php?tab=cancelled" class="filter-pill <?php echo ($activeTab === 'cancelled') ? 'active' : ''; ?>">✕ Cancelled</a>
        </div>

        <!-- Orders Grid -->
        <?php if (!empty($orders)): ?>
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(360px, 1fr)); gap: 24px;">
                <?php foreach ($orders as $ord): 
                    // Fetch items with addons
                    $itStmt = $pdo->prepare("SELECT oi.*, GROUP_CONCAT(oia.addon_name SEPARATOR ', ') as addons_str 
                                             FROM order_items oi 
                                             LEFT JOIN order_item_addons oia ON oi.id = oia.order_item_id 
                                             WHERE oi.order_id = ? 
                                             GROUP BY oi.id");
                    $itStmt->execute([$ord['id']]);
                    $orderItems = $itStmt->fetchAll();
                    $dest = json_decode($ord['delivery_address_json'], true);
                ?>
                <div style="background: #FFFFFF; border-radius: 16px; border: 1.5px solid <?php echo ($ord['order_status'] === 'placed') ? '#EF4444' : 'var(--light-border)'; ?>; padding: 22px; box-shadow: var(--shadow-sm); display: flex; flex-direction: column; justify-content: space-between; position: relative;">
                    
                    <?php if ($ord['order_status'] === 'placed'): ?>
                        <div style="position: absolute; top: -10px; right: 20px; background: #DC2626; color: #fff; font-size: 11px; font-weight: 700; padding: 2px 10px; border-radius: 10px; animation: pulse 1s infinite;">
                            ACTION REQUIRED
                        </div>
                    <?php endif; ?>

                    <div>
                        <!-- Order Header -->
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px; border-bottom: 1px solid #F1EDE2; padding-bottom: 10px;">
                            <div>
                                <h3 style="font-family: 'Playfair Display', serif; font-size: 19px; color: var(--primary-green); margin: 0;">
                                    #<?php echo htmlspecialchars($ord['order_number']); ?>
                                </h3>
                                <div style="font-size: 12px; color: #888;">
                                    <?php echo date('h:i A, d M', strtotime($ord['created_at'])); ?>
                                </div>
                            </div>
                            <div>
                                <?php echo get_status_badge($ord['order_status']); ?>
                            </div>
                        </div>

                        <!-- Customer Info -->
                        <div style="font-size: 13px; color: #444; margin-bottom: 14px; background: #FAF8F2; padding: 10px 14px; border-radius: 10px;">
                            <div style="font-weight: 700; color: #333;">👤 <?php echo htmlspecialchars($ord['customer_name']); ?> (📞 <?php echo htmlspecialchars($ord['customer_phone']); ?>)</div>
                            <div style="font-size: 12px; color: #666; margin-top: 3px;">📍 <?php echo htmlspecialchars($dest['address_line'] ?? ''); ?></div>
                            <?php if ($ord['special_instructions']): ?>
                                <div style="font-size: 12px; color: #D97706; font-weight: 600; margin-top: 4px;">
                                    📝 Notes: "<?php echo htmlspecialchars($ord['special_instructions']); ?>"
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Items List -->
                        <div style="display: flex; flex-direction: column; gap: 8px; margin-bottom: 16px;">
                            <div style="font-size: 11px; font-weight: 700; color: #888; text-transform: uppercase;">Items Ordered:</div>
                            <?php foreach ($orderItems as $it): ?>
                            <div style="display: flex; justify-content: space-between; font-size: 14px;">
                                <div>
                                    <strong style="color: var(--primary-green);"><?php echo (int)$it['quantity']; ?>x</strong> 
                                    <span style="font-weight: 600; color: #333;"><?php echo htmlspecialchars($it['item_name']); ?></span>
                                    <?php if (!empty($it['addons_str'])): ?>
                                        <div style="font-size: 11px; color: #777;">+ <?php echo htmlspecialchars($it['addons_str']); ?></div>
                                    <?php endif; ?>
                                </div>
                                <span style="font-weight: 600; color: #555;"><?php echo format_price($it['total']); ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Rider Status & Action Buttons -->
                    <div style="border-top: 1px dashed var(--light-border); padding-top: 14px; margin-top: 10px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; font-size: 12px; margin-bottom: 12px;">
                            <div>
                                Rider: 
                                <?php if ($ord['rider_name']): ?>
                                    <strong style="color: #059669;"><?php echo htmlspecialchars($ord['rider_name']); ?></strong> (<?php echo htmlspecialchars($ord['rider_phone']); ?>)
                                <?php else: ?>
                                    <span style="color: #888;">Looking for nearby rider...</span>
                                <?php endif; ?>
                            </div>
                            <div style="font-weight: 700; font-size: 15px; color: var(--primary-green);">
                                <?php echo format_price($ord['total_amount']); ?>
                            </div>
                        </div>

                        <!-- Action Controls -->
                        <div style="display: flex; gap: 8px;">
                            <?php if ($ord['order_status'] === 'placed'): ?>
                                <button onclick="acceptOrder(<?php echo $ord['id']; ?>)" 
                                        style="flex: 2; padding: 10px; background: #059669; color: #fff; border-radius: 8px; font-weight: 700; border: none; cursor: pointer;">
                                    ✓ Accept Order
                                </button>
                                <button onclick="rejectOrder(<?php echo $ord['id']; ?>)" 
                                        style="flex: 1; padding: 10px; background: #FDE8E8; color: #9B1C1C; border-radius: 8px; font-weight: 700; border: none; cursor: pointer;">
                                    ✕ Reject
                                </button>
                            <?php elseif ($ord['order_status'] === 'accepted'): ?>
                                <button onclick="updateStatus(<?php echo $ord['id']; ?>, 'preparing')" 
                                        style="width: 100%; padding: 10px; background: #D97706; color: #fff; border-radius: 8px; font-weight: 700; border: none; cursor: pointer;">
                                    🍳 Mark Preparing Food
                                </button>
                            <?php elseif ($ord['order_status'] === 'preparing'): ?>
                                <button onclick="updateStatus(<?php echo $ord['id']; ?>, 'ready_for_pickup')" 
                                        style="width: 100%; padding: 10px; background: #7C3AED; color: #fff; border-radius: 8px; font-weight: 700; border: none; cursor: pointer;">
                                    📦 Mark Food Ready for Pickup
                                </button>
                            <?php elseif ($ord['order_status'] === 'ready_for_pickup'): ?>
                                <div style="width: 100%; text-align: center; padding: 8px; background: #EDE9FE; color: #7C3AED; border-radius: 8px; font-weight: 700; font-size: 13px;">
                                    Waiting for rider to pick up
                                </div>
                            <?php elseif (in_array($ord['order_status'], ['picked_up', 'out_for_delivery'])): ?>
                                <div style="width: 100%; text-align: center; padding: 8px; background: #FFEDD5; color: #EA580C; border-radius: 8px; font-weight: 700; font-size: 13px;">
                                    🛵 On the road with rider
                                </div>
                            <?php else: ?>
                                <div style="width: 100%; text-align: center; padding: 8px; background: #DEF7EC; color: #046C4E; border-radius: 8px; font-weight: 700; font-size: 13px;">
                                    Order completed
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div style="background: #FFFFFF; border-radius: 16px; border: 1px solid var(--light-border); text-align: center; padding: 60px 20px; max-width: 500px; margin: 40px auto;">
                <div style="font-size: 48px; margin-bottom: 12px;">📋</div>
                <h3 style="font-family: 'Playfair Display', serif; color: var(--primary-green); font-size: 20px; margin: 0 0 6px;">No Orders in This View</h3>
                <p style="color: #666; font-size: 13px; margin: 0;">New incoming orders will appear here automatically.</p>
            </div>
        <?php endif; ?>

    </div>
</div>

<script>
function acceptOrder(id) {
    const prep = prompt('Enter preparation time in minutes:', '25');
    if (prep) {
        const formData = new FormData();
        formData.append('action', 'update_status');
        formData.append('order_id', id);
        formData.append('status', 'accepted');
        formData.append('prep_time', prep);

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

function rejectOrder(id) {
    const reason = prompt('Reason for rejection (e.g. Kitchen overloaded, Ingredients finished):', 'Kitchen overloaded');
    if (reason) {
        const formData = new FormData();
        formData.append('action', 'update_status');
        formData.append('order_id', id);
        formData.append('status', 'cancelled');
        formData.append('notes', reason);

        fetch('../api/orders.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    showToast('Order rejected.', 'warning');
                    setTimeout(() => window.location.reload(), 600);
                } else {
                    showToast(data.message, 'error');
                }
            });
    }
}

function updateStatus(id, status) {
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

// Auto-refresh orders every 8 seconds
setInterval(() => {
    // Only refresh if no prompt is active
    window.location.reload();
}, 8000);
</script>

<?php include 'includes/footer.php'; ?>
