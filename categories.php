<?php
// ===================================================
// Categories Directory - Zayka Platform
// ===================================================
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
include 'includes/header.php';

$cats = $pdo->query("SELECT * FROM categories WHERE is_active = 1 ORDER BY sort_order ASC")->fetchAll();
?>

<div style="background: #FAF8F2; min-height: 85vh; padding: 40px 0 80px;">
    <div class="container">
        <h1 style="font-family: 'Playfair Display', serif; font-size: 32px; color: var(--primary-green); margin: 0 0 8px;">
            Food Categories 🍃
        </h1>
        <p style="color: #666; font-size: 14px; margin: 0 0 30px;">Explore dishes by flavour and culinary style</p>

        <div class="categories-grid">
            <?php foreach ($cats as $cat): ?>
            <a href="search.php?category=<?php echo urlencode($cat['slug']); ?>" class="category-card" style="text-decoration: none; color: inherit;">
                <div class="card-image">
                    <img src="<?php echo htmlspecialchars($cat['image']); ?>" alt="<?php echo htmlspecialchars($cat['name']); ?>" class="category-img">
                    <div class="card-icon">
                        <span style="font-size: 20px;">🍽️</span>
                    </div>
                </div>
                <div class="card-content">
                    <h3><?php echo strtoupper(htmlspecialchars($cat['name'])); ?></h3>
                    <p><?php echo htmlspecialchars($cat['description']); ?></p>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
