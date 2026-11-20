<?php
// ===================================================
// Rider Onboarding Step 2 — KYC Documents
// Reached right after registration, or from login while KYC is
// pending / rejected. Uses $_SESSION['rider_kyc_user_id'].
// ===================================================
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/rider-kyc.php';

ensure_rider_kyc_schema();

$kycUserId = (int) ($_SESSION['rider_kyc_user_id'] ?? 0);
if (!$kycUserId) {
    set_flash('error', 'Please sign in with your rider email and password to continue your KYC.');
    header('Location: login.php');
    exit;
}

$stmt = $pdo->prepare("SELECT dp.*, u.name, u.email, u.phone FROM delivery_partners dp JOIN users u ON u.id = dp.user_id WHERE dp.user_id = ? AND u.role = 'delivery'");
$stmt->execute([$kycUserId]);
$rider = $stmt->fetch();

if (!$rider) {
    unset($_SESSION['rider_kyc_user_id']);
    header('Location: login.php');
    exit;
}
if ($rider['kyc_status'] === 'approved') {
    unset($_SESSION['rider_kyc_user_id']);
    set_flash('success', 'Your KYC is already approved. Please log in with your shift selfie.');
    header('Location: login.php');
    exit;
}

$riderId = (int) $rider['id'];
$errors = [];
$notice = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_kyc') {
    $aadhaar = preg_replace('/\D/', '', $_POST['aadhaar_number'] ?? '');
    $pan = strtoupper(trim($_POST['pan_number'] ?? ''));
    $dlNumber = strtoupper(trim($_POST['driving_license'] ?? ''));
    $dlExpiry = trim($_POST['dl_expiry'] ?? '');
    $holder = trim($_POST['bank_account_holder'] ?? '');
    $account = preg_replace('/\s/', '', $_POST['bank_account_number'] ?? '');
    $account2 = preg_replace('/\s/', '', $_POST['bank_account_confirm'] ?? '');
    $ifsc = strtoupper(trim($_POST['bank_ifsc'] ?? ''));
    $qualification = trim($_POST['qualification'] ?? '');

    $updates = [];

    // Aadhaar: validate checksum, keep only the last 4 digits
    if ($aadhaar !== '') {
        if (!kyc_valid_aadhaar($aadhaar)) {
            $errors[] = 'Aadhaar number is not valid. Please check the 12 digits.';
        } else {
            $updates['aadhaar_last4'] = substr($aadhaar, -4);
        }
    } elseif (empty($rider['aadhaar_last4'])) {
        $errors[] = 'Aadhaar number is required.';
    }

    if ($pan !== '' || empty($rider['pan_number'])) {
        if (!kyc_valid_pan($pan)) {
            $errors[] = 'PAN must look like ABCDE1234F.';
        } else {
            $updates['pan_number'] = $pan;
        }
    }

    if ($dlNumber === '' || strlen($dlNumber) < 8) {
        $errors[] = 'Driving licence number is required.';
    } else {
        $updates['driving_license'] = $dlNumber;
    }
    $expiryTs = strtotime($dlExpiry);
    if (!$dlExpiry || !$expiryTs) {
        $errors[] = 'Driving licence expiry date is required.';
    } elseif ($expiryTs < strtotime('today')) {
        $errors[] = 'Your driving licence has expired. Please renew it before applying.';
    } else {
        $updates['dl_expiry'] = date('Y-m-d', $expiryTs);
    }

    if ($holder === '' || mb_strlen($holder) < 3) {
        $errors[] = 'Bank account holder name is required.';
    } else {
        $updates['bank_account_holder'] = mb_substr($holder, 0, 120);
    }
    if ($account !== '' || empty($rider['bank_account_number'])) {
        if (!preg_match('/^[0-9]{9,18}$/', $account)) {
            $errors[] = 'Bank account number must be 9–18 digits.';
        } elseif ($account !== $account2) {
            $errors[] = 'Bank account numbers do not match.';
        } else {
            $updates['bank_account_number'] = $account;
        }
    }
    if (!kyc_valid_ifsc($ifsc)) {
        $errors[] = 'IFSC code must look like SBIN0001234.';
    } else {
        $updates['bank_ifsc'] = $ifsc;
    }

    if (!in_array($qualification, kyc_qualification_options(), true)) {
        $errors[] = 'Please select your highest qualification.';
    } else {
        $updates['qualification'] = $qualification;
    }

    // Files are saved even if some text fields need fixing, so the rider doesn't re-upload
    foreach (array_keys(kyc_document_types()) as $type) {
        [$ok, $err] = kyc_store_upload($riderId, $type, $_FILES['doc_' . $type] ?? null);
        if (!$ok) {
            $errors[] = $err;
        }
    }

    if ($updates) {
        $set = implode(', ', array_map(fn($c) => "`$c` = ?", array_keys($updates)));
        $pdo->prepare("UPDATE delivery_partners SET $set WHERE id = ?")->execute(array_merge(array_values($updates), [$riderId]));
    }

    $missing = kyc_missing_documents($riderId);
    if (!$errors && !$missing) {
        $pdo->prepare("UPDATE delivery_partners SET kyc_submitted_at = NOW(), kyc_status = 'pending', kyc_remarks = NULL WHERE id = ?")
            ->execute([$riderId]);
        notify_admin('rider_kyc', "KYC documents submitted: {$rider['name']}",
            ($rider['kyc_status'] === 'rejected' ? 'Re-submitted after rejection' : 'Ready for verification'), 'rider-kyc.php?id=' . $riderId);
        unset($_SESSION['rider_kyc_user_id']);
        set_flash('success', '✓ KYC documents submitted! Our team will verify them within 24–48 hours. You can log in once approved.');
        header('Location: login.php');
        exit;
    }
    if ($missing) {
        $errors[] = 'Still needed: ' . implode(', ', $missing) . '.';
    }

    $stmt->execute([$kycUserId]);
    $rider = $stmt->fetch();
    $notice = 'Your progress has been saved. Fix the items below and submit again.';
}

