<?php
// ===================================================
// Rate & Review Experience - Zayka Platform
// ===================================================
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

$user = current_user();
if (!$user) {
    header("Location: login.php");
    exit;
}

$orderId = (int)($_GET['order_id'] ?? 0);
$stmt = $pdo->prepare("SELECT o.*, r.name as rest_name, u.name as rider_name 
                       FROM orders o 
                       JOIN restaurants r ON o.restaurant_id = r.id 
                       LEFT JOIN delivery_partners dp ON o.delivery_partner_id = dp.id 
                       LEFT JOIN users u ON dp.user_id = u.id 
                       WHERE o.id = ? AND o.user_id = ?");
$stmt->execute([$orderId, $user['id']]);
$order = $stmt->fetch();

if (!$order || $order['order_status'] !== 'delivered') {
    die("<div style='padding:50px; text-align:center;'>Order cannot be reviewed. <a href='orders.php'>View Orders</a></div>");
}

// Check if already reviewed
$revCheck = $pdo->prepare("SELECT id FROM reviews WHERE order_id = ?");
$revCheck->execute([$orderId]);
$existingReview = $revCheck->fetch();

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $restRating = max(1, min(5, (int)($_POST['restaurant_rating'] ?? 5)));
    $foodRating = max(1, min(5, (int)($_POST['food_rating'] ?? 5)));
    $deliveryRating = max(1, min(5, (int)($_POST['delivery_rating'] ?? 5)));
    $restReview = trim($_POST['restaurant_review'] ?? '');
    $deliveryReview = trim($_POST['delivery_review'] ?? '');

    if ($existingReview) {
        $upd = $pdo->prepare("UPDATE reviews SET restaurant_rating = ?, restaurant_review = ?, food_rating = ?, delivery_rating = ?, delivery_review = ? WHERE id = ?");
        $upd->execute([$restRating, $restReview, $foodRating, $deliveryRating, $deliveryReview, $existingReview['id']]);
    } else {
        $ins = $pdo->prepare("INSERT INTO reviews (order_id, user_id, restaurant_id, delivery_partner_id, restaurant_rating, restaurant_review, food_rating, delivery_rating, delivery_review) 
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $ins->execute([$orderId, $user['id'], $order['restaurant_id'], $order['delivery_partner_id'], $restRating, $restReview, $foodRating, $deliveryRating, $deliveryReview]);
    }

    // Recalculate restaurant rating
    $calcStmt = $pdo->prepare("SELECT AVG(restaurant_rating) as avg_r, COUNT(*) as cnt FROM reviews WHERE restaurant_id = ?");
    $calcStmt->execute([$order['restaurant_id']]);
    $res = $calcStmt->fetch();
    $pdo->prepare("UPDATE restaurants SET rating = ?, total_ratings = ? WHERE id = ?")
        ->execute([round($res['avg_r'], 2), $res['cnt'], $order['restaurant_id']]);

    set_flash('success', 'Thank you! Your ratings and review have been published.');
    header("Location: orders.php");
    exit;
}

include 'includes/header.php';
?>

<div style="background: #FAF8F2; min-height: 85vh; padding: 50px 0;">
    <div class="container" style="max-width: 650px;">
        <div style="background: #fff; border-radius: 18px; border: 1px solid var(--light-border); padding: 36px; box-shadow: var(--shadow-sm);">
            <div style="text-align: center; margin-bottom: 24px;">
                <span style="font-size: 42px;">⭐</span>
                <h1 style="font-family: 'Playfair Display', serif; font-size: 28px; color: var(--primary-green); margin: 8px 0 4px;">
                    Rate Your Experience
                </h1>
                <p style="color: #666; font-size: 14px; margin: 0;">
                    Order #<?php echo htmlspecialchars($order['order_number']); ?> from <strong><?php echo htmlspecialchars($order['rest_name']); ?></strong>
                </p>
            </div>

            <form method="POST">
                <!-- 1. Restaurant Rating -->
                <div style="margin-bottom: 24px; padding-bottom: 20px; border-bottom: 1px solid #eee;">
                    <label style="font-weight: 700; color: var(--dark-text); font-size: 15px; display: block; margin-bottom: 6px;">
                        1. Rate <?php echo htmlspecialchars($order['rest_name']); ?>
                    </label>
                    <div style="display: flex; gap: 10px; margin: 10px 0;">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <label style="cursor: pointer; display: flex; align-items: center; gap: 4px; padding: 8px 14px; background: #FAF8F2; border-radius: 20px; border: 1px solid var(--light-border); font-weight: 700;">
                                <input type="radio" name="restaurant_rating" value="<?php echo $i; ?>" <?php echo ($i === 5) ? 'checked' : ''; ?> style="accent-color: var(--gold);">
                                <span><?php echo $i; ?> ★</span>
                            </label>
                        <?php endfor; ?>
                    </div>
                    <textarea name="restaurant_review" placeholder="How was the taste, packaging, and portion size? Write your thoughts..." 
                              style="width: 100%; height: 80px; padding: 10px; border: 1px solid var(--light-border); border-radius: 8px; font-size: 13px;"></textarea>
                </div>

                <!-- 2. Food Rating -->
                <div style="margin-bottom: 24px; padding-bottom: 20px; border-bottom: 1px solid #eee;">
                    <label style="font-weight: 700; color: var(--dark-text); font-size: 15px; display: block; margin-bottom: 6px;">
                        2. Food Quality & Freshness
                    </label>
                    <div style="display: flex; gap: 10px; margin: 10px 0;">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <label style="cursor: pointer; display: flex; align-items: center; gap: 4px; padding: 8px 14px; background: #FAF8F2; border-radius: 20px; border: 1px solid var(--light-border); font-weight: 700;">
                                <input type="radio" name="food_rating" value="<?php echo $i; ?>" <?php echo ($i === 5) ? 'checked' : ''; ?> style="accent-color: var(--gold);">
                                <span><?php echo $i; ?> ★</span>
                            </label>
                        <?php endfor; ?>
                    </div>
                </div>

                <!-- 3. Delivery Partner Rating -->
                <?php if ($order['rider_name']): ?>
                <div style="margin-bottom: 28px;">
                    <label style="font-weight: 700; color: var(--dark-text); font-size: 15px; display: block; margin-bottom: 6px;">
                        3. Rate Delivery Partner (<?php echo htmlspecialchars($order['rider_name']); ?>)
                    </label>
                    <div style="display: flex; gap: 10px; margin: 10px 0;">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <label style="cursor: pointer; display: flex; align-items: center; gap: 4px; padding: 8px 14px; background: #FAF8F2; border-radius: 20px; border: 1px solid var(--light-border); font-weight: 700;">
                                <input type="radio" name="delivery_rating" value="<?php echo $i; ?>" <?php echo ($i === 5) ? 'checked' : ''; ?> style="accent-color: var(--gold);">
                                <span><?php echo $i; ?> ★</span>
                            </label>
                        <?php endfor; ?>
                    </div>
                    <textarea name="delivery_review" placeholder="Was the rider punctual, courteous, and careful with the food?" 
                              style="width: 100%; height: 70px; padding: 10px; border: 1px solid var(--light-border); border-radius: 8px; font-size: 13px;"></textarea>
                </div>
                <?php endif; ?>

                <button type="submit" style="width: 100%; padding: 14px; background: var(--primary-green); color: #fff; border-radius: 25px; font-weight: 700; font-size: 16px; border: none; cursor: pointer; box-shadow: var(--shadow-sm);">
                    Submit Review & Ratings ⭐
                </button>
            </form>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
