<?php
// ===================================================
// Admin: Customer Accounts Management
// ===================================================
require_once __DIR__ . '/includes/admin-header.php';

// Handle Actions: Status Toggle & Wallet Adjustment
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $user_id = (int)($_POST['user_id'] ?? 0);

    if ($action === 'toggle_status') {
        $stmt = $pdo->prepare("SELECT status, name FROM users WHERE id = ? AND role = 'customer'");
        $stmt->execute([$user_id]);
        $cust = $stmt->fetch();
        if ($cust) {
            $new_status = ($cust['status'] === 'active') ? 'suspended' : 'active';
            $upd = $pdo->prepare("UPDATE users SET status = ? WHERE id = ?");
            $upd->execute([$new_status, $user_id]);
            set_flash('success', "Customer <strong>{$cust['name']}</strong> is now <strong>" . strtoupper($new_status) . "</strong>.");
        }
    } elseif ($action === 'adjust_wallet') {
        $amount = floatval($_POST['amount'] ?? 0);
        $type = $_POST['type'] ?? 'credit';
        $desc = trim($_POST['description'] ?? 'Admin wallet adjustment');

        if ($amount > 0 && in_array($type, ['credit', 'debit'])) {
            $stmt = $pdo->prepare("SELECT wallet_balance, name FROM users WHERE id = ? AND role = 'customer'");
            $stmt->execute([$user_id]);
            $cust = $stmt->fetch();

            if ($cust) {
                if ($type === 'debit' && $cust['wallet_balance'] < $amount) {
                    set_flash('error', "Cannot debit " . format_price($amount) . ": Current balance is only " . format_price($cust['wallet_balance']));
                } else {
                    $new_bal = ($type === 'credit') ? ($cust['wallet_balance'] + $amount) : ($cust['wallet_balance'] - $amount);
                    $pdo->prepare("UPDATE users SET wallet_balance = ? WHERE id = ?")->execute([$new_bal, $user_id]);
                    $pdo->prepare("INSERT INTO wallet_transactions (user_id, type, amount, description) VALUES (?, ?, ?, ?)")
                        ->execute([$user_id, $type, $amount, $desc]);

                    set_flash('success', "Adjusted wallet for {$cust['name']} by " . ($type === 'credit' ? '+' : '-') . format_price($amount) . ". New balance: " . format_price($new_bal));
                }
            }
        } else {
            set_flash('error', 'Please provide a valid amount and transaction type.');
        }
    }
    header('Location: customers.php');
    exit;
}

// Search and filter
$search = trim($_GET['q'] ?? '');
$status_filter = $_GET['status'] ?? '';

$sql = "SELECT u.*, 
        COUNT(DISTINCT o.id) as order_count,
        COALESCE(SUM(CASE WHEN o.order_status = 'delivered' THEN o.total_amount ELSE 0 END), 0) as total_spent
        FROM users u
        LEFT JOIN orders o ON u.id = o.user_id
        WHERE u.role = 'customer'";

$params = [];
if (!empty($search)) {
    $sql .= " AND (u.name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
    $term = "%{$search}%";
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
}
if (!empty($status_filter)) {
    $sql .= " AND u.status = ?";
    $params[] = $status_filter;
}

$sql .= " GROUP BY u.id ORDER BY u.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$customers = $stmt->fetchAll();

// Stats
$total_customers = count($customers);
$total_wallet_liability = array_sum(array_column($customers, 'wallet_balance'));
$total_platform_orders = array_sum(array_column($customers, 'order_count'));
$total_customer_spend = array_sum(array_column($customers, 'total_spent'));
?>

