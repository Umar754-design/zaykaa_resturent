<?php
// ===================================================
// Admin: Financial Center & Partner Payouts
// ===================================================
require_once __DIR__ . '/includes/admin-header.php';

// Handle Actions (Process or Reject Payout, Manual Disbursal)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $payout_id = (int)($_POST['payout_id'] ?? 0);

    if ($action === 'process_payout' && $payout_id > 0) {
        $ref_id = trim($_POST['reference_id'] ?? 'TRX-' . strtoupper(substr(uniqid(), 7)));
        $notes = trim($_POST['notes'] ?? 'Approved and disbursed via NEFT/IMPS');

        $stmt = $pdo->prepare("UPDATE payouts SET status = 'processed', reference_id = ?, notes = ? WHERE id = ?");
        $stmt->execute([$ref_id, $notes, $payout_id]);

        set_flash('success', "Payout #{$payout_id} marked as PROCESSED. Ref: {$ref_id}");
    } elseif ($action === 'reject_payout' && $payout_id > 0) {
        $notes = trim($_POST['notes'] ?? 'Rejected by Admin');
        // Fetch payout details to refund partner balance if needed
        $stmt = $pdo->prepare("SELECT * FROM payouts WHERE id = ?");
        $stmt->execute([$payout_id]);
        $payout = $stmt->fetch();

        if ($payout && $payout['status'] === 'pending') {
            if ($payout['entity_type'] === 'delivery') {
                $pdo->prepare("UPDATE delivery_partners SET earnings_balance = earnings_balance + ? WHERE id = ?")
                    ->execute([$payout['amount'], $payout['entity_id']]);
            }
            $pdo->prepare("UPDATE payouts SET status = 'rejected', notes = ? WHERE id = ?")->execute([$notes, $payout_id]);
            set_flash('warning', "Payout #{$payout_id} rejected. Balance refunded if applicable.");
        }
    }
    header('Location: finance.php');
    exit;
}

