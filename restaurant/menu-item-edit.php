<?php
// ===================================================
// Add / Edit Menu Item & Add-ons - Restaurant Partner
// ===================================================
include 'includes/header.php';

$restId = (int)$currentRestaurant['id'];
$itemId = (int)($_GET['id'] ?? 0);
$item = null;
$addons = [];

if ($itemId) {
    $stmt = $pdo->prepare("SELECT * FROM menu_items WHERE id = ? AND restaurant_id = ?");
    $stmt->execute([$itemId, $restId]);
    $item = $stmt->fetch();
    if ($item) {
        $adStmt = $pdo->prepare("SELECT * FROM item_addons WHERE item_id = ?");
        $adStmt->execute([$itemId]);
        $addons = $adStmt->fetchAll();
    }
}

// Fetch categories & menu categories
$catList = $pdo->query("SELECT * FROM categories WHERE is_active = 1 ORDER BY name ASC")->fetchAll();
$menuCatList = $pdo->prepare("SELECT * FROM menu_categories WHERE restaurant_id = ? ORDER BY sort_order ASC");
$menuCatList->execute([$restId]);
$menuCats = $menuCatList->fetchAll();

// Handle form submission
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price = (float)($_POST['price'] ?? 0);
    $discountPrice = !empty($_POST['discount_price']) ? (float)$_POST['discount_price'] : null;
    $servingSize = trim($_POST['serving_size'] ?? '');
    $categoryId = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
    $menuCategoryId = !empty($_POST['menu_category_id']) ? (int)$_POST['menu_category_id'] : null;
    $isVeg = !empty($_POST['is_veg']) ? 1 : 0;
    $isBestseller = !empty($_POST['is_bestseller']) ? 1 : 0;
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
                $upd = $pdo->prepare("UPDATE menu_items SET name = ?, description = ?, price = ?, discount_price = ?, serving_size = ?,
                                      category_id = ?, menu_category_id = ?, is_veg = ?, is_bestseller = ?, 
                                      prep_time_minutes = ?, image = ?, updated_at = NOW() WHERE id = ? AND restaurant_id = ?");
                $upd->execute([$name, $description, $price, $discountPrice, ($servingSize !== '' ? $servingSize : null), $categoryId, $menuCategoryId, $isVeg, $isBestseller, $prepTime, $imagePath, $itemId, $restId]);
                $targetItemId = $itemId;
            } else {
                // Insert
                $ins = $pdo->prepare("INSERT INTO menu_items (restaurant_id, category_id, menu_category_id, name, description, price, discount_price, serving_size, is_veg, is_bestseller, prep_time_minutes, image) 
                                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $ins->execute([$restId, $categoryId, $menuCategoryId, $name, $description, $price, $discountPrice, ($servingSize !== '' ? $servingSize : null), $isVeg, $isBestseller, $prepTime, $imagePath]);
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
            set_flash('success', 'Dish successfully saved!');
            header("Location: menu.php");
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Error saving dish: ' . $e->getMessage();
        }
    } else {
        $error = 'Dish name and valid price are required.';
    }
}
?>

