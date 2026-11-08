<?php
// ===================================================
// Edit Restaurant & Commission - Admin Control Center
// ===================================================
include 'includes/admin-header.php';

$rId = (int)($_GET['id'] ?? 0);
$restaurant = null;
if ($rId) {
    $stmt = $pdo->prepare("SELECT * FROM restaurants WHERE id = ?");
    $stmt->execute([$rId]);
    $restaurant = $stmt->fetch();
}

$owners = $pdo->query("SELECT id, name, email FROM users WHERE role IN ('restaurant', 'admin')")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $ownerId = (int)($_POST['owner_id'] ?? 0);
    $cuisines = trim($_POST['cuisines'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $city = trim($_POST['city'] ?? 'Dhanbad');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $commissionRate = (float)($_POST['commission_rate'] ?? 15.00);
    $isApproved = !empty($_POST['is_approved']) ? 1 : 0;
    $isFeatured = !empty($_POST['is_featured']) ? 1 : 0;
    $isPureVeg = !empty($_POST['is_pure_veg']) ? 1 : 0;

    if ($name && $ownerId) {
        if ($restaurant) {
            $upd = $pdo->prepare("UPDATE restaurants SET name = ?, owner_id = ?, cuisines = ?, address = ?, city = ?, phone = ?, email = ?, commission_rate = ?, is_approved = ?, is_featured = ?, is_pure_veg = ? WHERE id = ?");
            $upd->execute([$name, $ownerId, $cuisines, $address, $city, $phone, $email, $commissionRate, $isApproved, $isFeatured, $isPureVeg, $rId]);
            set_flash('success', 'Restaurant details updated successfully!');
        } else {
            $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name), '-')) . '-' . rand(10, 99);
            $ins = $pdo->prepare("INSERT INTO restaurants (name, slug, owner_id, cuisines, address, city, phone, email, commission_rate, is_approved, is_featured, is_pure_veg) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $ins->execute([$name, $slug, $ownerId, $cuisines, $address, $city, $phone, $email, $commissionRate, $isApproved, $isFeatured, $isPureVeg]);
            set_flash('success', 'New restaurant created successfully!');
        }
        header("Location: restaurants.php");
        exit;
    } else {
        set_flash('error', 'Name and owner are required.');
    }
}
?>

