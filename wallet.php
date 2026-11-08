<?php
// ===================================================
// Zayka Wallet & Referral Credits - Zayka Platform
// ===================================================
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

$user = current_user();
if (!$user) {
    header("Location: login.php?redirect=wallet.php");
    exit;
}

// Fetch wallet transactions
$tStmt = $pdo->prepare("SELECT * FROM wallet_transactions WHERE user_id = ? ORDER BY created_at DESC");
$tStmt->execute([$user['id']]);
$transactions = $tStmt->fetchAll();

// Handle mock add money to wallet
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_money'])) {
    $amount = max(50, min(5000, (float)($_POST['amount'] ?? 100)));
    $pdo->beginTransaction();
    try {
        $pdo->prepare("UPDATE users SET wallet_balance = wallet_balance + ? WHERE id = ?")->execute([$amount, $user['id']]);
        $pdo->prepare("INSERT INTO wallet_transactions (user_id, type, amount, description) VALUES (?, 'credit', ?, ?)")
            ->execute([$user['id'], $amount, 'Added money to wallet via UPI']);
        $pdo->commit();
        set_flash('success', "₹{$amount} added to your Zayka Wallet!");
        header("Location: wallet.php");
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        set_flash('error', 'Failed to add money: ' . $e->getMessage());
    }
}

include 'includes/header.php';
?>

<div style="background: #FAF8F2; min-height: 85vh; padding: 40px 0 80px;">
    <div class="container" style="max-width: 800px;">
        <h1 style="font-family: 'Playfair Display', serif; font-size: 32px; color: var(--primary-green); margin: 0 0 24px;">
            Zayka Wallet & Credits 💳
        </h1>

        <!-- Balance Card -->
        <div style="background: linear-gradient(135deg, #063F2B 0%, #0B5138 100%); color: #fff; border-radius: 20px; padding: 30px; margin-bottom: 30px; box-shadow: var(--shadow-md); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 20px;">
            <div>
                <div style="font-size: 13px; color: var(--light-gold); text-transform: uppercase; font-weight: 600; letter-spacing: 1px;">Available Balance</div>
                <div style="font-family: 'Playfair Display', serif; font-size: 38px; font-weight: 700; margin: 4px 0;">
                    ₹<?php echo number_format($user['wallet_balance'], 2); ?>
                </div>
                <div style="font-size: 12px; opacity: 0.8;">Instant 1-click checkout with zero payment failures</div>
            </div>

            <form method="POST" style="display: flex; gap: 8px; align-items: center;">
                <input type="number" name="amount" value="200" min="50" max="5000" step="50" 
                       style="padding: 10px 14px; border-radius: 20px; border: none; font-size: 14px; width: 100px; text-align: center; font-weight: bold;">
                <button type="submit" name="add_money" value="1" 
                        style="padding: 10px 20px; background: var(--gold); color: #fff; border-radius: 20px; font-weight: 700; font-size: 13px; border: none; cursor: pointer;">
                    + Add Money
                </button>
            </form>
        </div>

        <!-- Referral Banner -->
        <div style="background: #fff; border-radius: 16px; border: 1px dashed var(--gold); padding: 24px; margin-bottom: 30px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
            <div>
                <div style="font-weight: 700; font-size: 16px; color: var(--primary-green);">Invite Friends & Earn ₹50 Cash!</div>
                <p style="color: #666; font-size: 13px; margin: 4px 0 0;">Share your referral code. When a friend joins and orders, both of you get ₹50 bonus credits!</p>
            </div>
            <div style="display: flex; align-items: center; gap: 10px; background: #FAF8F2; padding: 10px 18px; border-radius: 12px; border: 1px solid var(--light-border);">
                <span style="font-size: 14px; color: #888;">Code:</span>
                <strong style="font-family: monospace; font-size: 18px; color: var(--primary-green); letter-spacing: 2px;">
                    <?php echo htmlspecialchars($user['referral_code'] ?: 'ZAYKA' . $user['id']); ?>
                </strong>
                <button onclick="navigator.clipboard.writeText('<?php echo htmlspecialchars($user['referral_code'] ?: 'ZAYKA' . $user['id']); ?>'); showToast('Referral code copied!', 'success');" 
                        style="padding: 4px 10px; background: var(--primary-green); color: #fff; border-radius: 6px; font-size: 11px; cursor: pointer;">
                    Copy
                </button>
            </div>
        </div>

        <!-- Transactions History -->
        <div style="background: #fff; border-radius: 16px; border: 1px solid var(--light-border); padding: 24px; box-shadow: var(--shadow-sm);">
            <h3 style="font-family: 'Playfair Display', serif; font-size: 20px; color: var(--primary-green); margin: 0 0 16px;">
                Transaction Ledger
            </h3>

            <?php if (!empty($transactions)): ?>
                <div style="display: flex; flex-direction: column; gap: 12px;">
                    <?php foreach ($transactions as $t): ?>
                    <div style="display: flex; justify-content: space-between; align-items: center; padding-bottom: 12px; border-bottom: 1px solid #F1EDE2;">
                        <div>
                            <div style="font-weight: 600; color: #333; font-size: 14px;"><?php echo htmlspecialchars($t['description']); ?></div>
                            <div style="font-size: 12px; color: #888;"><?php echo date('d M Y, h:i A', strtotime($t['created_at'])); ?></div>
                        </div>
                        <div style="font-weight: 700; font-size: 16px; color: <?php echo ($t['type'] === 'credit') ? '#16A34A' : '#DC2626'; ?>;">
                            <?php echo ($t['type'] === 'credit') ? '+' : '-'; ?><?php echo format_price($t['amount']); ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div style="text-align: center; padding: 30px; color: #888; font-size: 14px;">
                    No transactions yet.
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
