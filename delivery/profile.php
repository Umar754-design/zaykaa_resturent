<?php
// ===================================================
// Rider Profile & KYC Document Status - Delivery Partner
// ===================================================
include 'includes/header.php';

$riderId = (int)$rider['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $vehicleType = trim($_POST['vehicle_type'] ?? 'Motorcycle');
    $vehicleNumber = trim($_POST['vehicle_number'] ?? '');
    $dl = trim($_POST['driving_license'] ?? '');

    $upd = $pdo->prepare("UPDATE delivery_partners SET vehicle_type = ?, vehicle_number = ?, driving_license = ? WHERE id = ?");
    $upd->execute([$vehicleType, $vehicleNumber, $dl, $riderId]);

    set_flash('success', 'Vehicle information updated successfully!');
    header("Location: profile.php");
    exit;
}
?>

<div style="padding: 30px 0 60px;">
    <div class="container" style="max-width: 700px;">
        <h1 style="font-family: 'Playfair Display', serif; font-size: 28px; color: #1E293B; margin: 0 0 24px;">
            Rider Profile & KYC Credentials 🪪
        </h1>

        <div style="background: #FFFFFF; border-radius: 16px; border: 1px solid var(--light-border); padding: 30px; box-shadow: var(--shadow-sm);">
            
            <!-- KYC Badge Row -->
            <div style="background: #F8FAFC; border-radius: 12px; padding: 18px; margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
                <div>
                    <div style="font-size: 11px; font-weight: 700; color: #64748B; text-transform: uppercase;">Identity Verification Status</div>
                    <div style="font-size: 18px; font-weight: 700; color: #1E293B; margin-top: 2px;">
                        <?php echo htmlspecialchars($user['name']); ?>
                    </div>
                    <div style="font-size: 12px; color: #64748B;">Phone: <?php echo htmlspecialchars($user['phone']); ?></div>
                </div>

                <div style="font-size: 13px; font-weight: 800; padding: 6px 16px; border-radius: 20px; background: <?php echo ($rider['kyc_status'] === 'approved') ? '#DEF7EC' : '#FEF3C7'; ?>; color: <?php echo ($rider['kyc_status'] === 'approved') ? '#046C4E' : '#B45309'; ?>;">
                    ✓ <?php echo strtoupper($rider['kyc_status']); ?>
                </div>
            </div>

            <!-- Rider Verified Selfies Display -->
            <div style="margin-bottom: 24px; padding: 18px; background: #F1F5F9; border-radius: 12px; border: 1px solid #E2E8F0;">
                <div style="font-size: 12px; font-weight: 700; color: #334155; text-transform: uppercase; margin-bottom: 12px;">
                    📸 Verified Rider Photos & Shift Attendance
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <!-- Registered KYC Selfie -->
                    <div style="text-align: center; background: #FFFFFF; padding: 14px; border-radius: 10px; border: 1px solid #CBD5E1;">
                        <div style="font-size: 11px; font-weight: 700; color: #64748B; margin-bottom: 8px;">Registration KYC Selfie</div>
                        <?php if (!empty($rider['selfie_image'])): ?>
                            <img src="../<?php echo htmlspecialchars($rider['selfie_image']); ?>" alt="KYC Selfie" 
                                 style="width: 90px; height: 90px; border-radius: 50%; object-fit: cover; margin: 0 auto 6px; border: 2px solid #059669; display: block;">
                            <span style="font-size: 11px; color: #059669; font-weight: 600;">KYC Verified ✓</span>
                        <?php else: ?>
                            <div style="width: 90px; height: 90px; border-radius: 50%; background: #E2E8F0; margin: 0 auto 6px; display: flex; align-items: center; justify-content: center; font-size: 24px;">👤</div>
                            <span style="font-size: 11px; color: #888;">No photo uploaded</span>
                        <?php endif; ?>
                    </div>

                    <!-- Current Shift Live Selfie -->
                    <div style="text-align: center; background: #FFFFFF; padding: 14px; border-radius: 10px; border: 1px solid #CBD5E1;">
                        <div style="font-size: 11px; font-weight: 700; color: #059669; margin-bottom: 8px;">Current Shift Live Selfie</div>
                        <?php if (!empty($rider['current_selfie'])): ?>
                            <img src="../<?php echo htmlspecialchars($rider['current_selfie']); ?>" alt="Current Shift Selfie" 
                                 style="width: 90px; height: 90px; border-radius: 50%; object-fit: cover; margin: 0 auto 6px; border: 2px solid #3B82F6; display: block;">
                            <span style="font-size: 11px; color: #1E293B; font-weight: 600;">
                                <?php echo !empty($rider['current_selfie_at']) ? date('d M, h:i A', strtotime($rider['current_selfie_at'])) : 'Active Today'; ?>
                            </span>
                        <?php else: ?>
                            <div style="width: 90px; height: 90px; border-radius: 50%; background: #E2E8F0; margin: 0 auto 6px; display: flex; align-items: center; justify-content: center; font-size: 24px;">📸</div>
                            <span style="font-size: 11px; color: #888;">Required on login</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <form method="POST">
                <div style="margin-bottom: 16px;">
                    <label style="font-size: 13px; font-weight: 600; color: #444; display: block; margin-bottom: 4px;">Vehicle Type</label>
                    <select name="vehicle_type" style="width: 100%; padding: 10px; border: 1px solid var(--light-border); border-radius: 8px; font-size: 14px;">
                        <option value="Motorcycle" <?php echo ($rider['vehicle_type'] === 'Motorcycle') ? 'selected' : ''; ?>>🏍️ Motorcycle</option>
                        <option value="Scooter" <?php echo ($rider['vehicle_type'] === 'Scooter') ? 'selected' : ''; ?>>🛵 Scooter</option>
                        <option value="Electric Bike" <?php echo ($rider['vehicle_type'] === 'Electric Bike') ? 'selected' : ''; ?>>⚡ Electric Bike (EV)</option>
                        <option value="Bicycle" <?php echo ($rider['vehicle_type'] === 'Bicycle') ? 'selected' : ''; ?>>🚲 Bicycle</option>
                    </select>
                </div>

                <div style="margin-bottom: 16px;">
                    <label style="font-size: 13px; font-weight: 600; color: #444; display: block; margin-bottom: 4px;">Vehicle Registration Number *</label>
                    <input type="text" name="vehicle_number" value="<?php echo htmlspecialchars($rider['vehicle_number']); ?>" required 
                           style="width: 100%; padding: 10px; border: 1px solid var(--light-border); border-radius: 8px; font-size: 14px;">
                </div>

                <div style="margin-bottom: 24px;">
                    <label style="font-size: 13px; font-weight: 600; color: #444; display: block; margin-bottom: 4px;">Driving License Number *</label>
                    <input type="text" name="driving_license" value="<?php echo htmlspecialchars($rider['driving_license']); ?>" required 
                           style="width: 100%; padding: 10px; border: 1px solid var(--light-border); border-radius: 8px; font-size: 14px;">
                </div>

                <button type="submit" style="width: 100%; padding: 14px; background: #1E293B; color: #fff; border-radius: 25px; font-weight: 700; font-size: 15px; border: none; cursor: pointer;">
                    Save Profile Details
                </button>
            </form>

        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
