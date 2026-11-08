<?php
// ===================================================
// Admin: Orders Control & Master Dispatch
// ===================================================
require_once __DIR__ . '/includes/admin-header.php';

// Handle Post Actions (Status Change, Rider Reassignment, Cancellation)
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $action = $_POST['action'] ?? '';
    $order_id = (int)($_POST['order_id'] ?? 0);

    if ($action === 'change_status') {
        $new_status = $_POST['new_status'] ?? '';
        $allowed = ['placed', 'accepted', 'preparing', 'ready_for_pickup', 'picked_up', 'out_for_delivery', 'delivered', 'cancelled'];
        
        if (in_array($new_status, $allowed) && $order_id > 0) {
            $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
            $stmt->execute([$order_id]);
            $ord = $stmt->fetch();

            if ($ord) {
                if ($new_status === 'cancelled') {
                    $reason = trim($_POST['cancellation_reason'] ?? 'Cancelled by Platform Admin');
                    // Check if refund needed
                    if ($ord['payment_status'] === 'paid') {
                        $pdo->prepare("UPDATE users SET wallet_balance = wallet_balance + ? WHERE id = ?")
                            ->execute([$ord['total_amount'], $ord['user_id']]);
                        $pdo->prepare("INSERT INTO wallet_transactions (user_id, type, amount, description, reference_order_id) VALUES (?, 'credit', ?, ?, ?)")
                            ->execute([$ord['user_id'], $ord['total_amount'], "Admin refund for cancelled order #{$ord['order_number']}", $order_id]);
                        $refund_sql = ", payment_status = 'refunded'";
                    } else {
                        $refund_sql = "";
                    }

                    $pdo->prepare("UPDATE orders SET order_status = 'cancelled', cancellation_reason = ?, cancelled_by = 'admin' {$refund_sql} WHERE id = ?")
                        ->execute([$reason, $order_id]);
                    $pdo->prepare("INSERT INTO order_status_logs (order_id, status, notes) VALUES (?, 'cancelled', ?)")
                        ->execute([$order_id, "Admin cancellation: {$reason}"]);

                    set_flash('success', "Order #{$ord['order_number']} has been cancelled. Refund processed if paid.");
                } else {
                    $pdo->prepare("UPDATE orders SET order_status = ? WHERE id = ?")->execute([$new_status, $order_id]);
                    $pdo->prepare("INSERT INTO order_status_logs (order_id, status, notes) VALUES (?, ?, ?)")
                        ->execute([$order_id, $new_status, "Status updated by Admin to {$new_status}"]);

                    // If delivered and payment was cod, mark paid
                    if ($new_status === 'delivered' && $ord['payment_method'] === 'cod') {
                        $pdo->prepare("UPDATE orders SET payment_status = 'paid' WHERE id = ?")->execute([$order_id]);
                    }

                    set_flash('success', "Order #{$ord['order_number']} status updated to <strong>" . strtoupper(str_replace('_', ' ', $new_status)) . "</strong>.");
                }
            }
        }
    } elseif ($action === 'assign_rider') {
        $rider_id = (int)($_POST['delivery_partner_id'] ?? 0);
        if ($order_id > 0 && $rider_id > 0) {
            $pdo->prepare("UPDATE orders SET delivery_partner_id = ? WHERE id = ?")->execute([$rider_id, $order_id]);
            
            // Get rider name
            $r_stmt = $pdo->prepare("SELECT u.name FROM delivery_partners dp JOIN users u ON dp.user_id = u.id WHERE dp.id = ?");
            $r_stmt->execute([$rider_id]);
            $rider = $r_stmt->fetch();
            $rider_name = $rider['name'] ?? 'Rider';

            $pdo->prepare("INSERT INTO order_status_logs (order_id, status, notes) VALUES (?, 'assigned', ?)")
                ->execute([$order_id, "Manually assigned by Admin to {$rider_name}"]);

            set_flash('success', "Assigned delivery partner {$rider_name} to Order #{$order_id}.");
        }
    }
    header("Location: orders.php?" . http_build_query($_GET));
    exit;
}

// Fetch Filters
$status_filter = $_GET['status'] ?? '';
$restaurant_filter = (int)($_GET['restaurant_id'] ?? 0);
$search = trim($_GET['q'] ?? '');
$date_filter = $_GET['date'] ?? '';

