<?php
// ===================================================
// Complete Menu Page - Zayka Platform
// ===================================================
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
include 'includes/header.php';

$selectedCategory = trim($_GET['category'] ?? '');

// Fetch all available menu items for Zayka
$sql = "SELECT m.*, c.name as cat_name, c.slug as cat_slug 
        FROM menu_items m 
        LEFT JOIN categories c ON m.category_id = c.id 
        WHERE m.is_available = 1";
$params = [];

if ($selectedCategory) {
    $sql .= " AND c.slug = ?";
    $params[] = $selectedCategory;
}

$sql .= " ORDER BY m.is_bestseller DESC, m.name ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$menuItems = $stmt->fetchAll();

// Fetch Categories for Filter
$cats = $pdo->query("SELECT * FROM categories WHERE is_active = 1 ORDER BY sort_order ASC")->fetchAll();
?>

<div style="background: #FAF8F2; min-height: 85vh; padding: 40px 0 80px;">
    <div class="container">
        <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 24px; flex-wrap: wrap; gap: 15px;">
            <div>
                <h1 style="font-family: 'Playfair Display', serif; font-size: 32px; color: var(--primary-green); margin: 0 0 6px;">
                    Our Master Menu 🍽️
                </h1>
                <p style="color: #666; margin: 0; font-size: 14px;">
                    Authentic dum biryanis, chargrilled tandoori kebabs, rich gravies, and artisan beverages
                </p>
            </div>

            <!-- Veg Only Toggle -->
            <label style="display: flex; align-items: center; gap: 8px; font-size: 13px; font-weight: 600; cursor: pointer; user-select: none; background: #fff; padding: 8px 16px; border-radius: 20px; border: 1px solid var(--light-border);">
                <input type="checkbox" id="vegToggle" onchange="toggleVeg(this.checked)" style="width: 18px; height: 18px; accent-color: #16A34A;">
                <span>Pure Veg Only 🥬</span>
            </label>
        </div>

        <!-- Category Pills Filter Bar -->
        <div style="display: flex; gap: 10px; overflow-x: auto; padding-bottom: 12px; margin-bottom: 24px; scrollbar-width: none;">
            <a href="menu.php" style="padding: 8px 18px; border-radius: 20px; font-size: 13px; font-weight: 600; text-decoration: none; white-space: nowrap; background: <?php echo empty($selectedCategory) ? 'var(--primary-green)' : '#FFFFFF'; ?>; color: <?php echo empty($selectedCategory) ? '#FFFFFF' : '#333'; ?>; border: 1px solid var(--light-border);">
                All Dishes
            </a>
            <?php foreach ($cats as $ct): ?>
                <a href="menu.php?category=<?php echo urlencode($ct['slug']); ?>" 
                   style="padding: 8px 18px; border-radius: 20px; font-size: 13px; font-weight: 600; text-decoration: none; white-space: nowrap; background: <?php echo ($selectedCategory === $ct['slug']) ? 'var(--primary-green)' : '#FFFFFF'; ?>; color: <?php echo ($selectedCategory === $ct['slug']) ? '#FFFFFF' : '#333'; ?>; border: 1px solid var(--light-border);">
                    <?php echo htmlspecialchars($ct['name']); ?>
                </a>
            <?php endforeach; ?>
        </div>

        <!-- Menu Grid -->
        <div class="master-menu-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(min(100%, 280px), 1fr)); gap: 20px;">
            <?php foreach ($menuItems as $item): ?>
            <div class="master-menu-card" data-is-veg="<?php echo $item['is_veg']; ?>" 
                 onclick="window.location.href='dish.php?id=<?php echo $item['id']; ?>'"
                 style="background: #FFFFFF; border-radius: 16px; border: 1px solid var(--light-border); padding: 18px; display: flex; flex-direction: column; justify-content: space-between; box-shadow: var(--shadow-sm); transition: transform 0.2s, box-shadow 0.2s; cursor: pointer;">
                <div>
                    <a href="dish.php?id=<?php echo $item['id']; ?>" style="text-decoration: none; color: inherit; display: block;">
                        <div style="position: relative; height: 170px; border-radius: 12px; overflow: hidden; background: #FAF8F2; margin-bottom: 12px;">
                            <img src="<?php echo htmlspecialchars($item['image']); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>" style="width: 100%; height: 100%; object-fit: cover; transition: transform 0.3s ease;">
                            <div style="position: absolute; top: 10px; left: 10px;">
                                <span class="<?php echo $item['is_veg'] ? 'veg-icon' : 'non-veg-icon'; ?>"></span>
                            </div>
                            <?php if ($item['is_bestseller']): ?>
                                <div style="position: absolute; bottom: 10px; left: 10px; background: rgba(217, 119, 6, 0.95); color: #fff; font-size: 10px; font-weight: 700; padding: 3px 8px; border-radius: 4px;">
                                    ★ BESTSELLER
                                </div>
                            <?php endif; ?>
                        </div>

                        <div style="font-size: 11px; color: var(--gold); font-weight: 700; text-transform: uppercase;">
                            <?php echo htmlspecialchars($item['cat_name'] ?? 'Zaykaa Special'); ?>
                        </div>
                        
                        <h3 style="font-family: 'Playfair Display', serif; font-size: 18px; margin: 4px 0 6px; color: var(--dark-text);">
                            <?php echo htmlspecialchars($item['name']); ?>
                        </h3>
                    </a>

                    <p style="font-size: 12px; color: #666; margin: 0; line-height: 1.4; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                        <?php echo htmlspecialchars($item['description']); ?>
                    </p>
                </div>

                <div style="margin-top: 14px; padding-top: 12px; border-top: 1px dashed var(--light-border);">
                    <!-- Serving Quantity / Serving Size (just above price) -->
                    <div class="menu-item-serving">
                        <span class="serving-label">Serving:</span>
                        <?php if (!empty($item['serving_size'])): ?>
                            <span class="serving-val"><?php echo htmlspecialchars($item['serving_size']); ?></span>
                        <?php else: ?>
                            <span class="serving-empty">Serving size not specified</span>
                        <?php endif; ?>
                    </div>

                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <span style="font-size: 18px; font-weight: 700; color: var(--primary-green);">
                                <?php echo format_price($item['discount_price'] ?: $item['price']); ?>
                            </span>
                            <?php if ($item['discount_price']): ?>
                                <span style="font-size: 12px; text-decoration: line-through; color: #999; margin-left: 4px;">
                                    <?php echo format_price($item['price']); ?>
                                </span>
                            <?php endif; ?>
                        </div>

                        <button onclick="event.stopPropagation(); addToCart(<?php echo $item['id']; ?>)" 
                                style="padding: 8px 18px; background: var(--primary-green); color: #fff; border-radius: 20px; font-weight: 700; font-size: 13px; border: none; cursor: pointer; box-shadow: var(--shadow-sm); transition: 0.2s;">
                            + ADD
                        </button>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<script>
function toggleVeg(isVeg) {
    document.querySelectorAll('.master-menu-card').forEach(card => {
        if (isVeg && card.getAttribute('data-is-veg') === '0') {
            card.style.display = 'none';
        } else {
            card.style.display = 'flex';
        }
    });
}
</script>

<?php include 'includes/footer.php'; ?>
