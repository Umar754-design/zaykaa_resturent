<?php
// ===================================================
// Live Order Tracking & Status Page - Zayka Platform
// ===================================================
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

$user = current_user();
if (!$user) {
    header("Location: login.php");
    exit;
}

$orderId = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT o.*, r.name as rest_name, r.address as rest_address, r.phone as rest_phone, r.latitude as rest_lat, r.longitude as rest_lng,
                              u.name as rider_name, u.phone as rider_phone, dp.vehicle_number, dp.vehicle_type, dp.rating as rider_rating
                       FROM orders o
                       JOIN restaurants r ON o.restaurant_id = r.id
                       LEFT JOIN delivery_partners dp ON o.delivery_partner_id = dp.id
                       LEFT JOIN users u ON dp.user_id = u.id
                       WHERE o.id = ?");
$stmt->execute([$orderId]);
$order = $stmt->fetch();

if (!$order) {
    die("<div style='padding:50px; text-align:center;'>Order not found. <a href='orders.php'>View All Orders</a></div>");
}

if ($user['role'] === 'customer' && $order['user_id'] != $user['id']) {
    die("<div style='padding:50px; text-align:center;'>Access Denied.</div>");
}

// Fetch items
$itemStmt = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ?");
$itemStmt->execute([$orderId]);
$orderItems = $itemStmt->fetchAll();

$dest = json_decode($order['delivery_address_json'], true);

include 'includes/header.php';
?>

<!-- Leaflet CSS & JS for Interactive Map -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>

