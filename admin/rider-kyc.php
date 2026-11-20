<?php
// ===================================================
// Rider KYC Review — Admin Control Center
// ===================================================
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/rider-kyc.php';

require_role(['admin']);
ensure_rider_kyc_schema();

$riderId = (int) ($_GET['id'] ?? $_POST['rider_id'] ?? 0);
$load = $pdo->prepare("SELECT dp.*, u.name, u.email, u.phone, u.created_at AS joined_at FROM delivery_partners dp JOIN users u ON u.id = dp.user_id WHERE dp.id = ?");
$load->execute([$riderId]);
$rider = $load->fetch();
if (!$rider) {
    set_flash('error', 'Rider not found.');
    header('Location: delivery-partners.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $remarks = mb_substr(trim($_POST['remarks'] ?? ''), 0, 255);

    if ($action === 'doc_status') {
        $status = $_POST['status'] ?? '';
        if (in_array($status, ['approved', 'rejected'], true)) {
            if ($status === 'rejected' && $remarks === '') {
                set_flash('error', 'Please give a reason when rejecting a document.');
            } else {
                $pdo->prepare("UPDATE rider_kyc_documents SET status = ?, remarks = ? WHERE id = ? AND rider_id = ?")
                    ->execute([$status, $status === 'rejected' ? $remarks : null, (int) $_POST['doc_id'], $riderId]);
                set_flash('success', 'Document marked as ' . $status . '.');
            }
        }
    } elseif ($action === 'approve_rider') {
        $missing = kyc_missing_documents($riderId);
        $unapproved = $pdo->prepare("SELECT COUNT(*) FROM rider_kyc_documents WHERE rider_id = ? AND status <> 'approved'");
        $unapproved->execute([$riderId]);
        if (empty($rider['kyc_submitted_at']) || $missing) {
            set_flash('error', 'Cannot approve yet — required documents are missing.');
        } elseif ((int) $unapproved->fetchColumn() > 0) {
            set_flash('error', 'Approve or reject every uploaded document first.');
        } else {
            $pdo->prepare("UPDATE delivery_partners SET kyc_status = 'approved', kyc_remarks = NULL WHERE id = ?")->execute([$riderId]);
            $pdo->prepare("UPDATE users SET status = 'active' WHERE id = ?")->execute([$rider['user_id']]);
            set_flash('success', $rider['name'] . "'s KYC is approved. They can now log in with a shift selfie.");
        }
    } elseif ($action === 'reject_rider') {
        if ($remarks === '') {
            set_flash('error', 'Please tell the rider why the application was rejected.');
        } else {
            $pdo->prepare("UPDATE delivery_partners SET kyc_status = 'rejected', kyc_remarks = ?, is_online = 0 WHERE id = ?")->execute([$remarks, $riderId]);
            set_flash('success', 'Application rejected. The rider can fix the documents and resubmit.');
        }
    }
    header('Location: rider-kyc.php?id=' . $riderId);
    exit;
}

include 'includes/admin-header.php';

$docs = kyc_documents_for($riderId);
$missing = kyc_missing_documents($riderId);
$statusColors = ['pending' => ['#FEF3C7', '#B45309'], 'approved' => ['#DEF7EC', '#046C4E'], 'rejected' => ['#FDE8E8', '#9B1C1C']];
$dlExpired = !empty($rider['dl_expiry']) && strtotime($rider['dl_expiry']) < strtotime('today');
?>

<div style="padding: 30px 0 60px;">
    <div class="container" style="max-width: 1100px;">
        <a href="delivery-partners.php" style="font-size: 13px; color: #64748B; text-decoration: none;">← Delivery Fleet</a>
        <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 14px; margin: 8px 0 22px;">
            <div>
                <h1 style="font-family: 'Playfair Display', serif; font-size: 28px; color: #0B192C; margin: 0;">KYC Review: <?php echo e($rider['name']); ?></h1>
                <p style="color: #64748B; font-size: 14px; margin: 4px 0 0;">
                    <?php echo e($rider['email']); ?> · <?php echo e($rider['phone']); ?> · Joined <?php echo e(date('d M Y', strtotime($rider['joined_at']))); ?>
                </p>
            </div>
            <?php [$bg, $fg] = $statusColors[$rider['kyc_status']] ?? $statusColors['pending']; ?>
            <span style="padding: 6px 14px; border-radius: 14px; background: <?php echo $bg; ?>; color: <?php echo $fg; ?>; font-weight: 700; font-size: 13px;">
                KYC <?php echo e(strtoupper($rider['kyc_status'])); ?>
                <?php if ($rider['kyc_status'] === 'pending'): ?>· <?php echo $rider['kyc_submitted_at'] ? 'submitted ' . e(date('d M, h:i A', strtotime($rider['kyc_submitted_at']))) : 'documents not submitted'; ?><?php endif; ?>
            </span>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 18px; margin-bottom: 22px;">
            <div style="background: #fff; border-radius: 14px; border: 1px solid var(--light-border); padding: 18px;">
                <h3 style="margin: 0 0 12px; font-size: 15px; color: #0B192C;">Identity &amp; Licence</h3>
                <table style="width: 100%; font-size: 13px; border-collapse: collapse;">
                    <tr><td style="color: #64748B; padding: 4px 0;">Aadhaar</td><td><strong><?php echo $rider['aadhaar_last4'] ? 'XXXX XXXX ' . e($rider['aadhaar_last4']) : '—'; ?></strong></td></tr>
                    <tr><td style="color: #64748B; padding: 4px 0;">PAN</td><td><strong><?php echo e($rider['pan_number'] ?: '—'); ?></strong></td></tr>
                    <tr><td style="color: #64748B; padding: 4px 0;">Driving Licence</td><td><strong><?php echo e($rider['driving_license'] ?: '—'); ?></strong></td></tr>
                    <tr><td style="color: #64748B; padding: 4px 0;">DL valid till</td><td><strong style="color: <?php echo $dlExpired ? '#DC2626' : 'inherit'; ?>;"><?php echo $rider['dl_expiry'] ? e(date('d M Y', strtotime($rider['dl_expiry']))) . ($dlExpired ? ' (EXPIRED)' : '') : '—'; ?></strong></td></tr>
                    <tr><td style="color: #64748B; padding: 4px 0;">Vehicle</td><td><strong><?php echo e($rider['vehicle_type'] . ' · ' . $rider['vehicle_number']); ?></strong></td></tr>
                    <tr><td style="color: #64748B; padding: 4px 0;">Qualification</td><td><strong><?php echo e($rider['qualification'] ?: '—'); ?></strong></td></tr>
                </table>
            </div>
            <div style="background: #fff; border-radius: 14px; border: 1px solid var(--light-border); padding: 18px;">
                <h3 style="margin: 0 0 12px; font-size: 15px; color: #0B192C;">Bank Account (payouts)</h3>
                <table style="width: 100%; font-size: 13px; border-collapse: collapse;">
                    <tr><td style="color: #64748B; padding: 4px 0;">Holder</td><td><strong><?php echo e($rider['bank_account_holder'] ?: '—'); ?></strong></td></tr>
                    <tr><td style="color: #64748B; padding: 4px 0;">Account no.</td><td><strong><?php echo $rider['bank_account_number'] ? e(kyc_mask_account($rider['bank_account_number'])) : '—'; ?></strong></td></tr>
                    <tr><td style="color: #64748B; padding: 4px 0;">IFSC</td><td><strong><?php echo e($rider['bank_ifsc'] ?: '—'); ?></strong></td></tr>
                </table>
                <?php if ($rider['bank_account_holder'] && strcasecmp(trim($rider['bank_account_holder']), trim($rider['name'])) !== 0): ?>
                    <p style="margin: 10px 0 0; font-size: 12px; color: #B45309;">⚠ Account holder name differs from the rider's name — check the passbook.</p>
                <?php endif; ?>
            </div>
            <div style="background: #fff; border-radius: 14px; border: 1px solid var(--light-border); padding: 18px;">
                <h3 style="margin: 0 0 12px; font-size: 15px; color: #0B192C;">Registration Selfie</h3>
                <?php if ($rider['selfie_image']): ?>
                    <img src="../<?php echo e($rider['selfie_image']); ?>" alt="Registration selfie" style="width: 100%; max-height: 180px; object-fit: cover; border-radius: 10px;">
                <?php else: ?>
                    <p style="color: #94A3B8; font-size: 13px;">No selfie on file.</p>
                <?php endif; ?>
            </div>
        </div>

        <h2 style="font-size: 18px; color: #0B192C; margin: 0 0 12px;">Documents</h2>
        <?php if ($missing): ?>
            <div style="background: #FEF3C7; color: #92400E; padding: 10px 14px; border-radius: 10px; font-size: 13px; margin-bottom: 14px;">
                Missing / needs re-upload: <strong><?php echo e(implode(', ', $missing)); ?></strong>
            </div>
        <?php endif; ?>

        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 14px; margin-bottom: 26px;">
            <?php foreach (kyc_document_types() as $type => $def):
                $d = $docs[$type] ?? null;
                [$bg, $fg] = $d ? $statusColors[$d['status']] : ['#F1F5F9', '#64748B'];
            ?>
                <div style="background: #fff; border-radius: 14px; border: 1px solid var(--light-border); padding: 12px; display: flex; flex-direction: column; gap: 8px;">
                    <div style="display: flex; justify-content: space-between; gap: 6px; align-items: center;">
                        <strong style="font-size: 13px; color: #0B192C;"><?php echo e($def['label']); ?><?php echo $def['required'] ? ' *' : ''; ?></strong>
                        <span style="font-size: 10px; font-weight: 700; padding: 2px 8px; border-radius: 10px; background: <?php echo $bg; ?>; color: <?php echo $fg; ?>;"><?php echo $d ? e(strtoupper($d['status'])) : 'MISSING'; ?></span>
                    </div>
                    <?php if ($d): ?>
                        <a href="kyc-file.php?id=<?php echo (int) $d['id']; ?>" target="_blank" rel="noopener" style="display: block; height: 150px; border-radius: 10px; overflow: hidden; background: #F1F5F9;">
                            <?php if (strpos($d['mime_type'], 'image/') === 0): ?>
                                <img src="kyc-file.php?id=<?php echo (int) $d['id']; ?>" alt="<?php echo e($def['label']); ?>" style="width: 100%; height: 100%; object-fit: cover;">
                            <?php else: ?>
                                <span style="display: grid; place-items: center; height: 100%; font-size: 13px; color: #334155; font-weight: 600;">📄 Open PDF</span>
                            <?php endif; ?>
                        </a>
                        <span style="font-size: 11px; color: #94A3B8;">Uploaded <?php echo e(date('d M, h:i A', strtotime($d['uploaded_at']))); ?></span>
                        <?php if ($d['remarks']): ?><span style="font-size: 11px; color: #9B1C1C;">Reason: <?php echo e($d['remarks']); ?></span><?php endif; ?>
                        <?php if ($d['status'] === 'pending'): ?>
                            <form method="POST" style="display: flex; flex-direction: column; gap: 6px; margin: 0;">
                                <input type="hidden" name="action" value="doc_status">
                                <input type="hidden" name="rider_id" value="<?php echo $riderId; ?>">
                                <input type="hidden" name="doc_id" value="<?php echo (int) $d['id']; ?>">
                                <input type="text" name="remarks" placeholder="Reason (required to reject)" maxlength="255" style="padding: 6px 8px; border: 1px solid #CBD5E1; border-radius: 6px; font-size: 12px;">
                                <div style="display: flex; gap: 6px;">
                                    <button type="submit" name="status" value="approved" class="btn" style="flex: 1; padding: 6px; font-size: 12px; background: #F0FDF4; color: #15803D;">✓ Valid</button>
                                    <button type="submit" name="status" value="rejected" class="btn" style="flex: 1; padding: 6px; font-size: 12px; background: #FEF2F2; color: #DC2626;">✕ Reject</button>
                                </div>
                            </form>
                        <?php endif; ?>
                    <?php else: ?>
                        <div style="height: 150px; border-radius: 10px; background: #F8FAFC; border: 1px dashed #CBD5E1; display: grid; place-items: center; font-size: 12px; color: #94A3B8;">Not uploaded</div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if ($rider['kyc_status'] !== 'approved'): ?>
        <div style="background: #fff; border-radius: 14px; border: 1px solid var(--light-border); padding: 18px; display: flex; flex-wrap: wrap; gap: 14px; align-items: flex-end;">
            <form method="POST" style="margin: 0;">
                <input type="hidden" name="action" value="approve_rider">
                <input type="hidden" name="rider_id" value="<?php echo $riderId; ?>">
                <button type="submit" class="btn" style="padding: 10px 22px; background: #059669; color: #fff; font-weight: 700;" <?php echo ($missing || empty($rider['kyc_submitted_at'])) ? 'disabled title="Required documents missing"' : ''; ?>>
                    ✓ Approve Rider KYC
                </button>
            </form>
            <form method="POST" style="margin: 0; display: flex; gap: 8px; flex: 1; min-width: 280px;">
                <input type="hidden" name="action" value="reject_rider">
                <input type="hidden" name="rider_id" value="<?php echo $riderId; ?>">
                <input type="text" name="remarks" required maxlength="255" placeholder="Reason shown to rider (e.g. PAN photo blurred)" style="flex: 1; padding: 9px 12px; border: 1px solid #CBD5E1; border-radius: 8px; font-size: 13px;">
                <button type="submit" class="btn" style="padding: 10px 18px; background: #FEF2F2; color: #DC2626; font-weight: 700;">Reject Application</button>
            </form>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/admin-footer.php'; ?>
