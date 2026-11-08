<?php
// ===================================================
// Admin: Coupons & Promotional Campaigns
// ===================================================
require_once __DIR__ . '/includes/admin-header.php';

// Handle POST actions: Create, Update, Toggle, Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create' || $action === 'update') {
        $code = strtoupper(trim($_POST['code'] ?? ''));
        $title = trim($_POST['title'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        $discount_type = in_array($_POST['discount_type'] ?? '', ['percentage', 'flat']) ? $_POST['discount_type'] : 'percentage';
        $discount_value = floatval($_POST['discount_value'] ?? 0);
        $max_discount = !empty($_POST['max_discount']) ? floatval($_POST['max_discount']) : null;
        $min_order_amount = floatval($_POST['min_order_amount'] ?? 0);
        $restaurant_id = !empty($_POST['restaurant_id']) ? (int)$_POST['restaurant_id'] : null;
        $usage_limit = (int)($_POST['usage_limit'] ?? 100);
        $valid_from = $_POST['valid_from'] ?? date('Y-m-d');
        $valid_until = $_POST['valid_until'] ?? date('Y-m-d', strtotime('+30 days'));
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        if (empty($code) || empty($title) || $discount_value <= 0) {
            set_flash('error', 'Please fill in all required coupon fields with valid values.');
        } else {
            if ($action === 'create') {
                // Check duplicate code
                $chk = $pdo->prepare("SELECT id FROM coupons WHERE code = ?");
                $chk->execute([$code]);
                if ($chk->fetch()) {
                    set_flash('error', "Coupon code '{$code}' already exists. Please choose a unique code.");
                } else {
                    $ins = $pdo->prepare("INSERT INTO coupons (code, title, description, discount_type, discount_value, max_discount, min_order_amount, restaurant_id, usage_limit, valid_from, valid_until, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $ins->execute([$code, $title, $desc, $discount_type, $discount_value, $max_discount, $min_order_amount, $restaurant_id, $usage_limit, $valid_from, $valid_until, $is_active]);
                    set_flash('success', "Coupon campaign <strong>{$code}</strong> created successfully!");
                }
            } else {
                $coupon_id = (int)$_POST['coupon_id'];
                $upd = $pdo->prepare("UPDATE coupons SET code = ?, title = ?, description = ?, discount_type = ?, discount_value = ?, max_discount = ?, min_order_amount = ?, restaurant_id = ?, usage_limit = ?, valid_from = ?, valid_until = ?, is_active = ? WHERE id = ?");
                $upd->execute([$code, $title, $desc, $discount_type, $discount_value, $max_discount, $min_order_amount, $restaurant_id, $usage_limit, $valid_from, $valid_until, $is_active, $coupon_id]);
                set_flash('success', "Coupon <strong>{$code}</strong> updated successfully!");
            }
        }
    } elseif ($action === 'toggle_active') {
        $coupon_id = (int)$_POST['coupon_id'];
        $pdo->prepare("UPDATE coupons SET is_active = IF(is_active = 1, 0, 1) WHERE id = ?")->execute([$coupon_id]);
        set_flash('success', "Coupon status toggled successfully.");
    } elseif ($action === 'delete') {
        $coupon_id = (int)$_POST['coupon_id'];
        $pdo->prepare("DELETE FROM coupons WHERE id = ?")->execute([$coupon_id]);
        set_flash('success', "Coupon deleted permanently.");
    }
    header('Location: coupons.php');
    exit;
}

// Fetch all coupons with restaurant name if scoped
$sql = "SELECT c.*, r.name as restaurant_name 
        FROM coupons c 
        LEFT JOIN restaurants r ON c.restaurant_id = r.id 
        ORDER BY c.created_at DESC";
$coupons = $pdo->query($sql)->fetchAll();

// Fetch restaurants list for dropdown
$restaurants = $pdo->query("SELECT id, name FROM restaurants ORDER BY name ASC")->fetchAll();
?>

<div class="container" style="padding: 24px 20px; max-width: 1280px;">
    <!-- Top Action Bar -->
    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 24px; flex-wrap: wrap; gap: 16px;">
        <div>
            <h1 style="font-size: 26px; font-weight: 700; color: #0B192C; margin: 0 0 6px 0;">Coupons & Promotional Offers</h1>
            <p style="color: #64748B; font-size: 14px; margin: 0;">Create and oversee discount promo codes, maximum caps, minimum cart limits, and restaurant scopes.</p>
        </div>
        <button onclick="openCreateCouponModal()" class="btn btn-primary" style="padding: 10px 18px; font-size: 14px; font-weight: 600;">
            + Create New Coupon
        </button>
    </div>

    <!-- Coupons Grid/Table -->
    <div style="background: #fff; border-radius: 12px; border: 1px solid #E2E8F0; overflow: hidden; box-shadow: var(--shadow-sm);">
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 13px;">
                <thead>
                    <tr style="background: #F8FAFC; border-bottom: 1px solid #E2E8F0; color: #475569; font-weight: 600; font-size: 11px; text-transform: uppercase;">
                        <th style="padding: 14px 16px;">Coupon Code & Title</th>
                        <th style="padding: 14px 16px;">Discount Rule</th>
                        <th style="padding: 14px 16px;">Min Order</th>
                        <th style="padding: 14px 16px;">Scope</th>
                        <th style="padding: 14px 16px;">Redemptions</th>
                        <th style="padding: 14px 16px;">Validity Period</th>
                        <th style="padding: 14px 16px;">Status</th>
                        <th style="padding: 14px 16px; text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($coupons)): ?>
                        <tr>
                            <td colspan="8" style="padding: 40px; text-align: center; color: #94A3B8;">
                                No coupons created yet. Click "+ Create New Coupon" to start an offer.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($coupons as $c): ?>
                            <tr style="border-bottom: 1px solid #F1F5F9;">
                                <td style="padding: 14px 16px;">
                                    <div style="display: flex; align-items: center; gap: 8px;">
                                        <span style="font-family: monospace; font-size: 14px; font-weight: 700; background: #EEF2FF; color: #4338CA; padding: 4px 8px; border-radius: 6px; border: 1px dashed #A5B4FC;">
                                            <?php echo htmlspecialchars($c['code']); ?>
                                        </span>
                                    </div>
                                    <div style="font-weight: 600; color: #1E293B; margin-top: 4px;"><?php echo htmlspecialchars($c['title']); ?></div>
                                    <div style="font-size: 11px; color: #64748B;"><?php echo htmlspecialchars($c['description']); ?></div>
                                </td>
                                <td style="padding: 14px 16px;">
                                    <strong style="color: #063F2B; font-size: 14px;">
                                        <?php if ($c['discount_type'] === 'percentage'): ?>
                                            <?php echo (int)$c['discount_value']; ?>% OFF
                                        <?php else: ?>
                                            <?php echo format_price($c['discount_value']); ?> FLAT OFF
                                        <?php endif; ?>
                                    </strong>
                                    <?php if ($c['max_discount']): ?>
                                        <div style="font-size: 11px; color: #64748B;">Max up to <?php echo format_price($c['max_discount']); ?></div>
                                    <?php endif; ?>
                                </td>
                                <td style="padding: 14px 16px;">
                                    <span style="font-weight: 600; color: #334155;">
                                        <?php echo format_price($c['min_order_amount']); ?>
                                    </span>
                                </td>
                                <td style="padding: 14px 16px;">
                                    <?php if ($c['restaurant_name']): ?>
                                        <span style="display: inline-block; padding: 2px 8px; background: #FEF3C7; color: #92400E; border-radius: 10px; font-size: 11px; font-weight: 600;">
                                            🍴 <?php echo htmlspecialchars($c['restaurant_name']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="display: inline-block; padding: 2px 8px; background: #E0E7FF; color: #3730A3; border-radius: 10px; font-size: 11px; font-weight: 600;">
                                            🌐 All Restaurants
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td style="padding: 14px 16px;">
                                    <div><strong><?php echo $c['used_count']; ?></strong> / <?php echo $c['usage_limit']; ?></div>
                                    <div style="font-size: 11px; color: #94A3B8;">uses</div>
                                </td>
                                <td style="padding: 14px 16px; font-size: 12px; color: #64748B;">
                                    <div><?php echo date('M d, Y', strtotime($c['valid_from'])); ?></div>
                                    <div>to <strong><?php echo date('M d, Y', strtotime($c['valid_until'])); ?></strong></div>
                                </td>
                                <td style="padding: 14px 16px;">
                                    <?php if ($c['is_active']): ?>
                                        <span style="display: inline-block; padding: 3px 8px; background: #DCFCE7; color: #15803D; border-radius: 12px; font-size: 11px; font-weight: 600;">Active</span>
                                    <?php else: ?>
                                        <span style="display: inline-block; padding: 3px 8px; background: #F1F5F9; color: #94A3B8; border-radius: 12px; font-size: 11px; font-weight: 600;">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td style="padding: 14px 16px; text-align: right;">
                                    <div style="display: flex; gap: 6px; justify-content: flex-end;">
                                        <button type="button" onclick='openEditCouponModal(<?php echo json_encode($c); ?>)' class="btn" style="padding: 4px 8px; font-size: 11px; background: #EFF6FF; color: #2563EB;">
                                            Edit
                                        </button>
                                        <form method="POST" style="margin: 0;">
                                            <input type="hidden" name="action" value="toggle_active">
                                            <input type="hidden" name="coupon_id" value="<?php echo $c['id']; ?>">
                                            <button type="submit" class="btn" style="padding: 4px 8px; font-size: 11px; <?php echo $c['is_active'] ? 'background: #FEF2F2; color: #DC2626;' : 'background: #F0FDF4; color: #16A34A;'; ?>">
                                                <?php echo $c['is_active'] ? 'Pause' : 'Activate'; ?>
                                            </button>
                                        </form>
                                        <form method="POST" style="margin: 0;" onsubmit="return confirm('Delete coupon <?php echo htmlspecialchars($c['code']); ?> permanently?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="coupon_id" value="<?php echo $c['id']; ?>">
                                            <button type="submit" class="btn" style="padding: 4px 8px; font-size: 11px; background: #F1F5F9; color: #64748B;">
                                                🗑️
                                            </button>
                                        </form>
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

<!-- Create / Edit Coupon Modal -->
<div id="couponModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 2000; align-items: center; justify-content: center; padding: 20px;">
    <div style="background: #fff; width: 100%; max-width: 550px; border-radius: 16px; padding: 24px; max-height: 90vh; overflow-y: auto; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.2);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
            <h3 id="couponModalTitle" style="margin: 0; font-size: 18px; color: #0B192C;">Create New Coupon</h3>
            <button type="button" onclick="closeCouponModal()" style="background: none; border: none; font-size: 20px; cursor: pointer; color: #94A3B8;">&times;</button>
        </div>

        <form method="POST">
            <input type="hidden" name="action" id="c_action" value="create">
            <input type="hidden" name="coupon_id" id="c_id" value="">

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 14px;">
                <div>
                    <label style="display: block; font-size: 12px; font-weight: 600; color: #334155; margin-bottom: 4px;">Coupon Code *</label>
                    <input type="text" name="code" id="c_code" required placeholder="e.g. FESTIVE50" style="width: 100%; padding: 8px 12px; border: 1px solid #CBD5E1; border-radius: 8px; font-size: 13px; text-transform: uppercase; font-weight: 700;">
                </div>
                <div>
                    <label style="display: block; font-size: 12px; font-weight: 600; color: #334155; margin-bottom: 4px;">Title *</label>
                    <input type="text" name="title" id="c_title" required placeholder="e.g. 50% Off On First 3 Orders" style="width: 100%; padding: 8px 12px; border: 1px solid #CBD5E1; border-radius: 8px; font-size: 13px;">
                </div>
            </div>

            <div style="margin-bottom: 14px;">
                <label style="display: block; font-size: 12px; font-weight: 600; color: #334155; margin-bottom: 4px;">Description</label>
                <input type="text" name="description" id="c_desc" placeholder="e.g. Valid on all restaurant orders above ₹249" style="width: 100%; padding: 8px 12px; border: 1px solid #CBD5E1; border-radius: 8px; font-size: 13px;">
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px; margin-bottom: 14px;">
                <div>
                    <label style="display: block; font-size: 12px; font-weight: 600; color: #334155; margin-bottom: 4px;">Discount Type</label>
                    <select name="discount_type" id="c_discount_type" style="width: 100%; padding: 8px; border: 1px solid #CBD5E1; border-radius: 8px; font-size: 13px;">
                        <option value="percentage">Percentage (%)</option>
                        <option value="flat">Flat Amount (₹)</option>
                    </select>
                </div>
                <div>
                    <label style="display: block; font-size: 12px; font-weight: 600; color: #334155; margin-bottom: 4px;">Discount Value *</label>
                    <input type="number" step="0.01" name="discount_value" id="c_discount_value" required placeholder="50 or 100" style="width: 100%; padding: 8px; border: 1px solid #CBD5E1; border-radius: 8px; font-size: 13px;">
                </div>
                <div>
                    <label style="display: block; font-size: 12px; font-weight: 600; color: #334155; margin-bottom: 4px;">Max Discount (₹)</label>
                    <input type="number" step="0.01" name="max_discount" id="c_max_discount" placeholder="e.g. 100" style="width: 100%; padding: 8px; border: 1px solid #CBD5E1; border-radius: 8px; font-size: 13px;">
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 14px;">
                <div>
                    <label style="display: block; font-size: 12px; font-weight: 600; color: #334155; margin-bottom: 4px;">Min Order Value (₹)</label>
                    <input type="number" step="0.01" name="min_order_amount" id="c_min_order" value="149.00" style="width: 100%; padding: 8px 12px; border: 1px solid #CBD5E1; border-radius: 8px; font-size: 13px;">
                </div>
                <div>
                    <label style="display: block; font-size: 12px; font-weight: 600; color: #334155; margin-bottom: 4px;">Usage Limit Total</label>
                    <input type="number" name="usage_limit" id="c_usage_limit" value="500" style="width: 100%; padding: 8px 12px; border: 1px solid #CBD5E1; border-radius: 8px; font-size: 13px;">
                </div>
            </div>

            <div style="margin-bottom: 14px;">
                <label style="display: block; font-size: 12px; font-weight: 600; color: #334155; margin-bottom: 4px;">Restaurant Scope</label>
                <select name="restaurant_id" id="c_restaurant_id" style="width: 100%; padding: 8px 12px; border: 1px solid #CBD5E1; border-radius: 8px; font-size: 13px;">
                    <option value="">🌐 Platform-wide (All Restaurants)</option>
                    <?php foreach ($restaurants as $r): ?>
                        <option value="<?php echo $r['id']; ?>">🍴 <?php echo htmlspecialchars($r['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 16px;">
                <div>
                    <label style="display: block; font-size: 12px; font-weight: 600; color: #334155; margin-bottom: 4px;">Valid From</label>
                    <input type="date" name="valid_from" id="c_valid_from" value="<?php echo date('Y-m-d'); ?>" style="width: 100%; padding: 8px 12px; border: 1px solid #CBD5E1; border-radius: 8px; font-size: 13px;">
                </div>
                <div>
                    <label style="display: block; font-size: 12px; font-weight: 600; color: #334155; margin-bottom: 4px;">Valid Until</label>
                    <input type="date" name="valid_until" id="c_valid_until" value="<?php echo date('Y-m-d', strtotime('+30 days')); ?>" style="width: 100%; padding: 8px 12px; border: 1px solid #CBD5E1; border-radius: 8px; font-size: 13px;">
                </div>
            </div>

            <div style="margin-bottom: 20px;">
                <label style="display: flex; align-items: center; gap: 8px; font-size: 13px; font-weight: 600; color: #334155; cursor: pointer;">
                    <input type="checkbox" name="is_active" id="c_is_active" value="1" checked style="width: 16px; height: 16px;">
                    Make Coupon Active Immediately
                </label>
            </div>

            <div style="display: flex; gap: 10px; justify-content: flex-end;">
                <button type="button" onclick="closeCouponModal()" class="btn" style="background: #E2E8F0; color: #334155;">Cancel</button>
                <button type="submit" class="btn btn-primary" id="c_submitBtn">Save Campaign</button>
            </div>
        </form>
    </div>
</div>

<script>
function openCreateCouponModal() {
    document.getElementById('couponModalTitle').textContent = 'Create New Coupon';
    document.getElementById('c_action').value = 'create';
    document.getElementById('c_id').value = '';
    document.getElementById('c_code').value = '';
    document.getElementById('c_title').value = '';
    document.getElementById('c_desc').value = '';
    document.getElementById('c_discount_type').value = 'percentage';
    document.getElementById('c_discount_value').value = '';
    document.getElementById('c_max_discount').value = '';
    document.getElementById('c_min_order').value = '149.00';
    document.getElementById('c_usage_limit').value = '500';
    document.getElementById('c_restaurant_id').value = '';
    document.getElementById('c_valid_from').value = '<?php echo date('Y-m-d'); ?>';
    document.getElementById('c_valid_until').value = '<?php echo date('Y-m-d', strtotime('+30 days')); ?>';
    document.getElementById('c_is_active').checked = true;
    document.getElementById('c_submitBtn').textContent = 'Create Coupon';

    document.getElementById('couponModal').style.display = 'flex';
}

function openEditCouponModal(coupon) {
    document.getElementById('couponModalTitle').textContent = 'Edit Coupon: ' + coupon.code;
    document.getElementById('c_action').value = 'update';
    document.getElementById('c_id').value = coupon.id;
    document.getElementById('c_code').value = coupon.code;
    document.getElementById('c_title').value = coupon.title;
    document.getElementById('c_desc').value = coupon.description || '';
    document.getElementById('c_discount_type').value = coupon.discount_type;
    document.getElementById('c_discount_value').value = coupon.discount_value;
    document.getElementById('c_max_discount').value = coupon.max_discount || '';
    document.getElementById('c_min_order').value = coupon.min_order_amount;
    document.getElementById('c_usage_limit').value = coupon.usage_limit;
    document.getElementById('c_restaurant_id').value = coupon.restaurant_id || '';
    document.getElementById('c_valid_from').value = coupon.valid_from;
    document.getElementById('c_valid_until').value = coupon.valid_until;
    document.getElementById('c_is_active').checked = (coupon.is_active == 1);
    document.getElementById('c_submitBtn').textContent = 'Update Coupon';

    document.getElementById('couponModal').style.display = 'flex';
}

function closeCouponModal() {
    document.getElementById('couponModal').style.display = 'none';
}
</script>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
