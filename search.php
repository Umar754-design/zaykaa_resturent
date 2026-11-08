<?php
// ===================================================
// Search Menu Items - Zayka Platform
// Single-Restaurant Search & Filter
// ===================================================
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
include 'includes/header.php';

// Filter inputs
$query = trim($_GET['query'] ?? '');
$categorySlug = trim($_GET['category'] ?? '');
$pureVeg = isset($_GET['pure_veg']) && $_GET['pure_veg'] === '1';
$sortBy = $_GET['sort_by'] ?? 'bestseller';

// Build SQL query on menu items
$sql = "SELECT m.*, c.name as cat_name, c.slug as cat_slug 
        FROM menu_items m 
        LEFT JOIN categories c ON m.category_id = c.id 
        WHERE m.is_available = 1";
$params = [];

if ($query) {
    $sql .= " AND (m.name LIKE ? OR m.description LIKE ? OR c.name LIKE ?)";
    $like = "%$query%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

if ($categorySlug) {
    $sql .= " AND c.slug = ?";
    $params[] = $categorySlug;
}

if ($pureVeg) {
    $sql .= " AND m.is_veg = 1";
}

// Sorting
switch ($sortBy) {
    case 'cost_low':
        $sql .= " ORDER BY COALESCE(m.discount_price, m.price) ASC";
        break;
    case 'cost_high':
        $sql .= " ORDER BY COALESCE(m.discount_price, m.price) DESC";
        break;
    case 'name':
        $sql .= " ORDER BY m.name ASC";
        break;
    case 'bestseller':
    default:
        $sql .= " ORDER BY m.is_bestseller DESC, m.name ASC";
        break;
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$searchResults = $stmt->fetchAll();

// Fetch Categories for Filter
$cats = $pdo->query("SELECT * FROM categories WHERE is_active = 1 ORDER BY sort_order ASC")->fetchAll();
?>

<div style="background: #FAF8F2; min-height: 85vh; padding: 40px 0 80px;">
    <div class="container">
        <!-- Search Header -->
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 20px; margin-bottom: 24px;">
            <div>
                <h1 style="font-family: 'Playfair Display', serif; font-size: 32px; color: var(--primary-green); margin: 0 0 6px;">
                    <?php if ($query): ?>
                        Search results for "<?php echo htmlspecialchars($query); ?>" 🔍
                    <?php elseif ($categorySlug): ?>
                        <?php echo ucfirst(htmlspecialchars($categorySlug)); ?> Dishes 🍽️
                    <?php else: ?>
                        Search Zaykaa's Menu 🍽️
                    <?php endif; ?>
                </h1>
                <p style="color: #666; margin: 0; font-size: 14px;">
                    Found <strong><?php echo count($searchResults); ?></strong> delicious dish<?php echo count($searchResults) === 1 ? '' : 'es'; ?> freshly prepared at Zayka
                </p>
            </div>

            <!-- Sort By Form -->
            <form method="GET" action="search.php" id="filterForm" style="display: flex; gap: 10px; align-items: center;">
                <?php if ($query): ?><input type="hidden" name="query" value="<?php echo htmlspecialchars($query); ?>"><?php endif; ?>
                <?php if ($categorySlug): ?><input type="hidden" name="category" value="<?php echo htmlspecialchars($categorySlug); ?>"><?php endif; ?>
                <?php if ($pureVeg): ?><input type="hidden" name="pure_veg" value="1"><?php endif; ?>

                <label style="font-size: 13px; font-weight: 600; color: #555;">Sort By:</label>
                <select name="sort_by" onchange="document.getElementById('filterForm').submit()" 
                        style="padding: 8px 16px; border-radius: 20px; border: 1px solid var(--light-border); font-size: 13px; background: #fff; outline: none; font-weight: 500;">
                    <option value="bestseller" <?php echo ($sortBy === 'bestseller') ? 'selected' : ''; ?>>Popular Bestsellers ★</option>
                    <option value="cost_low" <?php echo ($sortBy === 'cost_low') ? 'selected' : ''; ?>>Price: Low to High</option>
                    <option value="cost_high" <?php echo ($sortBy === 'cost_high') ? 'selected' : ''; ?>>Price: High to Low</option>
                    <option value="name" <?php echo ($sortBy === 'name') ? 'selected' : ''; ?>>Name: A to Z</option>
                </select>
            </form>
        </div>

        <!-- Filter Pills Bar -->
        <div style="display: flex; gap: 10px; overflow-x: auto; padding-bottom: 12px; margin-bottom: 24px; scrollbar-width: none;">
            <a href="search.php<?php echo $query ? '?query=' . urlencode($query) : ''; ?>" 
               style="padding: 8px 18px; border-radius: 20px; font-size: 13px; font-weight: 600; text-decoration: none; white-space: nowrap; background: <?php echo (!$pureVeg && empty($categorySlug)) ? 'var(--primary-green)' : '#FFFFFF'; ?>; color: <?php echo (!$pureVeg && empty($categorySlug)) ? '#FFFFFF' : '#333'; ?>; border: 1px solid var(--light-border);">
                All Dishes
            </a>

            <a href="search.php?<?php echo http_build_query(array_merge($_GET, ['pure_veg' => $pureVeg ? null : '1'])); ?>" 
               style="padding: 8px 18px; border-radius: 20px; font-size: 13px; font-weight: 600; text-decoration: none; white-space: nowrap; background: <?php echo $pureVeg ? '#16A34A' : '#FFFFFF'; ?>; color: <?php echo $pureVeg ? '#FFFFFF' : '#333'; ?>; border: 1px solid var(--light-border);">
                🥬 Pure Veg Only
            </a>

            <?php foreach ($cats as $ct): ?>
                <a href="search.php?<?php echo http_build_query(array_merge($_GET, ['category' => ($categorySlug === $ct['slug']) ? null : $ct['slug']])); ?>" 
                   style="padding: 8px 18px; border-radius: 20px; font-size: 13px; font-weight: 600; text-decoration: none; white-space: nowrap; background: <?php echo ($categorySlug === $ct['slug']) ? 'var(--primary-green)' : '#FFFFFF'; ?>; color: <?php echo ($categorySlug === $ct['slug']) ? '#FFFFFF' : '#333'; ?>; border: 1px solid var(--light-border);">
                    <?php echo htmlspecialchars($ct['name']); ?>
                </a>
            <?php endforeach; ?>
        </div>

        <!-- Dishes Grid -->
        <?php if (!empty($searchResults)): ?>
            <div class="master-menu-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(min(100%, 280px), 1fr)); gap: 20px;">
                <?php foreach ($searchResults as $item): ?>
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
        <?php else: ?>
            <div style="background: #FFFFFF; border-radius: 16px; border: 1px solid var(--light-border); text-align: center; padding: 60px 20px; max-width: 600px; margin: 40px auto;">
                <div style="font-size: 48px; margin-bottom: 16px;">🔍</div>
                <h3 style="font-family: 'Playfair Display', serif; color: var(--primary-green); font-size: 22px; margin: 0 0 10px;">No Dishes Found</h3>
                <p style="color: #666; font-size: 14px; margin-bottom: 24px;">We couldn't find any dishes matching your search. Explore our full authentic menu.</p>
                <a href="menu.php" style="display: inline-block; padding: 12px 28px; background: var(--primary-green); color: #fff; border-radius: 24px; font-weight: 600; text-decoration: none;">
                    View Master Menu
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
