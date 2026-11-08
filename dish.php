<?php
// ===================================================
// Dish Details Page - Zaykaa Restaurant Platform
// Detailed Product View, Customizations & Add-to-Cart
// ===================================================

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

$dishId = (int)($_GET['id'] ?? 0);
$dish = null;
$addons = [];
$relatedDishes = [];

if ($dishId > 0) {
    // Fetch Dish with Category and Restaurant Info
    $stmt = $pdo->prepare("
        SELECT m.*, 
               c.name AS cat_name, c.slug AS cat_slug,
               r.name AS restaurant_name, r.city AS restaurant_city, r.rating AS restaurant_rating
        FROM menu_items m
        LEFT JOIN categories c ON m.category_id = c.id
        LEFT JOIN restaurants r ON m.restaurant_id = r.id
        WHERE m.id = ?
    ");
    $stmt->execute([$dishId]);
    $dish = $stmt->fetch();

    if ($dish) {
        // Fetch Active Addons
        $addonStmt = $pdo->prepare("
            SELECT * FROM item_addons 
            WHERE item_id = ? AND is_available = 1 
            ORDER BY price ASC
        ");
        $addonStmt->execute([$dishId]);
        $addons = $addonStmt->fetchAll();

        // Fetch Related Dishes from same category or bestsellers
        $relStmt = $pdo->prepare("
            SELECT m.*, c.name AS cat_name 
            FROM menu_items m
            LEFT JOIN categories c ON m.category_id = c.id
            WHERE m.id != ? 
              AND (m.category_id = ? OR m.is_bestseller = 1)
              AND m.is_available = 1
            ORDER BY (m.category_id = ?) DESC, m.is_bestseller DESC, m.name ASC
            LIMIT 4
        ");
        $relStmt->execute([$dishId, $dish['category_id'] ?? 0, $dish['category_id'] ?? 0]);
        $relatedDishes = $relStmt->fetchAll();
    }
}

include 'includes/header.php';
?>

<style>
/* Dish Detail Premium Styling */
.dish-detail-wrap {
    background: #FAF8F2;
    min-height: 85vh;
    padding: 30px 0 70px;
}
.dish-breadcrumb {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 13px;
    color: #666;
    margin-bottom: 24px;
    flex-wrap: wrap;
}
.dish-breadcrumb a {
    color: var(--primary-green);
    text-decoration: none;
    font-weight: 500;
}
.dish-breadcrumb a:hover {
    color: var(--gold);
    text-decoration: underline;
}
.dish-breadcrumb-sep {
    color: #AAA;
}

.dish-card-main {
    background: #FFFFFF;
    border-radius: 20px;
    border: 1px solid var(--light-border);
    box-shadow: 0 4px 20px rgba(6, 63, 43, 0.06);
    overflow: hidden;
    display: grid;
    grid-template-columns: 46% 54%;
    gap: 0;
    margin-bottom: 40px;
}
@media (max-width: 900px) {
    .dish-card-main {
        grid-template-columns: 1fr;
    }
}

/* Image Visual Column */
.dish-visual-col {
    background: #F4EFE6;
    padding: 30px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    position: relative;
}
.dish-img-container {
    position: relative;
    width: 100%;
    aspect-ratio: 4 / 3;
    border-radius: 16px;
    overflow: hidden;
    background: #EDE8DE;
    box-shadow: 0 8px 24px rgba(0,0,0,0.08);
}
.dish-img-container img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.4s ease;
}
.dish-img-container:hover img {
    transform: scale(1.04);
}
.dish-img-badges {
    position: absolute;
    top: 14px;
    left: 14px;
    display: flex;
    flex-direction: column;
    gap: 8px;
    z-index: 2;
}
.dish-badge-pill {
    padding: 4px 10px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.3px;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    box-shadow: 0 2px 6px rgba(0,0,0,0.15);
}
.badge-bestseller {
    background: #D97706;
    color: #FFFFFF;
}
.badge-veg-status {
    background: rgba(255, 255, 255, 0.95);
    color: #1F2937;
    border: 1px solid rgba(0,0,0,0.08);
}

.dish-visual-footer {
    margin-top: 24px;
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 12px;
    text-align: center;
}
.dish-feature-pill {
    background: #FFFFFF;
    border: 1px solid var(--light-border);
    padding: 10px 8px;
    border-radius: 10px;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 4px;
}
.dish-feature-icon {
    font-size: 18px;
}
.dish-feature-text {
    font-size: 11px;
    font-weight: 600;
    color: #4A5568;
}

/* Info & Actions Column */
.dish-info-col {
    padding: 36px 40px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
}
@media (max-width: 600px) {
    .dish-info-col {
        padding: 24px 20px;
    }
    .dish-visual-col {
        padding: 20px;
    }
}

.dish-header-meta {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    margin-bottom: 8px;
}
.dish-category-tag {
    font-size: 12px;
    font-weight: 700;
    color: var(--gold);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.dish-rating-tag {
    font-size: 12px;
    font-weight: 700;
    background: #ECFDF5;
    color: #047857;
    padding: 3px 10px;
    border-radius: 12px;
    border: 1px solid #A7F3D0;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}

.dish-title {
    font-family: 'Playfair Display', serif;
    font-size: 30px;
    font-weight: 700;
    color: var(--primary-green);
    line-height: 1.25;
    margin: 0 0 14px;
}
@media (max-width: 600px) {
    .dish-title {
        font-size: 24px;
    }
}

/* Serving Size Highlight Badge */
.dish-serving-highlight {
    background: #FDF8ED;
    border: 1px solid #F3DCAC;
    border-radius: 12px;
    padding: 10px 16px;
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 18px;
}
.dish-serving-highlight .serving-ico {
    font-size: 20px;
}
.dish-serving-highlight .serving-meta-title {
    font-size: 11px;
    text-transform: uppercase;
    font-weight: 700;
    color: var(--gold);
    letter-spacing: 0.5px;
    margin-bottom: 1px;
}
.dish-serving-highlight .serving-meta-value {
    font-size: 14px;
    font-weight: 700;
    color: #1E293B;
}

/* Price & Offer Row */
.dish-pricing-strip {
    display: flex;
    align-items: baseline;
    gap: 12px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}
.dish-main-price {
    font-size: 30px;
    font-weight: 800;
    color: var(--primary-green);
    letter-spacing: -0.5px;
}
.dish-original-price {
    font-size: 18px;
    color: #9CA3AF;
    text-decoration: line-through;
}
.dish-discount-badge {
    background: #FEF3C7;
    color: #B45309;
    font-size: 12px;
    font-weight: 700;
    padding: 4px 10px;
    border-radius: 6px;
    border: 1px solid #FDE68A;
}

/* Full Description */
.dish-desc-box {
    margin-bottom: 24px;
}
.dish-desc-title {
    font-size: 13px;
    font-weight: 700;
    color: #4B5563;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 6px;
}
.dish-desc-text {
    font-size: 14px;
    line-height: 1.7;
    color: #4A5568;
    margin: 0;
}

/* Add-ons Checklist */
.dish-addons-section {
    background: #FAF8F2;
    border: 1px solid var(--light-border);
    border-radius: 14px;
    padding: 16px 18px;
    margin-bottom: 24px;
}
.dish-addons-title {
    font-size: 13px;
    font-weight: 700;
    color: var(--dark-text);
    margin-bottom: 10px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.dish-addon-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 8px 0;
    border-bottom: 1px dashed #E5E0D2;
    cursor: pointer;
    user-select: none;
}
.dish-addon-row:last-child {
    border-bottom: none;
}
.dish-addon-left {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 13px;
    font-weight: 500;
    color: #2D3748;
}
.dish-addon-left input[type="checkbox"] {
    width: 17px;
    height: 17px;
    accent-color: var(--primary-green);
    cursor: pointer;
}
.dish-addon-price {
    font-size: 13px;
    font-weight: 700;
    color: var(--primary-green);
}

/* Action Strip (Quantity + Add Button) */
.dish-action-strip {
    display: flex;
    align-items: center;
    gap: 16px;
    padding-top: 18px;
    border-top: 1px solid var(--light-border);
    flex-wrap: wrap;
}
.dish-qty-stepper {
    display: flex;
    align-items: center;
    border: 1.5px solid var(--primary-green);
    border-radius: 25px;
    background: #FFFFFF;
    overflow: hidden;
    height: 48px;
}
.dish-qty-btn {
    width: 44px;
    height: 100%;
    background: transparent;
    border: none;
    font-size: 18px;
    font-weight: 700;
    color: var(--primary-green);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background 0.15s ease;
}
.dish-qty-btn:hover {
    background: #EBF7EE;
}
.dish-qty-display {
    min-width: 36px;
    text-align: center;
    font-weight: 700;
    font-size: 15px;
    color: var(--dark-text);
}
.dish-add-btn {
    flex: 1;
    min-width: 200px;
    height: 48px;
    background: var(--primary-green);
    color: #FFFFFF;
    border: none;
    border-radius: 25px;
    font-size: 15px;
    font-weight: 700;
    cursor: pointer;
    box-shadow: 0 4px 14px rgba(6, 63, 43, 0.25);
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    transition: transform 0.15s ease, background 0.15s ease;
}
.dish-add-btn:hover {
    background: var(--secondary-green);
    transform: translateY(-2px);
}
.dish-view-cart-btn {
    height: 48px;
    padding: 0 22px;
    background: #FFFFFF;
    color: var(--primary-green);
    border: 2px solid var(--primary-green);
    border-radius: 25px;
    font-size: 14px;
    font-weight: 700;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    transition: all 0.2s ease;
}
.dish-view-cart-btn:hover {
    background: var(--primary-green);
    color: #FFFFFF;
}

/* Related Dishes Carousel / Grid */
.related-section {
    margin-top: 20px;
}
.related-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 22px;
}
.related-title {
    font-family: 'Playfair Display', serif;
    font-size: 24px;
    font-weight: 700;
    color: var(--primary-green);
    margin: 0;
}
.related-link {
    font-size: 13px;
    font-weight: 600;
    color: var(--gold);
    text-decoration: none;
}
.related-link:hover {
    text-decoration: underline;
}

/* Clickable Menu Card Links */
.dish-card-link {
    text-decoration: none;
    color: inherit;
    display: block;
}
.dish-card-clickable {
    cursor: pointer;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.dish-card-clickable:hover {
    transform: translateY(-4px);
    box-shadow: var(--shadow-md);
}

/* 404 Empty State */
.dish-empty-state {
    text-align: center;
    padding: 80px 20px;
    background: #FFFFFF;
    border-radius: 20px;
    border: 1px solid var(--light-border);
    max-width: 550px;
    margin: 40px auto;
}
</style>

<div class="dish-detail-wrap">
    <div class="container">
        
        <?php if (!$dish): ?>
            <!-- Dish Not Found State -->
            <div class="dish-empty-state">
                <div style="font-size: 64px; margin-bottom: 16px;">🍽️</div>
                <h1 style="font-family: 'Playfair Display', serif; font-size: 26px; color: var(--primary-green); margin-bottom: 10px;">
                    Dish Not Found
                </h1>
                <p style="color: #666; font-size: 14px; margin-bottom: 24px; line-height: 1.5;">
                    The dish you are looking for is either unavailable or has been removed from our current seasonal menu.
                </p>
                <a href="menu.php" style="display: inline-block; padding: 12px 30px; background: var(--primary-green); color: #FFF; border-radius: 25px; font-weight: 700; text-decoration: none;">
                    Explore Master Menu →
                </a>
            </div>
        <?php else: 
            $finalPrice = !empty($dish['discount_price']) ? (float)$dish['discount_price'] : (float)$dish['price'];
            $originalPrice = (float)$dish['price'];
            $hasDiscount = !empty($dish['discount_price']) && ($dish['discount_price'] < $dish['price']);
            $savings = $hasDiscount ? ($originalPrice - $finalPrice) : 0;
            $discountPct = $hasDiscount ? round(($savings / $originalPrice) * 100) : 0;
            $prepTime = !empty($dish['prep_time_minutes']) ? (int)$dish['prep_time_minutes'] : 20;
        ?>
            <!-- Breadcrumbs -->
            <nav class="dish-breadcrumb" aria-label="Breadcrumb">
                <a href="index.php">Home</a>
                <span class="dish-breadcrumb-sep">/</span>
                <a href="menu.php">Menu</a>
                <?php if (!empty($dish['cat_name'])): ?>
                    <span class="dish-breadcrumb-sep">/</span>
                    <a href="menu.php?category=<?php echo urlencode($dish['cat_slug'] ?? ''); ?>">
                        <?php echo htmlspecialchars($dish['cat_name']); ?>
                    </a>
                <?php endif; ?>
                <span class="dish-breadcrumb-sep">/</span>
                <span style="color: #333; font-weight: 600;"><?php echo htmlspecialchars($dish['name']); ?></span>
            </nav>

            <!-- Main Dish Detail Card -->
            <div class="dish-card-main">
                
                <!-- Left Visual Column -->
                <div class="dish-visual-col">
                    <div class="dish-img-container">
                        <img src="<?php echo htmlspecialchars($dish['image'] ?: 'assets/images/categories/biryani.png'); ?>" 
                             alt="<?php echo htmlspecialchars($dish['name']); ?>" 
                             id="mainDishImage">
                        
                        <!-- Floating Badges -->
                        <div class="dish-img-badges">
                            <!-- Veg / Non-Veg Tag -->
                            <div class="dish-badge-pill badge-veg-status">
                                <span class="<?php echo $dish['is_veg'] ? 'veg-icon' : 'non-veg-icon'; ?>"></span>
                                <span><?php echo $dish['is_veg'] ? '100% Pure Veg' : 'Non-Veg'; ?></span>
                            </div>

                            <?php if ($dish['is_bestseller']): ?>
                                <div class="dish-badge-pill badge-bestseller">
                                    ★ BESTSELLER
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Culinary Quality Badges -->
                    <div class="dish-visual-footer">
                        <div class="dish-feature-pill">
                            <span class="dish-feature-icon">⏱️</span>
                            <span class="dish-feature-text"><?php echo $prepTime; ?> Mins Prep</span>
                        </div>
                        <div class="dish-feature-pill">
                            <span class="dish-feature-icon">🌿</span>
                            <span class="dish-feature-text">Fresh Spices</span>
                        </div>
                        <div class="dish-feature-pill">
                            <span class="dish-feature-icon">🛡️</span>
                            <span class="dish-feature-text">Hygienic Pack</span>
                        </div>
                    </div>
                </div>

                <!-- Right Information & Ordering Column -->
                <div class="dish-info-col">
                    <div>
                        <!-- Header Meta -->
                        <div class="dish-header-meta">
                            <span class="dish-category-tag">
                                <?php echo htmlspecialchars($dish['cat_name'] ?? 'Zaykaa Signature'); ?>
                            </span>
                            <span class="dish-rating-tag">
                                ★ 4.8 (120+ reviews)
                            </span>
                        </div>

                        <!-- Dish Title -->
                        <h1 class="dish-title">
                            <?php echo htmlspecialchars($dish['name']); ?>
                        </h1>

                        <!-- Serving Quantity / Serving Size (Clear & Highlighted) -->
                        <div class="dish-serving-highlight">
                            <span class="serving-ico">🍽️</span>
                            <div>
                                <div class="serving-meta-title">Serving Portion & Quantity</div>
                                <div class="serving-meta-value">
                                    <?php if (!empty($dish['serving_size'])): ?>
                                        <span><?php echo htmlspecialchars($dish['serving_size']); ?></span>
                                    <?php else: ?>
                                        <span style="color: #888; font-style: italic; font-weight: normal;">Serving size not specified</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Pricing & Offers -->
                        <div class="dish-pricing-strip">
                            <div class="dish-main-price">
                                <?php echo format_price($finalPrice); ?>
                            </div>
                            <?php if ($hasDiscount): ?>
                                <div class="dish-original-price">
                                    <?php echo format_price($originalPrice); ?>
                                </div>
                                <div class="dish-discount-badge">
                                    SAVE <?php echo format_price($savings); ?> (<?php echo $discountPct; ?>% OFF)
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Full Description -->
                        <div class="dish-desc-box">
                            <div class="dish-desc-title">Description</div>
                            <p class="dish-desc-text">
                                <?php echo nl2br(htmlspecialchars($dish['description'] ?: 'Prepared fresh with master chef culinary expertise, premium authentic ingredients, and traditional slow-cooked flavors.')); ?>
                            </p>
                        </div>

                        <!-- Customization Addons Checklist (if available) -->
                        <?php if (!empty($addons)): ?>
                            <div class="dish-addons-section">
                                <div class="dish-addons-title">
                                    <span>Customise Your Dish</span>
                                    <span style="font-size: 11px; font-weight: normal; color: #777;">Optional Add-ons</span>
                                </div>
                                <div id="addonsList">
                                    <?php foreach ($addons as $ad): ?>
                                        <label class="dish-addon-row">
                                            <div class="dish-addon-left">
                                                <input type="checkbox" 
                                                       name="dish_addon" 
                                                       value="<?php echo $ad['id']; ?>" 
                                                       data-price="<?php echo (float)$ad['price']; ?>"
                                                       onchange="recalculateDishPrice()">
                                                <span><?php echo htmlspecialchars($ad['name']); ?></span>
                                            </div>
                                            <div class="dish-addon-price">
                                                +<?php echo format_price($ad['price']); ?>
                                            </div>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Action Strip -->
                    <div>
                        <div class="dish-action-strip">
                            <!-- Quantity Stepper -->
                            <div class="dish-qty-stepper" role="group" aria-label="Quantity Stepper">
                                <button type="button" class="dish-qty-btn" onclick="changeDetailQty(-1)" aria-label="Decrease quantity">−</button>
                                <span class="dish-qty-display" id="detailQtyDisplay">1</span>
                                <button type="button" class="dish-qty-btn" onclick="changeDetailQty(1)" aria-label="Increase quantity">+</button>
                            </div>

                            <!-- Add to Cart Primary Button -->
                            <button type="button" 
                                    class="dish-add-btn" 
                                    id="addDetailCartBtn"
                                    onclick="handleDetailAddToCart()">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/>
                                    <line x1="3" y1="6" x2="21" y2="6"/>
                                    <path d="M16 10a4 4 0 0 1-8 0"/>
                                </svg>
                                <span>Add to Cart • <span id="btnTotalFmt"><?php echo format_price($finalPrice); ?></span></span>
                            </button>

                            <!-- Shortcut to View Cart -->
                            <a href="cart.php" class="dish-view-cart-btn" id="viewCartShortcut" style="display: none;">
                                View Cart 🛒
                            </a>
                        </div>

                        <!-- Safe Food & Delivery Guarantee Note -->
                        <div style="margin-top: 16px; display: flex; align-items: center; gap: 8px; font-size: 12px; color: #64748B;">
                            <span>🛵 Delivered fresh & warm to your door</span>
                            <span>•</span>
                            <span>100% Contactless Delivery</span>
                        </div>
                    </div>

                </div>
            </div>

            <!-- Related Recommended Dishes Section -->
            <?php if (!empty($relatedDishes)): ?>
                <section class="related-section">
                    <div class="related-header">
                        <h2 class="related-title">You Might Also Love 🍲</h2>
                        <a href="menu.php<?php echo !empty($dish['cat_slug']) ? '?category=' . urlencode($dish['cat_slug']) : ''; ?>" class="related-link">
                            View All <?php echo htmlspecialchars($dish['cat_name'] ?? 'Dishes'); ?> →
                        </a>
                    </div>

                    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(min(100%, 270px), 1fr)); gap: 20px;">
                        <?php foreach ($relatedDishes as $ritem): ?>
                            <div class="master-menu-card dish-card-clickable" 
                                 onclick="window.location.href='dish.php?id=<?php echo $ritem['id']; ?>'"
                                 style="background: #FFFFFF; border-radius: 16px; border: 1px solid var(--light-border); padding: 16px; display: flex; flex-direction: column; justify-content: space-between; box-shadow: var(--shadow-sm);">
                                <div>
                                    <div style="position: relative; height: 160px; border-radius: 12px; overflow: hidden; background: #FAF8F2; margin-bottom: 12px;">
                                        <img src="<?php echo htmlspecialchars($ritem['image'] ?: 'assets/images/categories/biryani.png'); ?>" 
                                             alt="<?php echo htmlspecialchars($ritem['name']); ?>" 
                                             style="width: 100%; height: 100%; object-fit: cover;">
                                        <div style="position: absolute; top: 10px; left: 10px;">
                                            <span class="<?php echo $ritem['is_veg'] ? 'veg-icon' : 'non-veg-icon'; ?>"></span>
                                        </div>
                                        <?php if ($ritem['is_bestseller']): ?>
                                            <div style="position: absolute; bottom: 10px; left: 10px; background: rgba(217, 119, 6, 0.95); color: #fff; font-size: 10px; font-weight: 700; padding: 2px 7px; border-radius: 4px;">
                                                ★ BESTSELLER
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <div style="font-size: 11px; color: var(--gold); font-weight: 700; text-transform: uppercase;">
                                        <?php echo htmlspecialchars($ritem['cat_name'] ?? 'Zaykaa Special'); ?>
                                    </div>
                                    
                                    <h3 style="font-family: 'Playfair Display', serif; font-size: 17px; margin: 4px 0 6px; color: var(--dark-text);">
                                        <a href="dish.php?id=<?php echo $ritem['id']; ?>" style="text-decoration: none; color: inherit;">
                                            <?php echo htmlspecialchars($ritem['name']); ?>
                                        </a>
                                    </h3>

                                    <p style="font-size: 12px; color: #666; margin: 0; line-height: 1.4; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                                        <?php echo htmlspecialchars($ritem['description']); ?>
                                    </p>
                                </div>

                                <div style="margin-top: 12px; padding-top: 10px; border-top: 1px dashed var(--light-border);">
                                    <!-- Serving Size -->
                                    <div class="menu-item-serving">
                                        <span class="serving-label">Serving:</span>
                                        <?php if (!empty($ritem['serving_size'])): ?>
                                            <span class="serving-val"><?php echo htmlspecialchars($ritem['serving_size']); ?></span>
                                        <?php else: ?>
                                            <span class="serving-empty">Serving size not specified</span>
                                        <?php endif; ?>
                                    </div>

                                    <div style="display: flex; justify-content: space-between; align-items: center;">
                                        <div>
                                            <span style="font-size: 17px; font-weight: 700; color: var(--primary-green);">
                                                <?php echo format_price($ritem['discount_price'] ?: $ritem['price']); ?>
                                            </span>
                                            <?php if ($ritem['discount_price']): ?>
                                                <span style="font-size: 12px; text-decoration: line-through; color: #999; margin-left: 4px;">
                                                    <?php echo format_price($ritem['price']); ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>

                                        <button onclick="event.stopPropagation(); addToCart(<?php echo $ritem['id']; ?>)" 
                                                style="padding: 7px 16px; background: var(--primary-green); color: #fff; border-radius: 20px; font-weight: 700; font-size: 12px; border: none; cursor: pointer; box-shadow: var(--shadow-sm);">
                                            + ADD
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>

        <?php endif; ?>

    </div>
</div>

<script>
// Dish Detail Interactive Logic
const dishBasePrice = <?php echo isset($finalPrice) ? (float)$finalPrice : 0; ?>;
let detailQuantity = 1;

function changeDetailQty(delta) {
    detailQuantity = Math.max(1, Math.min(20, detailQuantity + delta));
    const qtyDisplay = document.getElementById('detailQtyDisplay');
    if (qtyDisplay) qtyDisplay.textContent = detailQuantity;
    recalculateDishPrice();
}

function recalculateDishPrice() {
    let unitTotal = dishBasePrice;
    
    // Sum selected addons
    const addonCheckboxes = document.querySelectorAll('input[name="dish_addon"]:checked');
    addonCheckboxes.forEach(cb => {
        unitTotal += parseFloat(cb.getAttribute('data-price') || 0);
    });

    const grandTotal = unitTotal * detailQuantity;
    const btnTotalFmt = document.getElementById('btnTotalFmt');
    if (btnTotalFmt) {
        btnTotalFmt.textContent = '₹' + grandTotal.toFixed(2);
    }
}

function handleDetailAddToCart() {
    const dishId = <?php echo (int)$dishId; ?>;
    if (!dishId) return;

    // Collect selected addons
    const selectedAddons = [];
    const addonCheckboxes = document.querySelectorAll('input[name="dish_addon"]:checked');
    addonCheckboxes.forEach(cb => {
        selectedAddons.push(parseInt(cb.value));
    });

    const addBtn = document.getElementById('addDetailCartBtn');
    const originalText = addBtn.innerHTML;

    // Call global addToCart
    addToCart(dishId, detailQuantity, selectedAddons);

    // Provide visual feedback
    if (addBtn) {
        addBtn.innerHTML = `
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="20 6 9 17 4 12"/>
            </svg>
            <span>Added to Cart!</span>
        `;
        addBtn.style.background = '#047857';

        setTimeout(() => {
            addBtn.innerHTML = originalText;
            addBtn.style.background = '';
            recalculateDishPrice();
        }, 2200);
    }

    // Show View Cart shortcut button
    const viewCartBtn = document.getElementById('viewCartShortcut');
    if (viewCartBtn) {
        viewCartBtn.style.display = 'inline-flex';
    }
}
</script>

<?php include 'includes/footer.php'; ?>
