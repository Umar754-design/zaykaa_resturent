<?php
// ===================================================
// Homepage - Zaykaa Café & Restaurant Platform
// ===================================================
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
include 'includes/header.php';

// Fetch Active Categories
$catStmt = $pdo->query("SELECT * FROM categories WHERE is_active = 1 ORDER BY sort_order ASC");
$categories = $catStmt->fetchAll();

// Fetch Bestseller Menu Items from Zayka
$bestStmt = $pdo->query("SELECT m.*, c.name as cat_name 
                         FROM menu_items m 
                         LEFT JOIN categories c ON m.category_id = c.id 
                         WHERE m.is_available = 1 
                         ORDER BY m.is_bestseller DESC, m.id ASC 
                         LIMIT 6");
$bestsellers = $bestStmt->fetchAll();

// Fetch Active Coupons
$coupStmt = $pdo->query("SELECT * FROM coupons WHERE is_active = 1 AND valid_until >= CURDATE() LIMIT 3");
$coupons = $coupStmt->fetchAll();
?>

    <!-- Hero Section -->
    <section class="hero">
        <div class="hero-content">
            <div class="hero-left">
                <div class="hero-tagline">
                    <span class="tagline-green">GOOD FOOD</span>
                    <span class="tagline-gold">GOOD MOOD</span>
                </div>
                
                <p class="hero-welcome">Welcome to</p>
                <h1 class="hero-title">
                    <span class="title-main">Zaykaa</span>
                    <span class="title-leaf">🍃</span>
                </h1>
                <p class="hero-subtitle">CAFÉ & RESTAURANT PLATFORM</p>
                
                <div class="hero-features">
                    <div class="feature-item">
                        <div class="feature-icon">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M12 2L2 7l10 5 10-5-10-5z"/>
                                <path d="M2 17l10 5 10-5"/>
                                <path d="M2 12l10 5 10-5"/>
                            </svg>
                        </div>
                        <div class="feature-text">
                            <h3>Fresh Ingredients</h3>
                            <p>Fresh & Quality</p>
                        </div>
                    </div>
                    
                    <div class="feature-item">
                        <div class="feature-icon">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2z"/>
                                <path d="M12 6v6l4 2"/>
                            </svg>
                        </div>
                        <div class="feature-text">
                            <h3>Authentic Flavours</h3>
                            <p>Traditional Taste</p>
                        </div>
                    </div>
                    
                    <div class="feature-item">
                        <div class="feature-icon">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                                <polyline points="22 4 12 14.01 9 11.01"/>
                            </svg>
                        </div>
                        <div class="feature-text">
                            <h3>Hygienic Preparation</h3>
                            <p>Clean & Safe</p>
                        </div>
                    </div>
                    
                    <div class="feature-item">
                        <div class="feature-icon">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
                            </svg>
                        </div>
                        <div class="feature-text">
                            <h3>Quality You Deserve</h3>
                            <p>Best Experience</p>
                        </div>
                    </div>
                </div>
                
                <div class="hero-location" style="display: flex; gap: 15px; align-items: center; margin-top: 25px;">
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <span class="location-icon">📍</span>
                        <span>Delivering to <strong><?php echo htmlspecialchars($currentCity); ?></strong> & surrounding areas</span>
                    </div>
                    <a href="menu.php" style="background: var(--primary-green); color: #fff; padding: 8px 18px; border-radius: 20px; font-size: 13px; font-weight: 600; text-decoration: none;">Order Online →</a>
                </div>
            </div>
            
            <div class="hero-right">
                <div class="hero-image-container">
                    <img src="assets/images/hero/biryani.png" alt="Delicious Chicken Biryani" class="hero-image">
                    <div class="hero-badge">
                        <span class="badge-text">AUTHENTIC</span>
                        <span class="badge-text">FLAVOURS</span>
                        <span class="badge-sub">THAT STAY WITH YOU</span>
                        <span class="badge-leaf">🍃</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Benefits Strip -->
    <section class="benefits">
        <div class="container">
            <div class="benefits-content">
                <div class="benefit-item">
                    <div class="benefit-icon">
                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="1" y="3" width="15" height="13"/>
                            <polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/>
                            <circle cx="5.5" cy="18.5" r="2.5"/>
                            <circle cx="18.5" cy="18.5" r="2.5"/>
                        </svg>
                    </div>
                    <h3>FAST DELIVERY</h3>
                    <p>Quick & Reliable</p>
                </div>
                
                <div class="benefit-divider"></div>
                
                <div class="benefit-item">
                    <div class="benefit-icon">
                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M12 2L2 7l10 5 10-5-10-5z"/>
                            <path d="M2 17l10 5 10-5"/>
                            <path d="M2 12l10 5 10-5"/>
                        </svg>
                    </div>
                    <h3>FRESHLY PREPARED</h3>
                    <p>Made to Order</p>
                </div>
                
                <div class="benefit-divider"></div>
                
                <div class="benefit-item">
                    <div class="benefit-icon">
                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2z"/>
                            <path d="M12 6v6l4 2"/>
                        </svg>
                    </div>
                    <h3>QUALITY INGREDIENTS</h3>
                    <p>Always Fresh</p>
                </div>
                
                <div class="benefit-divider"></div>
                
                <div class="benefit-item">
                    <div class="benefit-icon">
                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
                        </svg>
                    </div>
                    <h3>GREAT EXPERIENCE</h3>
                    <p>Every Time</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Promo Offers Banner Strip -->
    <?php if (!empty($coupons)): ?>
    <section style="background: #FDFBF7; padding: 24px 0; border-bottom: 1px solid var(--light-border);">
        <div class="container">
            <div style="display: flex; gap: 16px; overflow-x: auto; scrollbar-width: none; padding-bottom: 5px;">
                <?php foreach ($coupons as $cp): ?>
                    <div style="flex: 1; min-width: 280px; background: #FFFFFF; border: 1px dashed var(--gold); border-radius: 12px; padding: 14px 18px; display: flex; align-items: center; gap: 14px; box-shadow: var(--shadow-sm);">
                        <div style="font-size: 28px;">🏷️</div>
                        <div>
                            <div style="font-weight: 700; color: var(--primary-green); font-size: 14px;"><?php echo htmlspecialchars($cp['title']); ?></div>
                            <div style="font-size: 12px; color: #666; margin: 2px 0;">Use Code: <strong style="color: var(--gold); font-size: 13px;"><?php echo htmlspecialchars($cp['code']); ?></strong></div>
                            <div style="font-size: 11px; color: #888;">Min order <?php echo format_price($cp['min_order_amount']); ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Popular Categories -->
    <section class="categories" id="categoriesSection">
        <div class="container">
            <div class="section-header">
                <h2 class="section-title">
                    <span class="title-small">EXPLORE OUR</span>
                    <span class="title-large">POPULAR CUISINES</span>
                </h2>
                <div class="title-decoration">
                    <span class="decoration-leaf">🍃</span>
                    <span class="decoration-line"></span>
                    <span class="decoration-leaf">🍃</span>
                </div>
            </div>
            
            <div class="categories-grid">
                <?php foreach ($categories as $cat): ?>
                <a href="menu.php?category=<?php echo urlencode($cat['slug']); ?>" class="category-card" style="text-decoration: none; color: inherit;">
                    <div class="card-image">
                        <img src="<?php echo htmlspecialchars($cat['image']); ?>" alt="<?php echo htmlspecialchars($cat['name']); ?>" class="category-img">
                        <div class="card-icon">
                            <span style="font-size: 20px;">🍽️</span>
                        </div>
                    </div>
                    <div class="card-content">
                        <h3><?php echo strtoupper(htmlspecialchars($cat['name'])); ?></h3>
                        <p><?php echo htmlspecialchars($cat['description'] ?: 'Authentic & Delicious'); ?></p>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Chef's Recommended Bestsellers -->
    <?php if (!empty($bestsellers)): ?>
    <section style="padding: 50px 0; background: #FFFFFF;">
        <div class="container">
            <div class="section-header">
                <h2 class="section-title">
                    <span class="title-small">CHEF'S SELECTION</span>
                    <span class="title-large">POPULAR SIGNATURE DISHES</span>
                </h2>
                <div class="title-decoration">
                    <span class="decoration-leaf">🍃</span>
                    <span class="decoration-line"></span>
                    <span class="decoration-leaf">🍃</span>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 24px; margin-top: 30px;">
                <?php foreach ($bestsellers as $bitem): ?>
                <div onclick="window.location.href='dish.php?id=<?php echo $bitem['id']; ?>'" 
                     style="background: #FAF8F2; border-radius: 14px; border: 1px solid var(--light-border); padding: 16px; display: flex; flex-direction: column; justify-content: space-between; gap: 10px; transition: transform 0.2s, box-shadow 0.2s; box-shadow: var(--shadow-sm); cursor: pointer;">
                    <div>
                        <a href="dish.php?id=<?php echo $bitem['id']; ?>" style="text-decoration: none; color: inherit; display: block;">
                            <div style="position: relative; height: 160px; border-radius: 10px; overflow: hidden; margin-bottom: 10px;">
                                <img src="<?php echo htmlspecialchars($bitem['image']); ?>" alt="<?php echo htmlspecialchars($bitem['name']); ?>" style="width: 100%; height: 100%; object-fit: cover; transition: transform 0.3s ease;">
                                <div style="position: absolute; top: 10px; left: 10px;">
                                    <span class="<?php echo $bitem['is_veg'] ? 'veg-icon' : 'non-veg-icon'; ?>"></span>
                                </div>
                                <?php if ($bitem['is_bestseller']): ?>
                                    <div style="position: absolute; bottom: 8px; left: 8px; background: rgba(217, 119, 6, 0.95); color: #fff; font-size: 10px; font-weight: 700; padding: 2px 8px; border-radius: 4px;">
                                        ★ BESTSELLER
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div style="font-size: 11px; color: var(--gold); font-weight: 700; text-transform: uppercase;"><?php echo htmlspecialchars($bitem['cat_name'] ?? 'Signature Dish'); ?></div>
                            <h4 style="font-family: 'Playfair Display', serif; font-size: 17px; margin: 4px 0; color: var(--primary-green);"><?php echo htmlspecialchars($bitem['name']); ?></h4>
                        </a>
                        <p style="font-size: 12px; color: #666; margin: 0; line-height: 1.4; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                            <?php echo htmlspecialchars($bitem['description']); ?>
                        </p>
                    </div>
                    <div style="margin-top: 10px; padding-top: 10px; border-top: 1px dashed var(--light-border);">
                        <!-- Serving Quantity / Serving Size (just above price) -->
                        <div class="menu-item-serving" style="margin-bottom: 5px;">
                            <span class="serving-label">Serving:</span>
                            <?php if (!empty($bitem['serving_size'])): ?>
                                <span class="serving-val"><?php echo htmlspecialchars($bitem['serving_size']); ?></span>
                            <?php else: ?>
                                <span class="serving-empty">Serving size not specified</span>
                            <?php endif; ?>
                        </div>

                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <span style="font-size: 18px; font-weight: 700; color: var(--dark-text);"><?php echo format_price($bitem['discount_price'] ?: $bitem['price']); ?></span>
                                <?php if ($bitem['discount_price']): ?>
                                    <span style="font-size: 13px; text-decoration: line-through; color: #888; margin-left: 6px;"><?php echo format_price($bitem['price']); ?></span>
                                <?php endif; ?>
                            </div>
                            <button onclick="event.stopPropagation(); addToCart(<?php echo $bitem['id']; ?>)" 
                                    style="padding: 7px 18px; background: var(--primary-green); border: none; border-radius: 20px; font-weight: 700; font-size: 13px; color: #FFFFFF; cursor: pointer; transition: 0.2s; box-shadow: var(--shadow-sm);">
                                + ADD
                            </button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <div style="text-align: center; margin-top: 35px;">
                <a href="menu.php" style="display: inline-block; padding: 12px 32px; background: var(--primary-green); color: #fff; border-radius: 30px; font-weight: 600; text-decoration: none; box-shadow: var(--shadow-sm);">
                    Explore Full Menu →
                </a>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Full Menu CTA Banner -->
    <section class="cta-banner">
        <div class="container">
            <div class="cta-content">
                <p class="cta-subtitle">Experience Culinary Perfection</p>
                <h2 class="cta-title">AUTHENTIC ZAYKAA DINING</h2>
                <a href="menu.php" class="cta-button">
                    <span>EXPLORE OUR FULL MENU</span>
                    <span class="cta-arrow">→</span>
                </a>
                <div class="cta-decoration">
                    <span class="cta-leaf cta-leaf-left">🍃</span>
                    <span class="cta-leaf cta-leaf-right">🍃</span>
                </div>
            </div>
        </div>
    </section>

<?php include 'includes/footer.php'; ?>
