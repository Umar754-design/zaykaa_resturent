<?php
// ===================================================
// Restaurant Partner Portal Login
// ===================================================
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

if (is_logged_in() && has_role(['restaurant', 'admin'])) {
    header("Location: dashboard.php");
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND role IN ('restaurant', 'admin')");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        init_session();
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['user_name'] = $user['name'];
        header("Location: dashboard.php");
        exit;
    } else {
        $error = 'Invalid restaurant partner credentials.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restaurant Partner Login | Zaykaa</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/responsive.css">
</head>
<body style="background: #F8F5EC; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; padding: 20px;">
    <div style="background: #fff; border-radius: 20px; padding: 32px 24px; width: 100%; max-width: 440px; box-shadow: var(--shadow-md); border: 1px solid var(--light-border);">
        <div style="text-align: center; margin-bottom: 24px;">
            <span style="font-size: 38px;">🍳</span>
            <h2 style="font-family: 'Playfair Display', serif; color: #063F2B; margin: 8px 0 4px;">Partner Portal</h2>
            <p style="color: #666; font-size: 13px; margin: 0;">Manage your kitchen orders, dishes, and payouts</p>
        </div>

        <?php if ($error): ?>
            <div style="background: #FDE8E8; color: #9B1C1C; padding: 10px; border-radius: 8px; font-size: 13px; margin-bottom: 16px; font-weight: 600; text-align: center;">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div style="margin-bottom: 14px;">
                <label style="font-size: 13px; font-weight: 600; color: #444; display: block; margin-bottom: 4px;">Partner Email</label>
                <input type="email" name="email" id="partnerEmail" value="restaurant@zayka.com" required 
                       style="width: 100%; padding: 10px 14px; border: 1px solid var(--light-border); border-radius: 8px; font-size: 14px;">
            </div>

            <div style="margin-bottom: 20px;">
                <label style="font-size: 13px; font-weight: 600; color: #444; display: block; margin-bottom: 4px;">Password</label>
                <input type="password" name="password" id="partnerPass" value="Partner@123" required 
                       style="width: 100%; padding: 10px 14px; border: 1px solid var(--light-border); border-radius: 8px; font-size: 14px;">
            </div>

            <button type="submit" style="width: 100%; padding: 12px; background: #063F2B; color: #fff; border-radius: 20px; font-weight: 700; border: none; cursor: pointer; font-size: 15px;">
                Access Dashboard →
            </button>
        </form>

        <div style="margin-top: 20px; text-align: center; font-size: 13px;">
            <a href="../index.php" style="color: #666; text-decoration: none;">← Back to Main Website</a>
        </div>
    </div>
</body>
</html>
