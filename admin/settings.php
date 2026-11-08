<?php
// ===================================================
// Admin: Global Platform Settings & Configurations
// ===================================================
require_once __DIR__ . '/includes/admin-header.php';

// Handle Settings Update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $settings = $_POST['settings'] ?? [];

    // Ensure checkboxes if unchecked are treated as '0'
    $checkbox_keys = ['cod_enabled', 'online_payment_enabled'];
    foreach ($checkbox_keys as $ck) {
        if (!isset($settings[$ck])) {
            $settings[$ck] = '0';
        }
    }

    $stmt = $pdo->prepare("INSERT INTO platform_settings (setting_key, setting_value) VALUES (?, ?) 
                           ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");

    foreach ($settings as $key => $val) {
        $stmt->execute([$key, trim($val)]);
    }

    set_flash('success', "Platform configuration parameters updated successfully!");
    header('Location: settings.php');
    exit;
}

// Load all current settings into a lookup map
$rows = $pdo->query("SELECT setting_key, setting_value, description FROM platform_settings")->fetchAll();
$s = [];
foreach ($rows as $r) {
    $s[$r['setting_key']] = $r['setting_value'];
}
?>

<div class="container" style="padding: 24px 20px; max-width: 1100px;">
    <!-- Page Header -->
    <div style="margin-bottom: 24px;">
        <h1 style="font-size: 26px; font-weight: 700; color: #0B192C; margin: 0 0 6px 0;">Platform System Configurations</h1>
        <p style="color: #64748B; font-size: 14px; margin: 0;">Global fee schedules, tax rules, delivery radius limits, payment gateway controls, and partner commissions.</p>
    </div>

    <form method="POST">
        <!-- 1. Platform Identity & Support Info -->
        <div style="background: #fff; border-radius: 12px; border: 1px solid #E2E8F0; padding: 24px; margin-bottom: 24px; box-shadow: var(--shadow-sm);">
            <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 18px; border-bottom: 1px solid #F1F5F9; padding-bottom: 14px;">
                <span style="font-size: 20px;">🏢</span>
                <h3 style="margin: 0; font-size: 17px; color: #0F172A;">Platform Identity & Contact</h3>
            </div>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 18px;">
                <div>
                    <label style="display: block; font-size: 13px; font-weight: 600; color: #334155; margin-bottom: 6px;">Platform Brand Name</label>
                    <input type="text" name="settings[platform_name]" value="<?php echo htmlspecialchars($s['platform_name'] ?? 'Zaykaa'); ?>" style="width: 100%; padding: 10px 12px; border: 1px solid #CBD5E1; border-radius: 8px; font-size: 14px;">
                </div>
                <div>
                    <label style="display: block; font-size: 13px; font-weight: 600; color: #334155; margin-bottom: 6px;">Customer Support Email</label>
                    <input type="email" name="settings[support_email]" value="<?php echo htmlspecialchars($s['support_email'] ?? 'support@zayka.com'); ?>" style="width: 100%; padding: 10px 12px; border: 1px solid #CBD5E1; border-radius: 8px; font-size: 14px;">
                </div>
                <div>
                    <label style="display: block; font-size: 13px; font-weight: 600; color: #334155; margin-bottom: 6px;">Support Toll-Free / Phone</label>
                    <input type="text" name="settings[support_phone]" value="<?php echo htmlspecialchars($s['support_phone'] ?? '+91 96935 17942'); ?>" style="width: 100%; padding: 10px 12px; border: 1px solid #CBD5E1; border-radius: 8px; font-size: 14px;">
                </div>
            </div>
        </div>

        <!-- 2. Pricing & Delivery Distance Engine -->
        <div style="background: #fff; border-radius: 12px; border: 1px solid #E2E8F0; padding: 24px; margin-bottom: 24px; box-shadow: var(--shadow-sm);">
            <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 18px; border-bottom: 1px solid #F1F5F9; padding-bottom: 14px;">
                <span style="font-size: 20px;">🛵</span>
                <h3 style="margin: 0; font-size: 17px; color: #0F172A;">Delivery Pricing & Distance Engine</h3>
            </div>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 18px;">
                <div>
                    <label style="display: block; font-size: 13px; font-weight: 600; color: #334155; margin-bottom: 6px;">Base Delivery Fee (₹)</label>
                    <input type="number" step="0.01" name="settings[base_delivery_fee]" value="<?php echo htmlspecialchars($s['base_delivery_fee'] ?? '30.00'); ?>" style="width: 100%; padding: 10px 12px; border: 1px solid #CBD5E1; border-radius: 8px; font-size: 14px;">
                    <span style="font-size: 11px; color: #64748B;">For first 3 km distance</span>
                </div>
                <div>
                    <label style="display: block; font-size: 13px; font-weight: 600; color: #334155; margin-bottom: 6px;">Per-KM Surcharge (₹)</label>
                    <input type="number" step="0.01" name="settings[per_km_fee]" value="<?php echo htmlspecialchars($s['per_km_fee'] ?? '10.00'); ?>" style="width: 100%; padding: 10px 12px; border: 1px solid #CBD5E1; border-radius: 8px; font-size: 14px;">
                    <span style="font-size: 11px; color: #64748B;">Added beyond 3 km base</span>
                </div>
                <div>
                    <label style="display: block; font-size: 13px; font-weight: 600; color: #334155; margin-bottom: 6px;">Platform Service Fee (₹)</label>
                    <input type="number" step="0.01" name="settings[platform_service_fee]" value="<?php echo htmlspecialchars($s['platform_service_fee'] ?? '5.00'); ?>" style="width: 100%; padding: 10px 12px; border: 1px solid #CBD5E1; border-radius: 8px; font-size: 14px;">
                    <span style="font-size: 11px; color: #64748B;">Flat convenience fee per checkout</span>
                </div>
                <div>
                    <label style="display: block; font-size: 13px; font-weight: 600; color: #334155; margin-bottom: 6px;">Max Delivery Radius (KM)</label>
                    <input type="number" step="0.5" name="settings[max_delivery_radius_km]" value="<?php echo htmlspecialchars($s['max_delivery_radius_km'] ?? '15.00'); ?>" style="width: 100%; padding: 10px 12px; border: 1px solid #CBD5E1; border-radius: 8px; font-size: 14px;">
                    <span style="font-size: 11px; color: #64748B;">Max customer search boundary</span>
                </div>
            </div>
        </div>

        <!-- 3. Commission Economics & Taxation -->
        <div style="background: #fff; border-radius: 12px; border: 1px solid #E2E8F0; padding: 24px; margin-bottom: 24px; box-shadow: var(--shadow-sm);">
            <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 18px; border-bottom: 1px solid #F1F5F9; padding-bottom: 14px;">
                <span style="font-size: 20px;">⚖️</span>
                <h3 style="margin: 0; font-size: 17px; color: #0F172A;">Commissions & GST Taxation</h3>
            </div>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 18px;">
                <div>
                    <label style="display: block; font-size: 13px; font-weight: 600; color: #334155; margin-bottom: 6px;">Default Restaurant Commission (%)</label>
                    <input type="number" step="0.1" name="settings[default_commission_percent]" value="<?php echo htmlspecialchars($s['default_commission_percent'] ?? '15.00'); ?>" style="width: 100%; padding: 10px 12px; border: 1px solid #CBD5E1; border-radius: 8px; font-size: 14px;">
                    <span style="font-size: 11px; color: #64748B;">Applied to food subtotal unless customized per restaurant</span>
                </div>
                <div>
                    <label style="display: block; font-size: 13px; font-weight: 600; color: #334155; margin-bottom: 6px;">Restaurant Food GST Rate (%)</label>
                    <input type="number" step="0.1" name="settings[gst_tax_rate_percent]" value="<?php echo htmlspecialchars($s['gst_tax_rate_percent'] ?? '5.00'); ?>" style="width: 100%; padding: 10px 12px; border: 1px solid #CBD5E1; border-radius: 8px; font-size: 14px;">
                    <span style="font-size: 11px; color: #64748B;">Government GST compliance for food services</span>
                </div>
            </div>
        </div>

        <!-- 4. Rider Compensation Engine -->
        <div style="background: #fff; border-radius: 12px; border: 1px solid #E2E8F0; padding: 24px; margin-bottom: 24px; box-shadow: var(--shadow-sm);">
            <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 18px; border-bottom: 1px solid #F1F5F9; padding-bottom: 14px;">
                <span style="font-size: 20px;">💰</span>
                <h3 style="margin: 0; font-size: 17px; color: #0F172A;">Delivery Partner Trip Pay Rates</h3>
            </div>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 18px;">
                <div>
                    <label style="display: block; font-size: 13px; font-weight: 600; color: #334155; margin-bottom: 6px;">Rider Base Trip Earning (₹)</label>
                    <input type="number" step="0.01" name="settings[rider_delivery_base_pay]" value="<?php echo htmlspecialchars($s['rider_delivery_base_pay'] ?? '35.00'); ?>" style="width: 100%; padding: 10px 12px; border: 1px solid #CBD5E1; border-radius: 8px; font-size: 14px;">
                    <span style="font-size: 11px; color: #64748B;">Fixed amount paid to rider per delivery</span>
                </div>
                <div>
                    <label style="display: block; font-size: 13px; font-weight: 600; color: #334155; margin-bottom: 6px;">Rider Distance Incentive (₹/KM)</label>
                    <input type="number" step="0.01" name="settings[rider_per_km_pay]" value="<?php echo htmlspecialchars($s['rider_per_km_pay'] ?? '8.00'); ?>" style="width: 100%; padding: 10px 12px; border: 1px solid #CBD5E1; border-radius: 8px; font-size: 14px;">
                    <span style="font-size: 11px; color: #64748B;">Added incentive per KM of transit</span>
                </div>
            </div>
        </div>

        <!-- 5. Payment Methods & Gateway Modes -->
        <div style="background: #fff; border-radius: 12px; border: 1px solid #E2E8F0; padding: 24px; margin-bottom: 24px; box-shadow: var(--shadow-sm);">
            <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 18px; border-bottom: 1px solid #F1F5F9; padding-bottom: 14px;">
                <span style="font-size: 20px;">💳</span>
                <h3 style="margin: 0; font-size: 17px; color: #0F172A;">Payment Gateways & Checkout Controls</h3>
            </div>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 18px; margin-bottom: 18px;">
                <div>
                    <label style="display: block; font-size: 13px; font-weight: 600; color: #334155; margin-bottom: 6px;">Payment Gateway Mode</label>
                    <select name="settings[payment_gateway_mode]" style="width: 100%; padding: 10px 12px; border: 1px solid #CBD5E1; border-radius: 8px; font-size: 14px; background: #fff;">
                        <option value="simulation" <?php echo (($s['payment_gateway_mode'] ?? 'simulation') === 'simulation') ? 'selected' : ''; ?>>
                            ⚡ Instant Instant Simulation (Zero API key needed)
                        </option>
                        <option value="razorpay" <?php echo (($s['payment_gateway_mode'] ?? '') === 'razorpay') ? 'selected' : ''; ?>>
                            Razorpay Production Gateway
                        </option>
                    </select>
                </div>
            </div>

            <div style="display: flex; flex-direction: column; gap: 12px;">
                <label style="display: flex; align-items: center; gap: 10px; font-size: 14px; font-weight: 600; color: #334155; cursor: pointer;">
                    <input type="checkbox" name="settings[cod_enabled]" value="1" <?php echo (!isset($s['cod_enabled']) || $s['cod_enabled'] == '1') ? 'checked' : ''; ?> style="width: 18px; height: 18px;">
                    Enable Cash on Delivery (COD) for Customers
                </label>

                <label style="display: flex; align-items: center; gap: 10px; font-size: 14px; font-weight: 600; color: #334155; cursor: pointer;">
                    <input type="checkbox" name="settings[online_payment_enabled]" value="1" <?php echo (!isset($s['online_payment_enabled']) || $s['online_payment_enabled'] == '1') ? 'checked' : ''; ?> style="width: 18px; height: 18px;">
                    Enable Instant Online / UPI / Card Payments
                </label>
            </div>
        </div>

        <!-- Submit Bar -->
        <div style="display: flex; justify-content: flex-end; gap: 14px; margin-bottom: 40px;">
            <button type="reset" class="btn" style="background: #E2E8F0; color: #334155; padding: 12px 24px;">Discard Changes</button>
            <button type="submit" class="btn btn-primary" style="padding: 12px 32px; font-size: 15px; font-weight: 600;">
                💾 Save Platform Settings
            </button>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
