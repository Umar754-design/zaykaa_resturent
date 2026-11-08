<?php
// ===================================================
// Admin: Unified Support Desk & Dispute Resolution
// ===================================================
require_once __DIR__ . '/includes/admin-header.php';

// Handle POST reply or status change
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $ticket_id = (int)($_POST['ticket_id'] ?? 0);

    if ($action === 'reply' && $ticket_id > 0) {
        $reply_msg = trim($_POST['message'] ?? '');
        $new_status = $_POST['status'] ?? '';

        if (!empty($reply_msg)) {
            $pdo->prepare("INSERT INTO ticket_replies (ticket_id, user_id, message) VALUES (?, ?, ?)")
                ->execute([$ticket_id, $user['id'], $reply_msg]);
        }

        if (!empty($new_status)) {
            $pdo->prepare("UPDATE support_tickets SET status = ? WHERE id = ?")->execute([$new_status, $ticket_id]);
        }

        set_flash('success', "Ticket updated and reply sent successfully.");
    } elseif ($action === 'quick_status' && $ticket_id > 0) {
        $status = $_POST['status'] ?? 'resolved';
        $pdo->prepare("UPDATE support_tickets SET status = ? WHERE id = ?")->execute([$status, $ticket_id]);
        set_flash('success', "Ticket marked as " . strtoupper($status));
    }
    header('Location: support.php' . (isset($_GET['ticket_id']) ? '?ticket_id=' . (int)$_GET['ticket_id'] : ''));
    exit;
}

// Active ticket selection for discussion view
$selected_ticket_id = (int)($_GET['ticket_id'] ?? 0);
$status_filter = $_GET['status'] ?? '';
$role_filter = $_GET['role'] ?? '';

// Build list query
$sql = "SELECT st.*, u.name as user_name, u.email as user_email, u.phone as user_phone, o.order_number
        FROM support_tickets st
        JOIN users u ON st.user_id = u.id
        LEFT JOIN orders o ON st.order_id = o.id
        WHERE 1=1";
$params = [];

if (!empty($status_filter)) {
    $sql .= " AND st.status = ?";
    $params[] = $status_filter;
}
if (!empty($role_filter)) {
    $sql .= " AND st.role = ?";
    $params[] = $role_filter;
}

$sql .= " ORDER BY (st.status = 'open') DESC, (st.priority = 'high') DESC, st.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tickets = $stmt->fetchAll();

// If no ticket selected and list is non-empty, default to first ticket
if ($selected_ticket_id === 0 && !empty($tickets)) {
    $selected_ticket_id = $tickets[0]['id'];
}