// Financial Aggregations from real orders
$gmv_res = $pdo->query("SELECT 
    COALESCE(SUM(total_amount), 0) as total_gmv,
    COALESCE(SUM(subtotal), 0) as total_subtotal,
    COALESCE(SUM(platform_commission), 0) as total_commission,
    COALESCE(SUM(platform_fee), 0) as total_platform_fee,
    COALESCE(SUM(tax_amount), 0) as total_gst,
    COALESCE(SUM(restaurant_earnings), 0) as total_restaurant_payable,
    COALESCE(SUM(delivery_earnings), 0) as total_delivery_payable
    FROM orders WHERE order_status = 'delivered'")->fetch();

$total_gmv = (float)$gmv_res['total_gmv'];
$total_comm = (float)$gmv_res['total_commission'];
$total_platform_fee = (float)$gmv_res['total_platform_fee'];
$net_platform_revenue = $total_comm + $total_platform_fee;
$total_gst = (float)$gmv_res['total_gst'];
$total_rest_payable = (float)$gmv_res['total_restaurant_payable'];
$total_deliv_payable = (float)$gmv_res['total_delivery_payable'];

// Total Payouts Disbursed
$disbursed_rest = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM payouts WHERE entity_type = 'restaurant' AND status = 'processed'")->fetchColumn();
$disbursed_deliv = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM payouts WHERE entity_type = 'delivery' AND status = 'processed'")->fetchColumn();

// Pending Payouts
$pending_payouts_sum = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM payouts WHERE status = 'pending'")->fetchColumn();

// Fetch all payouts with partner names
$payouts_sql = "SELECT p.*,
    CASE 
        WHEN p.entity_type = 'restaurant' THEN r.name
        WHEN p.entity_type = 'delivery' THEN u.name
        ELSE 'Unknown'
    END as partner_name,
    CASE
        WHEN p.entity_type = 'restaurant' THEN r.phone
        WHEN p.entity_type = 'delivery' THEN u.phone
        ELSE ''
    END as partner_phone
    FROM payouts p
    LEFT JOIN restaurants r ON p.entity_type = 'restaurant' AND p.entity_id = r.id
    LEFT JOIN delivery_partners dp ON p.entity_type = 'delivery' AND p.entity_id = dp.id
    LEFT JOIN users u ON dp.user_id = u.id
    ORDER BY p.created_at DESC";

$payouts = $pdo->query($payouts_sql)->fetchAll();
?>

<div class="container" style="padding: 24px 20px; max-width: 1300px;">
    <!-- Header -->
    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 24px; flex-wrap: wrap; gap: 16px;">
        <div>
            <h1 style="font-size: 26px; font-weight: 700; color: #0B192C; margin: 0 0 6px 0;">Platform Financial Headquarters</h1>
            <p style="color: #64748B; font-size: 14px; margin: 0;">Transparent platform accounting: Gross Merchandise Value, revenue margins, taxes, and vendor settlement ledgers.</p>
        </div>
    </div>

    <!-- Metric Cards Grid -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 24px;">
        <div style="background: #fff; border-radius: 12px; padding: 20px; border: 1px solid #E2E8F0; box-shadow: var(--shadow-sm); border-top: 4px solid #063F2B;">
            <div style="font-size: 13px; color: #64748B; margin-bottom: 6px;">Total GMV (Delivered)</div>
            <div style="font-size: 24px; font-weight: 700; color: #063F2B;"><?php echo format_price($total_gmv); ?></div>
            <div style="font-size: 11px; color: #94A3B8; margin-top: 4px;">Platform Order Value</div>
        </div>

        <div style="background: #fff; border-radius: 12px; padding: 20px; border: 1px solid #E2E8F0; box-shadow: var(--shadow-sm); border-top: 4px solid #C9A24D;">
            <div style="font-size: 13px; color: #64748B; margin-bottom: 6px;">Net Platform Revenue</div>
            <div style="font-size: 24px; font-weight: 700; color: #B45309;"><?php echo format_price($net_platform_revenue); ?></div>
            <div style="font-size: 11px; color: #94A3B8; margin-top: 4px;">Commissions + Platform fees</div>
        </div>

        <div style="background: #fff; border-radius: 12px; padding: 20px; border: 1px solid #E2E8F0; box-shadow: var(--shadow-sm); border-top: 4px solid #3B82F6;">
            <div style="font-size: 13px; color: #64748B; margin-bottom: 6px;">Restaurant Earnings</div>
            <div style="font-size: 24px; font-weight: 700; color: #1E40AF;"><?php echo format_price($total_rest_payable); ?></div>
            <div style="font-size: 11px; color: #64748B; margin-top: 4px;">Paid: <?php echo format_price($disbursed_rest); ?></div>
        </div>

        <div style="background: #fff; border-radius: 12px; padding: 20px; border: 1px solid #E2E8F0; box-shadow: var(--shadow-sm); border-top: 4px solid #10B981;">
            <div style="font-size: 13px; color: #64748B; margin-bottom: 6px;">Delivery Fleet Earnings</div>
            <div style="font-size: 24px; font-weight: 700; color: #047857;"><?php echo format_price($total_deliv_payable); ?></div>
            <div style="font-size: 11px; color: #64748B; margin-top: 4px;">Paid: <?php echo format_price($disbursed_deliv); ?></div>
        </div>

        <div style="background: #fff; border-radius: 12px; padding: 20px; border: 1px solid #E2E8F0; box-shadow: var(--shadow-sm); border-top: 4px solid #EF4444;">
            <div style="font-size: 13px; color: #64748B; margin-bottom: 6px;">GST Escrow / Tax</div>
            <div style="font-size: 24px; font-weight: 700; color: #B91C1C;"><?php echo format_price($total_gst); ?></div>
            <div style="font-size: 11px; color: #94A3B8; margin-top: 4px;">5% Food Service GST</div>
        </div>
    </div>

    <!-- Settlement Breakdown Table -->
    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 24px; margin-bottom: 24px;">
        <!-- Payout Requests Table -->
        <div style="background: #fff; border-radius: 12px; border: 1px solid #E2E8F0; overflow: hidden; box-shadow: var(--shadow-sm);">
            <div style="padding: 18px 20px; border-bottom: 1px solid #E2E8F0; display: flex; justify-content: space-between; align-items: center;">
                <h3 style="margin: 0; font-size: 16px; color: #0B192C;">Partner Payout Settlements</h3>
                <span style="font-size: 12px; font-weight: 600; padding: 4px 10px; background: #FEF3C7; color: #92400E; border-radius: 12px;">
                    Pending Approval: <?php echo format_price($pending_payouts_sum); ?>
                </span>
            </div>

            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 13px;">
                    <thead>
                        <tr style="background: #F8FAFC; border-bottom: 1px solid #E2E8F0; color: #475569; font-weight: 600; font-size: 11px; text-transform: uppercase;">
                            <th style="padding: 12px 16px;">Partner & Type</th>
                            <th style="padding: 12px 16px;">Amount</th>
                            <th style="padding: 12px 16px;">Status</th>
                            <th style="padding: 12px 16px;">Reference / Notes</th>
                            <th style="padding: 12px 16px;">Requested On</th>
                            <th style="padding: 12px 16px; text-align: right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($payouts)): ?>
                            <tr>
                                <td colspan="6" style="padding: 30px; text-align: center; color: #94A3B8;">
                                    No payout requests on record.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($payouts as $p): ?>
                                <tr style="border-bottom: 1px solid #F1F5F9;">
                                    <td style="padding: 12px 16px;">
                                        <div style="font-weight: 600; color: #0F172A;"><?php echo htmlspecialchars($p['partner_name']); ?></div>
                                        <span style="display: inline-block; padding: 2px 6px; font-size: 10px; border-radius: 4px; font-weight: 600; <?php echo ($p['entity_type'] === 'restaurant') ? 'background: #EDE9FE; color: #6D28D9;' : 'background: #CFFAFE; color: #0E7490;'; ?>">
                                            <?php echo strtoupper($p['entity_type']); ?>
                                        </span>
                                    </td>
                                    <td style="padding: 12px 16px;">
                                        <strong style="color: #063F2B;"><?php echo format_price($p['amount']); ?></strong>
                                    </td>
                                    <td style="padding: 12px 16px;">
                                        <?php if ($p['status'] === 'processed'): ?>
                                            <span style="color: #15803D; font-weight: 600; font-size: 11px; background: #DCFCE7; padding: 3px 8px; border-radius: 12px;">Processed</span>
                                        <?php elseif ($p['status'] === 'rejected'): ?>
                                            <span style="color: #B91C1C; font-weight: 600; font-size: 11px; background: #FEE2E2; padding: 3px 8px; border-radius: 12px;">Rejected</span>
                                        <?php else: ?>
                                            <span style="color: #B45309; font-weight: 600; font-size: 11px; background: #FEF3C7; padding: 3px 8px; border-radius: 12px;">Pending</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding: 12px 16px; font-size: 12px; color: #64748B;">
                                        <?php if ($p['reference_id']): ?>
                                            <code style="font-size: 11px; background: #F1F5F9; padding: 2px 4px; border-radius: 4px;"><?php echo htmlspecialchars($p['reference_id']); ?></code>
                                        <?php endif; ?>
                                        <div><?php echo htmlspecialchars($p['notes'] ?? ''); ?></div>
                                    </td>
                                    <td style="padding: 12px 16px; color: #64748B; font-size: 12px;">
                                        <?php echo date('M d, Y', strtotime($p['created_at'])); ?>
                                    </td>
                                    <td style="padding: 12px 16px; text-align: right;">
                                        <?php if ($p['status'] === 'pending'): ?>
                                            <div style="display: flex; gap: 6px; justify-content: flex-end;">
                                                <button type="button" onclick="openApproveModal(<?php echo $p['id']; ?>, '<?php echo addslashes($p['partner_name']); ?>', <?php echo $p['amount']; ?>)" class="btn" style="background: #16A34A; color: #fff; padding: 4px 10px; font-size: 11px;">
                                                    Disburse
                                                </button>
                                                <form method="POST" style="margin:0;" onsubmit="return confirm('Reject this payout request?');">
                                                    <input type="hidden" name="action" value="reject_payout">
                                                    <input type="hidden" name="payout_id" value="<?php echo $p['id']; ?>">
                                                    <button type="submit" class="btn" style="background: #FEE2E2; color: #DC2626; padding: 4px 8px; font-size: 11px;">
                                                        Reject
                                                    </button>
                                                </form>
                                            </div>
                                        <?php else: ?>
                                            <span style="font-size: 12px; color: #94A3B8;">Completed</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Revenue Model Insights Card -->
        <div style="background: #fff; border-radius: 12px; border: 1px solid #E2E8F0; padding: 24px; box-shadow: var(--shadow-sm); height: fit-content;">
            <h3 style="margin-top: 0; margin-bottom: 14px; font-size: 16px; color: #0B192C;">Platform Margin Architecture</h3>
            <p style="font-size: 13px; color: #64748B; margin-bottom: 20px;">Real-time allocation of every rupee collected across platform orders.</p>

            <div style="display: flex; flex-direction: column; gap: 14px;">
                <div>
                    <div style="display: flex; justify-content: space-between; font-size: 13px; margin-bottom: 4px;">
                        <span style="font-weight: 600; color: #334155;">Restaurant Share (~80-85%)</span>
                        <span style="font-weight: 700; color: #063F2B;"><?php echo format_price($total_rest_payable); ?></span>
                    </div>
                    <div style="height: 8px; background: #E2E8F0; border-radius: 4px; overflow: hidden;">
                        <div style="width: 80%; height: 100%; background: #063F2B;"></div>
                    </div>
                </div>

                <div>
                    <div style="display: flex; justify-content: space-between; font-size: 13px; margin-bottom: 4px;">
                        <span style="font-weight: 600; color: #334155;">Delivery Partner Share</span>
                        <span style="font-weight: 700; color: #047857;"><?php echo format_price($total_deliv_payable); ?></span>
                    </div>
                    <div style="height: 8px; background: #E2E8F0; border-radius: 4px; overflow: hidden;">
                        <div style="width: 12%; height: 100%; background: #10B981;"></div>
                    </div>
                </div>

                <div>
                    <div style="display: flex; justify-content: space-between; font-size: 13px; margin-bottom: 4px;">
                        <span style="font-weight: 600; color: #334155;">Platform Net Margin</span>
                        <span style="font-weight: 700; color: #C9A24D;"><?php echo format_price($net_platform_revenue); ?></span>
                    </div>
                    <div style="height: 8px; background: #E2E8F0; border-radius: 4px; overflow: hidden;">
                        <div style="width: 15%; height: 100%; background: #C9A24D;"></div>
                    </div>
                </div>

                <div>
                    <div style="display: flex; justify-content: space-between; font-size: 13px; margin-bottom: 4px;">
                        <span style="font-weight: 600; color: #334155;">GST Escrow Reserve</span>
                        <span style="font-weight: 700; color: #B91C1C;"><?php echo format_price($total_gst); ?></span>
                    </div>
                    <div style="height: 8px; background: #E2E8F0; border-radius: 4px; overflow: hidden;">
                        <div style="width: 5%; height: 100%; background: #EF4444;"></div>
                    </div>
                </div>
            </div>

            <div style="margin-top: 24px; padding: 14px; background: #F8FAFC; border-radius: 8px; border: 1px dashed #CBD5E1; font-size: 12px; color: #64748B;">
                💡 <strong>Instant Settlement Note:</strong> Once marked processed, payouts are recorded in the partner's ledger and reference tracking numbers are visible to the restaurant or delivery partner.
            </div>
        </div>
    </div>
</div>

<!-- Disbursal Modal -->
<div id="approveModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 2000; align-items: center; justify-content: center;">
    <div style="background: #fff; width: 90%; max-width: 440px; border-radius: 16px; padding: 24px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.2);">
        <h3 style="margin-top: 0; margin-bottom: 6px; font-size: 18px; color: #0B192C;">Authorize Payout Disbursal</h3>
        <p style="font-size: 13px; color: #64748B; margin-bottom: 18px;">Disbursing <strong id="modalAmount" style="color: #063F2B;"></strong> to <strong id="modalPartner"></strong></p>

        <form method="POST">
            <input type="hidden" name="action" value="process_payout">
            <input type="hidden" name="payout_id" id="modalPayoutId" value="">

            <div style="margin-bottom: 14px;">
                <label style="display: block; font-size: 13px; font-weight: 600; color: #334155; margin-bottom: 6px;">Bank UTR / Transaction Ref</label>
                <input type="text" name="reference_id" required value="UTR-<?php echo strtoupper(bin2hex(random_bytes(4))); ?>" style="width: 100%; padding: 10px; border: 1px solid #CBD5E1; border-radius: 8px; font-size: 14px; font-family: monospace;">
            </div>

            <div style="margin-bottom: 20px;">
                <label style="display: block; font-size: 13px; font-weight: 600; color: #334155; margin-bottom: 6px;">Disbursal Notes</label>
                <input type="text" name="notes" value="Processed via Direct Bank Transfer" style="width: 100%; padding: 10px; border: 1px solid #CBD5E1; border-radius: 8px; font-size: 14px;">
            </div>

            <div style="display: flex; gap: 10px; justify-content: flex-end;">
                <button type="button" onclick="closeApproveModal()" class="btn" style="background: #E2E8F0; color: #334155;">Cancel</button>
                <button type="submit" class="btn btn-primary" style="background: #16A34A; border-color: #16A34A;">Confirm Disbursal</button>
            </div>
        </form>
    </div>
</div>

<script>
function openApproveModal(id, partner, amount) {
    document.getElementById('modalPayoutId').value = id;
    document.getElementById('modalPartner').textContent = partner;
    document.getElementById('modalAmount').textContent = '₹' + parseFloat(amount).toFixed(2);
    document.getElementById('approveModal').style.display = 'flex';
}
function closeApproveModal() {
    document.getElementById('approveModal').style.display = 'none';
}
</script>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