// Build Query
$sql = "SELECT o.*, 
        u.name as customer_name, u.phone as customer_phone, u.email as customer_email,
        r.name as restaurant_name,
        ru.name as rider_name, ru.phone as rider_phone, dp.vehicle_number,
        (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) as item_count
        FROM orders o
        JOIN users u ON o.user_id = u.id
        JOIN restaurants r ON o.restaurant_id = r.id
        LEFT JOIN delivery_partners dp ON o.delivery_partner_id = dp.id
        LEFT JOIN users ru ON dp.user_id = ru.id
        WHERE 1=1";

$params = [];

if (!empty($status_filter)) {
    $sql .= " AND o.order_status = ?";
    $params[] = $status_filter;
}
if ($restaurant_filter > 0) {
    $sql .= " AND o.restaurant_id = ?";
    $params[] = $restaurant_filter;
}
if (!empty($search)) {
    $sql .= " AND (o.order_number LIKE ? OR u.name LIKE ? OR u.phone LIKE ? OR r.name LIKE ?)";
    $term = "%{$search}%";
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
}
if (!empty($date_filter)) {
    $sql .= " AND DATE(o.created_at) = ?";
    $params[] = $date_filter;
}

$sql .= " ORDER BY o.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();

// KPIs
$kpi_total = $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
$kpi_active = $pdo->query("SELECT COUNT(*) FROM orders WHERE order_status NOT IN ('delivered', 'cancelled')")->fetchColumn();
$kpi_delivered = $pdo->query("SELECT COUNT(*) FROM orders WHERE order_status = 'delivered'")->fetchColumn();
$kpi_gmv = $pdo->query("SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE order_status = 'delivered'")->fetchColumn();

