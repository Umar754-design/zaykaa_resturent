<?php
// ===================================================
// Restaurant Detail & Interactive Menu - Zayka Platform
// ===================================================
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
include 'includes/header.php';

$restaurantId = 1; // Single restaurant platform: Zayka

// Fetch restaurant
$restaurant = get_main_restaurant();

if (!$restaurant) {
    echo "<div class='container' style='padding:80px 20px; text-align:center;'>
        <h2>Restaurant Unavailable</h2>
        <p>Our kitchen is currently undergoing maintenance. Please check back shortly.</p>
        <a href='index.php' class='cta-button' style='margin-top:20px; display:inline-block;'>Return to Homepage</a>
    </div>";
    include 'includes/footer.php';
    exit;
}

// Fetch menu categories
$catStmt = $pdo->prepare("SELECT * FROM menu_categories WHERE restaurant_id = ? AND is_active = 1 ORDER BY sort_order ASC");
$catStmt->execute([$restaurantId]);
$menuCategories = $catStmt->fetchAll();

// Fetch menu items with their add-ons
$itemStmt = $pdo->prepare("SELECT * FROM menu_items WHERE restaurant_id = ? ORDER BY is_bestseller DESC, id ASC");
$itemStmt->execute([$restaurantId]);
$allItems = $itemStmt->fetchAll();

// Fetch add-ons for all items
$addonsStmt = $pdo->prepare("SELECT * FROM item_addons WHERE is_available = 1");
$addonsStmt->execute();
$allAddons = $addonsStmt->fetchAll();
$itemAddonsMap = [];
foreach ($allAddons as $ad) {
    $itemAddonsMap[$ad['item_id']][] = $ad;
}

// Group items by menu category or fallback
$categorizedItems = [];
foreach ($allItems as $item) {
    $catName = 'Main Menu';
    foreach ($menuCategories as $mc) {
        if ($mc['id'] == $item['menu_category_id']) {
            $catName = $mc['name'];
            break;
        }
    }
    $categorizedItems[$catName][] = $item;
}

