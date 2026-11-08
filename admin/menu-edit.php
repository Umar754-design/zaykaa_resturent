<?php
// ===================================================
// Add / Edit Menu Item - Admin Control Center
// ===================================================
include 'includes/admin-header.php';

$itemId = (int)($_GET['id'] ?? 0);
$item = null;
$addons = [];

if ($itemId) {
    $stmt = $pdo->prepare("SELECT * FROM menu_items WHERE id = ?");
    $stmt->execute([$itemId]);
    $item = $stmt->fetch();
    if ($item) {
        $adStmt = $pdo->prepare("SELECT * FROM item_addons WHERE item_id = ?");
        $adStmt->execute([$itemId]);
        $addons = $adStmt->fetchAll();
    }
}

// Fetch categories & restaurants
$catList = $pdo->query("SELECT * FROM categories WHERE is_active = 1 ORDER BY sort_order ASC, name ASC")->fetchAll();
$restList = $pdo->query("SELECT id, name FROM restaurants ORDER BY name ASC")->fetchAll();

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price = (float)($_POST['price'] ?? 0);
    $discountPrice = !empty($_POST['discount_price']) ? (float)$_POST['discount_price'] : null;
    $servingSize = trim($_POST['serving_size'] ?? '');
    $categoryId = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
    $restaurantId = !empty($_POST['restaurant_id']) ? (int)$_POST['restaurant_id'] : 1;
    $isVeg = !empty($_POST['is_veg']) ? 1 : 0;
    $isBestseller = !empty($_POST['is_bestseller']) ? 1 : 0;
    $isAvailable = !empty($_POST['is_available']) ? 1 : 0;
    $prepTime = max(5, (int)($_POST['prep_time_minutes'] ?? 20));
    $imagePath = $item['image'] ?? 'assets/images/categories/biryani.png';

    // Image upload handling
    if (!empty($_FILES['image']['name'])) {
        $uploadDir = __DIR__ . '/../uploads/menu/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
            $newFileName = 'dish_' . time() . '_' . rand(100, 999) . '.' . $ext;
            if (move_uploaded_file($_FILES['image']['tmp_name'], $uploadDir . $newFileName)) {
                $imagePath = 'uploads/menu/' . $newFileName;
            }
        }
    }

    if ($name && $price > 0) {
        $pdo->beginTransaction();
        try {
            if ($item) {
                // Update
                $upd = $pdo->prepare("UPDATE menu_items SET 
                                        name = ?, description = ?, price = ?, discount_price = ?, serving_size = ?,
                                        category_id = ?, restaurant_id = ?, is_veg = ?, is_bestseller = ?, 
                                        is_available = ?, prep_time_minutes = ?, image = ?, updated_at = NOW() 
                                      WHERE id = ?");
                $upd->execute([
                    $name, $description, $price, $discountPrice, ($servingSize !== '' ? $servingSize : null),
                    $categoryId, $restaurantId, $isVeg, $isBestseller, $isAvailable, $prepTime, $imagePath, $itemId
                ]);
                $targetItemId = $itemId;
            } else {
                // Insert
                $ins = $pdo->prepare("INSERT INTO menu_items (
                                        restaurant_id, category_id, name, description, price, 
                                        discount_price, serving_size, is_veg, is_bestseller, 
                                        is_available, prep_time_minutes, image
                                      ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $ins->execute([
                    $restaurantId, $categoryId, $name, $description, $price,
                    $discountPrice, ($servingSize !== '' ? $servingSize : null), $isVeg, $isBestseller,
                    $isAvailable, $prepTime, $imagePath
                ]);
                $targetItemId = $pdo->lastInsertId();
            }

            // Save Addons
            $addonNames = $_POST['addon_name'] ?? [];
            $addonPrices = $_POST['addon_price'] ?? [];
            $pdo->prepare("DELETE FROM item_addons WHERE item_id = ?")->execute([$targetItemId]);
            $insAddon = $pdo->prepare("INSERT INTO item_addons (item_id, name, price, is_available) VALUES (?, ?, ?, 1)");
            for ($i = 0; $i < count($addonNames); $i++) {
                $an = trim($addonNames[$i]);
                $ap = (float)($addonPrices[$i] ?? 0);
                if ($an) {
                    $insAddon->execute([$targetItemId, $an, $ap]);
                }
            }

            $pdo->commit();
            set_flash('success', 'Dish successfully saved with serving quantity!');
            header("Location: menu.php");
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Error saving dish: ' . $e->getMessage();
        }
    } else {
        $error = 'Valid dish name and price are required.';
    }
}
?>

<div style="padding: 30px 0 60px;">
    <div class="container" style="max-width: 820px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
            <div>
                <h1 style="font-family: 'Playfair Display', serif; font-size: 28px; color: #0B192C; margin: 0 0 4px;">
                    <?php echo $item ? 'Edit Dish: ' . htmlspecialchars($item['name']) : 'Add New Dish to Platform Menu'; ?>
                </h1>
                <p style="color: #64748B; font-size: 13px; margin: 0;">Configure dish details, category-based serving size, and pricing</p>
            </div>
            <a href="menu.php" style="color: #64748B; font-size: 13px; font-weight: 600; text-decoration: none;">← Back to Menu Catalog</a>
        </div>

        <?php if ($error): ?>
            <div style="background: #FDE8E8; color: #9B1C1C; padding: 12px 16px; border-radius: 8px; font-weight: 600; margin-bottom: 20px;">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <div style="background: #FFFFFF; border-radius: 16px; border: 1px solid var(--light-border); padding: 30px; box-shadow: var(--shadow-sm);">
            <form method="POST" enctype="multipart/form-data">
                <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 16px; margin-bottom: 16px;">
                    <div>
                        <label style="font-size: 13px; font-weight: 600; color: #334155; display: block; margin-bottom: 4px;">Dish Name *</label>
                        <input type="text" name="name" value="<?php echo htmlspecialchars($item['name'] ?? ''); ?>" required placeholder="e.g. Amritsari Kulcha with Chole" 
                               style="width: 100%; padding: 10px 12px; border: 1px solid var(--light-border); border-radius: 8px; font-size: 14px;">
                    </div>
                    <div>
                        <label style="font-size: 13px; font-weight: 600; color: #334155; display: block; margin-bottom: 4px;">Food Type *</label>
                        <select name="is_veg" style="width: 100%; padding: 10px 12px; border: 1px solid var(--light-border); border-radius: 8px; font-size: 14px;">
                            <option value="1" <?php echo (!empty($item['is_veg'])) ? 'selected' : ''; ?>>🥬 Pure Vegetarian</option>
                            <option value="0" <?php echo (isset($item['is_veg']) && !$item['is_veg']) ? 'selected' : ''; ?>>🍗 Non-Vegetarian</option>
                        </select>
                    </div>
                </div>

                <div style="margin-bottom: 16px;">
                    <label style="font-size: 13px; font-weight: 600; color: #334155; display: block; margin-bottom: 4px;">Description & Highlights</label>
                    <textarea name="description" placeholder="Describe the flavors, preparation, accompaniments..." 
                              style="width: 100%; height: 75px; padding: 10px 12px; border: 1px solid var(--light-border); border-radius: 8px; font-size: 13px;"><?php echo htmlspecialchars($item['description'] ?? ''); ?></textarea>
                </div>

                <!-- Platform Category & Serving Size Row -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 14px;">
                    <div>
                        <label style="font-size: 13px; font-weight: 600; color: #334155; display: block; margin-bottom: 4px;">Platform Food Category *</label>
                        <select name="category_id" id="adminCategorySelect" onchange="updateServingSuggestionsAdmin()" style="width: 100%; padding: 10px 12px; border: 1px solid var(--light-border); border-radius: 8px; font-size: 13px;">
                            <option value="">-- Choose Category --</option>
                            <?php foreach ($catList as $c): ?>
                                <option value="<?php echo $c['id']; ?>" data-slug="<?php echo htmlspecialchars($c['slug']); ?>" <?php echo (!empty($item['category_id']) && $item['category_id'] == $c['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($c['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label style="font-size: 13px; font-weight: 600; color: #334155; display: block; margin-bottom: 4px;">
                            Serving Quantity / Portion Size ⚖️
                        </label>
                        <input type="text" name="serving_size" id="adminServingSizeInput" value="<?php echo htmlspecialchars($item['serving_size'] ?? ''); ?>" placeholder="e.g. 2 Pieces, 500 g, 250 ml" 
                               style="width: 100%; padding: 10px 12px; border: 1px solid var(--light-border); border-radius: 8px; font-size: 14px;">
                    </div>
                </div>

                <!-- Category Smart Unit Suggestions Box -->
                <div id="unitSuggestionBox" style="background: #F8FAFC; border-radius: 10px; border: 1px solid #E2E8F0; padding: 12px 16px; margin-bottom: 18px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px;">
                        <div style="font-size: 12px; color: #475569;">
                            <strong style="color: #059669; font-size: 12px; text-transform: uppercase; letter-spacing: 0.3px;">Category Recommended Units:</strong>
                            <span id="adminUnitHelpText" style="margin-left: 4px; color: #334155;">Select a category to view standard units</span>
                        </div>
                        <div id="adminUnitChipsContainer" style="display: flex; gap: 6px; flex-wrap: wrap;"></div>
                    </div>
                </div>

                <!-- Pricing & Prep Time -->
                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-bottom: 16px;">
                    <div>
                        <label style="font-size: 13px; font-weight: 600; color: #334155; display: block; margin-bottom: 4px;">Base Price (₹) *</label>
                        <input type="number" step="0.01" name="price" value="<?php echo htmlspecialchars($item['price'] ?? ''); ?>" required placeholder="299.00" 
                               style="width: 100%; padding: 10px 12px; border: 1px solid var(--light-border); border-radius: 8px; font-size: 14px;">
                    </div>
                    <div>
                        <label style="font-size: 13px; font-weight: 600; color: #334155; display: block; margin-bottom: 4px;">Offer Price (₹, Optional)</label>
                        <input type="number" step="0.01" name="discount_price" value="<?php echo htmlspecialchars($item['discount_price'] ?? ''); ?>" placeholder="249.00" 
                               style="width: 100%; padding: 10px 12px; border: 1px solid var(--light-border); border-radius: 8px; font-size: 14px;">
                    </div>
                    <div>
                        <label style="font-size: 13px; font-weight: 600; color: #334155; display: block; margin-bottom: 4px;">Prep Time (Mins)</label>
                        <input type="number" name="prep_time_minutes" value="<?php echo htmlspecialchars($item['prep_time_minutes'] ?? '20'); ?>" 
                               style="width: 100%; padding: 10px 12px; border: 1px solid var(--light-border); border-radius: 8px; font-size: 14px;">
                    </div>
                </div>

                <!-- Restaurant & Image -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 20px;">
                    <div>
                        <label style="font-size: 13px; font-weight: 600; color: #334155; display: block; margin-bottom: 4px;">Restaurant Kitchen</label>
                        <select name="restaurant_id" style="width: 100%; padding: 10px 12px; border: 1px solid var(--light-border); border-radius: 8px; font-size: 13px;">
                            <?php foreach ($restList as $r): ?>
                                <option value="<?php echo $r['id']; ?>" <?php echo (!empty($item['restaurant_id']) && $item['restaurant_id'] == $r['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($r['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label style="font-size: 13px; font-weight: 600; color: #334155; display: block; margin-bottom: 4px;">Dish Image</label>
                        <input type="file" name="image" accept="image/*" style="font-size: 12px;">
                        <?php if (!empty($item['image'])): ?>
                            <div style="font-size: 11px; color: #64748B; margin-top: 4px;">Current: <?php echo htmlspecialchars($item['image']); ?></div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Flags: Bestseller and Availability -->
                <div style="display: flex; gap: 24px; margin-bottom: 24px; flex-wrap: wrap;">
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 14px; font-weight: 600; color: #D97706;">
                        <input type="checkbox" name="is_bestseller" value="1" <?php echo (!empty($item['is_bestseller'])) ? 'checked' : ''; ?> style="width: 18px; height: 18px; accent-color: #D97706;">
                        <span>Mark as Bestseller / Signature Special ⭐</span>
                    </label>

                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 14px; font-weight: 600; color: #059669;">
                        <input type="checkbox" name="is_available" value="1" <?php echo (!isset($item['is_available']) || $item['is_available']) ? 'checked' : ''; ?> style="width: 18px; height: 18px; accent-color: #059669;">
                        <span>Available in Kitchen (In Stock) ✓</span>
                    </label>
                </div>

                <!-- Add-ons Section -->
                <div style="background: #F8FAFC; border-radius: 12px; padding: 20px; border: 1px solid var(--light-border); margin-bottom: 24px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                        <div>
                            <strong style="color: #0B192C; font-size: 14px;">Customization Add-ons & Extras</strong>
                            <div style="font-size: 12px; color: #64748B;">Allow customers to choose add-on portions, extra toppings, or dips</div>
                        </div>
                        <button type="button" onclick="addAdminAddonRow()" style="padding: 6px 14px; background: #fff; border: 1px solid #CBD5E1; border-radius: 16px; font-size: 12px; font-weight: 600; color: #059669; cursor: pointer;">
                            + Add Option
                        </button>
                    </div>

                    <div id="adminAddonsContainer" style="display: flex; flex-direction: column; gap: 10px;">
                        <?php if (!empty($addons)): ?>
                            <?php foreach ($addons as $ad): ?>
                            <div style="display: flex; gap: 10px; align-items: center;" class="addon-row">
                                <input type="text" name="addon_name[]" value="<?php echo htmlspecialchars($ad['name']); ?>" placeholder="Add-on Name (e.g. Extra Raita)" style="flex: 2; padding: 8px 12px; border: 1px solid var(--light-border); border-radius: 6px; font-size: 13px;">
                                <input type="number" step="0.01" name="addon_price[]" value="<?php echo htmlspecialchars($ad['price']); ?>" placeholder="Price (₹)" style="flex: 1; padding: 8px 12px; border: 1px solid var(--light-border); border-radius: 6px; font-size: 13px;">
                                <button type="button" onclick="this.closest('.addon-row').remove()" style="color: #DC2626; background: none; border: none; font-size: 18px; cursor: pointer;">✕</button>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <button type="submit" style="width: 100%; padding: 14px; background: #059669; color: #fff; border-radius: 25px; font-weight: 700; font-size: 15px; border: none; cursor: pointer; box-shadow: var(--shadow-sm);">
                    Save Dish to Menu Catalog ✓
                </button>
            </form>
        </div>
    </div>
</div>

<script>
function addAdminAddonRow() {
    const container = document.getElementById('adminAddonsContainer');
    const div = document.createElement('div');
    div.className = 'addon-row';
    div.style.display = 'flex';
    div.style.gap = '10px';
    div.style.alignItems = 'center';
    div.innerHTML = `
        <input type="text" name="addon_name[]" placeholder="Add-on Name (e.g. Extra Chole)" style="flex: 2; padding: 8px 12px; border: 1px solid var(--light-border); border-radius: 6px; font-size: 13px;">
        <input type="number" step="0.01" name="addon_price[]" placeholder="Price (₹)" value="30.00" style="flex: 1; padding: 8px 12px; border: 1px solid var(--light-border); border-radius: 6px; font-size: 13px;">
        <button type="button" onclick="this.closest('.addon-row').remove()" style="color: #DC2626; background: none; border: none; font-size: 18px; cursor: pointer;">✕</button>
    `;
    container.appendChild(div);
}

// Category-specific serving units guide & dynamic suggestions
const categoryUnitsGuideAdmin = {
    'rotis': {
        name: 'Rotis & Breads',
        guide: 'e.g. 2 Pieces, 4 Pieces, Serves 2',
        chips: ['2 Pieces', '4 Pieces', '1 Piece', 'Serves 2']
    },
    'kebabs': {
        name: 'Kebabs & Starters',
        guide: 'e.g. 4 Pieces, 6 Pieces, 8 Pieces',
        chips: ['4 Pieces', '6 Pieces', '8 Pieces', 'Half (4 Pcs)', 'Full Platter (8 Pcs)']
    },
    'biryani': {
        name: 'Biryani',
        guide: 'e.g. 500 g, Serves 1 Person, 1 kg',
        chips: ['500 g, Serves 1 Person', '500 g', 'Serves 1 Person', '1 kg, Serves 2-3']
    },
    'mojitos': {
        name: 'Mojitos & Coolers',
        guide: 'e.g. 250 ml, 300 ml, 350 ml',
        chips: ['250 ml', '300 ml', '350 ml']
    },
    'milkshakes': {
        name: 'Milkshakes & Sweets',
        guide: 'e.g. 250 ml, 300 ml, 2 Pieces',
        chips: ['250 ml', '300 ml', '2 Pieces', '4 Pieces', '1 Piece with Ice Cream']
    },
    'juices': {
        name: 'Fresh Juices',
        guide: 'e.g. 250 ml, 300 ml, 400 ml',
        chips: ['250 ml', '300 ml', '400 ml']
    }
};

function setAdminServingValue(val) {
    const input = document.getElementById('adminServingSizeInput');
    if (input) {
        input.value = val;
        input.focus();
    }
}

function updateServingSuggestionsAdmin() {
    const sel = document.getElementById('adminCategorySelect');
    if (!sel) return;
    const selectedOpt = sel.options[sel.selectedIndex];
    const slug = selectedOpt ? (selectedOpt.getAttribute('data-slug') || '').toLowerCase() : '';
    const text = selectedOpt ? selectedOpt.textContent.trim().toLowerCase() : '';
    
    let info = null;
    if (slug && categoryUnitsGuideAdmin[slug]) {
        info = categoryUnitsGuideAdmin[slug];
    } else {
        for (const k in categoryUnitsGuideAdmin) {
            if (slug.includes(k) || text.includes(k) || text.includes(categoryUnitsGuideAdmin[k].name.toLowerCase())) {
                info = categoryUnitsGuideAdmin[k];
                break;
            }
        }
    }

    const helpEl = document.getElementById('adminUnitHelpText');
    const chipsEl = document.getElementById('adminUnitChipsContainer');

    if (info) {
        helpEl.innerHTML = `<strong>${info.name}:</strong> ${info.guide}`;
        chipsEl.innerHTML = info.chips.map(c => `
            <button type="button" onclick="setAdminServingValue('${c}')" style="background: #FFFFFF; border: 1.5px solid #059669; color: #059669; padding: 4px 12px; border-radius: 14px; font-size: 11px; font-weight: 700; cursor: pointer; transition: 0.15s; box-shadow: 0 1px 2px rgba(0,0,0,0.05);">
                + ${c}
            </button>
        `).join('');
    } else {
        helpEl.textContent = 'Units: Rotis (Pieces), Kebabs (Pieces), Biryani (g / Serves), Coolers/Juices (ml)';
        chipsEl.innerHTML = `
            <button type="button" onclick="setAdminServingValue('2 Pieces')" style="background: #FFFFFF; border: 1px solid #CBD5E1; color: #334155; padding: 4px 10px; border-radius: 14px; font-size: 11px; cursor: pointer;">2 Pieces</button>
            <button type="button" onclick="setAdminServingValue('500 g')" style="background: #FFFFFF; border: 1px solid #CBD5E1; color: #334155; padding: 4px 10px; border-radius: 14px; font-size: 11px; cursor: pointer;">500 g</button>
            <button type="button" onclick="setAdminServingValue('250 ml')" style="background: #FFFFFF; border: 1px solid #CBD5E1; color: #334155; padding: 4px 10px; border-radius: 14px; font-size: 11px; cursor: pointer;">250 ml</button>
        `;
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', updateServingSuggestionsAdmin);
} else {
    updateServingSuggestionsAdmin();
}
</script>

<?php include 'includes/admin-footer.php'; ?>