// Fetch restaurants & active riders for filters and assignment modals
$restaurants = $pdo->query("SELECT id, name FROM restaurants ORDER BY name ASC")->fetchAll();
$available_riders = $pdo->query("SELECT dp.id, u.name, dp.vehicle_type, dp.vehicle_number, dp.is_online 
                                  FROM delivery_partners dp 
                                  JOIN users u ON dp.user_id = u.id 
                                  WHERE dp.kyc_status = 'approved' ORDER BY dp.is_online DESC, u.name ASC")->fetchAll();
?>

<div class="container" style="padding: 24px 20px; max-width: 1400px;">
    <!-- Page Title -->
    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 24px; flex-wrap: wrap; gap: 16px;">
        <div>
            <h1 style="font-size: 26px; font-weight: 700; color: #0B192C; margin: 0 0 6px 0;">Orders Central Control</h1>
            <p style="color: #64748B; font-size: 14px; margin: 0;">Real-time platform order monitoring, manual rider assignment, status overrides, and dispute resolution.</p>
        </div>
        <div style="display: flex; gap: 10px;">
            <button onclick="window.location.reload();" class="btn" style="background: #fff; border: 1px solid #CBD5E1; color: #334155; font-size: 13px; font-weight: 600;">
                🔄 Refresh Stream
            </button>
        </div>
    </div>

    <!-- Live KPI Metrics -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 24px;">
        <div style="background: #fff; border-radius: 12px; padding: 18px 20px; border: 1px solid #E2E8F0; box-shadow: var(--shadow-sm);">
            <div style="font-size: 13px; color: #64748B; margin-bottom: 6px;">Total Platform Orders</div>
            <div style="font-size: 26px; font-weight: 700; color: #0B192C;"><?php echo number_format($kpi_total); ?></div>
        </div>
        <div style="background: #fff; border-radius: 12px; padding: 18px 20px; border: 1px solid #E2E8F0; box-shadow: var(--shadow-sm); border-left: 4px solid #F59E0B;">
            <div style="font-size: 13px; color: #64748B; margin-bottom: 6px;">Active In-Flight Orders</div>
            <div style="font-size: 26px; font-weight: 700; color: #D97706;"><?php echo number_format($kpi_active); ?></div>
        </div>
        <div style="background: #fff; border-radius: 12px; padding: 18px 20px; border: 1px solid #E2E8F0; box-shadow: var(--shadow-sm); border-left: 4px solid #10B981;">
            <div style="font-size: 13px; color: #64748B; margin-bottom: 6px;">Successfully Delivered</div>
            <div style="font-size: 26px; font-weight: 700; color: #059669;"><?php echo number_format($kpi_delivered); ?></div>
        </div>
        <div style="background: #fff; border-radius: 12px; padding: 18px 20px; border: 1px solid #E2E8F0; box-shadow: var(--shadow-sm); border-left: 4px solid #063F2B;">
            <div style="font-size: 13px; color: #64748B; margin-bottom: 6px;">Total Platform GMV</div>
            <div style="font-size: 26px; font-weight: 700; color: #063F2B;"><?php echo format_price($kpi_gmv); ?></div>
        </div>
    </div>

    <!-- Search & Multi-Filters -->
    <div style="background: #fff; border-radius: 12px; padding: 18px 20px; border: 1px solid #E2E8F0; margin-bottom: 24px; box-shadow: var(--shadow-sm);">
        <form method="GET" class="admin-filter-form" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)) 120px; gap: 14px; align-items: end;">
            <div>
                <label style="display: block; font-size: 12px; font-weight: 600; color: #475569; margin-bottom: 4px;">Search</label>
                <input type="text" name="q" value="<?php echo htmlspecialchars($search); ?>" placeholder="Order #, customer, restaurant..." style="width: 100%; padding: 9px 12px; border: 1px solid #CBD5E1; border-radius: 8px; font-size: 13px;">
            </div>

            <div>
                <label style="display: block; font-size: 12px; font-weight: 600; color: #475569; margin-bottom: 4px;">Order Status</label>
                <select name="status" style="width: 100%; padding: 9px 12px; border: 1px solid #CBD5E1; border-radius: 8px; font-size: 13px; background: #fff;">
                    <option value="">All Statuses</option>
                    <option value="placed" <?php echo ($status_filter === 'placed') ? 'selected' : ''; ?>>Placed (New)</option>
                    <option value="accepted" <?php echo ($status_filter === 'accepted') ? 'selected' : ''; ?>>Accepted</option>
                    <option value="preparing" <?php echo ($status_filter === 'preparing') ? 'selected' : ''; ?>>Preparing</option>
                    <option value="ready_for_pickup" <?php echo ($status_filter === 'ready_for_pickup') ? 'selected' : ''; ?>>Ready for Pickup</option>
                    <option value="picked_up" <?php echo ($status_filter === 'picked_up') ? 'selected' : ''; ?>>Picked Up</option>
                    <option value="out_for_delivery" <?php echo ($status_filter === 'out_for_delivery') ? 'selected' : ''; ?>>Out for Delivery</option>
                    <option value="delivered" <?php echo ($status_filter === 'delivered') ? 'selected' : ''; ?>>Delivered</option>
                    <option value="cancelled" <?php echo ($status_filter === 'cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                </select>
            </div>

            <div>
                <label style="display: block; font-size: 12px; font-weight: 600; color: #475569; margin-bottom: 4px;">Restaurant</label>
                <select name="restaurant_id" style="width: 100%; padding: 9px 12px; border: 1px solid #CBD5E1; border-radius: 8px; font-size: 13px; background: #fff;">
                    <option value="0">All Restaurants</option>
                    <?php foreach ($restaurants as $r): ?>
                        <option value="<?php echo $r['id']; ?>" <?php echo ($restaurant_filter === (int)$r['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($r['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label style="display: block; font-size: 12px; font-weight: 600; color: #475569; margin-bottom: 4px;">Date</label>
                <input type="date" name="date" value="<?php echo htmlspecialchars($date_filter); ?>" style="width: 100%; padding: 9px 12px; border: 1px solid #CBD5E1; border-radius: 8px; font-size: 13px; background: #fff;">
            </div>

            <div>
                <button type="submit" class="btn btn-primary" style="width: 100%; padding: 10px; font-size: 13px; justify-content: center;">
                    Apply Filter
                </button>
            </div>
        </form>
    </div>

    <!-- Orders Master Table -->
    <div style="background: #fff; border-radius: 12px; border: 1px solid #E2E8F0; overflow: hidden; box-shadow: var(--shadow-sm);">
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 13px;">
                <thead>
                    <tr style="background: #F8FAFC; border-bottom: 1px solid #E2E8F0; color: #475569; font-weight: 600; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px;">
                        <th style="padding: 14px 16px;">Order Ref & Date</th>
                        <th style="padding: 14px 16px;">Customer</th>
                        <th style="padding: 14px 16px;">Restaurant</th>
                        <th style="padding: 14px 16px;">Bill Total</th>
                        <th style="padding: 14px 16px;">Payment</th>
                        <th style="padding: 14px 16px;">Status</th>
                        <th style="padding: 14px 16px;">Delivery Partner</th>
                        <th style="padding: 14px 16px;">OTP</th>
                        <th style="padding: 14px 16px; text-align: right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($orders)): ?>
                        <tr>
                            <td colspan="9" style="padding: 40px; text-align: center; color: #94A3B8;">
                                No orders matching the selected criteria.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($orders as $o): ?>
                            <tr style="border-bottom: 1px solid #F1F5F9;">
                                <td style="padding: 14px 16px;">
                                    <div style="font-weight: 700; color: #0F172A;">#<?php echo htmlspecialchars($o['order_number']); ?></div>
                                    <div style="font-size: 11px; color: #64748B; margin-top: 2px;">
                                        <?php echo date('M d, Y h:i A', strtotime($o['created_at'])); ?>
                                    </div>
                                </td>
                                <td style="padding: 14px 16px;">
                                    <div style="font-weight: 600; color: #1E293B;"><?php echo htmlspecialchars($o['customer_name']); ?></div>
                                    <div style="font-size: 11px; color: #64748B;"><?php echo htmlspecialchars($o['customer_phone']); ?></div>
                                </td>
                                <td style="padding: 14px 16px;">
                                    <div style="font-weight: 600; color: #1E293B;"><?php echo htmlspecialchars($o['restaurant_name']); ?></div>
                                    <div style="font-size: 11px; color: #94A3B8;"><?php echo $o['item_count']; ?> item(s)</div>
                                </td>
                                <td style="padding: 14px 16px;">
                                    <div style="font-weight: 700; color: #063F2B;"><?php echo format_price($o['total_amount']); ?></div>
                                    <div style="font-size: 11px; color: #64748B;">Admin cut: <?php echo format_price($o['platform_commission']); ?></div>
                                </td>
                                <td style="padding: 14px 16px;">
                                    <span style="font-size: 11px; font-weight: 600; text-transform: uppercase; color: #475569;">
                                        <?php echo $o['payment_method']; ?>
                                    </span>
                                    <div>
                                        <?php if ($o['payment_status'] === 'paid'): ?>
                                            <span style="color: #16A34A; font-size: 11px; font-weight: 600;">● Paid</span>
                                        <?php elseif ($o['payment_status'] === 'refunded'): ?>
                                            <span style="color: #6366F1; font-size: 11px; font-weight: 600;">● Refunded</span>
                                        <?php else: ?>
                                            <span style="color: #D97706; font-size: 11px; font-weight: 600;">● Pending</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td style="padding: 14px 16px;">
                                    <?php echo get_status_badge($o['order_status']); ?>
                                </td>
                                <td style="padding: 14px 16px;">
                                    <?php if ($o['rider_name']): ?>
                                        <div style="font-weight: 600; color: #0F172A;"><?php echo htmlspecialchars($o['rider_name']); ?></div>
                                        <div style="font-size: 11px; color: #64748B;"><?php echo htmlspecialchars($o['vehicle_number']); ?></div>
                                    <?php else: ?>
                                        <span style="font-size: 11px; color: #94A3B8; font-style: italic;">Unassigned</span>
                                    <?php endif; ?>
                                </td>
                                <td style="padding: 14px 16px;">
                                    <span style="font-family: monospace; font-size: 13px; font-weight: 700; background: #FEF3C7; color: #92400E; padding: 2px 6px; border-radius: 4px;">
                                        <?php echo htmlspecialchars($o['delivery_otp']); ?>
                                    </span>
                                </td>
                                <td style="padding: 14px 16px; text-align: right;">
                                    <div style="display: flex; gap: 6px; justify-content: flex-end;">
                                        <a href="../invoice.php?order_id=<?php echo $o['id']; ?>" target="_blank" class="btn" style="padding: 4px 8px; font-size: 11px; background: #F1F5F9; color: #475569;" title="View Tax Invoice">
                                            📄 Invoice
                                        </a>
                                        <button type="button" onclick='openActionModal(<?php echo json_encode($o); ?>)' class="btn btn-primary" style="padding: 4px 10px; font-size: 11px;">
                                            Manage
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Order Management Modal -->
<div id="orderActionModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 2000; align-items: center; justify-content: center; padding: 20px;">
    <div style="background: #fff; width: 100%; max-width: 600px; border-radius: 16px; padding: 24px; max-height: 90vh; overflow-y: auto; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.2);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
            <h3 style="margin: 0; font-size: 18px; color: #0B192C;">Manage Order <span id="m_orderNum" style="color: #063F2B;"></span></h3>
            <button type="button" onclick="closeActionModal()" style="background: none; border: none; font-size: 20px; cursor: pointer; color: #94A3B8;">&times;</button>
        </div>

        <div style="background: #F8FAFC; border-radius: 10px; padding: 14px; margin-bottom: 20px; font-size: 13px; line-height: 1.6;">
            <div><strong>Customer:</strong> <span id="m_cust"></span></div>
            <div><strong>Restaurant:</strong> <span id="m_rest"></span></div>
            <div><strong>Bill Total:</strong> <span id="m_amt" style="color: #063F2B; font-weight: 700;"></span></div>
            <div><strong>Delivery Address:</strong> <span id="m_addr"></span></div>
            <div><strong>Delivery OTP:</strong> <span id="m_otp" style="font-family: monospace; font-weight: 700; color: #B45309;"></span></div>
        </div>

        <!-- Update Status Form -->
        <form method="POST" style="margin-bottom: 20px; border-bottom: 1px solid #E2E8F0; padding-bottom: 20px;">
            <input type="hidden" name="action" value="change_status">
            <input type="hidden" name="order_id" id="statusOrderId" value="">

            <h4 style="margin: 0 0 10px 0; font-size: 14px; color: #1E293B;">1. Update Order Status</h4>
            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                <select name="new_status" id="m_statusSelect" style="flex: 1; min-width: 200px; padding: 10px; border: 1px solid #CBD5E1; border-radius: 8px; font-size: 13px;">
                    <option value="placed">Placed (Pending Restaurant)</option>
                    <option value="accepted">Accepted by Restaurant</option>
                    <option value="preparing">Preparing in Kitchen</option>
                    <option value="ready_for_pickup">Ready for Pickup</option>
                    <option value="picked_up">Picked Up by Rider</option>
                    <option value="out_for_delivery">Out for Delivery</option>
                    <option value="delivered">Delivered (Success)</option>
                    <option value="cancelled">Cancelled (Refund if Paid)</option>
                </select>
                <button type="submit" class="btn btn-primary" style="padding: 10px 16px; font-size: 13px;">Save Status</button>
            </div>
            <div id="cancelReasonBox" style="display: none; margin-top: 10px;">
                <input type="text" name="cancellation_reason" placeholder="Reason for cancellation..." style="width: 100%; padding: 8px 12px; border: 1px solid #CBD5E1; border-radius: 8px; font-size: 13px;">
            </div>
        </form>

        <!-- Assign Rider Form -->
        <form method="POST">
            <input type="hidden" name="action" value="assign_rider">
            <input type="hidden" name="order_id" id="riderOrderId" value="">

            <h4 style="margin: 0 0 10px 0; font-size: 14px; color: #1E293B;">2. Assign Delivery Partner</h4>
            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                <select name="delivery_partner_id" style="flex: 1; min-width: 200px; padding: 10px; border: 1px solid #CBD5E1; border-radius: 8px; font-size: 13px;">
                    <option value="">-- Choose Rider --</option>
                    <?php foreach ($available_riders as $ar): ?>
                        <option value="<?php echo $ar['id']; ?>">
                            <?php echo htmlspecialchars($ar['name']); ?> (<?php echo $ar['vehicle_type']; ?> - <?php echo $ar['vehicle_number']; ?>) <?php echo $ar['is_online'] ? '🟢 Online' : '⚪ Offline'; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn" style="background: #063F2B; color: #fff; padding: 10px 16px; font-size: 13px; font-weight: 600;">Assign Rider</button>
            </div>
        </form>
    </div>
</div>

<script>
function openActionModal(order) {
    document.getElementById('statusOrderId').value = order.id;
    document.getElementById('riderOrderId').value = order.id;
    document.getElementById('m_orderNum').textContent = '#' + order.order_number;
    document.getElementById('m_cust').textContent = order.customer_name + ' (' + order.customer_phone + ')';
    document.getElementById('m_rest').textContent = order.restaurant_name;
    document.getElementById('m_amt').textContent = '₹' + parseFloat(order.total_amount).toFixed(2);
    
    let addrText = 'N/A';
    try {
        const addrObj = JSON.parse(order.delivery_address_json);
        addrText = (addrObj.address_line || '') + (addrObj.landmark ? ', ' + addrObj.landmark : '') + (addrObj.city ? ', ' + addrObj.city : '');
    } catch(e) {
        addrText = order.delivery_address_json;
    }
    document.getElementById('m_addr').textContent = addrText;
    document.getElementById('m_otp').textContent = order.delivery_otp;

    const select = document.getElementById('m_statusSelect');
    select.value = order.order_status;
    select.onchange = function() {
        document.getElementById('cancelReasonBox').style.display = (this.value === 'cancelled') ? 'block' : 'none';
    };

    document.getElementById('orderActionModal').style.display = 'flex';
}

function closeActionModal() {
    document.getElementById('orderActionModal').style.display = 'none';
}
</script>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
