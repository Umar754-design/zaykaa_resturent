<?php
// ===================================================
// Multi-Role Login - Zayka Platform
// ===================================================
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

$redirect = $_GET['redirect'] ?? 'index.php';

// If already logged in, redirect
if (is_logged_in()) {
    $u = current_user();
    if ($u['role'] === 'admin') header("Location: admin/dashboard.php");
    elseif ($u['role'] === 'restaurant') header("Location: restaurant/dashboard.php");
    elseif ($u['role'] === 'delivery') header("Location: delivery/dashboard.php");
    else header("Location: $redirect");
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($email && $password) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            if ($user['status'] === 'suspended') {
                $error = 'Your account has been suspended. Please contact platform support.';
            } else {
                init_session();
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_role'] = $user['role'];
                $_SESSION['user_name'] = $user['name'];

                set_flash('success', 'Welcome back, ' . htmlspecialchars($user['name']) . '!');

                if ($user['role'] === 'admin') {
                    header("Location: admin/dashboard.php");
                } elseif ($user['role'] === 'restaurant') {
                    header("Location: restaurant/dashboard.php");
                } elseif ($user['role'] === 'delivery') {
                    // Check Rider KYC status & live selfie verification
                    $dpStmt = $pdo->prepare("SELECT * FROM delivery_partners WHERE user_id = ?");
                    $dpStmt->execute([$user['id']]);
                    $rider = $dpStmt->fetch();

                    if (!$rider || $rider['kyc_status'] === 'pending') {
                        unset($_SESSION['user_id'], $_SESSION['user_role'], $_SESSION['user_name']);
                        set_flash('error', '⏳ Your Rider KYC verification is pending Admin approval. Once approved, you can login with your live shift selfie.');
                        header("Location: delivery/login.php");
                        exit;
                    } elseif ($rider['kyc_status'] === 'rejected') {
                        unset($_SESSION['user_id'], $_SESSION['user_role'], $_SESSION['user_name']);
                        set_flash('error', '❌ Your Rider KYC application was rejected by operations. Please contact Fleet Support.');
                        header("Location: delivery/login.php");
                        exit;
                    } elseif ($rider['kyc_status'] === 'approved') {
                        $_SESSION['pending_rider_login'] = [
                            'user_id'      => (int)$user['id'],
                            'rider_id'     => (int)$rider['id'],
                            'name'         => $user['name'],
                            'email'        => $user['email'],
                            'phone'        => $user['phone'],
                            'selfie_image' => $rider['selfie_image'] ?? ''
                        ];
                        unset($_SESSION['user_id'], $_SESSION['user_role'], $_SESSION['user_name']);
                        header("Location: delivery/login.php");
                        exit;
                    }
                    header("Location: delivery/dashboard.php");
                } else {
                    header("Location: $redirect");
                }
                exit;
            }
        } else {
            $error = 'Invalid email address or password.';
        }
    } else {
        $error = 'Please enter both email and password.';
    }
}

include 'includes/header.php';
?>

<div style="background: #FAF8F2; min-height: 85vh; padding: 50px 0;">
    <div class="container" style="max-width: 480px;">
        <div style="background: #FFFFFF; border-radius: 20px; border: 1px solid var(--light-border); padding: 36px; box-shadow: var(--shadow-sm);">
            
            <div style="text-align: center; margin-bottom: 24px;">
                <span style="font-size: 38px;">🍃</span>
                <h1 style="font-family: 'Playfair Display', serif; font-size: 28px; color: var(--primary-green); margin: 6px 0 4px;">
                    Welcome to Zaykaa
                </h1>
                <p style="color: #666; font-size: 14px; margin: 0;">Login to access your orders, restaurant, or portal</p>
            </div>

            <?php if ($error): ?>
                <div style="padding: 12px; border-radius: 8px; background: #FDE8E8; color: #9B1C1C; font-size: 13px; font-weight: 600; margin-bottom: 20px; text-align: center;">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <div style="margin-bottom: 16px;">
                    <label style="font-size: 13px; font-weight: 600; color: #444; display: block; margin-bottom: 6px;">Email Address</label>
                    <input type="email" name="email" id="loginEmail" required placeholder="you@example.com" 
                           style="width: 100%; padding: 12px 14px; border: 1px solid var(--light-border); border-radius: 10px; font-size: 14px; background: #FAF8F2;">
                </div>

                <div style="margin-bottom: 20px;">
                    <label style="font-size: 13px; font-weight: 600; color: #444; display: block; margin-bottom: 6px;">Password</label>
                    <input type="password" name="password" id="loginPassword" required placeholder="••••••••" 
                           style="width: 100%; padding: 12px 14px; border: 1px solid var(--light-border); border-radius: 10px; font-size: 14px; background: #FAF8F2;">
                </div>

                <button type="submit" style="width: 100%; padding: 14px; background: var(--primary-green); color: #fff; border-radius: 25px; font-weight: 700; font-size: 15px; border: none; cursor: pointer; box-shadow: var(--shadow-sm);">
                    Sign In →
                </button>
            </form>

            <!-- Quick-fill Demo Accounts -->
            <div style="margin-top: 24px; padding-top: 18px; border-top: 1px dashed var(--light-border);">
                <div style="font-size: 11px; font-weight: 700; color: #888; text-transform: uppercase; margin-bottom: 10px; text-align: center;">
                    Quick Demo Logins (Click to Autofill):
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px;">
                    <button onclick="fillDemo('customer@zayka.com', 'Customer@123')" 
                            style="padding: 7px; background: #FAF8F2; border: 1px solid var(--light-border); border-radius: 8px; font-size: 11px; cursor: pointer; font-weight: 600;">
                        👤 Customer
                    </button>
                    <button onclick="fillDemo('restaurant@zayka.com', 'Partner@123')" 
                            style="padding: 7px; background: #FAF8F2; border: 1px solid var(--light-border); border-radius: 8px; font-size: 11px; cursor: pointer; font-weight: 600;">
                        🍳 Restaurant
                    </button>
                    <button onclick="fillDemo('delivery@zayka.com', 'Rider@123')" 
                            style="padding: 7px; background: #FAF8F2; border: 1px solid var(--light-border); border-radius: 8px; font-size: 11px; cursor: pointer; font-weight: 600;">
                        🛵 Delivery Rider
                    </button>
                    <button onclick="fillDemo('admin@zayka.com', 'Admin@123')" 
                            style="padding: 7px; background: #FAF8F2; border: 1px solid var(--light-border); border-radius: 8px; font-size: 11px; cursor: pointer; font-weight: 600;">
                        ⚡ Admin
                    </button>
                </div>
            </div>

            <div style="text-align: center; font-size: 13px; color: #666; margin-top: 20px;">
                Don't have an account? <a href="register.php" style="color: var(--primary-green); font-weight: 700;">Sign up here</a>
            </div>
        </div>
    </div>
</div>

<script>
function fillDemo(email, pass) {
    document.getElementById('loginEmail').value = email;
    document.getElementById('loginPassword').value = pass;
}
</script>

<?php include 'includes/footer.php'; ?>