<div class="container" style="padding: 24px 20px; max-width: 1280px;">
    <!-- Header -->
    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 24px; flex-wrap: wrap; gap: 16px;">
        <div>
            <h1 style="font-size: 26px; font-weight: 700; color: #0B192C; margin: 0 0 6px 0;">Customer Directory</h1>
            <p style="color: #64748B; font-size: 14px; margin: 0;">Manage registered customers, wallet balances, order track records, and access status.</p>
        </div>
    </div>

    <!-- Quick Stats -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 24px;">
        <div style="background: #fff; border-radius: 12px; padding: 18px 20px; border: 1px solid #E2E8F0; box-shadow: var(--shadow-sm);">
            <div style="font-size: 13px; color: #64748B; margin-bottom: 6px;">Total Customers</div>
            <div style="font-size: 24px; font-weight: 700; color: #0B192C;"><?php echo number_format($total_customers); ?></div>
        </div>
        <div style="background: #fff; border-radius: 12px; padding: 18px 20px; border: 1px solid #E2E8F0; box-shadow: var(--shadow-sm);">
            <div style="font-size: 13px; color: #64748B; margin-bottom: 6px;">Delivered Orders Placed</div>
            <div style="font-size: 24px; font-weight: 700; color: #063F2B;"><?php echo number_format($total_platform_orders); ?></div>
        </div>
        <div style="background: #fff; border-radius: 12px; padding: 18px 20px; border: 1px solid #E2E8F0; box-shadow: var(--shadow-sm);">
            <div style="font-size: 13px; color: #64748B; margin-bottom: 6px;">Total Customer Spend</div>
            <div style="font-size: 24px; font-weight: 700; color: #063F2B;"><?php echo format_price($total_customer_spend); ?></div>
        </div>
        <div style="background: #fff; border-radius: 12px; padding: 18px 20px; border: 1px solid #E2E8F0; box-shadow: var(--shadow-sm);">
            <div style="font-size: 13px; color: #64748B; margin-bottom: 6px;">Platform Wallet Float</div>
            <div style="font-size: 24px; font-weight: 700; color: #C9A24D;"><?php echo format_price($total_wallet_liability); ?></div>
        </div>
    </div>

    <!-- Search & Filter Bar -->
    <div style="background: #fff; border-radius: 12px; padding: 16px 20px; border: 1px solid #E2E8F0; margin-bottom: 24px;">
        <form method="GET" style="display: flex; gap: 14px; flex-wrap: wrap; align-items: center;">
            <div style="flex: 1; min-width: 250px;">
                <input type="text" name="q" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search customer by name, email, or phone..." style="width: 100%; padding: 10px 14px; border: 1px solid #CBD5E1; border-radius: 8px; font-size: 14px;">
            </div>
            <div style="min-width: 160px;">
                <select name="status" style="width: 100%; padding: 10px 14px; border: 1px solid #CBD5E1; border-radius: 8px; font-size: 14px; background: #fff;">
                    <option value="">All Statuses</option>
                    <option value="active" <?php echo ($status_filter === 'active') ? 'selected' : ''; ?>>Active Only</option>
                    <option value="suspended" <?php echo ($status_filter === 'suspended') ? 'selected' : ''; ?>>Suspended</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary" style="padding: 10px 20px; font-size: 14px;">Filter</button>
            <?php if (!empty($search) || !empty($status_filter)): ?>
                <a href="customers.php" class="btn" style="background: #E2E8F0; color: #334155; padding: 10px 16px; font-size: 14px;">Clear</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Customers Table -->
    <div style="background: #fff; border-radius: 12px; border: 1px solid #E2E8F0; overflow: hidden; box-shadow: var(--shadow-sm);">
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 14px;">
                <thead>
                    <tr style="background: #F8FAFC; border-bottom: 1px solid #E2E8F0; color: #475569; font-weight: 600; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px;">
                        <th style="padding: 14px 18px;">Customer</th>
                        <th style="padding: 14px 18px;">Contact Info</th>
                        <th style="padding: 14px 18px;">Wallet Balance</th>
                        <th style="padding: 14px 18px;">Orders Placed</th>
                        <th style="padding: 14px 18px;">Total Spent</th>
                        <th style="padding: 14px 18px;">Status</th>
                        <th style="padding: 14px 18px;">Joined</th>
                        <th style="padding: 14px 18px; text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($customers)): ?>
                        <tr>
                            <td colspan="8" style="padding: 36px; text-align: center; color: #94A3B8;">
                                No customers found matching your criteria.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($customers as $c): ?>
                            <tr style="border-bottom: 1px solid #F1F5F9;">
                                <td style="padding: 14px 18px;">
                                    <div style="display: flex; align-items: center; gap: 12px;">
                                        <div style="width: 38px; height: 38px; border-radius: 50%; background: #E2E8F0; display: flex; align-items: center; justify-content: center; font-weight: 700; color: #063F2B;">
                                            <?php echo strtoupper(substr($c['name'], 0, 1)); ?>
                                        </div>
                                        <div>
                                            <div style="font-weight: 600; color: #0F172A;"><?php echo htmlspecialchars($c['name']); ?></div>
                                            <div style="font-size: 12px; color: #94A3B8;">ID: #<?php echo $c['id']; ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td style="padding: 14px 18px;">
                                    <div style="color: #334155;"><?php echo htmlspecialchars($c['email']); ?></div>
                                    <div style="font-size: 12px; color: #64748B;"><?php echo htmlspecialchars($c['phone']); ?></div>
                                </td>
                                <td style="padding: 14px 18px;">
                                    <strong style="color: #063F2B;"><?php echo format_price($c['wallet_balance']); ?></strong>
                                </td>
                                <td style="padding: 14px 18px;">
                                    <span style="display: inline-block; padding: 2px 10px; background: #F1F5F9; border-radius: 12px; font-size: 12px; font-weight: 600; color: #334155;">
                                        <?php echo $c['order_count']; ?> orders
                                    </span>
                                </td>
                                <td style="padding: 14px 18px;">
                                    <strong><?php echo format_price($c['total_spent']); ?></strong>
                                </td>
                                <td style="padding: 14px 18px;">
                                    <?php if ($c['status'] === 'active'): ?>
                                        <span style="display: inline-block; padding: 4px 10px; background: #DCFCE7; color: #15803D; border-radius: 12px; font-size: 12px; font-weight: 600;">Active</span>
                                    <?php else: ?>
                                        <span style="display: inline-block; padding: 4px 10px; background: #FEE2E2; color: #B91C1C; border-radius: 12px; font-size: 12px; font-weight: 600;">Suspended</span>
                                    <?php endif; ?>
                                </td>
                                <td style="padding: 14px 18px; color: #64748B; font-size: 13px;">
                                    <?php echo date('M d, Y', strtotime($c['created_at'])); ?>
                                </td>
                                <td style="padding: 14px 18px; text-align: right;">
                                    <div style="display: flex; gap: 8px; justify-content: flex-end;">
                                        <button type="button" onclick="openWalletModal(<?php echo $c['id']; ?>, '<?php echo addslashes($c['name']); ?>', <?php echo $c['wallet_balance']; ?>)" class="btn" style="background: #EFF6FF; color: #2563EB; padding: 6px 12px; font-size: 12px; font-weight: 600;">
                                            💳 Adjust
                                        </button>
                                        <form method="POST" style="margin: 0;" onsubmit="return confirm('Change status for <?php echo htmlspecialchars($c['name']); ?>?');">
                                            <input type="hidden" name="action" value="toggle_status">
                                            <input type="hidden" name="user_id" value="<?php echo $c['id']; ?>">
                                            <button type="submit" class="btn" style="padding: 6px 12px; font-size: 12px; font-weight: 600; <?php echo ($c['status'] === 'active') ? 'background: #FEF2F2; color: #DC2626;' : 'background: #F0FDF4; color: #16A34A;'; ?>">
                                                <?php echo ($c['status'] === 'active') ? 'Suspend' : 'Activate'; ?>
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

