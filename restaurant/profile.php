<?php
// ===================================================
// Restaurant Profile & Operating Settings
// ===================================================
include 'includes/header.php';

$restId = (int)$currentRestaurant['id'];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $tagline = trim($_POST['tagline'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $cuisines = trim($_POST['cuisines'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $city = trim($_POST['city'] ?? 'Dhanbad');
    $phone = trim($_POST['phone'] ?? '');
    $openingTime = $_POST['opening_time'] ?? '10:00:00';
    $closingTime = $_POST['closing_time'] ?? '23:00:00';
    $minOrder = (float)($_POST['min_order_amount'] ?? 149.00);
    $costForTwo = (float)($_POST['cost_for_two'] ?? 400.00);
    $deliveryRadius = (float)($_POST['delivery_radius_km'] ?? 10.00);
    $isPureVeg = !empty($_POST['is_pure_veg']) ? 1 : 0;

    $upd = $pdo->prepare("UPDATE restaurants SET name = ?, tagline = ?, description = ?, cuisines = ?, 
                          address = ?, city = ?, phone = ?, opening_time = ?, closing_time = ?, 
                          min_order_amount = ?, cost_for_two = ?, delivery_radius_km = ?, is_pure_veg = ? 
                          WHERE id = ?");
    $upd->execute([$name, $tagline, $description, $cuisines, $address, $city, $phone, $openingTime, $closingTime, $minOrder, $costForTwo, $deliveryRadius, $isPureVeg, $restId]);

    set_flash('success', 'Restaurant profile and operating settings updated!');
    header("Location: profile.php");
    exit;
}
?>

<div style="padding: 30px 0 60px;">
    <div class="container" style="max-width: 800px;">
        <h1 style="font-family: 'Playfair Display', serif; font-size: 28px; color: var(--primary-green); margin: 0 0 24px;">
            Restaurant Profile & Operating Settings ⚙️
        </h1>

        <div style="background: #FFFFFF; border-radius: 16px; border: 1px solid var(--light-border); padding: 30px; box-shadow: var(--shadow-sm);">
            <form method="POST">
                <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 16px; margin-bottom: 16px;">
                    <div>
                        <label style="font-size: 13px; font-weight: 600; color: #444; display: block; margin-bottom: 4px;">Restaurant Name *</label>
                        <input type="text" name="name" value="<?php echo htmlspecialchars($currentRestaurant['name']); ?>" required 
                               style="width: 100%; padding: 10px; border: 1px solid var(--light-border); border-radius: 8px; font-size: 14px;">
                    </div>
                    <div>
                        <label style="font-size: 13px; font-weight: 600; color: #444; display: block; margin-bottom: 4px;">Dietary Classification</label>
                        <select name="is_pure_veg" style="width: 100%; padding: 10px; border: 1px solid var(--light-border); border-radius: 8px; font-size: 14px;">
                            <option value="0" <?php echo !$currentRestaurant['is_pure_veg'] ? 'selected' : ''; ?>>Non-Veg & Veg Mixed</option>
                            <option value="1" <?php echo $currentRestaurant['is_pure_veg'] ? 'selected' : ''; ?>>🥬 100% Pure Vegetarian</option>
                        </select>
                    </div>
                </div>

                <div style="margin-bottom: 16px;">
                    <label style="font-size: 13px; font-weight: 600; color: #444; display: block; margin-bottom: 4px;">Tagline</label>
                    <input type="text" name="tagline" value="<?php echo htmlspecialchars($currentRestaurant['tagline']); ?>" 
                           style="width: 100%; padding: 10px; border: 1px solid var(--light-border); border-radius: 8px; font-size: 14px;">
                </div>

                <div style="margin-bottom: 16px;">
                    <label style="font-size: 13px; font-weight: 600; color: #444; display: block; margin-bottom: 4px;">Cuisines (Comma separated)</label>
                    <input type="text" name="cuisines" value="<?php echo htmlspecialchars($currentRestaurant['cuisines']); ?>" placeholder="Biryani, North Indian, Tandoori" 
                           style="width: 100%; padding: 10px; border: 1px solid var(--light-border); border-radius: 8px; font-size: 14px;">
                </div>

                <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 16px; margin-bottom: 16px;">
                    <div>
                        <label style="font-size: 13px; font-weight: 600; color: #444; display: block; margin-bottom: 4px;">Physical Street Address *</label>
                        <input type="text" name="address" value="<?php echo htmlspecialchars($currentRestaurant['address']); ?>" required 
                               style="width: 100%; padding: 10px; border: 1px solid var(--light-border); border-radius: 8px; font-size: 14px;">
                    </div>
                    <div>
                        <label style="font-size: 13px; font-weight: 600; color: #444; display: block; margin-bottom: 4px;">City</label>
                        <input type="text" name="city" value="<?php echo htmlspecialchars($currentRestaurant['city']); ?>" required 
                               style="width: 100%; padding: 10px; border: 1px solid var(--light-border); border-radius: 8px; font-size: 14px;">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-bottom: 16px;">
                    <div>
                        <label style="font-size: 13px; font-weight: 600; color: #444; display: block; margin-bottom: 4px;">Opening Time</label>
                        <input type="time" name="opening_time" value="<?php echo htmlspecialchars($currentRestaurant['opening_time']); ?>" 
                               style="width: 100%; padding: 10px; border: 1px solid var(--light-border); border-radius: 8px; font-size: 14px;">
                    </div>
                    <div>
                        <label style="font-size: 13px; font-weight: 600; color: #444; display: block; margin-bottom: 4px;">Closing Time</label>
                        <input type="time" name="closing_time" value="<?php echo htmlspecialchars($currentRestaurant['closing_time']); ?>" 
                               style="width: 100%; padding: 10px; border: 1px solid var(--light-border); border-radius: 8px; font-size: 14px;">
                    </div>
                    <div>
                        <label style="font-size: 13px; font-weight: 600; color: #444; display: block; margin-bottom: 4px;">Delivery Radius (Km)</label>
                        <input type="number" step="0.5" name="delivery_radius_km" value="<?php echo htmlspecialchars($currentRestaurant['delivery_radius_km']); ?>" 
                               style="width: 100%; padding: 10px; border: 1px solid var(--light-border); border-radius: 8px; font-size: 14px;">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 24px;">
                    <div>
                        <label style="font-size: 13px; font-weight: 600; color: #444; display: block; margin-bottom: 4px;">Minimum Order Amount (₹)</label>
                        <input type="number" step="1" name="min_order_amount" value="<?php echo htmlspecialchars($currentRestaurant['min_order_amount']); ?>" 
                               style="width: 100%; padding: 10px; border: 1px solid var(--light-border); border-radius: 8px; font-size: 14px;">
                    </div>
                    <div>
                        <label style="font-size: 13px; font-weight: 600; color: #444; display: block; margin-bottom: 4px;">Approx Cost for Two (₹)</label>
                        <input type="number" step="10" name="cost_for_two" value="<?php echo htmlspecialchars($currentRestaurant['cost_for_two']); ?>" 
                               style="width: 100%; padding: 10px; border: 1px solid var(--light-border); border-radius: 8px; font-size: 14px;">
                    </div>
                </div>

                <button type="submit" style="width: 100%; padding: 14px; background: var(--primary-green); color: #fff; border-radius: 25px; font-weight: 700; font-size: 16px; border: none; cursor: pointer; box-shadow: var(--shadow-sm);">
                    Save Restaurant Settings ✓
                </button>
            </form>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