<div style="padding: 30px 0 60px;">
    <div class="container" style="max-width: 800px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
            <h1 style="font-family: 'Playfair Display', serif; font-size: 28px; color: var(--primary-green); margin: 0;">
                <?php echo $item ? 'Edit Dish: ' . htmlspecialchars($item['name']) : 'Add New Dish to Menu'; ?>
            </h1>
            <a href="menu.php" style="color: #666; font-size: 13px; font-weight: 600; text-decoration: none;">← Back to Menu</a>
        </div>

        <?php if ($error): ?>
            <div style="background: #FDE8E8; color: #9B1C1C; padding: 12px; border-radius: 8px; font-weight: 600; margin-bottom: 20px;">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <div style="background: #FFFFFF; border-radius: 16px; border: 1px solid var(--light-border); padding: 30px; box-shadow: var(--shadow-sm);">
            <form method="POST" enctype="multipart/form-data">
                <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px; margin-bottom: 16px;">
                    <div>
                        <label style="font-size: 13px; font-weight: 600; color: #444; display: block; margin-bottom: 4px;">Dish Name *</label>
                        <input type="text" name="name" value="<?php echo htmlspecialchars($item['name'] ?? ''); ?>" required placeholder="e.g. Shahi Chicken Biryani" 
                               style="width: 100%; padding: 10px; border: 1px solid var(--light-border); border-radius: 8px; font-size: 14px;">
                    </div>
                    <div>
                        <label style="font-size: 13px; font-weight: 600; color: #444; display: block; margin-bottom: 4px;">Food Type *</label>
                        <select name="is_veg" style="width: 100%; padding: 10px; border: 1px solid var(--light-border); border-radius: 8px; font-size: 14px;">
                            <option value="1" <?php echo (!empty($item['is_veg'])) ? 'selected' : ''; ?>>🥬 Vegetarian</option>
                            <option value="0" <?php echo (isset($item['is_veg']) && !$item['is_veg']) ? 'selected' : ''; ?>>🍗 Non-Vegetarian</option>
                        </select>
                    </div>
                </div>

                <div style="margin-bottom: 16px;">
                    <label style="font-size: 13px; font-weight: 600; color: #444; display: block; margin-bottom: 4px;">Description & Ingredients</label>
                    <textarea name="description" placeholder="Describe the flavors, spices, and preparation..." 
                              style="width: 100%; height: 75px; padding: 10px; border: 1px solid var(--light-border); border-radius: 8px; font-size: 13px;"><?php echo htmlspecialchars($item['description'] ?? ''); ?></textarea>
                </div>

                <!-- Category & Serving Size Row -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 14px;">
                    <div>
                        <label style="font-size: 13px; font-weight: 600; color: #444; display: block; margin-bottom: 4px;">Platform Category *</label>
                        <select name="category_id" id="categorySelect" onchange="updateServingSuggestions()" style="width: 100%; padding: 10px; border: 1px solid var(--light-border); border-radius: 8px; font-size: 13px;">
                            <option value="">-- Choose Category --</option>
                            <?php foreach ($catList as $c): ?>
                                <option value="<?php echo $c['id']; ?>" data-slug="<?php echo htmlspecialchars($c['slug']); ?>" <?php echo (!empty($item['category_id']) && $item['category_id'] == $c['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($c['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label style="font-size: 13px; font-weight: 600; color: #444; display: block; margin-bottom: 4px;">
                            Serving Quantity / Portion Size ⚖️
                        </label>
                        <input type="text" name="serving_size" id="servingSizeInput" value="<?php echo htmlspecialchars($item['serving_size'] ?? ''); ?>" placeholder="e.g. 2 Pieces, 500 g, 250 ml" 
                               style="width: 100%; padding: 10px; border: 1px solid var(--light-border); border-radius: 8px; font-size: 14px;">
                    </div>
                </div>

                <!-- Category Smart Unit Suggestions Box -->
                <div id="unitSuggestionBox" style="background: #FAF8F2; border-radius: 10px; border: 1px solid #EFE9D9; padding: 12px 16px; margin-bottom: 18px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px;">
                        <div style="font-size: 12px; color: #555;">
                            <strong style="color: var(--gold); font-size: 12px; text-transform: uppercase; letter-spacing: 0.3px;">Category Recommended Units:</strong>
                            <span id="unitHelpText" style="margin-left: 4px; color: #444;">Select a category to view standard units</span>
                        </div>
                        <div id="unitChipsContainer" style="display: flex; gap: 6px; flex-wrap: wrap;"></div>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-bottom: 16px;">
                    <div>
                        <label style="font-size: 13px; font-weight: 600; color: #444; display: block; margin-bottom: 4px;">Base Price (₹) *</label>
                        <input type="number" step="0.01" name="price" value="<?php echo htmlspecialchars($item['price'] ?? ''); ?>" required placeholder="299.00" 
                               style="width: 100%; padding: 10px; border: 1px solid var(--light-border); border-radius: 8px; font-size: 14px;">
                    </div>
                    <div>
                        <label style="font-size: 13px; font-weight: 600; color: #444; display: block; margin-bottom: 4px;">Offer Price (₹, Optional)</label>
                        <input type="number" step="0.01" name="discount_price" value="<?php echo htmlspecialchars($item['discount_price'] ?? ''); ?>" placeholder="249.00" 
                               style="width: 100%; padding: 10px; border: 1px solid var(--light-border); border-radius: 8px; font-size: 14px;">
                    </div>
                    <div>
                        <label style="font-size: 13px; font-weight: 600; color: #444; display: block; margin-bottom: 4px;">Prep Time (Mins)</label>
                        <input type="number" name="prep_time_minutes" value="<?php echo htmlspecialchars($item['prep_time_minutes'] ?? '20'); ?>" 
                               style="width: 100%; padding: 10px; border: 1px solid var(--light-border); border-radius: 8px; font-size: 14px;">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 20px;">
                    <div>
                        <label style="font-size: 13px; font-weight: 600; color: #444; display: block; margin-bottom: 4px;">Dish Image</label>
                        <input type="file" name="image" accept="image/*" style="font-size: 12px;">
                        <?php if (!empty($item['image'])): ?>
                            <div style="font-size: 11px; color: #888; margin-top: 4px;">Current: <?php echo htmlspecialchars($item['image']); ?></div>
                        <?php endif; ?>
                    </div>
                    <div>
                        <label style="font-size: 13px; font-weight: 600; color: #444; display: block; margin-bottom: 4px;">Menu Sub-Section (Optional)</label>
                        <select name="menu_category_id" style="width: 100%; padding: 10px; border: 1px solid var(--light-border); border-radius: 8px; font-size: 13px;">
                            <option value="">-- Main Restaurant Menu --</option>
                            <?php foreach ($menuCats as $mc): ?>
                                <option value="<?php echo $mc['id']; ?>" <?php echo (!empty($item['menu_category_id']) && $item['menu_category_id'] == $mc['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($mc['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div style="margin-bottom: 24px;">
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 14px; font-weight: 600; color: #D97706;">
                        <input type="checkbox" name="is_bestseller" value="1" <?php echo (!empty($item['is_bestseller'])) ? 'checked' : ''; ?> style="width: 18px; height: 18px; accent-color: #D97706;">
                        <span>Mark as Bestseller / Chef's Special ⭐</span>
                    </label>
                </div>

                <!-- Customization Add-ons Section -->
                <div style="background: #FAF8F2; border-radius: 12px; padding: 20px; border: 1px solid var(--light-border); margin-bottom: 24px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                        <div>
                            <strong style="color: var(--primary-green); font-size: 14px;">Customization Add-ons & Extras</strong>
                            <div style="font-size: 12px; color: #666;">Allow customers to add extra toppings, raitas, dips, or sauces</div>
                        </div>
                        <button type="button" onclick="addAddonRow()" style="padding: 6px 14px; background: #fff; border: 1px solid var(--gold); border-radius: 16px; font-size: 12px; font-weight: 600; color: var(--primary-green); cursor: pointer;">
                            + Add Option
                        </button>
                    </div>

                    <div id="addonsContainer" style="display: flex; flex-direction: column; gap: 10px;">
                        <?php if (!empty($addons)): ?>
                            <?php foreach ($addons as $ad): ?>
                            <div style="display: flex; gap: 10px; align-items: center;" class="addon-row">
                                <input type="text" name="addon_name[]" value="<?php echo htmlspecialchars($ad['name']); ?>" placeholder="Add-on Name (e.g. Extra Raita)" style="flex: 2; padding: 8px; border: 1px solid var(--light-border); border-radius: 6px; font-size: 13px;">
                                <input type="number" step="0.01" name="addon_price[]" value="<?php echo htmlspecialchars($ad['price']); ?>" placeholder="Price (₹)" style="flex: 1; padding: 8px; border: 1px solid var(--light-border); border-radius: 6px; font-size: 13px;">
                                <button type="button" onclick="this.closest('.addon-row').remove()" style="color: #DC2626; background: none; border: none; font-size: 18px; cursor: pointer;">✕</button>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <button type="submit" style="width: 100%; padding: 14px; background: var(--primary-green); color: #fff; border-radius: 25px; font-weight: 700; font-size: 16px; border: none; cursor: pointer; box-shadow: var(--shadow-sm);">
                    Save Dish to Menu ✓
                </button>
            </form>
        </div>
    </div>
</div>

<script>
function addAddonRow() {
    const container = document.getElementById('addonsContainer');
    const div = document.createElement('div');
    div.className = 'addon-row';
    div.style.display = 'flex';
    div.style.gap = '10px';
    div.style.alignItems = 'center';
    div.innerHTML = `
        <input type="text" name="addon_name[]" placeholder="Add-on Name (e.g. Extra Cheese)" style="flex: 2; padding: 8px; border: 1px solid var(--light-border); border-radius: 6px; font-size: 13px;">
        <input type="number" step="0.01" name="addon_price[]" placeholder="Price (₹)" value="30.00" style="flex: 1; padding: 8px; border: 1px solid var(--light-border); border-radius: 6px; font-size: 13px;">
        <button type="button" onclick="this.closest('.addon-row').remove()" style="color: #DC2626; background: none; border: none; font-size: 18px; cursor: pointer;">✕</button>
    `;
    container.appendChild(div);
}

// Category-specific serving units guide & dynamic suggestions
const categoryUnitsGuide = {
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

function setServingValue(val) {
    const input = document.getElementById('servingSizeInput');
    if (input) {
        input.value = val;
        input.focus();
    }
}

function updateServingSuggestions() {
    const sel = document.getElementById('categorySelect');
    if (!sel) return;
    const selectedOpt = sel.options[sel.selectedIndex];
    const slug = selectedOpt ? (selectedOpt.getAttribute('data-slug') || '').toLowerCase() : '';
    const text = selectedOpt ? selectedOpt.textContent.trim().toLowerCase() : '';
    
    let info = null;
    if (slug && categoryUnitsGuide[slug]) {
        info = categoryUnitsGuide[slug];
    } else {
        for (const k in categoryUnitsGuide) {
            if (slug.includes(k) || text.includes(k) || text.includes(categoryUnitsGuide[k].name.toLowerCase())) {
                info = categoryUnitsGuide[k];
                break;
            }
        }
    }

    const helpEl = document.getElementById('unitHelpText');
    const chipsEl = document.getElementById('unitChipsContainer');

    if (info) {
        helpEl.innerHTML = `<strong>${info.name}:</strong> ${info.guide}`;
        chipsEl.innerHTML = info.chips.map(c => `
            <button type="button" onclick="setServingValue('${c}')" style="background: #FFFFFF; border: 1.5px solid var(--gold); color: var(--primary-green); padding: 4px 12px; border-radius: 14px; font-size: 11px; font-weight: 700; cursor: pointer; transition: 0.15s; box-shadow: 0 1px 3px rgba(0,0,0,0.06);">
                + ${c}
            </button>
        `).join('');
    } else {
        helpEl.textContent = 'Units: Rotis (Pieces), Kebabs (Pieces), Biryani (g / Serves), Coolers/Juices (ml)';
        chipsEl.innerHTML = `
            <button type="button" onclick="setServingValue('2 Pieces')" style="background: #FFFFFF; border: 1px solid #ccc; color: #444; padding: 4px 10px; border-radius: 14px; font-size: 11px; cursor: pointer;">2 Pieces</button>
            <button type="button" onclick="setServingValue('500 g')" style="background: #FFFFFF; border: 1px solid #ccc; color: #444; padding: 4px 10px; border-radius: 14px; font-size: 11px; cursor: pointer;">500 g</button>
            <button type="button" onclick="setServingValue('250 ml')" style="background: #FFFFFF; border: 1px solid #ccc; color: #444; padding: 4px 10px; border-radius: 14px; font-size: 11px; cursor: pointer;">250 ml</button>
        `;
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', updateServingSuggestions);
} else {
    updateServingSuggestions();
}
</script>

<?php include 'includes/footer.php'; ?>