<!-- Wallet Adjustment Modal -->
<div id="walletModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 2000; align-items: center; justify-content: center;">
    <div style="background: #fff; width: 90%; max-width: 440px; border-radius: 16px; padding: 24px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.2);">
        <h3 style="margin-top: 0; margin-bottom: 6px; font-size: 18px; color: #0B192C;">Adjust Customer Wallet</h3>
        <p style="font-size: 13px; color: #64748B; margin-bottom: 18px;">Customer: <strong id="modalCustName"></strong> (<span id="modalCustBal"></span>)</p>

        <form method="POST">
            <input type="hidden" name="action" value="adjust_wallet">
            <input type="hidden" name="user_id" id="modalUserId" value="">

            <div style="margin-bottom: 14px;">
                <label style="display: block; font-size: 13px; font-weight: 600; color: #334155; margin-bottom: 6px;">Transaction Type</label>
                <select name="type" style="width: 100%; padding: 10px; border: 1px solid #CBD5E1; border-radius: 8px; font-size: 14px;">
                    <option value="credit">Credit (Add Money / Goodwill)</option>
                    <option value="debit">Debit (Deduct Money)</option>
                </select>
            </div>

            <div style="margin-bottom: 14px;">
                <label style="display: block; font-size: 13px; font-weight: 600; color: #334155; margin-bottom: 6px;">Amount (₹)</label>
                <input type="number" step="0.01" name="amount" required min="1" placeholder="e.g. 100.00" style="width: 100%; padding: 10px; border: 1px solid #CBD5E1; border-radius: 8px; font-size: 14px;">
            </div>

            <div style="margin-bottom: 20px;">
                <label style="display: block; font-size: 13px; font-weight: 600; color: #334155; margin-bottom: 6px;">Reason / Notes</label>
                <input type="text" name="description" placeholder="e.g. Compensation for late delivery" required style="width: 100%; padding: 10px; border: 1px solid #CBD5E1; border-radius: 8px; font-size: 14px;">
            </div>

            <div style="display: flex; gap: 10px; justify-content: flex-end;">
                <button type="button" onclick="closeWalletModal()" class="btn" style="background: #E2E8F0; color: #334155;">Cancel</button>
                <button type="submit" class="btn btn-primary">Apply Adjustment</button>
            </div>
        </form>
    </div>
</div>

<script>
function openWalletModal(id, name, bal) {
    document.getElementById('modalUserId').value = id;
    document.getElementById('modalCustName').textContent = name;
    document.getElementById('modalCustBal').textContent = 'Current: ₹' + parseFloat(bal).toFixed(2);
    const modal = document.getElementById('walletModal');
    modal.style.display = 'flex';
}
function closeWalletModal() {
    document.getElementById('walletModal').style.display = 'none';
}
</script>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
