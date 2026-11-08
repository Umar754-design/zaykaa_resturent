<?php
// ===================================================
// Delivery Partner Login & Mandatory Shift Selfie Verification
// Zaykaa Fleet Management
// ===================================================
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

init_session();

// If user is already logged in with verified selfie
if (is_logged_in() && has_role(['delivery', 'admin'])) {
    if (!empty($_SESSION['rider_selfie_verified']) || current_user()['role'] === 'admin') {
        header("Location: dashboard.php");
        exit;
    }
}

// Cancel pending login if requested
if (isset($_GET['action']) && $_GET['action'] === 'cancel_login') {
    unset($_SESSION['pending_rider_login']);
    header("Location: login.php");
    exit;
}

$error = '';
$success = '';
$step = isset($_SESSION['pending_rider_login']) ? 'selfie' : 'credentials';

// ---------------------------------------------------
// STEP 1: Handle Email & Password Submission
// ---------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login_credentials') {
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND role IN ('delivery', 'admin')");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        // Fetch rider record
        $dpStmt = $pdo->prepare("SELECT * FROM delivery_partners WHERE user_id = ?");
        $dpStmt->execute([$user['id']]);
        $rider = $dpStmt->fetch();

        // If Admin user without delivery_partner row, allow direct bypass
        if (!$rider && $user['role'] === 'admin') {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['rider_selfie_verified'] = true;
            header("Location: dashboard.php");
            exit;
        }

        if (!$rider) {
            $error = 'Rider profile not found for this account. Please register first.';
        } elseif ($rider['kyc_status'] === 'pending') {
            $error = '⏳ KYC Verification Pending: Your rider account has not been approved by Admin yet. Once approved, you can login with your live shift selfie.';
        } elseif ($rider['kyc_status'] === 'rejected') {
            $error = '❌ Account Rejected: Your KYC application was rejected by the operations team. Please contact Fleet Support.';
        } elseif ($rider['kyc_status'] === 'approved') {
            // APPROVED! Now MUST require live current selfie
            $_SESSION['pending_rider_login'] = [
                'user_id'      => (int)$user['id'],
                'rider_id'     => (int)$rider['id'],
                'name'         => $user['name'],
                'email'        => $user['email'],
                'phone'        => $user['phone'],
                'selfie_image' => $rider['selfie_image'] ?? ''
            ];
            $step = 'selfie';
        }
    } else {
        $error = 'Invalid delivery partner email or password.';
    }
}