<div style="padding: 30px 0 60px;">
    <div class="container" style="max-width: 800px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
            <h1 style="font-family: 'Playfair Display', serif; font-size: 28px; color: #0B192C; margin: 0;">
                <?php echo $restaurant ? 'Edit Restaurant: ' . htmlspecialchars($restaurant['name']) : 'Add New Restaurant'; ?>
            </h1>
            <a href="restaurants.php" style="color: #64748B; font-size: 13px; font-weight: 600; text-decoration: none;">← Back to Restaurants</a>
        </div>

        <div style="background: #FFFFFF; border-radius: 16px; border: 1px solid var(--light-border); padding: 30px; box-shadow: var(--shadow-sm);">
            <form method="POST">
                <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 16px; margin-bottom: 16px;">
                    <div>
                        <label style="font-size: 13px; font-weight: 600; color: #444; display: block; margin-bottom: 4px;">Restaurant Name *</label>
                        <input type="text" name="name" value="<?php echo htmlspecialchars($restaurant['name'] ?? ''); ?>" required 
                               style="width: 100%; padding: 10px; border: 1px solid var(--light-border); border-radius: 8px; font-size: 14px;">
                    </div>
                    <div>
                        <label style="font-size: 13px; font-weight: 600; color: #444; display: block; margin-bottom: 4px;">Merchant Owner Account *</label>
                        <select name="owner_id" required style="width: 100%; padding: 10px; border: 1px solid var(--light-border); border-radius: 8px; font-size: 13px;">
                            <?php foreach ($owners as $ow): ?>
                                <option value="<?php echo $ow['id']; ?>" <?php echo (!empty($restaurant['owner_id']) && $restaurant['owner_id'] == $ow['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($ow['name'] . ' (' . $ow['email'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
                    <div>
                        <label style="font-size: 13px; font-weight: 600; color: #444; display: block; margin-bottom: 4px;">Contact Phone</label>
                        <input type="text" name="phone" value="<?php echo htmlspecialchars($restaurant['phone'] ?? '+91 98765 43210'); ?>" 
                               style="width: 100%; padding: 10px; border: 1px solid var(--light-border); border-radius: 8px; font-size: 14px;">
                    </div>
                    <div>
                        <label style="font-size: 13px; font-weight: 600; color: #444; display: block; margin-bottom: 4px;">Contact Email</label>
                        <input type="email" name="email" value="<?php echo htmlspecialchars($restaurant['email'] ?? 'partner@zayka.com'); ?>" 
                               style="width: 100%; padding: 10px; border: 1px solid var(--light-border); border-radius: 8px; font-size: 14px;">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 16px; margin-bottom: 16px;">
                    <div>
                        <label style="font-size: 13px; font-weight: 600; color: #444; display: block; margin-bottom: 4px;">Street Address</label>
                        <input type="text" name="address" value="<?php echo htmlspecialchars($restaurant['address'] ?? ''); ?>" required 
                               style="width: 100%; padding: 10px; border: 1px solid var(--light-border); border-radius: 8px; font-size: 14px;">
                    </div>
                    <div>
                        <label style="font-size: 13px; font-weight: 600; color: #444; display: block; margin-bottom: 4px;">City</label>
                        <input type="text" name="city" value="<?php echo htmlspecialchars($restaurant['city'] ?? 'Dhanbad'); ?>" required 
                               style="width: 100%; padding: 10px; border: 1px solid var(--light-border); border-radius: 8px; font-size: 14px;">
                    </div>
                </div>

                <div style="margin-bottom: 16px;">
                    <label style="font-size: 13px; font-weight: 600; color: #444; display: block; margin-bottom: 4px;">Cuisines</label>
                    <input type="text" name="cuisines" value="<?php echo htmlspecialchars($restaurant['cuisines'] ?? 'North Indian, Biryani'); ?>" 
                           style="width: 100%; padding: 10px; border: 1px solid var(--light-border); border-radius: 8px; font-size: 14px;">
                </div>

                <div style="background: #FAF8F2; border-radius: 12px; padding: 20px; border: 1px solid var(--light-border); margin-bottom: 24px;">
                    <h4 style="margin: 0 0 14px; color: var(--primary-green);">Platform Governance & Commission</h4>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 14px;">
                        <div>
                            <label style="font-size: 13px; font-weight: 600; color: #444; display: block; margin-bottom: 4px;">Platform Commission (%) *</label>
                            <input type="number" step="0.5" name="commission_rate" value="<?php echo htmlspecialchars($restaurant['commission_rate'] ?? '15.00'); ?>" required 
                                   style="width: 100%; padding: 10px; border: 1px solid var(--light-border); border-radius: 8px; font-size: 14px; font-weight: 700; color: #059669;">
                        </div>
                    </div>

                    <div style="display: flex; gap: 20px; flex-wrap: wrap;">
                        <label style="display: flex; align-items: center; gap: 8px; font-size: 13px; font-weight: 600; cursor: pointer;">
                            <input type="checkbox" name="is_approved" value="1" <?php echo (!empty($restaurant['is_approved']) || !$restaurant) ? 'checked' : ''; ?> style="accent-color: #059669; width: 18px; height: 18px;">
                            <span>Approved (Visible on Storefront)</span>
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; font-size: 13px; font-weight: 600; cursor: pointer;">
                            <input type="checkbox" name="is_featured" value="1" <?php echo (!empty($restaurant['is_featured'])) ? 'checked' : ''; ?> style="accent-color: var(--gold); width: 18px; height: 18px;">
                            <span>Featured on Homepage Carousel</span>
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; font-size: 13px; font-weight: 600; cursor: pointer;">
                            <input type="checkbox" name="is_pure_veg" value="1" <?php echo (!empty($restaurant['is_pure_veg'])) ? 'checked' : ''; ?> style="accent-color: #16A34A; width: 18px; height: 18px;">
                            <span>Pure Vegetarian Restaurant 🥬</span>
                        </label>
                    </div>
                </div>

                <button type="submit" style="width: 100%; padding: 14px; background: #0B192C; color: #fff; border-radius: 25px; font-weight: 700; font-size: 15px; border: none; cursor: pointer;">
                    Save Restaurant Configuration ✓
                </button>
            </form>
        </div>
    </div>
</div>

<?php include 'includes/admin-footer.php'; ?>
