<?php
// ===================================================
// Delivery Fleet Management - Admin Control Center
// ===================================================
include 'includes/admin-header.php';

// Handle KYC approval / status toggle
if (isset($_GET['action']) && isset($_GET['id'])) {
    $act = $_GET['action'];
    $rId = (int)$_GET['id'];

    if ($act === 'approve_kyc') {
        $pdo->prepare("UPDATE delivery_partners SET kyc_status = 'approved' WHERE id = ?")->execute([$rId]);
        set_flash('success', 'Rider KYC approved successfully!');
    } elseif ($act === 'reject_kyc') {
        $pdo->prepare("UPDATE delivery_partners SET kyc_status = 'rejected', is_online = 0 WHERE id = ?")->execute([$rId]);
        set_flash('error', 'Rider KYC rejected.');
    }
    header("Location: delivery-partners.php");
    exit;
}

$riders = $pdo->query("SELECT dp.*, u.name, u.email, u.phone, u.status as user_status 
                       FROM delivery_partners dp 
                       JOIN users u ON dp.user_id = u.id 
                       ORDER BY dp.id DESC")->fetchAll();
?>

<div style="padding: 30px 0 60px;">
    <div class="container">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 15px;">
            <div>
                <h1 style="font-family: 'Playfair Display', serif; font-size: 28px; color: #0B192C; margin: 0 0 4px;">
                    Delivery Fleet & Rider KYC
                </h1>
                <p style="color: #64748B; font-size: 14px; margin: 0;">
                    Verify driver licenses, manage active duty riders, and monitor delivery ratings
                </p>
            </div>
        </div>

        <div style="background: #FFFFFF; border-radius: 16px; border: 1px solid var(--light-border); padding: 24px; box-shadow: var(--shadow-sm);">
            <div class="table-responsive">
                <table class="zayka-table">
                    <thead>
                        <tr>
                            <th>Rider Name</th>
                            <th>Selfie Verification</th>
                            <th>Contact Phone</th>
                            <th>Vehicle & License</th>
                            <th>Duty Status</th>
                            <th>Trips Completed</th>
                            <th>Rating</th>
                            <th>KYC Verification</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($riders)): ?>
                            <?php foreach ($riders as $rd): ?>
                            <tr>
                                <td>
                                    <strong style="color: #0B192C; font-size: 15px;"><?php echo htmlspecialchars($rd['name']); ?></strong>
                                    <div style="font-size: 11px; color: #64748B;"><?php echo htmlspecialchars($rd['email']); ?></div>
                                </td>
                                <td>
                                    <div style="display: flex; gap: 8px; align-items: center;">
                                        <!-- KYC Selfie -->
                                        <div style="text-align: center;">
                                            <?php if (!empty($rd['selfie_image'])): ?>
                                                <img src="../<?php echo htmlspecialchars($rd['selfie_image']); ?>" 
                                                     alt="KYC Selfie" 
                                                     onclick="previewSelfieModal('../<?php echo htmlspecialchars($rd['selfie_image']); ?>', 'KYC Registration Photo: <?php echo htmlspecialchars(addslashes($rd['name'])); ?>')"
                                                     style="width: 44px; height: 44px; border-radius: 8px; object-fit: cover; border: 2px solid #059669; cursor: pointer; display: block;" 
                                                     title="Click to view full photo">
                                                <span style="font-size: 10px; color: #059669; font-weight: 700;">KYC Photo</span>
                                            <?php else: ?>
                                                <div style="width: 44px; height: 44px; border-radius: 8px; background: #F1F5F9; border: 1px dashed #CBD5E1; display: flex; align-items: center; justify-content: center; font-size: 18px;">👤</div>
                                                <span style="font-size: 10px; color: #888;">No KYC</span>
                                            <?php endif; ?>
                                        </div>

                                        <!-- Current Shift Selfie -->
                                        <div style="text-align: center;">
                                            <?php if (!empty($rd['current_selfie'])): ?>
                                                <img src="../<?php echo htmlspecialchars($rd['current_selfie']); ?>" 
                                                     alt="Current Shift Selfie" 
                                                     onclick="previewSelfieModal('../<?php echo htmlspecialchars($rd['current_selfie']); ?>', 'Live Shift Selfie: <?php echo htmlspecialchars(addslashes($rd['name'])); ?> (<?php echo !empty($rd['current_selfie_at']) ? date('d M, h:i A', strtotime($rd['current_selfie_at'])) : ''; ?>)')"
                                                     style="width: 44px; height: 44px; border-radius: 8px; object-fit: cover; border: 2px solid #3B82F6; cursor: pointer; display: block;" 
                                                     title="Click to view full photo">
                                                <span style="font-size: 10px; color: #2563EB; font-weight: 700;">Shift Selfie</span>
                                            <?php else: ?>
                                                <div style="width: 44px; height: 44px; border-radius: 8px; background: #F1F5F9; border: 1px dashed #CBD5E1; display: flex; align-items: center; justify-content: center; font-size: 18px;">📸</div>
                                                <span style="font-size: 10px; color: #888;">No Shift</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($rd['phone']); ?></td>
                                <td style="font-size: 13px;">
                                    <strong><?php echo htmlspecialchars($rd['vehicle_type']); ?></strong><br>
                                    <span style="color: #64748B; font-size: 12px;"><?php echo htmlspecialchars($rd['vehicle_number']); ?></span><br>
                                    <span style="color: #888; font-size: 11px;">DL: <?php echo htmlspecialchars($rd['driving_license']); ?></span>
                                </td>
                                <td>
                                    <?php if ($rd['is_online']): ?>
                                        <span style="font-size: 11px; font-weight: 700; background: #DEF7EC; color: #046C4E; padding: 2px 8px; border-radius: 4px;">
                                            ● ONLINE (ON DUTY)
                                        </span>
                                    <?php else: ?>
                                        <span style="font-size: 11px; font-weight: 700; background: #F1F5F9; color: #64748B; padding: 2px 8px; border-radius: 4px;">
                                            ○ OFFLINE
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong><?php echo (int)$rd['total_deliveries']; ?></strong> trips
                                    <div style="font-size: 11px; color: #059669; font-weight: bold;">Bal: <?php echo format_price($rd['earnings_balance']); ?></div>
                                </td>
                                <td>
                                    <span class="rating-pill">★ <?php echo number_format($rd['rating'], 1); ?></span>
                                </td>
                                <td>
                                    <span style="font-size: 11px; font-weight: 700; padding: 3px 8px; border-radius: 12px; background: <?php echo ($rd['kyc_status'] === 'approved') ? '#DEF7EC' : '#FEF3C7'; ?>; color: <?php echo ($rd['kyc_status'] === 'approved') ? '#046C4E' : '#B45309'; ?>;">
                                        <?php echo strtoupper($rd['kyc_status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div style="display: flex; gap: 6px;">
                                        <?php if ($rd['kyc_status'] !== 'approved'): ?>
                                            <a href="delivery-partners.php?action=approve_kyc&id=<?php echo $rd['id']; ?>" style="padding: 4px 10px; background: #DEF7EC; color: #046C4E; border-radius: 6px; font-size: 12px; font-weight: 700; text-decoration: none;">
                                                Approve KYC
                                            </a>
                                        <?php else: ?>
                                            <a href="delivery-partners.php?action=reject_kyc&id=<?php echo $rd['id']; ?>" style="padding: 4px 10px; background: #FDE8E8; color: #9B1C1C; border-radius: 6px; font-size: 12px; font-weight: 700; text-decoration: none;">
                                                Revoke
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" style="text-align: center; color: #888; padding: 30px;">
                                    No delivery partners registered yet.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

<!-- Selfie Photo Modal -->
<div id="selfieInspectModal" style="display: none; position: fixed; inset: 0; z-index: 99999; background: rgba(0,0,0,0.75); align-items: center; justify-content: center; padding: 20px;">
    <div style="background: #FFFFFF; border-radius: 16px; max-width: 440px; width: 100%; overflow: hidden; box-shadow: 0 10px 40px rgba(0,0,0,0.3); text-align: center;">
        <div style="padding: 14px 18px; background: #0B192C; color: #FFFFFF; display: flex; justify-content: space-between; align-items: center;">
            <span id="selfieModalTitle" style="font-size: 14px; font-weight: 700;">Rider Photo Inspection</span>
            <button type="button" onclick="closeSelfieModal()" style="background: none; border: none; color: #FFF; font-size: 18px; cursor: pointer;">✕</button>
        </div>
        <div style="padding: 20px; background: #F8FAFC;">
            <img id="selfieModalImg" src="" alt="Selfie" style="max-width: 100%; max-height: 380px; border-radius: 10px; object-fit: contain; box-shadow: var(--shadow-sm); display: inline-block;">
        </div>
        <div style="padding: 12px; background: #FFFFFF; border-top: 1px solid #E2E8F0;">
            <button type="button" onclick="closeSelfieModal()" style="padding: 8px 24px; background: #0B192C; color: #FFF; border-radius: 20px; font-weight: 700; border: none; cursor: pointer; font-size: 13px;">
                Close Preview
            </button>
        </div>
    </div>
</div>

<script>
function previewSelfieModal(imgSrc, title) {
    document.getElementById('selfieModalImg').src = imgSrc;
    document.getElementById('selfieModalTitle').textContent = title || 'Rider Photo Inspection';
    const modal = document.getElementById('selfieInspectModal');
    modal.style.display = 'flex';
}
function closeSelfieModal() {
    document.getElementById('selfieInspectModal').style.display = 'none';
}
</script>

<?php include 'includes/admin-footer.php'; ?>