// ---------------------------------------------------
// STEP 2: Handle Mandatory Live Shift Selfie Verification
// ---------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'verify_current_selfie') {
    if (empty($_SESSION['pending_rider_login'])) {
        header("Location: login.php");
        exit;
    }

    $pending = $_SESSION['pending_rider_login'];
    $currentSelfieBase64 = $_POST['current_selfie_base64'] ?? '';
    $savedSelfiePath = null;

    $uploadDir = __DIR__ . '/../uploads/riders';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    // 1. Process base64 from live camera
    if (!empty($currentSelfieBase64) && preg_match('/^data:image\/(\w+);base64,/', $currentSelfieBase64)) {
        $base64Clean = substr($currentSelfieBase64, strpos($currentSelfieBase64, ',') + 1);
        $decoded = base64_decode($base64Clean);
        if ($decoded !== false) {
            $filename = 'current_' . $pending['rider_id'] . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.jpg';
            if (file_put_contents($uploadDir . '/' . $filename, $decoded)) {
                $savedSelfiePath = 'uploads/riders/' . $filename;
            }
        }
    }

    // 2. Process uploaded file fallback
    if (!$savedSelfiePath && !empty($_FILES['current_selfie_file']['tmp_name']) && is_uploaded_file($_FILES['current_selfie_file']['tmp_name'])) {
        $ext = strtolower(pathinfo($_FILES['current_selfie_file']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
            $ext = 'jpg';
        }
        $filename = 'current_' . $pending['rider_id'] . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        if (move_uploaded_file($_FILES['current_selfie_file']['tmp_name'], $uploadDir . '/' . $filename)) {
            $savedSelfiePath = 'uploads/riders/' . $filename;
        }
    }

    if (!$savedSelfiePath) {
        $error = 'Current live selfie is compulsory to start your shift. Please capture or upload a clear photo of your face.';
        $step = 'selfie';
    } else {
        // Update database with current shift selfie and mark online
        $updStmt = $pdo->prepare("
            UPDATE delivery_partners 
            SET current_selfie = ?, current_selfie_at = NOW(), is_online = 1 
            WHERE id = ?
        ");
        $updStmt->execute([$savedSelfiePath, $pending['rider_id']]);

        // Complete Login
        $_SESSION['user_id'] = $pending['user_id'];
        $_SESSION['user_role'] = 'delivery';
        $_SESSION['user_name'] = $pending['name'];
        $_SESSION['rider_selfie_verified'] = true;
        $_SESSION['current_selfie_path'] = $savedSelfiePath;

        unset($_SESSION['pending_rider_login']);

        set_flash('success', "Welcome back, {$pending['name']}! Live shift selfie verified. You are now ON DUTY.");
        header("Location: dashboard.php");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delivery Partner Login | Zaykaa Fleet</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/responsive.css">
    <style>
        .selfie-camera-box {
            width: 100%;
            max-width: 280px;
            aspect-ratio: 4/3;
            border-radius: 12px;
            background: #000;
            margin: 0 auto 12px;
            display: none;
            overflow: hidden;
            position: relative;
        }
        #loginCameraVideo {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transform: scaleX(-1);
        }
        #loginPhotoPreview {
            width: 140px;
            height: 140px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #059669;
            margin: 0 auto 12px;
            display: none;
            box-shadow: 0 4px 14px rgba(5, 150, 105, 0.4);
        }
        .cam-btn {
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            border: 1px solid #334155;
            background: #0F172A;
            color: #E2E8F0;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s ease;
        }
        .cam-btn:hover {
            background: #334155;
            color: #FFFFFF;
        }
        .cam-btn.primary {
            background: #059669;
            color: #FFFFFF;
            border-color: #059669;
        }
    </style>
</head>
<body style="background: #0F172A; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; padding: 20px; font-family: 'Poppins', sans-serif;">
    <div style="background: #1E293B; border-radius: 20px; padding: 32px 24px; width: 100%; max-width: 460px; box-shadow: 0 10px 30px rgba(0,0,0,0.5); border: 1px solid rgba(255,255,255,0.1); color: #fff;">
        
        <div style="text-align: center; margin-bottom: 22px;">
            <span style="font-size: 40px;">🛵</span>
            <h1 style="color: #fff; margin: 6px 0 4px; font-size: 22px; font-weight: 700;">Zaykaa Delivery Fleet</h1>
            <p style="color: #94A3B8; font-size: 13px; margin: 0;">Sign in to accept delivery orders and track earnings</p>
        </div>

        <?php if ($error): ?>
            <div style="background: rgba(239, 68, 68, 0.2); color: #F87171; border: 1px solid #EF4444; padding: 12px; border-radius: 10px; font-size: 13px; margin-bottom: 18px; font-weight: 600; line-height: 1.4;">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if ($step === 'selfie' && !empty($_SESSION['pending_rider_login'])): 
            $pending = $_SESSION['pending_rider_login'];
        ?>
            <!-- ===================================================
                 STEP 2: MANDATORY LIVE CURRENT SELFIE VERIFICATION
                 =================================================== -->
            <div style="background: rgba(5, 150, 105, 0.12); border: 1px solid #059669; border-radius: 14px; padding: 16px; margin-bottom: 20px;">
                <div style="display: flex; align-items: center; gap: 8px; color: #34D399; font-weight: 700; font-size: 13px; margin-bottom: 4px;">
                    <span>✓ KYC APPROVED</span>
                    <span style="color: #64748B;">•</span>
                    <span style="color: #FBBF24;">📸 Current Selfie Compulsory</span>
                </div>
                <div style="font-size: 14px; font-weight: 600; color: #FFFFFF;">
                    Welcome, <?php echo htmlspecialchars($pending['name']); ?>!
                </div>
                <div style="font-size: 12px; color: #94A3B8; margin-top: 2px;">
                    Take your live shift selfie now to verify your attendance and go on duty.
                </div>
            </div>

            <form method="POST" enctype="multipart/form-data" id="selfieVerifyForm">
                <input type="hidden" name="action" value="verify_current_selfie">
                <input type="hidden" name="current_selfie_base64" id="currentSelfieBase64">

                <div style="background: #0F172A; border: 2px dashed #334155; border-radius: 14px; padding: 18px; text-align: center; margin-bottom: 20px;" id="loginSelfieContainer">
                    
                    <!-- Live Video Box -->
                    <div class="selfie-camera-box" id="loginCameraBox">
                        <video id="loginCameraVideo" autoplay playsinline></video>
                    </div>

                    <!-- Photo Preview Thumbnail -->
                    <img id="loginPhotoPreview" alt="Current Shift Selfie Preview">
                    <canvas id="loginSelfieCanvas" style="display: none;"></canvas>

                    <!-- Camera & Upload Actions -->
                    <div style="display: flex; justify-content: center; gap: 8px; flex-wrap: wrap;">
                        <button type="button" class="cam-btn primary" id="btnLoginStartCam" onclick="startLoginCamera()">
                            📸 Live Camera
                        </button>
                        
                        <button type="button" class="cam-btn" id="btnLoginSnap" onclick="takeLoginSnap()" style="display: none; background: #10B981; border-color: #10B981; color: #fff;">
                            ⚡ Snap Photo
                        </button>

                        <button type="button" class="cam-btn" id="btnLoginRetake" onclick="retakeLoginSnap()" style="display: none;">
                            🔄 Retake
                        </button>

                        <label class="cam-btn" style="cursor: pointer;">
                            📁 Upload Photo
                            <input type="file" name="current_selfie_file" id="loginFileInput" accept="image/*" capture="user" style="display: none;" onchange="handleLoginFileSelect(this)">
                        </label>
                    </div>

                    <div id="loginSelfieStatus" style="font-size: 12px; color: #94A3B8; margin-top: 10px;">
                        Position your face clearly without mask or helmet.
                    </div>
                </div>

                <button type="submit" id="btnVerifyDuty" style="width: 100%; padding: 13px; background: #059669; color: #fff; border-radius: 25px; font-weight: 700; border: none; cursor: pointer; font-size: 15px; box-shadow: 0 4px 14px rgba(5, 150, 105, 0.4);">
                    Verify Selfie & Start Duty 🛵 →
                </button>

                <div style="margin-top: 14px; text-align: center;">
                    <a href="login.php?action=cancel_login" style="color: #94A3B8; font-size: 12px; text-decoration: none;">
                        ✕ Cancel / Sign in with another account
                    </a>
                </div>
            </form>

            <script>
            let loginStream = null;

            async function startLoginCamera() {
                try {
                    const video = document.getElementById('loginCameraVideo');
                    const camBox = document.getElementById('loginCameraBox');
                    const btnStart = document.getElementById('btnLoginStartCam');
                    const btnSnap = document.getElementById('btnLoginSnap');
                    const previewImg = document.getElementById('loginPhotoPreview');
                    const statusText = document.getElementById('loginSelfieStatus');

                    previewImg.style.display = 'none';

                    loginStream = await navigator.mediaDevices.getUserMedia({
                        video: { width: { ideal: 640 }, height: { ideal: 480 }, facingMode: 'user' }
                    });

                    video.srcObject = loginStream;
                    camBox.style.display = 'block';
                    btnStart.style.display = 'none';
                    btnSnap.style.display = 'inline-flex';
                    statusText.textContent = 'Look straight at the camera and click "Snap Photo".';
                    statusText.style.color = '#FBBF24';
                } catch (err) {
                    alert('Camera could not be accessed (' + err.message + '). Please use the "Upload Photo" option.');
                }
            }

            function takeLoginSnap() {
                const video = document.getElementById('loginCameraVideo');
                const canvas = document.getElementById('loginSelfieCanvas');
                const camBox = document.getElementById('loginCameraBox');
                const previewImg = document.getElementById('loginPhotoPreview');
                const btnSnap = document.getElementById('btnLoginSnap');
                const btnRetake = document.getElementById('btnLoginRetake');
                const hiddenInput = document.getElementById('currentSelfieBase64');
                const statusText = document.getElementById('loginSelfieStatus');
                const container = document.getElementById('loginSelfieContainer');

                canvas.width = video.videoWidth || 640;
                canvas.height = video.videoHeight || 480;
                const ctx = canvas.getContext('2d');

                // Mirror horizontal
                ctx.translate(canvas.width, 0);
                ctx.scale(-1, 1);
                ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

                const dataUrl = canvas.toDataURL('image/jpeg', 0.88);
                hiddenInput.value = dataUrl;

                previewImg.src = dataUrl;
                previewImg.style.display = 'block';

                if (loginStream) {
                    loginStream.getTracks().forEach(t => t.stop());
                    loginStream = null;
                }

                camBox.style.display = 'none';
                btnSnap.style.display = 'none';
                btnRetake.style.display = 'inline-flex';

                container.style.borderColor = '#059669';
                statusText.textContent = '✓ Live current selfie captured! Click "Verify Selfie & Start Duty".';
                statusText.style.color = '#34D399';
            }

            function retakeLoginSnap() {
                document.getElementById('currentSelfieBase64').value = '';
                document.getElementById('loginFileInput').value = '';
                document.getElementById('loginPhotoPreview').style.display = 'none';
                document.getElementById('btnLoginRetake').style.display = 'none';
                startLoginCamera();
            }

            function handleLoginFileSelect(input) {
                if (input.files && input.files[0]) {
                    const file = input.files[0];
                    const reader = new FileReader();

                    reader.onload = function(e) {
                        const previewImg = document.getElementById('loginPhotoPreview');
                        const statusText = document.getElementById('loginSelfieStatus');
                        const camBox = document.getElementById('loginCameraBox');
                        const btnStart = document.getElementById('btnLoginStartCam');
                        const btnSnap = document.getElementById('btnLoginSnap');
                        const btnRetake = document.getElementById('btnLoginRetake');
                        const container = document.getElementById('loginSelfieContainer');

                        if (loginStream) {
                            loginStream.getTracks().forEach(t => t.stop());
                            loginStream = null;
                        }

                        camBox.style.display = 'none';
                        btnSnap.style.display = 'none';
                        btnStart.style.display = 'inline-flex';
                        btnRetake.style.display = 'inline-flex';

                        previewImg.src = e.target.result;
                        previewImg.style.display = 'block';
                        document.getElementById('currentSelfieBase64').value = '';

                        container.style.borderColor = '#059669';
                        statusText.textContent = '✓ Selfie file ready: ' + file.name;
                        statusText.style.color = '#34D399';
                    };

                    reader.readAsDataURL(file);
                }
            }
            </script>

        <?php else: ?>

            <!-- ===================================================
                 STEP 1: RIDER EMAIL & PASSWORD
                 =================================================== -->
            <form method="POST">
                <input type="hidden" name="action" value="login_credentials">

                <div style="margin-bottom: 14px;">
                    <label style="font-size: 13px; font-weight: 600; color: #CBD5E1; display: block; margin-bottom: 4px;">Rider Email Address</label>
                    <input type="email" name="email" id="riderEmail" value="<?php echo htmlspecialchars($_POST['email'] ?? 'delivery@zayka.com'); ?>" required 
                           style="width: 100%; padding: 10px 14px; border: 1px solid #334155; border-radius: 8px; font-size: 14px; background: #0F172A; color: #fff;">
                </div>

                <div style="margin-bottom: 20px;">
                    <label style="font-size: 13px; font-weight: 600; color: #CBD5E1; display: block; margin-bottom: 4px;">Password</label>
                    <input type="password" name="password" id="riderPass" value="Rider@123" required 
                           style="width: 100%; padding: 10px 14px; border: 1px solid #334155; border-radius: 8px; font-size: 14px; background: #0F172A; color: #fff;">
                </div>

                <button type="submit" style="width: 100%; padding: 13px; background: #059669; color: #fff; border-radius: 20px; font-weight: 700; border: none; cursor: pointer; font-size: 15px; box-shadow: 0 4px 14px rgba(5, 150, 105, 0.35);">
                    Proceed to Duty Verification →
                </button>
            </form>

            <!-- Registration Link -->
            <div style="margin-top: 22px; padding-top: 18px; border-top: 1px solid #334155; text-align: center; font-size: 13px;">
                <span style="color: #94A3B8;">New Delivery Partner?</span><br>
                <a href="register.php" style="display: inline-block; margin-top: 6px; padding: 8px 18px; background: #1E3E62; color: #38BDF8; border-radius: 15px; font-weight: 700; text-decoration: none;">
                    + Create an Account (Register as Rider)
                </a>
            </div>

        <?php endif; ?>

        <div style="margin-top: 20px; text-align: center; font-size: 13px;">
            <a href="../index.php" style="color: #64748B; text-decoration: none;">← Return to Main Website</a>
        </div>
    </div>
</body>
</html>