// Fetch reviews
$revStmt = $pdo->prepare("SELECT r.*, u.name as customer_name, u.avatar 
                          FROM reviews r 
                          JOIN users u ON r.user_id = u.id 
                          WHERE r.restaurant_id = ? 
                          ORDER BY r.created_at DESC LIMIT 5");
$revStmt->execute([$restaurantId]);
$reviews = $revStmt->fetchAll();
?>

<!-- Restaurant Hero Banner -->
<div style="background: #FAF8F2; border-bottom: 1px solid var(--light-border); padding: 30px 0;">
    <div class="container">
        <div class="restaurant-hero-wrap" style="display: flex; flex-wrap: wrap; gap: 30px; align-items: center;">
            <div class="rest-hero-logo-wrap" style="width: 140px; height: 140px; border-radius: 16px; overflow: hidden; box-shadow: var(--shadow-md); border: 3px solid #fff; flex-shrink: 0; background: #fff;">
                <img src="<?php echo htmlspecialchars($restaurant['banner'] ?: $restaurant['logo']); ?>" alt="<?php echo htmlspecialchars($restaurant['name']); ?>" style="width: 100%; height: 100%; object-fit: cover;">
            </div>
            
            <div style="flex: 1; min-width: 0; width: 100%;">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 10px;">
                    <div>
                        <h1 style="font-family: 'Playfair Display', serif; font-size: 32px; color: var(--primary-green); margin: 0 0 6px;">
                            <?php echo htmlspecialchars($restaurant['name']); ?>
                        </h1>
                        <p style="color: #666; margin: 0 0 6px; font-size: 15px; font-weight: 500;">
                            <?php echo htmlspecialchars($restaurant['cuisines']); ?>
                        </p>
                        <p style="font-size: 13px; color: #888; margin: 0;">
                            📍 <?php echo htmlspecialchars($restaurant['address'] . ', ' . $restaurant['city'] . ' - ' . $restaurant['pincode']); ?>
                        </p>
                    </div>
                    
                    <div style="display: flex; gap: 10px; align-items: center;">
                        <button onclick="toggleFavorite(<?php echo $restaurant['id']; ?>, this)" 
                                style="background: #fff; border: 1px solid var(--light-border); border-radius: 50%; width: 44px; height: 44px; font-size: 20px; cursor: pointer; box-shadow: var(--shadow-sm);">
                            🤍
                        </button>
                    </div>
                </div>

                <!-- Meta Pills Row -->
                <div class="restaurant-meta-pills" style="display: flex; flex-wrap: wrap; gap: 16px; align-items: center; margin-top: 18px; padding-top: 14px; border-top: 1px dashed var(--light-border);">
                    <div class="rating-pill" style="font-size: 14px; padding: 5px 12px;">
                        <span>★</span> <?php echo number_format($restaurant['rating'], 1); ?>
                        <span style="font-size: 11px; font-weight: normal; margin-left: 4px;">(<?php echo $restaurant['total_ratings']; ?>+ reviews)</span>
                    </div>
                    <span style="font-size: 13px; color: #555; font-weight: 600;">⏱️ <?php echo (int)$restaurant['delivery_time_min']; ?>-<?php echo (int)$restaurant['delivery_time_max']; ?> mins</span>
                    <span style="font-size: 13px; color: #555; font-weight: 600;">🍽️ <?php echo format_price($restaurant['cost_for_two']); ?> for two</span>
                    <span style="font-size: 13px; color: #555; font-weight: 600;">🛵 Min Order: <?php echo format_price($restaurant['min_order_amount']); ?></span>
                    <span style="font-size: 12px; padding: 4px 10px; border-radius: 12px; font-weight: 700; background: <?php echo ($restaurant['status'] === 'open') ? '#DEF7EC' : '#FDE8E8'; ?>; color: <?php echo ($restaurant['status'] === 'open') ? '#046C4E' : '#9B1C1C'; ?>;">
                        ● <?php echo strtoupper($restaurant['status']); ?>
                    </span>
                    <?php if ($restaurant['is_pure_veg']): ?>
                        <span style="font-size: 12px; padding: 4px 10px; border-radius: 12px; font-weight: 700; background: #DEF7EC; color: #046C4E;">
                            🥬 100% PURE VEG
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Main Content Area -->
<div style="background: #FFFFFF; min-height: 70vh; padding: 30px 0 60px;">
    <div class="container">
        <!-- Filter & Category Navigation -->
        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #F1EDE2; padding-bottom: 12px; margin-bottom: 30px; flex-wrap: wrap; gap: 15px;">
            <div style="display: flex; gap: 20px; overflow-x: auto; scrollbar-width: none;">
                <?php foreach (array_keys($categorizedItems) as $idx => $categoryName): ?>
                    <a href="#cat-<?php echo md5($categoryName); ?>" 
                       style="font-size: 15px; font-weight: 700; color: <?php echo $idx === 0 ? 'var(--primary-green)' : '#666'; ?>; white-space: nowrap; text-decoration: none; padding-bottom: 8px; border-bottom: <?php echo $idx === 0 ? '3px solid var(--gold)' : 'none'; ?>;">
                        <?php echo htmlspecialchars($categoryName); ?>
                    </a>
                <?php endforeach; ?>
                <?php if (!empty($reviews)): ?>
                    <a href="#reviewsSection" style="font-size: 15px; font-weight: 700; color: #666; white-space: nowrap; text-decoration: none;">
                        Reviews (<?php echo count($reviews); ?>)
                    </a>
                <?php endif; ?>
            </div>

            <!-- Veg Only Toggle -->
            <label style="display: flex; align-items: center; gap: 8px; font-size: 13px; font-weight: 600; cursor: pointer; user-select: none;">
                <input type="checkbox" id="vegOnlyToggle" onchange="filterVegOnly(this.checked)" style="width: 18px; height: 18px; accent-color: #16A34A;">
                <span>Veg Only 🥬</span>
            </label>
        </div>

        <!-- Menu Sections -->
        <?php foreach ($categorizedItems as $categoryName => $items): ?>
        <section id="cat-<?php echo md5($categoryName); ?>" style="margin-bottom: 40px;">
            <h2 style="font-family: 'Playfair Display', serif; font-size: 24px; color: var(--primary-green); margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                <span><?php echo htmlspecialchars($categoryName); ?></span>
                <span style="font-size: 14px; font-weight: normal; color: #888; font-family: 'Poppins', sans-serif;">(<?php echo count($items); ?>)</span>
            </h2>

            <div class="restaurant-menu-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(min(100%, 340px), 1fr)); gap: 20px;">
                <?php foreach ($items as $item): 
                    $hasAddons = !empty($itemAddonsMap[$item['id']]);
                ?>
                <div class="menu-item-card" data-is-veg="<?php echo $item['is_veg']; ?>" 
                     onclick="window.location.href='dish.php?id=<?php echo $item['id']; ?>'"
                     style="background: #FFFFFF; border: 1px solid var(--light-border); border-radius: 16px; padding: 20px; display: flex; gap: 16px; justify-content: space-between; box-shadow: var(--shadow-sm); transition: transform 0.2s, box-shadow 0.2s; cursor: pointer;">
                    
                    <div style="flex: 1; display: flex; flex-direction: column; gap: 6px;">
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <span class="<?php echo $item['is_veg'] ? 'veg-icon' : 'non-veg-icon'; ?>"></span>
                            <?php if ($item['is_bestseller']): ?>
                                <span style="font-size: 11px; font-weight: 700; color: #D97706; background: #FEF3C7; padding: 2px 8px; border-radius: 4px;">★ BESTSELLER</span>
                            <?php endif; ?>
                        </div>

                        <h3 style="font-family: 'Playfair Display', serif; font-size: 18px; margin: 0; color: var(--dark-text);">
                            <a href="dish.php?id=<?php echo $item['id']; ?>" style="text-decoration: none; color: inherit;">
                                <?php echo htmlspecialchars($item['name']); ?>
                            </a>
                        </h3>

                        <!-- Serving Quantity / Serving Size (just above price) -->
                        <div class="menu-item-serving" style="margin-top: 2px; margin-bottom: 2px;">
                            <span class="serving-label">Serving:</span>
                            <?php if (!empty($item['serving_size'])): ?>
                                <span class="serving-val"><?php echo htmlspecialchars($item['serving_size']); ?></span>
                            <?php else: ?>
                                <span class="serving-empty">Serving size not specified</span>
                            <?php endif; ?>
                        </div>

                        <div style="font-size: 16px; font-weight: 700; color: var(--primary-green); margin: 2px 0 4px;">
                            <?php echo format_price($item['discount_price'] ?: $item['price']); ?>
                            <?php if ($item['discount_price']): ?>
                                <span style="font-size: 13px; text-decoration: line-through; color: #999; margin-left: 6px;">
                                    <?php echo format_price($item['price']); ?>
                                </span>
                            <?php endif; ?>
                        </div>

                        <p style="font-size: 13px; color: #666; margin: 0; line-height: 1.5; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                            <?php echo htmlspecialchars($item['description']); ?>
                        </p>
                    </div>

                    <!-- Dish Image & Add Button -->
                    <div class="dish-action-col" style="width: 120px; display: flex; flex-direction: column; align-items: center; gap: 10px; flex-shrink: 0;">
                        <a href="dish.php?id=<?php echo $item['id']; ?>" class="dish-img-wrap" style="width: 110px; height: 100px; border-radius: 12px; overflow: hidden; background: #FAF8F2; display: block;">
                            <img src="<?php echo htmlspecialchars($item['image']); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>" style="width: 100%; height: 100%; object-fit: cover; transition: transform 0.3s ease;">
                        </a>
                        
                        <?php if ($restaurant['status'] === 'closed'): ?>
                            <span style="font-size: 11px; font-weight: 700; color: #888;">Unavailable</span>
                        <?php elseif (!$item['is_available']): ?>
                            <span style="font-size: 11px; font-weight: 700; color: #DC2626;">Out of Stock</span>
                        <?php else: ?>
                            <?php if ($hasAddons): ?>
                                <button onclick="event.stopPropagation(); openCustomizationModal(<?php echo htmlspecialchars(json_encode($item)); ?>, <?php echo htmlspecialchars(json_encode($itemAddonsMap[$item['id']])); ?>)"
                                        style="width: 100%; padding: 8px 12px; background: #FFFFFF; border: 1.5px solid var(--gold); border-radius: 20px; font-weight: 700; font-size: 13px; color: var(--primary-green); cursor: pointer; box-shadow: 0 2px 6px rgba(0,0,0,0.08); transition: 0.2s;">
                                    ADD +
                                </button>
                                <span style="font-size: 10px; color: #888;">Customisable</span>
                            <?php else: ?>
                                <button onclick="event.stopPropagation(); addToCart(<?php echo $item['id']; ?>)"
                                        style="width: 100%; padding: 8px 12px; background: #FFFFFF; border: 1.5px solid var(--gold); border-radius: 20px; font-weight: 700; font-size: 13px; color: var(--primary-green); cursor: pointer; box-shadow: 0 2px 6px rgba(0,0,0,0.08); transition: 0.2s;">
                                    ADD +
                                </button>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endforeach; ?>

        <!-- Customer Reviews Section -->
        <?php if (!empty($reviews)): ?>
        <section id="reviewsSection" style="margin-top: 50px; padding-top: 30px; border-top: 2px solid #F1EDE2;">
            <h2 style="font-family: 'Playfair Display', serif; font-size: 24px; color: var(--primary-green); margin-bottom: 20px;">
                What Diners Say
            </h2>

            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 20px;">
                <?php foreach ($reviews as $rev): ?>
                <div style="background: #FAF8F2; border: 1px solid var(--light-border); border-radius: 14px; padding: 18px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                        <div style="font-weight: 700; color: var(--dark-text); font-size: 14px;">
                            <?php echo htmlspecialchars($rev['customer_name']); ?>
                        </div>
                        <span class="rating-pill">
                            <span>★</span> <?php echo $rev['restaurant_rating']; ?>.0
                        </span>
                    </div>
                    <p style="font-size: 13px; color: #555; line-height: 1.5; margin: 0 0 8px;">
                        "<?php echo htmlspecialchars($rev['restaurant_review']); ?>"
                    </p>
                    <div style="font-size: 11px; color: #888;">
                        Reviewed on <?php echo date('d M Y', strtotime($rev['created_at'])); ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>
    </div>
</div>

<!-- Customization Add-on Modal -->
<div class="zayka-modal-backdrop" id="customizationModal">
    <div class="zayka-modal">
        <div class="modal-header">
            <div>
                <h3 id="customModalItemName" style="margin: 0; font-size: 18px;">Customize Item</h3>
                <div style="display: flex; align-items: center; gap: 8px; margin-top: 3px;">
                    <span id="customModalItemPrice" style="font-weight: 700; color: var(--primary-green); font-size: 14px;"></span>
                    <span id="customModalItemServing" style="font-size: 12px; color: #555;"></span>
                </div>
            </div>
            <button class="modal-close" onclick="closeModal('customizationModal')">✕</button>
        </div>
        <div class="modal-body">
            <div style="font-size: 13px; font-weight: 700; text-transform: uppercase; color: #666; margin-bottom: 12px;">Select Add-ons & Extras</div>
            <div id="customModalAddonsList" style="display: flex; flex-direction: column; gap: 10px;"></div>
        </div>
        <div class="modal-footer" style="display: flex; justify-content: space-between; align-items: center;">
            <div style="font-size: 16px; font-weight: 700; color: var(--primary-green);" id="customModalGrandTotal"></div>
            <button id="customModalSubmitBtn" style="padding: 10px 24px; background: var(--primary-green); color: #FFFFFF; font-weight: 700; border-radius: 20px; border: none; cursor: pointer;">
                Add to Cart
            </button>
        </div>
    </div>
</div>

<script>
let currentCustomItem = null;
let currentCustomAddons = [];

function filterVegOnly(isVeg) {
    document.querySelectorAll('.menu-item-card').forEach(card => {
        if (isVeg && card.getAttribute('data-is-veg') === '0') {
            card.style.display = 'none';
        } else {
            card.style.display = 'flex';
        }
    });
}

function openCustomizationModal(item, addons) {
    currentCustomItem = item;
    currentCustomAddons = addons;

    document.getElementById('customModalItemName').innerText = item.name;
    const basePrice = item.discount_price ? parseFloat(item.discount_price) : parseFloat(item.price);
    document.getElementById('customModalItemPrice').innerText = '₹' + basePrice.toFixed(2);
    
    const servingEl = document.getElementById('customModalItemServing');
    if (servingEl) {
        servingEl.innerHTML = item.serving_size ? `• <strong style="color:var(--gold);">Serving:</strong> ${item.serving_size}` : '';
    }

    const list = document.getElementById('customModalAddonsList');
    list.innerHTML = '';

    addons.forEach(ad => {
        const row = document.createElement('label');
        row.style.display = 'flex';
        row.style.justifyContent = 'space-between';
        row.style.alignItems = 'center';
        row.style.padding = '10px 14px';
        row.style.background = '#FAF8F2';
        row.style.borderRadius = '10px';
        row.style.cursor = 'pointer';

        row.innerHTML = `
            <div style="display: flex; align-items: center; gap: 10px;">
                <input type="checkbox" class="addon-checkbox" value="${ad.id}" data-price="${ad.price}" onchange="updateCustomTotal()" style="width: 18px; height: 18px; accent-color: #063F2B;">
                <span style="font-weight: 600; font-size: 14px; color: #333;">${ad.name}</span>
            </div>
            <span style="font-weight: 700; font-size: 14px; color: #063F2B;">+₹${parseFloat(ad.price).toFixed(2)}</span>
        `;
        list.appendChild(row);
    });

    updateCustomTotal();
    openModal('customizationModal');
}

function updateCustomTotal() {
    if (!currentCustomItem) return;
    let basePrice = currentCustomItem.discount_price ? parseFloat(currentCustomItem.discount_price) : parseFloat(currentCustomItem.price);
    
    document.querySelectorAll('.addon-checkbox:checked').forEach(cb => {
        basePrice += parseFloat(cb.dataset.price);
    });

    document.getElementById('customModalGrandTotal').innerText = 'Total: ₹' + basePrice.toFixed(2);
}

document.getElementById('customModalSubmitBtn').addEventListener('click', function() {
    if (!currentCustomItem) return;
    const selectedAddons = [];
    document.querySelectorAll('.addon-checkbox:checked').forEach(cb => {
        selectedAddons.push(cb.value);
    });

    addToCart(currentCustomItem.id, 1, selectedAddons);
});
</script>

<?php include 'includes/footer.php'; ?>
