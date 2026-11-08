<?php
// ===================================================
// Customer Support & Helpdesk - Zayka Platform
// ===================================================
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

$user = current_user();
if (!$user) {
    header("Location: login.php?redirect=support.php");
    exit;
}

// Handle new ticket submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_ticket'])) {
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $orderId = !empty($_POST['order_id']) ? (int)$_POST['order_id'] : null;

    if ($subject && $message) {
        $ticketNum = 'TCK-' . date('Y') . '-' . rand(100, 999);
        $ins = $pdo->prepare("INSERT INTO support_tickets (ticket_number, user_id, role, order_id, subject, message, status, priority) 
                              VALUES (?, ?, 'customer', ?, ?, ?, 'open', 'medium')");
        $ins->execute([$ticketNum, $user['id'], $orderId, $subject, $message]);
        set_flash('success', "Ticket {$ticketNum} created! Our support team will respond shortly.");
        header("Location: support.php");
        exit;
    } else {
        set_flash('error', 'Please fill in both subject and description.');
    }
}

// Fetch user tickets with replies
$stmt = $pdo->prepare("SELECT * FROM support_tickets WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$user['id']]);
$tickets = $stmt->fetchAll();

// Fetch recent orders for dropdown
$ordStmt = $pdo->prepare("SELECT id, order_number FROM orders WHERE user_id = ? ORDER BY id DESC LIMIT 5");
$ordStmt->execute([$user['id']]);
$userOrders = $ordStmt->fetchAll();

include 'includes/header.php';
?>

<div style="background: #FAF8F2; min-height: 85vh; padding: 40px 0 80px;">
    <div class="container" style="max-width: 900px;">
        <h1 style="font-family: 'Playfair Display', serif; font-size: 32px; color: var(--primary-green); margin: 0 0 8px;">
            Help & Customer Support 💬
        </h1>
        <p style="color: #666; font-size: 14px; margin: 0 0 30px;">We are here to assist with your deliveries, refunds, and queries 24/7</p>

        <div style="display: flex; gap: 30px; flex-wrap: wrap; align-items: flex-start;">
            <!-- Ticket Creation Form -->
            <div style="flex: 1; min-width: 300px; background: #fff; border-radius: 16px; border: 1px solid var(--light-border); padding: 24px; box-shadow: var(--shadow-sm);">
                <h3 style="font-family: 'Playfair Display', serif; font-size: 20px; color: var(--primary-green); margin: 0 0 16px;">
                    Open a Support Ticket
                </h3>
                
                <form method="POST">
                    <input type="hidden" name="create_ticket" value="1">
                    
                    <div style="margin-bottom: 14px;">
                        <label style="font-size: 13px; font-weight: 600; color: #444; display: block; margin-bottom: 4px;">Related Order (Optional)</label>
                        <select name="order_id" style="width: 100%; padding: 10px; border: 1px solid var(--light-border); border-radius: 8px; font-size: 13px;">
                            <option value="">-- General Inquiry --</option>
                            <?php foreach ($userOrders as $uo): ?>
                                <option value="<?php echo $uo['id']; ?>">Order #<?php echo htmlspecialchars($uo['order_number']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div style="margin-bottom: 14px;">
                        <label style="font-size: 13px; font-weight: 600; color: #444; display: block; margin-bottom: 4px;">Subject *</label>
                        <input type="text" name="subject" placeholder="e.g. Missing item, Delivery delay, Coupon issue" required 
                               style="width: 100%; padding: 10px; border: 1px solid var(--light-border); border-radius: 8px; font-size: 13px;">
                    </div>

                    <div style="margin-bottom: 18px;">
                        <label style="font-size: 13px; font-weight: 600; color: #444; display: block; margin-bottom: 4px;">Message *</label>
                        <textarea name="message" placeholder="Please describe what happened so we can assist you quickly..." required 
                                  style="width: 100%; height: 100px; padding: 10px; border: 1px solid var(--light-border); border-radius: 8px; font-size: 13px;"></textarea>
                    </div>

                    <button type="submit" style="width: 100%; padding: 12px; background: var(--primary-green); color: #fff; border-radius: 20px; font-weight: 700; border: none; cursor: pointer;">
                        Submit Ticket 📨
                    </button>
                </form>
            </div>

            <!-- Existing Tickets List -->
            <div style="flex: 1.5; min-width: 320px; display: flex; flex-direction: column; gap: 16px;">
                <h3 style="font-family: 'Playfair Display', serif; font-size: 20px; color: var(--primary-green); margin: 0;">
                    My Support History
                </h3>

                <?php if (!empty($tickets)): ?>
                    <?php foreach ($tickets as $t): 
                        // Fetch replies
                        $repStmt = $pdo->prepare("SELECT tr.*, u.name as replier_name, u.role as replier_role 
                                                 FROM ticket_replies tr 
                                                 JOIN users u ON tr.user_id = u.id 
                                                 WHERE tr.ticket_id = ? ORDER BY tr.created_at ASC");
                        $repStmt->execute([$t['id']]);
                        $replies = $repStmt->fetchAll();
                    ?>
                    <div style="background: #fff; border-radius: 14px; border: 1px solid var(--light-border); padding: 20px; box-shadow: var(--shadow-sm);">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 10px;">
                            <div>
                                <span style="font-size: 11px; font-weight: 700; color: #888; text-transform: uppercase;">Ticket #<?php echo htmlspecialchars($t['ticket_number']); ?></span>
                                <h4 style="margin: 2px 0 0; color: var(--primary-green); font-size: 16px;"><?php echo htmlspecialchars($t['subject']); ?></h4>
                            </div>
                            <span style="font-size: 11px; font-weight: 700; padding: 3px 10px; border-radius: 12px; background: <?php echo ($t['status'] === 'resolved') ? '#DEF7EC' : '#FEF3C7'; ?>; color: <?php echo ($t['status'] === 'resolved') ? '#046C4E' : '#B45309'; ?>;">
                                ● <?php echo strtoupper($t['status']); ?>
                            </span>
                        </div>

                        <p style="font-size: 13px; color: #555; margin: 0 0 14px; line-height: 1.4;">
                            <?php echo nl2br(htmlspecialchars($t['message'])); ?>
                        </p>

                        <!-- Thread Replies -->
                        <?php if (!empty($replies)): ?>
                            <div style="background: #FAF8F2; border-radius: 10px; padding: 12px 16px; border: 1px solid var(--light-border); display: flex; flex-direction: column; gap: 10px; margin-top: 10px;">
                                <?php foreach ($replies as $rep): ?>
                                    <div style="font-size: 12px;">
                                        <strong style="color: var(--primary-green);"><?php echo htmlspecialchars($rep['replier_name']); ?> (Support Team):</strong>
                                        <p style="margin: 2px 0 0; color: #444;"><?php echo htmlspecialchars($rep['message']); ?></p>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <div style="font-size: 11px; color: #888; margin-top: 10px;">
                            Created on <?php echo date('d M Y, h:i A', strtotime($t['created_at'])); ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="background: #fff; border-radius: 14px; border: 1px solid var(--light-border); padding: 40px 20px; text-align: center; color: #888; font-size: 14px;">
                        No tickets submitted. Everything running smoothly!
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>

<?php include 'includes/footer.php'; ?>
