<?php
// ===================================================
// User Profile & Address Management - Zayka Platform
// ===================================================
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

$user = current_user();
if (!$user) {
    header("Location: login.php?redirect=profile.php");
    exit;
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');

    if ($name && $phone) {
        $upd = $pdo->prepare("UPDATE users SET name = ?, phone = ? WHERE id = ?");
        $upd->execute([$name, $phone, $user['id']]);
        set_flash('success', 'Profile information updated successfully!');
        header("Location: profile.php");
        exit;
    } else {
        set_flash('error', 'Name and phone are required.');
    }
}

// Fetch user addresses
$addrStmt = $pdo->prepare("SELECT * FROM user_addresses WHERE user_id = ? ORDER BY is_default DESC, id DESC");
$addrStmt->execute([$user['id']]);
$addresses = $addrStmt->fetchAll();

include 'includes/header.php';
?>

<div style="background: #FAF8F2; min-height: 85vh; padding: 40px 0 80px;">
    <div class="container" style="max-width: 850px;">
        <h1 style="font-family: 'Playfair Display', serif; font-size: 32px; color: var(--primary-green); margin: 0 0 24px;">
            Profile & Account Settings ⚙️
        </h1>

        <div class="profile-cols-wrap" style="display: flex; gap: 30px; flex-wrap: wrap; align-items: flex-start;">
            <!-- Profile Info Form -->
            <div style="flex: 1; min-width: 0; width: 100%; background: #fff; border-radius: 16px; border: 1px solid var(--light-border); padding: 24px; box-shadow: var(--shadow-sm);">
                <h3 style="font-family: 'Playfair Display', serif; font-size: 20px; color: var(--primary-green); margin: 0 0 16px;">
                    Personal Details
                </h3>

                <form method="POST">
                    <input type="hidden" name="update_profile" value="1">
                    
                    <div style="margin-bottom: 14px;">
                        <label style="font-size: 13px; font-weight: 600; color: #444; display: block; margin-bottom: 4px;">Full Name</label>
                        <input type="text" name="name" value="<?php echo htmlspecialchars($user['name']); ?>" required 
                               style="width: 100%; padding: 10px; border: 1px solid var(--light-border); border-radius: 8px; font-size: 13px;">
                    </div>

                    <div style="margin-bottom: 14px;">
                        <label style="font-size: 13px; font-weight: 600; color: #444; display: block; margin-bottom: 4px;">Email Address (Read-only)</label>
                        <input type="email" value="<?php echo htmlspecialchars($user['email']); ?>" disabled 
                               style="width: 100%; padding: 10px; border: 1px solid var(--light-border); border-radius: 8px; font-size: 13px; background: #eee;">
                    </div>

                    <div style="margin-bottom: 18px;">
                        <label style="font-size: 13px; font-weight: 600; color: #444; display: block; margin-bottom: 4px;">Phone Number</label>
                        <input type="text" name="phone" value="<?php echo htmlspecialchars($user['phone']); ?>" required 
                               style="width: 100%; padding: 10px; border: 1px solid var(--light-border); border-radius: 8px; font-size: 13px;">
                    </div>

                    <button type="submit" style="width: 100%; padding: 12px; background: var(--primary-green); color: #fff; border-radius: 20px; font-weight: 700; border: none; cursor: pointer;">
                        Save Changes
                    </button>
                </form>
            </div>

            <!-- Saved Addresses -->
            <div style="flex: 1.5; min-width: 0; width: 100%; background: #fff; border-radius: 16px; border: 1px solid var(--light-border); padding: 24px; box-shadow: var(--shadow-sm);">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                    <h3 style="font-family: 'Playfair Display', serif; font-size: 20px; color: var(--primary-green); margin: 0;">
                        Saved Addresses
                    </h3>
                    <button onclick="openModal('addAddressModal')" style="font-size: 13px; color: var(--primary-green); font-weight: 700; cursor: pointer; text-decoration: underline;">
                        + Add Address
                    </button>
                </div>

                <?php if (!empty($addresses)): ?>
                    <div style="display: flex; flex-direction: column; gap: 12px;">
                        <?php foreach ($addresses as $addr): ?>
                        <div style="background: #FAF8F2; border: 1px solid var(--light-border); border-radius: 12px; padding: 14px;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                                <strong style="color: var(--primary-green); font-size: 14px;">
                                    <?php echo ($addr['label'] === 'Home') ? '🏠 Home' : (($addr['label'] === 'Work') ? '🏢 Work' : '📍 ' . htmlspecialchars($addr['label'])); ?>
                                </strong>
                                <?php if ($addr['is_default']): ?>
                                    <span style="font-size: 10px; background: #DEF7EC; color: #046C4E; font-weight: 700; padding: 2px 8px; border-radius: 6px;">DEFAULT</span>
                                <?php endif; ?>
                            </div>
                            <div style="font-size: 13px; color: #555; line-height: 1.4;">
                                <?php echo htmlspecialchars($addr['address_line']); ?><br>
                                <?php if ($addr['landmark']): ?>Near <?php echo htmlspecialchars($addr['landmark']); ?><br><?php endif; ?>
                                <?php echo htmlspecialchars($addr['city'] . ' - ' . $addr['pincode']); ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p style="color: #888; font-size: 14px;">No addresses saved yet.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Add Address Modal -->