// Load selected ticket details & replies
$active_ticket = null;
$replies = [];
if ($selected_ticket_id > 0) {
    $t_stmt = $pdo->prepare("SELECT st.*, u.name as user_name, u.email as user_email, u.phone as user_phone, o.order_number, o.total_amount
                             FROM support_tickets st
                             JOIN users u ON st.user_id = u.id
                             LEFT JOIN orders o ON st.order_id = o.id
                             WHERE st.id = ?");
    $t_stmt->execute([$selected_ticket_id]);
    $active_ticket = $t_stmt->fetch();

    if ($active_ticket) {
        $r_stmt = $pdo->prepare("SELECT tr.*, u.name as author_name, u.role as author_role 
                                 FROM ticket_replies tr 
                                 JOIN users u ON tr.user_id = u.id 
                                 WHERE tr.ticket_id = ? ORDER BY tr.created_at ASC");
        $r_stmt->execute([$selected_ticket_id]);
        $replies = $r_stmt->fetchAll();
    }
}

// Counts
$open_count = $pdo->query("SELECT COUNT(*) FROM support_tickets WHERE status = 'open'")->fetchColumn();
$resolved_count = $pdo->query("SELECT COUNT(*) FROM support_tickets WHERE status = 'resolved'")->fetchColumn();
?>

<div class="container" style="padding: 24px 20px; max-width: 1400px;">
    <!-- Page Header -->
    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 24px; flex-wrap: wrap; gap: 16px;">
        <div>
            <h1 style="font-size: 26px; font-weight: 700; color: #0B192C; margin: 0 0 6px 0;">Platform Support & Disputes Desk</h1>
            <p style="color: #64748B; font-size: 14px; margin: 0;">Omnichannel escalation handling for customers, restaurant kitchen staff, and delivery riders.</p>
        </div>
        <div style="display: flex; gap: 10px;">
            <span style="background: #FEF2F2; color: #DC2626; padding: 6px 14px; border-radius: 20px; font-size: 13px; font-weight: 600;">
                🔥 <?php echo $open_count; ?> Open Tickets
            </span>
            <span style="background: #ECFDF5; color: #059669; padding: 6px 14px; border-radius: 20px; font-size: 13px; font-weight: 600;">
                ✅ <?php echo $resolved_count; ?> Resolved
            </span>
        </div>
    </div>

    <!-- 2-Column Split: Ticket List on Left, Active Conversation on Right -->
    <div style="display: grid; grid-template-columns: 420px 1fr; gap: 24px; min-height: 600px;">
        
        <!-- Left: Ticket Queue -->
        <div style="background: #fff; border-radius: 12px; border: 1px solid #E2E8F0; display: flex; flex-direction: column; overflow: hidden; box-shadow: var(--shadow-sm);">
            <!-- Filter Strip -->
            <div style="padding: 14px; border-bottom: 1px solid #E2E8F0; background: #F8FAFC;">
                <form method="GET" style="display: flex; gap: 8px;">
                    <select name="status" onchange="this.form.submit()" style="flex: 1; padding: 6px 8px; border: 1px solid #CBD5E1; border-radius: 6px; font-size: 12px; background: #fff;">
                        <option value="">All Statuses</option>
                        <option value="open" <?php echo ($status_filter === 'open') ? 'selected' : ''; ?>>Open</option>
                        <option value="in_progress" <?php echo ($status_filter === 'in_progress') ? 'selected' : ''; ?>>In Progress</option>
                        <option value="resolved" <?php echo ($status_filter === 'resolved') ? 'selected' : ''; ?>>Resolved</option>
                        <option value="closed" <?php echo ($status_filter === 'closed') ? 'selected' : ''; ?>>Closed</option>
                    </select>
                    <select name="role" onchange="this.form.submit()" style="flex: 1; padding: 6px 8px; border: 1px solid #CBD5E1; border-radius: 6px; font-size: 12px; background: #fff;">
                        <option value="">All Personas</option>
                        <option value="customer" <?php echo ($role_filter === 'customer') ? 'selected' : ''; ?>>Customer</option>
                        <option value="restaurant" <?php echo ($role_filter === 'restaurant') ? 'selected' : ''; ?>>Restaurant</option>
                        <option value="delivery" <?php echo ($role_filter === 'delivery') ? 'selected' : ''; ?>>Rider</option>
                    </select>
                </form>
            </div>

            <!-- Ticket Items Scroll Area -->
            <div style="overflow-y: auto; max-height: calc(100vh - 280px); flex: 1;">
                <?php if (empty($tickets)): ?>
                    <div style="padding: 40px 20px; text-align: center; color: #94A3B8; font-size: 13px;">
                        No tickets matching this filter.
                    </div>
                <?php else: ?>
                    <?php foreach ($tickets as $t): ?>
                        <a href="support.php?ticket_id=<?php echo $t['id']; ?>&status=<?php echo urlencode($status_filter); ?>&role=<?php echo urlencode($role_filter); ?>" 
                           style="display: block; padding: 14px; border-bottom: 1px solid #F1F5F9; text-decoration: none; color: inherit; background: <?php echo ($selected_ticket_id === (int)$t['id']) ? '#F0FDF4' : '#fff'; ?>; border-left: <?php echo ($selected_ticket_id === (int)$t['id']) ? '4px solid #063F2B' : '4px solid transparent'; ?>;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                                <span style="font-family: monospace; font-size: 11px; font-weight: 700; color: #64748B;">
                                    #<?php echo htmlspecialchars($t['ticket_number']); ?>
                                </span>
                                <div style="display: flex; gap: 4px;">
                                    <?php if ($t['priority'] === 'high'): ?>
                                        <span style="background: #FEE2E2; color: #DC2626; font-size: 10px; font-weight: 700; padding: 1px 6px; border-radius: 4px;">HIGH</span>
                                    <?php endif; ?>
                                    <span style="font-size: 10px; text-transform: uppercase; font-weight: 600; padding: 1px 6px; border-radius: 4px; <?php echo ($t['status'] === 'open') ? 'background: #FEF3C7; color: #B45309;' : (($t['status'] === 'resolved') ? 'background: #DCFCE7; color: #15803D;' : 'background: #E2E8F0; color: #475569;'); ?>">
                                        <?php echo $t['status']; ?>
                                    </span>
                                </div>
                            </div>
                            <div style="font-weight: 600; font-size: 13px; color: #0F172A; margin-bottom: 4px; line-height: 1.3;">
                                <?php echo htmlspecialchars($t['subject']); ?>
                            </div>
                            <div style="display: flex; justify-content: space-between; font-size: 11px; color: #64748B;">
                                <span><?php echo htmlspecialchars($t['user_name']); ?> (<?php echo ucfirst($t['role']); ?>)</span>
                                <span><?php echo date('M d, H:i', strtotime($t['created_at'])); ?></span>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Right: Active Ticket Conversation Drawer -->
        <div style="background: #fff; border-radius: 12px; border: 1px solid #E2E8F0; display: flex; flex-direction: column; overflow: hidden; box-shadow: var(--shadow-sm);">
            <?php if (!$active_ticket): ?>
                <div style="display: flex; align-items: center; justify-content: center; height: 100%; color: #94A3B8; font-size: 14px;">
                    Select a ticket from the left panel to view and reply.
                </div>
            <?php else: ?>
                <!-- Ticket Top Details -->
                <div style="padding: 18px 20px; border-bottom: 1px solid #E2E8F0; background: #F8FAFC; display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 14px;">
                    <div>
                        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 6px;">
                            <span style="font-family: monospace; font-size: 13px; font-weight: 700; color: #475569;">Ticket #<?php echo htmlspecialchars($active_ticket['ticket_number']); ?></span>
                            <span style="font-size: 11px; padding: 2px 8px; border-radius: 12px; font-weight: 600; text-transform: uppercase; <?php echo ($active_ticket['status'] === 'open') ? 'background: #FEF3C7; color: #B45309;' : (($active_ticket['status'] === 'resolved') ? 'background: #DCFCE7; color: #15803D;' : 'background: #E2E8F0; color: #475569;'); ?>">
                                <?php echo $active_ticket['status']; ?>
                            </span>
                            <span style="font-size: 11px; padding: 2px 8px; border-radius: 12px; font-weight: 600; background: #EDE9FE; color: #6D28D9;">
                                Priority: <?php echo strtoupper($active_ticket['priority']); ?>
                            </span>
                        </div>
                        <h2 style="margin: 0 0 6px 0; font-size: 18px; color: #0F172A;"><?php echo htmlspecialchars($active_ticket['subject']); ?></h2>
                        <div style="font-size: 12px; color: #64748B;">
                            From: <strong><?php echo htmlspecialchars($active_ticket['user_name']); ?></strong> (<?php echo htmlspecialchars($active_ticket['user_email']); ?> | <?php echo htmlspecialchars($active_ticket['user_phone']); ?>)
                            • Persona: <span style="font-weight: 600; color: #063F2B;"><?php echo ucfirst($active_ticket['role']); ?></span>
                            <?php if ($active_ticket['order_number']): ?>
                                • Order Ref: <a href="orders.php?q=<?php echo urlencode($active_ticket['order_number']); ?>" style="color: #2563EB; font-weight: 600;">#<?php echo htmlspecialchars($active_ticket['order_number']); ?></a>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Quick Status Change Actions -->
                    <form method="POST" style="display: flex; gap: 8px;">
                        <input type="hidden" name="action" value="quick_status">
                        <input type="hidden" name="ticket_id" value="<?php echo $active_ticket['id']; ?>">
                        <?php if ($active_ticket['status'] !== 'resolved'): ?>
                            <button type="submit" name="status" value="resolved" class="btn" style="background: #10B981; color: #fff; padding: 6px 12px; font-size: 12px; font-weight: 600;">
                                Mark Resolved
                            </button>
                        <?php endif; ?>
                        <?php if ($active_ticket['status'] !== 'closed'): ?>
                            <button type="submit" name="status" value="closed" class="btn" style="background: #64748B; color: #fff; padding: 6px 12px; font-size: 12px; font-weight: 600;">
                                Close Ticket
                            </button>
                        <?php endif; ?>
                    </form>
                </div>

                <!-- Messages Thread -->
                <div style="padding: 20px; overflow-y: auto; max-height: 400px; display: flex; flex-direction: column; gap: 14px; background: #FAFAFA; flex: 1;">
                    <!-- Original Message -->
                    <div style="background: #fff; border-radius: 10px; padding: 14px; border: 1px solid #E2E8F0; border-left: 4px solid #3B82F6;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 6px; font-size: 12px;">
                            <strong><?php echo htmlspecialchars($active_ticket['user_name']); ?> (Opening Message)</strong>
                            <span style="color: #94A3B8;"><?php echo date('M d, Y h:i A', strtotime($active_ticket['created_at'])); ?></span>
                        </div>
                        <div style="font-size: 13px; color: #334155; line-height: 1.5; white-space: pre-line;">
                            <?php echo htmlspecialchars($active_ticket['message']); ?>
                        </div>
                    </div>

                    <!-- Subsequent Replies -->
                    <?php foreach ($replies as $r): ?>
                        <div style="background: <?php echo ($r['author_role'] === 'admin') ? '#F0FDF4' : '#fff'; ?>; border-radius: 10px; padding: 14px; border: 1px solid <?php echo ($r['author_role'] === 'admin') ? '#BBF7D0' : '#E2E8F0'; ?>; <?php echo ($r['author_role'] === 'admin') ? 'margin-left: 30px;' : 'margin-right: 30px;'; ?>">
                            <div style="display: flex; justify-content: space-between; margin-bottom: 6px; font-size: 12px;">
                                <strong style="color: <?php echo ($r['author_role'] === 'admin') ? '#063F2B' : '#0F172A'; ?>;">
                                    <?php echo ($r['author_role'] === 'admin') ? '🛡️ Zaykaa Support Admin' : htmlspecialchars($r['author_name']); ?>
                                </strong>
                                <span style="color: #94A3B8;"><?php echo date('M d, Y h:i A', strtotime($r['created_at'])); ?></span>
                            </div>
                            <div style="font-size: 13px; color: #334155; line-height: 1.5; white-space: pre-line;">
                                <?php echo htmlspecialchars($r['message']); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Reply Composer -->
                <div style="padding: 16px 20px; border-top: 1px solid #E2E8F0; background: #fff;">
                    <form method="POST">
                        <input type="hidden" name="action" value="reply">
                        <input type="hidden" name="ticket_id" value="<?php echo $active_ticket['id']; ?>">

                        <div style="margin-bottom: 10px;">
                            <textarea name="message" rows="3" required placeholder="Type your official platform resolution or response..." style="width: 100%; padding: 10px 12px; border: 1px solid #CBD5E1; border-radius: 8px; font-size: 13px; resize: vertical;"></textarea>
                        </div>

                        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <label style="font-size: 12px; font-weight: 600; color: #475569;">Update Status to:</label>
                                <select name="status" style="padding: 6px 10px; border: 1px solid #CBD5E1; border-radius: 6px; font-size: 12px; background: #fff;">
                                    <option value="in_progress" <?php echo ($active_ticket['status'] === 'in_progress') ? 'selected' : ''; ?>>In Progress</option>
                                    <option value="resolved" <?php echo ($active_ticket['status'] === 'resolved') ? 'selected' : ''; ?>>Resolved</option>
                                    <option value="closed">Closed</option>
                                    <option value="open">Keep Open</option>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-primary" style="padding: 8px 18px; font-size: 13px;">
                                Send Official Reply
                            </button>
                        </div>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