<div style="background: #FAF8F2; min-height: 85vh; padding: 40px 0 80px;">
    <div class="container">
        
        <!-- Header Banner -->
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; margin-bottom: 24px;">
            <div>
                <div style="display: flex; align-items: center; gap: 10px;">
                    <h1 style="font-family: 'Playfair Display', serif; font-size: 28px; color: var(--primary-green); margin: 0;">
                        Live Order Tracking
                    </h1>
                    <span id="orderStatusBadge"><?php echo get_status_badge($order['order_status']); ?></span>
                </div>
                <p style="color: #666; margin: 4px 0 0; font-size: 14px;">
                    Order Number: <strong><?php echo htmlspecialchars($order['order_number']); ?></strong> | Placed on <?php echo date('d M Y, h:i A', strtotime($order['created_at'])); ?>
                </p>
            </div>

            <div style="display: flex; gap: 12px; align-items: center;">
                <a href="invoice.php?id=<?php echo $order['id']; ?>" target="_blank" 
                   style="padding: 9px 18px; background: #fff; border: 1px solid var(--light-border); border-radius: 20px; font-size: 13px; font-weight: 600; text-decoration: none; color: #333;">
                    🧾 View Invoice
                </a>
                <?php if (in_array($order['order_status'], ['placed', 'accepted'])): ?>
                    <button onclick="cancelOrder(<?php echo $order['id']; ?>)" 
                            style="padding: 9px 18px; background: #FDE8E8; border: 1px solid #F87171; border-radius: 20px; font-size: 13px; font-weight: 600; color: #9B1C1C; cursor: pointer;">
                        ✕ Cancel Order
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- Visual Timeline Card -->
        <div class="order-timeline-box" style="margin-bottom: 28px;">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <span style="font-size: 12px; font-weight: 700; color: #888; text-transform: uppercase;">Estimated Arrival</span>
                    <div style="font-family: 'Playfair Display', serif; font-size: 24px; font-weight: 700; color: var(--primary-green);">
                        ⏱️ <span id="etaMinutes"><?php echo (int)$order['estimated_delivery_time']; ?></span> Mins
                    </div>
                </div>

                <!-- 4-Digit Delivery OTP -->
                <div style="background: #FAF8F2; border: 2px dashed var(--gold); border-radius: 12px; padding: 10px 20px; text-align: center;">
                    <div style="font-size: 11px; font-weight: 700; color: #888; text-transform: uppercase;">Delivery Confirmation OTP</div>
                    <div style="font-size: 26px; font-weight: 800; letter-spacing: 4px; color: var(--primary-green); font-family: monospace;">
                        <?php echo htmlspecialchars($order['delivery_otp']); ?>
                    </div>
                    <div style="font-size: 10px; color: #777;">Share with rider upon delivery</div>
                </div>
            </div>

            <!-- Stages progression -->
            <?php
            $stages = [
                'placed'           => 'Placed',
                'accepted'         => 'Accepted',
                'preparing'        => 'Preparing',
                'ready_for_pickup' => 'Ready',
                'picked_up'        => 'Picked Up',
                'out_for_delivery' => 'On the Way',
                'delivered'        => 'Delivered'
            ];
            $stageKeys = array_keys($stages);
            $currentIdx = array_search($order['order_status'], $stageKeys);
            if ($currentIdx === false) $currentIdx = 0;
            ?>
            <div class="timeline-stages" id="timelineStagesWrap">
                <div class="timeline-progress-bar" id="timelineProgressBar" style="width: <?php echo ($order['order_status'] === 'delivered') ? '100%' : (($currentIdx / (count($stages) - 1)) * 100) . '%'; ?>;"></div>
                <?php foreach ($stageKeys as $sIdx => $stKey): 
                    $isCompleted = ($sIdx <= $currentIdx && $order['order_status'] !== 'cancelled');
                    $isCurrent = ($sIdx === $currentIdx && $order['order_status'] !== 'cancelled');
                ?>
                    <div class="stage-item <?php echo $isCompleted ? 'completed' : ''; ?> <?php echo $isCurrent ? 'current' : ''; ?>" id="stage-<?php echo $stKey; ?>">
                        <div class="stage-dot">
                            <?php echo $isCompleted ? '✓' : ($sIdx + 1); ?>
                        </div>
                        <span><?php echo $stages[$stKey]; ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Grid: Map & Order Details -->
        <div class="order-status-wrap" style="display: flex; gap: 28px; flex-wrap: wrap; align-items: flex-start;">
            <!-- Left: Live Leaflet Map -->
            <div class="order-status-left" style="flex: 2; min-width: 0; width: 100%;">
                <div style="background: #fff; border-radius: 16px; border: 1px solid var(--light-border); overflow: hidden; box-shadow: var(--shadow-sm);">
                    <div style="padding: 16px 20px; border-bottom: 1px solid var(--light-border); display: flex; justify-content: space-between; align-items: center;">
                        <span style="font-weight: 700; color: var(--primary-green); font-size: 15px;">📍 Live Route & Delivery Partner Position</span>
                        <span style="font-size: 12px; color: #16A34A; font-weight: 600;">● Live GPS Radar Active</span>
                    </div>
                    <!-- Leaflet Container -->
                    <div id="trackingMap" style="width: 100%; height: 380px; background: #e5e3df;"></div>
                </div>

                <!-- Delivery Partner Details Card -->
                <?php if ($order['rider_name']): ?>
                <div style="background: #fff; border-radius: 16px; border: 1px solid var(--light-border); padding: 20px; margin-top: 20px; display: flex; justify-content: space-between; align-items: center; box-shadow: var(--shadow-sm);">
                    <div style="display: flex; align-items: center; gap: 16px;">
                        <div style="width: 50px; height: 50px; border-radius: 50%; background: #FAF8F2; display: flex; align-items: center; justify-content: center; font-size: 24px; border: 2px solid var(--gold);">
                            🛵
                        </div>
                        <div>
                            <div style="font-weight: 700; font-size: 16px; color: var(--primary-green);"><?php echo htmlspecialchars($order['rider_name']); ?></div>
                            <div style="font-size: 13px; color: #666;"><?php echo htmlspecialchars($order['vehicle_type'] . ' • ' . $order['vehicle_number']); ?></div>
                            <div style="font-size: 12px; color: #D97706; font-weight: 600; margin-top: 2px;">★ <?php echo number_format($order['rider_rating'], 1); ?> Delivery Rating</div>
                        </div>
                    </div>
                    <a href="tel:<?php echo htmlspecialchars($order['rider_phone']); ?>" 
                       style="padding: 10px 20px; background: var(--primary-green); color: #fff; border-radius: 20px; font-weight: 600; text-decoration: none; font-size: 13px; display: flex; align-items: center; gap: 6px;">
                        <span>📞</span> Call Rider
                    </a>
                </div>
                <?php endif; ?>
            </div>

            <!-- Right: Order Items & Delivery Location Details -->
            <div class="order-status-right" style="flex: 1; min-width: 0; width: 100%; display: flex; flex-direction: column; gap: 20px;">
                <!-- Destination Card -->
                <div style="background: #fff; border-radius: 16px; border: 1px solid var(--light-border); padding: 20px; box-shadow: var(--shadow-sm);">
                    <div style="font-size: 12px; font-weight: 700; color: #888; text-transform: uppercase; margin-bottom: 6px;">Delivery Address</div>
                    <div style="font-weight: 700; color: var(--primary-green); font-size: 14px; margin-bottom: 4px;">
                        🏠 <?php echo htmlspecialchars($dest['label'] ?? 'Home'); ?>
                    </div>
                    <div style="font-size: 13px; color: #555; line-height: 1.4;">
                        <?php echo htmlspecialchars($dest['address_line'] ?? ''); ?><br>
                        <?php if (!empty($dest['landmark'])): ?><em>Near <?php echo htmlspecialchars($dest['landmark']); ?></em><br><?php endif; ?>
                        <?php echo htmlspecialchars($dest['city'] ?? 'Dhanbad') . ' - ' . htmlspecialchars($dest['pincode'] ?? ''); ?>
                    </div>
                </div>

                <!-- Order Details Breakdown -->
                <div style="background: #fff; border-radius: 16px; border: 1px solid var(--light-border); padding: 20px; box-shadow: var(--shadow-sm);">
                    <div style="font-size: 12px; font-weight: 700; color: #888; text-transform: uppercase; margin-bottom: 12px;">Items Ordered</div>
                    
                    <div style="display: flex; flex-direction: column; gap: 10px; margin-bottom: 16px; font-size: 13px;">
                        <?php foreach ($orderItems as $it): ?>
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <span style="font-weight: 500; color: #2D3748;"><strong><?php echo (int)$it['quantity']; ?>x</strong> <?php echo htmlspecialchars($it['item_name']); ?></span>
                                <?php if (!empty($it['serving_size'])): ?>
                                    <div style="font-size: 11px; color: #64748B;"><strong style="color:var(--gold);">Serving:</strong> <?php echo htmlspecialchars($it['serving_size']); ?></div>
                                <?php endif; ?>
                            </div>
                            <span style="font-weight: 600; color: #333;"><?php echo format_price($it['total']); ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <div style="border-top: 1px dashed #eee; padding-top: 12px; display: flex; flex-direction: column; gap: 6px; font-size: 13px; color: #666;">
                        <div style="display: flex; justify-content: space-between;">
                            <span>Subtotal</span>
                            <span><?php echo format_price($order['subtotal']); ?></span>
                        </div>
                        <?php if ($order['coupon_discount'] > 0): ?>
                        <div style="display: flex; justify-content: space-between; color: #16A34A;">
                            <span>Discount</span>
                            <span>-<?php echo format_price($order['coupon_discount']); ?></span>
                        </div>
                        <?php endif; ?>
                        <div style="display: flex; justify-content: space-between;">
                            <span>Delivery & Platform Fee</span>
                            <span><?php echo format_price($order['delivery_fee'] + $order['platform_fee']); ?></span>
                        </div>
                        <div style="display: flex; justify-content: space-between;">
                            <span>Taxes (GST)</span>
                            <span><?php echo format_price($order['tax_amount']); ?></span>
                        </div>
                        <div style="display: flex; justify-content: space-between; font-size: 16px; font-weight: 700; color: var(--primary-green); margin-top: 8px; border-top: 1px solid #eee; padding-top: 8px;">
                            <span>Total Paid</span>
                            <span><?php echo format_price($order['total_amount']); ?></span>
                        </div>
                    </div>
                </div>

                <!-- Rating CTA if Delivered -->
                <?php if ($order['order_status'] === 'delivered'): ?>
                <a href="review.php?order_id=<?php echo $order['id']; ?>" 
                   style="display: block; text-align: center; padding: 14px; background: var(--gold); color: #fff; border-radius: 20px; font-weight: 700; text-decoration: none; font-size: 14px; box-shadow: var(--shadow-sm);">
                    ⭐ Rate Restaurant & Delivery Partner
                </a>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>