<div class="zayka-modal-backdrop" id="addAddressModal">
    <div class="zayka-modal">
        <div class="modal-header">
            <h3>Add New Delivery Address</h3>
            <button class="modal-close" onclick="closeModal('addAddressModal')">✕</button>
        </div>
        <form onsubmit="submitNewAddress(event)">
            <div class="modal-body" style="display: flex; flex-direction: column; gap: 14px;">
                <div>
                    <label style="font-size: 13px; font-weight: 600; color: #444; display: block; margin-bottom: 4px;">Save Address As</label>
                    <select name="label" style="width: 100%; padding: 10px; border: 1px solid var(--light-border); border-radius: 8px;">
                        <option value="Home">Home</option>
                        <option value="Work">Work</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div>
                    <label style="font-size: 13px; font-weight: 600; color: #444; display: block; margin-bottom: 4px;">Full Address Line *</label>
                    <input type="text" name="address_line" placeholder="House/Flat No., Building Name, Street" required style="width: 100%; padding: 10px; border: 1px solid var(--light-border); border-radius: 8px;">
                </div>
                <div>
                    <label style="font-size: 13px; font-weight: 600; color: #444; display: block; margin-bottom: 4px;">Landmark (Optional)</label>
                    <input type="text" name="landmark" placeholder="Near City Mall, Opposite Bank" style="width: 100%; padding: 10px; border: 1px solid var(--light-border); border-radius: 8px;">
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                    <div>
                        <label style="font-size: 13px; font-weight: 600; color: #444; display: block; margin-bottom: 4px;">City</label>
                        <input type="text" name="city" value="<?php echo htmlspecialchars($currentCity ?? 'Dhanbad'); ?>" required style="width: 100%; padding: 10px; border: 1px solid var(--light-border); border-radius: 8px;">
                    </div>
                    <div>
                        <label style="font-size: 13px; font-weight: 600; color: #444; display: block; margin-bottom: 4px;">Pincode</label>
                        <input type="text" name="pincode" value="826001" required style="width: 100%; padding: 10px; border: 1px solid var(--light-border); border-radius: 8px;">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="modal-close" onclick="closeModal('addAddressModal')" style="padding: 10px 18px; border-radius: 20px; font-size: 13px;">Cancel</button>
                <button type="submit" style="padding: 10px 24px; background: var(--primary-green); color: #fff; border-radius: 20px; font-weight: 700; border: none; cursor: pointer;">Save Address</button>
            </div>
        </form>
    </div>
</div>

<script>
function submitNewAddress(e) {
    e.preventDefault();
    const form = e.target;
    const formData = new FormData(form);
    formData.append('action', 'add_address');

    fetch('api/location.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                showToast(data.message, 'success');
                closeModal('addAddressModal');
                setTimeout(() => window.location.reload(), 400);
            } else {
                showToast(data.message, 'error');
            }
        });
}
</script>

<?php include 'includes/footer.php'; ?>
