<?php
// ===================================================
// Saved / Favorite Restaurants - Zayka Platform
// ===================================================
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

$user = current_user();
header("Location: menu.php");
exit;

$stmt = $pdo->prepare("SELECT r.* FROM restaurants r 
                       JOIN favorites f ON r.id = f.restaurant_id 
                       WHERE f.user_id = ? 
                       ORDER BY f.created_at DESC");
$stmt->execute([$user['id']]);
$favorites = $stmt->fetchAll();

include 'includes/header.php';
?>

<div style="background: #FAF8F2; min-height: 85vh; padding: 40px 0 80px;">
    <div class="container">
        <h1 style="font-family: 'Playfair Display', serif; font-size: 32px; color: var(--primary-green); margin: 0 0 8px;">
            Saved Restaurants ❤️
        </h1>
        <p style="color: #666; font-size: 14px; margin: 0 0 24px;">Your bookmarked dining spots for quick ordering</p>

        <?php if (!empty($favorites)): ?>
            <div class="restaurants-grid">
                <?php foreach ($favorites as $rest): ?>
                <div class="restaurant-card-item">
                    <div class="rest-card-media">
                        <a href="restaurant.php?id=<?php echo $rest['id']; ?>">
                            <img src="<?php echo htmlspecialchars($rest['banner'] ?: $rest['logo']); ?>" alt="<?php echo htmlspecialchars($rest['name']); ?>">
                        </a>
                        <div class="rest-discount-badge">
                            <?php echo $rest['is_pure_veg'] ? '🥬 100% PURE VEG' : '🔥 SPECIALS'; ?>
                        </div>
                        <button class="rest-fav-btn" onclick="toggleFavorite(<?php echo $rest['id']; ?>, this)" aria-label="Remove">
                            ❤️
                        </button>
                    </div>
                    <div class="rest-card-body">
                        <div class="rest-header-row">
                            <h3 class="rest-name">
                                <a href="restaurant.php?id=<?php echo $rest['id']; ?>"><?php echo htmlspecialchars($rest['name']); ?></a>
                            </h3>
                            <span class="rating-pill">
                                <span>★</span> <?php echo number_format($rest['rating'], 1); ?>
                            </span>
                        </div>
                        <p class="rest-cuisines"><?php echo htmlspecialchars($rest['cuisines']); ?></p>
                        <div class="rest-meta-row">
                            <span>⏱️ <?php echo (int)$rest['delivery_time_min']; ?>-<?php echo (int)$rest['delivery_time_max']; ?> mins</span>
                            <span>•</span>
                            <span><?php echo format_price($rest['cost_for_two']); ?> for two</span>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div style="background: #FFFFFF; border-radius: 16px; border: 1px solid var(--light-border); text-align: center; padding: 60px 20px; max-width: 600px; margin: 40px auto;">
                <div style="font-size: 54px; margin-bottom: 12px;">🤍</div>
                <h3 style="font-family: 'Playfair Display', serif; color: var(--primary-green); font-size: 22px; margin: 0 0 8px;">No Saved Restaurants</h3>
                <p style="color: #666; font-size: 14px; margin-bottom: 24px;">Click the heart icon on any restaurant to save it here for fast reordering.</p>
                <a href="search.php" style="display: inline-block; padding: 12px 28px; background: var(--primary-green); color: #fff; border-radius: 24px; font-weight: 600; text-decoration: none;">
                    Explore Restaurants
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
