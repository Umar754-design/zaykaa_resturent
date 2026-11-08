<?php
// ===================================================
// Admin Platform Control Center Login
// ===================================================
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

if (is_logged_in() && has_role('admin')) {
    header("Location: dashboard.php");
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND role = 'admin'");
    $stmt->execute([$email]);
    $admin = $stmt->fetch();

    if ($admin && password_verify($password, $admin['password'])) {
        init_session();
        $_SESSION['user_id'] = $admin['id'];
        $_SESSION['user_role'] = 'admin';
        $_SESSION['user_name'] = $admin['name'];
        header("Location: dashboard.php");
        exit;
    } else {
        $error = 'Invalid administrator credentials.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin HQ Login | Zaykaa</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/responsive.css">
</head>
<body style="background: #0B192C; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; padding: 20px; font-family: 'Poppins', sans-serif;">
    <div style="background: #1E3E62; border-radius: 20px; padding: 32px 24px; width: 100%; max-width: 420px; box-shadow: 0 10px 40px rgba(0,0,0,0.5); border: 1px solid rgba(255,255,255,0.1); color: #fff;">
        <div style="text-align: center; margin-bottom: 24px;">
            <span style="font-size: 42px;">⚡</span>
            <h2 style="color: #fff; margin: 8px 0 4px; font-size: 24px;">Platform Control Center</h2>
            <p style="color: #94A3B8; font-size: 13px; margin: 0;">Superadmin authentication checkpoint</p>
        </div>

        <?php if ($error): ?>
            <div style="background: rgba(239, 68, 68, 0.2); color: #F87171; border: 1px solid #EF4444; padding: 10px; border-radius: 8px; font-size: 13px; margin-bottom: 16px; font-weight: 600; text-align: center;">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div style="margin-bottom: 14px;">
                <label style="font-size: 13px; font-weight: 600; color: #CBD5E1; display: block; margin-bottom: 4px;">Admin Email</label>
                <input type="email" name="email" id="adminEmail" value="admin@zayka.com" required 
                       style="width: 100%; padding: 10px 14px; border: 1px solid #334155; border-radius: 8px; font-size: 14px; background: #0B192C; color: #fff;">
            </div>

            <div style="margin-bottom: 20px;">
                <label style="font-size: 13px; font-weight: 600; color: #CBD5E1; display: block; margin-bottom: 4px;">Admin Password</label>
                <input type="password" name="password" id="adminPass" value="Admin@123" required 
                       style="width: 100%; padding: 10px 14px; border: 1px solid #334155; border-radius: 8px; font-size: 14px; background: #0B192C; color: #fff;">
            </div>

            <button type="submit" style="width: 100%; padding: 12px; background: #DC2626; color: #fff; border-radius: 20px; font-weight: 700; border: none; cursor: pointer; font-size: 15px;">
                Unlock Control Center →
            </button>
        </form>

        <div style="margin-top: 20px; text-align: center; font-size: 13px;">
            <a href="../index.php" style="color: #94A3B8; text-decoration: none;">← Return to Main Website</a>
        </div>
    </div>
</body>
</html>