<script>
const orderId = <?php echo $order['id']; ?>;
let map, restMarker, destMarker, riderMarker, routeLine;

document.addEventListener('DOMContentLoaded', function() {
    initTrackingMap();
    // Poll status every 4 seconds
    setInterval(pollOrderState, 4000);
});

function initTrackingMap() {
    const restLat = <?php echo (float)$order['rest_lat']; ?>;
    const restLng = <?php echo (float)$order['rest_lng']; ?>;
    const destLat = <?php echo (float)($dest['latitude'] ?? 23.798200); ?>;
    const destLng = <?php echo (float)($dest['longitude'] ?? 86.432000); ?>;

    map = L.map('trackingMap').setView([restLat, restLng], 14);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors'
    }).addTo(map);

    // Custom Icon helper
    const makeIcon = (emoji) => L.divIcon({
        className: 'custom-map-pin',
        html: `<div style="background:#fff; border:2px solid #063F2B; border-radius:50%; width:36px; height:36px; display:flex; align-items:center; justify-content:center; font-size:20px; box-shadow:0 3px 8px rgba(0,0,0,0.3);">${emoji}</div>`,
        iconSize: [36, 36],
        iconAnchor: [18, 18]
    });

    restMarker = L.marker([restLat, restLng], { icon: makeIcon('🍳') })
        .addTo(map)
        .bindPopup(`<strong><?php echo addslashes($order['rest_name']); ?></strong><br>Restaurant Kitchen`);

    destMarker = L.marker([destLat, destLng], { icon: makeIcon('🏠') })
        .addTo(map)
        .bindPopup(`<strong>Delivery Destination</strong><br><?php echo addslashes($dest['address_line'] ?? ''); ?>`);

    // Draw route connecting points
    routeLine = L.polyline([[restLat, restLng], [destLat, destLng]], {
        color: '#063F2B',
        weight: 4,
        dashArray: '8, 8'
    }).addTo(map);

    map.fitBounds(routeLine.getBounds(), { padding: [40, 40] });

    // Initial rider marker position
    riderMarker = L.marker([restLat, restLng], { icon: makeIcon('🛵') })
        .addTo(map)
        .bindPopup(`<strong>Delivery Partner</strong>`);
}

function pollOrderState() {
    fetch(`api/tracking.php?order_id=${orderId}`)
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                // Update badge
                const badge = document.getElementById('orderStatusBadge');
                if (badge) badge.innerHTML = data.status_badge;

                // Update timeline progress bar
                const bar = document.getElementById('timelineProgressBar');
                if (bar) bar.style.width = data.progress_percent + '%';

                // Update ETA
                const eta = document.getElementById('etaMinutes');
                if (eta && data.estimated_delivery_time) {
                    eta.innerText = data.estimated_delivery_time;
                }

                // Update rider position
                if (data.rider && riderMarker) {
                    riderMarker.setLatLng([data.rider.lat, data.rider.lng]);
                }
            }
        })
        .catch(() => {});
}

function cancelOrder(id) {
    const reason = prompt('Please enter a cancellation reason:');
    if (reason) {
        const formData = new FormData();
        formData.append('action', 'cancel_order');
        formData.append('order_id', id);
        formData.append('reason', reason);

        fetch('api/orders.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message, 'success');
                    setTimeout(() => window.location.reload(), 800);
                } else {
                    showToast(data.message, 'error');
                }
            });
    }
}
</script>

<?php include 'includes/footer.php'; ?>