$docs = kyc_documents_for($riderId);
$val = function ($key, $fallback = '') use ($rider) {
    return e($_POST[$key] ?? ($rider[$key] ?? $fallback));
};
$docTypes = kyc_document_types();

function kyc_file_slot($type, $def, $docs) {
    $have = $docs[$type] ?? null;
    $state = $have ? $have['status'] : 'missing';
    ?>
    <label class="kyc-slot kyc-<?php echo e($state); ?>">
        <span class="kyc-slot-head">
            <span class="kyc-slot-label"><?php echo e($def['label']); ?><?php echo $def['required'] ? ' *' : ' <em>(optional)</em>'; ?></span>
            <span class="kyc-slot-badge">
                <?php echo $state === 'missing' ? 'Not uploaded' : ($state === 'rejected' ? 'Rejected — re-upload' : ($state === 'approved' ? '✓ Verified' : '✓ Uploaded')); ?>
            </span>
        </span>
        <?php if ($have && $have['status'] === 'rejected' && $have['remarks']): ?>
            <span class="kyc-slot-remark">Reason: <?php echo e($have['remarks']); ?></span>
        <?php endif; ?>
        <input type="file" name="doc_<?php echo e($type); ?>" accept="image/jpeg,image/png,image/webp,application/pdf" data-kyc-file
               <?php echo ($def['required'] && (!$have || $have['status'] === 'rejected')) ? 'data-required="1"' : ''; ?>>
        <span class="kyc-slot-file"><?php echo $have ? 'Tap to replace · ' . e($have['original_name']) : 'Tap to choose photo or PDF (max 5 MB)'; ?></span>
    </label>
    <?php
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KYC Documents | Zaykaa Fleet</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        body { background: #0F172A; margin: 0; padding: 30px 15px; font-family: 'Poppins', sans-serif; color: #fff; }
        .kyc-card { background: #1E293B; border-radius: 20px; padding: 32px 24px; max-width: 640px; margin: 0 auto; box-shadow: 0 10px 30px rgba(0,0,0,0.5); border: 1px solid rgba(255,255,255,0.1); }
        .kyc-steps { display: flex; gap: 8px; margin-bottom: 22px; }
        .kyc-step { flex: 1; text-align: center; font-size: 11px; font-weight: 600; color: #64748B; }
        .kyc-step::before { content: ''; display: block; height: 4px; border-radius: 4px; background: #334155; margin-bottom: 6px; }
        .kyc-step.done { color: #34D399; } .kyc-step.done::before { background: #10B981; }
        .kyc-step.now { color: #38BDF8; } .kyc-step.now::before { background: #38BDF8; }
        .kyc-section { font-size: 12px; font-weight: 700; color: #38BDF8; text-transform: uppercase; letter-spacing: 0.5px; margin: 24px 0 12px; border-bottom: 1px solid #334155; padding-bottom: 6px; }
        .kyc-field { margin-bottom: 14px; }
        .kyc-field label { font-size: 13px; font-weight: 600; color: #CBD5E1; display: block; margin-bottom: 4px; }
        .kyc-field small { display: block; font-size: 11px; color: #64748B; margin-top: 3px; }
        .kyc-field input, .kyc-field select { width: 100%; box-sizing: border-box; padding: 10px 14px; border: 1px solid #334155; border-radius: 8px; font-size: 14px; background: #0F172A; color: #fff; font-family: inherit; }
        .kyc-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .kyc-slot { display: block; position: relative; background: #0F172A; border: 1.5px dashed #334155; border-radius: 12px; padding: 12px 14px; margin-bottom: 10px; cursor: pointer; }
        .kyc-slot:hover { border-color: #38BDF8; }
        .kyc-slot input[type=file] { position: absolute; inset: 0; opacity: 0; cursor: pointer; }
        .kyc-slot-head { display: flex; justify-content: space-between; align-items: center; gap: 8px; }
        .kyc-slot-label { font-size: 13px; font-weight: 600; color: #E2E8F0; }
        .kyc-slot-label em { font-style: normal; font-weight: 400; color: #64748B; }
        .kyc-slot-badge { font-size: 10px; font-weight: 700; padding: 3px 8px; border-radius: 10px; background: #334155; color: #94A3B8; white-space: nowrap; }
        .kyc-slot-file { display: block; font-size: 11px; color: #64748B; margin-top: 4px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .kyc-slot-remark { display: block; font-size: 11px; color: #FCA5A5; margin-top: 4px; }
        .kyc-pending, .kyc-approved { border-style: solid; border-color: #065F46; }
        .kyc-pending .kyc-slot-badge, .kyc-approved .kyc-slot-badge { background: rgba(16,185,129,0.15); color: #34D399; }
        .kyc-rejected { border-style: solid; border-color: #B91C1C; }
        .kyc-rejected .kyc-slot-badge { background: rgba(239,68,68,0.2); color: #F87171; }
        .kyc-slot.picked { border-style: solid; border-color: #38BDF8; }
        .kyc-slot.picked .kyc-slot-badge { background: rgba(56,189,248,0.15); color: #38BDF8; }
        .kyc-alert { padding: 12px 14px; border-radius: 10px; font-size: 13px; margin-bottom: 16px; line-height: 1.5; }
        .kyc-alert.err { background: rgba(239,68,68,0.15); color: #FCA5A5; border: 1px solid #EF4444; }
        .kyc-alert.info { background: rgba(56,189,248,0.12); color: #7DD3FC; border: 1px solid #0EA5E9; }
        .kyc-privacy { font-size: 11px; color: #64748B; background: #0F172A; border-radius: 10px; padding: 10px 12px; margin-top: 18px; line-height: 1.5; }
        .kyc-submit { width: 100%; padding: 14px; background: #059669; color: #fff; border-radius: 25px; font-weight: 700; border: none; cursor: pointer; font-size: 15px; margin-top: 18px; box-shadow: 0 4px 14px rgba(5,150,105,0.4); }
        @media (max-width: 520px) { .kyc-row { grid-template-columns: 1fr; } .kyc-card { padding: 24px 16px; } }
    </style>
</head>
<body>
<div class="kyc-card">
    <div class="kyc-steps" aria-label="Onboarding progress">
        <span class="kyc-step done">1. Account &amp; selfie</span>
        <span class="kyc-step now">2. KYC documents</span>
        <span class="kyc-step">3. Verification</span>
    </div>

    <h1 style="margin: 0 0 4px; font-size: 22px;">Complete your KYC, <?php echo e(explode(' ', $rider['name'])[0]); ?> 🪪</h1>
    <p style="color: #94A3B8; font-size: 13px; margin: 0 0 18px;">Upload clear photos of your documents. All 4 corners should be visible and text readable.</p>

    <?php echo render_flash_message(); ?>

    <?php if ($rider['kyc_status'] === 'rejected'): ?>
        <div class="kyc-alert err">Your earlier application was rejected<?php echo $rider['kyc_remarks'] ? ': ' . e($rider['kyc_remarks']) : '.'; ?> Please correct the documents and submit again.</div>
    <?php elseif (!empty($rider['kyc_submitted_at']) && !$errors): ?>
        <div class="kyc-alert info">Documents submitted on <?php echo e(date('d M Y, h:i A', strtotime($rider['kyc_submitted_at']))); ?> and waiting for verification. You can still replace a file below.</div>
    <?php endif; ?>
    <?php if ($notice): ?><div class="kyc-alert info"><?php echo e($notice); ?></div><?php endif; ?>
    <?php if ($errors): ?>
        <div class="kyc-alert err"><?php foreach ($errors as $er): ?>• <?php echo e($er); ?><br><?php endforeach; ?></div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" id="kycForm" novalidate>
        <input type="hidden" name="action" value="save_kyc">

        <div class="kyc-section">1. Aadhaar Card</div>
        <div class="kyc-field">
            <label for="aadhaar">Aadhaar Number <?php echo empty($rider['aadhaar_last4']) ? '*' : ''; ?></label>
            <input id="aadhaar" type="text" name="aadhaar_number" inputmode="numeric" maxlength="14" autocomplete="off"
                   placeholder="<?php echo $rider['aadhaar_last4'] ? 'Saved: XXXX XXXX ' . e($rider['aadhaar_last4']) . ' (leave blank to keep)' : '12-digit Aadhaar number'; ?>">
            <small>We only keep the last 4 digits of your Aadhaar number.</small>
        </div>
        <?php kyc_file_slot('aadhaar_front', $docTypes['aadhaar_front'], $docs); ?>
        <?php kyc_file_slot('aadhaar_back', $docTypes['aadhaar_back'], $docs); ?>

        <div class="kyc-section">2. PAN Card</div>
        <div class="kyc-field">
            <label for="pan">PAN Number *</label>
            <input id="pan" type="text" name="pan_number" maxlength="10" autocomplete="off" style="text-transform: uppercase;"
                   value="<?php echo $val('pan_number'); ?>" placeholder="ABCDE1234F">
        </div>
        <?php kyc_file_slot('pan', $docTypes['pan'], $docs); ?>

        <div class="kyc-section">3. Driving Licence</div>
        <div class="kyc-row">
            <div class="kyc-field">
                <label for="dl">DL Number *</label>
                <input id="dl" type="text" name="driving_license" maxlength="20" style="text-transform: uppercase;" value="<?php echo $val('driving_license'); ?>">
            </div>
            <div class="kyc-field">
                <label for="dlexp">Valid Till *</label>
                <input id="dlexp" type="date" name="dl_expiry" min="<?php echo date('Y-m-d'); ?>" value="<?php echo $val('dl_expiry'); ?>">
            </div>
        </div>
        <?php kyc_file_slot('dl_front', $docTypes['dl_front'], $docs); ?>
        <?php kyc_file_slot('dl_back', $docTypes['dl_back'], $docs); ?>

        <div class="kyc-section">4. Bank Account (for payouts)</div>
        <div class="kyc-field">
            <label for="holder">Account Holder Name *</label>
            <input id="holder" type="text" name="bank_account_holder" maxlength="120" value="<?php echo $val('bank_account_holder', $rider['name']); ?>">
            <small>Must match the name on your bank passbook.</small>
        </div>
        <div class="kyc-row">
            <div class="kyc-field">
                <label for="acct">Account Number <?php echo empty($rider['bank_account_number']) ? '*' : ''; ?></label>
                <input id="acct" type="password" name="bank_account_number" inputmode="numeric" maxlength="18" autocomplete="off"
                       placeholder="<?php echo $rider['bank_account_number'] ? 'Saved: ' . e(kyc_mask_account($rider['bank_account_number'])) : '9–18 digits'; ?>">
            </div>
            <div class="kyc-field">
                <label for="acct2">Confirm Account Number</label>
                <input id="acct2" type="text" name="bank_account_confirm" inputmode="numeric" maxlength="18" autocomplete="off">
            </div>
        </div>
        <div class="kyc-field">
            <label for="ifsc">IFSC Code *</label>
            <input id="ifsc" type="text" name="bank_ifsc" maxlength="11" style="text-transform: uppercase;" value="<?php echo $val('bank_ifsc'); ?>" placeholder="SBIN0001234">
        </div>
        <?php kyc_file_slot('bank_proof', $docTypes['bank_proof'], $docs); ?>

        <div class="kyc-section">5. Vehicle &amp; Qualification</div>
        <?php kyc_file_slot('vehicle_rc', $docTypes['vehicle_rc'], $docs); ?>
        <div class="kyc-field">
            <label for="qual">Highest Qualification *</label>
            <select id="qual" name="qualification">
                <option value="">Select…</option>
                <?php foreach (kyc_qualification_options() as $q): ?>
                    <option value="<?php echo e($q); ?>" <?php echo (($_POST['qualification'] ?? $rider['qualification']) === $q) ? 'selected' : ''; ?>><?php echo e($q); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php kyc_file_slot('qualification', $docTypes['qualification'], $docs); ?>

        <div class="kyc-privacy">🔒 Your documents are stored privately and are only visible to Zaykaa's verification team. They are used only to verify your identity and send your payouts.</div>

        <button type="submit" class="kyc-submit" id="kycSubmit">Submit KYC for Verification →</button>
    </form>

    <div style="margin-top: 18px; text-align: center; font-size: 12px;">
        <a href="login.php?action=cancel_kyc" style="color: #64748B; text-decoration: none;">Save &amp; finish later</a>
    </div>
</div>

<script>
(function () {
    const MAX = 5 * 1024 * 1024;
    document.querySelectorAll('[data-kyc-file]').forEach(input => {
        input.addEventListener('change', () => {
            const slot = input.closest('.kyc-slot');
            const out = slot.querySelector('.kyc-slot-file');
            const badge = slot.querySelector('.kyc-slot-badge');
            const f = input.files[0];
            if (!f) return;
            if (f.size > MAX) {
                alert('This file is larger than 5 MB. Please choose a smaller photo.');
                input.value = '';
                return;
            }
            slot.classList.add('picked');
            out.textContent = f.name + ' · ' + Math.round(f.size / 1024) + ' KB';
            badge.textContent = 'Ready to upload';
        });
    });

    const aad = document.getElementById('aadhaar');
    aad.addEventListener('input', () => {
        const d = aad.value.replace(/\D/g, '').slice(0, 12);
        aad.value = d.replace(/(\d{4})(?=\d)/g, '$1 ');
    });

    document.getElementById('kycForm').addEventListener('submit', (e) => {
        const missing = [...document.querySelectorAll('[data-kyc-file][data-required]')].filter(i => !i.files.length);
        if (missing.length) {
            const names = missing.map(i => i.closest('.kyc-slot').querySelector('.kyc-slot-label').textContent.replace(' *', '').trim());
            if (!confirm('These documents are still missing:\n\n• ' + names.join('\n• ') + '\n\nSave what you have and finish later?')) {
                e.preventDefault();
                return;
            }
        }
        const btn = document.getElementById('kycSubmit');
        btn.disabled = true;
        btn.textContent = 'Uploading…';
    });
})();
</script>
</body>
</html>
