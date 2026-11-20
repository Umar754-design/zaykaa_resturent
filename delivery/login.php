<?php
// ===================================================
// Delivery Partner Login & Mandatory Shift Selfie Verification
// Zaykaa Fleet Management - AI Biometric 1-to-1 Face Verification
// ===================================================
if (ob_get_level() === 0) {
    ob_start();
}

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

if (isset($_GET['action']) && $_GET['action'] === 'cancel_kyc') {
    unset($_SESSION['rider_kyc_user_id']);
    set_flash('success', 'Your KYC progress is saved. Sign in any time to finish it.');
    header("Location: login.php");
    exit;
}

$error = '';
$success = '';

// Check pending rider login state
if (isset($_SESSION['pending_rider_login'])) {
    // Ensure challenge token exists
    if (empty($_SESSION['pending_rider_login']['challenge'])) {
        $_SESSION['pending_rider_login']['challenge'] = bin2hex(random_bytes(16));
        $_SESSION['pending_rider_login']['challenge_time'] = time();
    }
    $step = 'selfie';
} else {
    $step = 'credentials';
}

// ---------------------------------------------------
// STEP 1: Handle Email & Password Submission
// ---------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login_credentials') {
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    $lockMinutes = login_lock_minutes('delivery', $email);
    $user = null;
    if (!$lockMinutes) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND role IN ('delivery', 'admin')");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
    }

    if ($lockMinutes) {
        $error = login_lock_message($lockMinutes);
    } elseif ($user && password_verify($password, $user['password'])) {
        login_clear_failures('delivery', $email);
        // Fetch rider record
        $dpStmt = $pdo->prepare("SELECT * FROM delivery_partners WHERE user_id = ?");
        $dpStmt->execute([$user['id']]);
        $rider = $dpStmt->fetch();

        // If Admin user without delivery_partner row, allow direct bypass
        if (!$rider && $user['role'] === 'admin') {
            login_session_regenerate();
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['rider_selfie_verified'] = true;
            header("Location: dashboard.php");
            exit;
        }

        if (!$rider) {
            $error = 'Rider profile not found for this account. Please register first.';
        } elseif ($rider['kyc_status'] === 'rejected' || ($rider['kyc_status'] === 'pending' && empty($rider['kyc_submitted_at']))) {
            // Documents still needed (or need fixing): continue onboarding step 2
            login_clear_failures('delivery', $email);
            login_session_regenerate();
            $_SESSION['rider_kyc_user_id'] = (int) $user['id'];
            header("Location: kyc-documents.php");
            exit;
        } elseif ($rider['kyc_status'] === 'pending') {
            $error = '⏳ KYC Verification Pending: Your documents were submitted and are being verified. Once approved, you can login with your live shift selfie.';
        } elseif ($rider['kyc_status'] === 'approved') {
            // Check if rider has a valid registered KYC photo on file
            $kycPhoto = $rider['selfie_image'] ?? '';
            $fullKycPath = __DIR__ . '/../' . $kycPhoto;

            if (empty($kycPhoto) || !file_exists($fullKycPath)) {
                $error = '⚠️ No approved KYC reference selfie found for your profile. Please contact Platform Operations to register your identity photo.';
            } else {
                // APPROVED! Generate security challenge and advance to selfie biometric verification
                $challenge = bin2hex(random_bytes(16));
                $_SESSION['pending_rider_login'] = [
                    'user_id'        => (int)$user['id'],
                    'rider_id'       => (int)$rider['id'],
                    'name'           => $user['name'],
                    'email'          => $user['email'],
                    'phone'          => $user['phone'],
                    'selfie_image'   => $kycPhoto,
                    'challenge'      => $challenge,
                    'challenge_time' => time()
                ];
                $step = 'selfie';
            }
        }
    } else {
        login_record_failure('delivery', $email);
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
    $authChallenge = trim($_POST['auth_challenge'] ?? '');
    $faceVerified = trim($_POST['face_verified'] ?? '0');
    $matchDistance = floatval($_POST['match_distance'] ?? 1.0);
    $matchScore = intval($_POST['match_score'] ?? 0);
    $currentSelfieBase64 = $_POST['current_selfie_base64'] ?? '';
    $savedSelfiePath = null;

    // 1. Enforce Server-Side Biometric Security Challenge & Match Verification
    $challengeAge = time() - (int) ($pending['challenge_time'] ?? 0);
    $selfieLockMinutes = login_lock_minutes('rider_selfie', (string) $pending['rider_id']);

    if (empty($pending['challenge']) || !hash_equals((string) $pending['challenge'], $authChallenge) || $challengeAge > RIDER_SELFIE_CHALLENGE_TTL) {
        $error = 'Biometric security session expired or invalid. Please sign in with your email and password again.';
        unset($_SESSION['pending_rider_login']);
        $step = 'credentials';
    } elseif ($selfieLockMinutes) {
        unset($_SESSION['pending_rider_login']);
        $error = 'Too many failed selfie attempts. ' . login_lock_message($selfieLockMinutes);
        $step = 'credentials';
    } elseif ($faceVerified !== '1' || $matchDistance > 0.48) {
        login_record_failure('rider_selfie', (string) $pending['rider_id']);
        $error = "⛔ Access Denied: Biometric face verification failed (Similarity: {$matchScore}%, Distance: {$matchDistance}). The live camera face does not match the registered KYC photo for {$pending['name']}. Only the exact verified person can login.";
        $step = 'selfie';
    } else {
        $uploadDir = __DIR__ . '/../uploads/riders';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        // 2. Live camera image only (file uploads are not accepted) — validated & re-encoded server-side
        $selfieCheck = rider_validate_live_selfie($currentSelfieBase64, $pending);
        if ($selfieCheck['ok']) {
            $filename = 'current_' . (int) $pending['rider_id'] . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.jpg';
            if (file_put_contents($uploadDir . '/' . $filename, $selfieCheck['jpeg'])) {
                $savedSelfiePath = 'uploads/riders/' . $filename;
            }
        } else {
            login_record_failure('rider_selfie', (string) $pending['rider_id']);
        }

        if (!$savedSelfiePath) {
            $error = $selfieCheck['error'] ?? 'Live shift selfie could not be saved. Please capture a clear photo of your face again.';
            $step = 'selfie';
        } else {
            // Update database with current verified shift selfie and mark rider online
            $updStmt = $pdo->prepare("
                UPDATE delivery_partners 
                SET current_selfie = ?, current_selfie_at = NOW(), is_online = 1 
                WHERE id = ?
            ");
            $updStmt->execute([$savedSelfiePath, $pending['rider_id']]);

            // Audit trail: every shift selfie is kept for admin review (client-side match is not trusted alone)
            rider_log_shift_selfie((int) $pending['rider_id'], $savedSelfiePath, $selfieCheck['hash'], $matchDistance, $matchScore);
            if ($matchScore < 80) {
                notify_admin('rider_selfie', "Check shift selfie: {$pending['name']}",
                    "Phone face match only {$matchScore}% — compare with KYC photo", 'delivery-partners.php#shiftSelfies');
            }
            login_clear_failures('rider_selfie', (string) $pending['rider_id']);

            // Complete Login Session
            login_session_regenerate();
            $_SESSION['user_id'] = $pending['user_id'];
            $_SESSION['user_role'] = 'delivery';
            $_SESSION['user_name'] = $pending['name'];
            $_SESSION['rider_selfie_verified'] = true;
            $_SESSION['current_selfie_path'] = $savedSelfiePath;

            unset($_SESSION['pending_rider_login']);

            set_flash('success', "✓ Welcome back, {$pending['name']}! Biometric face verification successful ({$matchScore}% Match). You are now ON DUTY.");
            header("Location: dashboard.php");
            exit;
        }
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
    <!-- Local Face Recognition AI Engine (Fallback to CDN if needed) -->
    <script src="../assets/js/face-api.min.js"></script>
    <style>
        .selfie-camera-box {
            width: 100%;
            max-width: 320px;
            aspect-ratio: 4/3;
            border-radius: 14px;
            background: #000;
            margin: 0 auto 12px;
            display: none;
            overflow: hidden;
            position: relative;
            box-shadow: 0 4px 18px rgba(0,0,0,0.6);
        }
        #loginCameraVideo {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transform: scaleX(-1);
        }
        /* Face guide reticle */
        .face-reticle {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 140px;
            height: 180px;
            border: 2px dashed rgba(56, 189, 248, 0.7);
            border-radius: 50%;
            pointer-events: none;
            box-shadow: 0 0 0 9999px rgba(0, 0, 0, 0.28);
        }
        #loginPhotoPreview {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #334155;
            margin: 0 auto 12px;
            display: none;
            box-shadow: 0 4px 14px rgba(0,0,0,0.5);
            transition: all 0.3s ease;
        }
        .cam-btn {
            padding: 9px 18px;
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
        .pulse-verifying {
            animation: pulse-ring 1.5s infinite;
        }
        @keyframes pulse-ring {
            0% { box-shadow: 0 0 0 0 rgba(56, 189, 248, 0.7); }
            70% { box-shadow: 0 0 0 14px rgba(56, 189, 248, 0); }
            100% { box-shadow: 0 0 0 0 rgba(56, 189, 248, 0); }
        }
    </style>
</head>
<body style="background: #0B132B; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; padding: 20px; font-family: 'Poppins', sans-serif;">
    <div style="background: #1C2541; border-radius: 22px; padding: 32px 24px; width: 100%; max-width: 480px; box-shadow: 0 15px 35px rgba(0,0,0,0.6); border: 1px solid rgba(255,255,255,0.1); color: #fff;">
        
        <div style="text-align: center; margin-bottom: 20px;">
            <span style="font-size: 38px;">🛵</span>
            <h1 style="color: #fff; margin: 6px 0 4px; font-size: 22px; font-weight: 700;">Zaykaa Delivery Fleet</h1>
            <p style="color: #94A3B8; font-size: 13px; margin: 0;">Biometric Shift Verification & Duty Portal</p>
        </div>

        <?php if ($error): ?>
            <div style="background: rgba(239, 68, 68, 0.18); color: #FCA5A5; border: 1px solid #EF4444; padding: 12px 16px; border-radius: 12px; font-size: 13px; margin-bottom: 18px; font-weight: 600; line-height: 1.45;">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if ($step === 'selfie' && !empty($_SESSION['pending_rider_login'])): 
            $pending = $_SESSION['pending_rider_login'];
        ?>
            <!-- ===================================================
                 STEP 2: MANDATORY LIVE BIOMETRIC FACE VERIFICATION
                 =================================================== -->
            
            <!-- Reference Rider Profile Banner -->
            <div style="background: #0F172A; border: 1px solid #334155; border-radius: 14px; padding: 14px 16px; margin-bottom: 16px; display: flex; align-items: center; gap: 14px;">
                <div style="position: relative; flex-shrink: 0;">
                    <img id="kycReferenceImg" 
                         src="../<?php echo htmlspecialchars($pending['selfie_image']); ?>" 
                         alt="KYC Photo: <?php echo htmlspecialchars($pending['name']); ?>" 
                         crossorigin="anonymous"
                         style="width: 58px; height: 58px; border-radius: 50%; object-fit: cover; border: 2px solid #059669; display: block; box-shadow: 0 2px 8px rgba(0,0,0,0.5);">
                    <span style="position: absolute; bottom: -2px; right: -2px; background: #059669; color: #fff; border-radius: 50%; width: 18px; height: 18px; font-size: 10px; display: flex; align-items: center; justify-content: center; font-weight: 700;">✓</span>
                </div>
                <div style="flex-grow: 1; min-width: 0;">
                    <div style="font-size: 11px; text-transform: uppercase; font-weight: 700; color: #34D399; letter-spacing: 0.5px;">
                        APPROVED KYC PROFILE
                    </div>
                    <div style="font-size: 14px; font-weight: 700; color: #FFFFFF; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                        <?php echo htmlspecialchars($pending['name']); ?>
                    </div>
                    <div style="font-size: 11px; color: #94A3B8;">
                        Rider #<?php echo $pending['rider_id']; ?> • Strict 1-to-1 Biometric Matching
                    </div>
                </div>
            </div>

            <!-- AI Engine Status Pill -->
            <div id="aiEngineBadge" style="display: flex; align-items: center; justify-content: space-between; background: rgba(56, 189, 248, 0.1); border: 1px solid rgba(56, 189, 248, 0.3); border-radius: 10px; padding: 8px 12px; margin-bottom: 16px; font-size: 11px; color: #38BDF8;">
                <span id="aiBadgeText">⚡ AI Biometrics: Initializing face models...</span>
                <span id="aiBadgeSpinner" style="display: inline-block;">⏳</span>
            </div>

            <form method="POST" id="selfieVerifyForm" onsubmit="return validateBeforeSubmit()">
                <input type="hidden" name="action" value="verify_current_selfie">
                <input type="hidden" name="auth_challenge" value="<?php echo htmlspecialchars($pending['challenge']); ?>">
                <input type="hidden" name="current_selfie_base64" id="currentSelfieBase64">
                <input type="hidden" name="face_verified" id="faceVerifiedInput" value="0">
                <input type="hidden" name="match_distance" id="matchDistanceInput" value="1.0">
                <input type="hidden" name="match_score" id="matchScoreInput" value="0">

                <div style="background: #0F172A; border: 2px dashed #334155; border-radius: 16px; padding: 18px; text-align: center; margin-bottom: 20px; transition: all 0.3s ease;" id="loginSelfieContainer">
                    
                    <!-- Live Camera Feed -->
                    <div class="selfie-camera-box" id="loginCameraBox">
                        <video id="loginCameraVideo" autoplay playsinline></video>
                        <div class="face-reticle"></div>
                    </div>

                    <!-- Photo Preview Thumbnail -->
                    <img id="loginPhotoPreview" alt="Current Shift Selfie Preview">
                    <canvas id="loginSelfieCanvas" style="display: none;"></canvas>

                    <!-- Camera & Upload Actions -->
                    <div style="display: flex; justify-content: center; gap: 8px; flex-wrap: wrap; margin-top: 8px;">
                        <button type="button" class="cam-btn primary" id="btnLoginStartCam" onclick="startLoginCamera()">
                            📸 Live Camera
                        </button>
                        
                        <button type="button" class="cam-btn" id="btnLoginSnap" onclick="takeLoginSnap()" style="display: none; background: #10B981; border-color: #10B981; color: #fff;">
                            ⚡ Snap Photo
                        </button>

                        <button type="button" class="cam-btn" id="btnLoginRetake" onclick="retakeLoginSnap()" style="display: none;">
                            🔄 Retake
                        </button>

                    </div>

                    <!-- Real-Time Feedback Status -->
                    <div id="loginSelfieStatus" style="font-size: 12px; color: #94A3B8; margin-top: 12px; line-height: 1.4; min-height: 34px; display: flex; align-items: center; justify-content: center;">
                        Position your face inside the circle and click "Snap Photo".
                    </div>
                </div>

                <button type="submit" id="btnVerifyDuty" disabled 
                        style="width: 100%; padding: 14px; background: #334155; color: #94A3B8; border-radius: 25px; font-weight: 700; border: none; cursor: not-allowed; font-size: 15px; transition: all 0.3s ease;">
                    Capture Selfie to Verify Identity
                </button>

                <div style="margin-top: 16px; text-align: center;">
                    <a href="login.php?action=cancel_login" style="color: #94A3B8; font-size: 12px; text-decoration: none;">
                        ✕ Cancel / Sign in with another account
                    </a>
                </div>
            </form>

            <script>
            let loginStream = null;
            let isModelLoaded = false;
            let kycDescriptor = null;
            let isAnalyzing = false;
            const MATCH_THRESHOLD = 0.48; // Standard Euclidean distance threshold for 1-to-1 face match

            // Helper to update status message
            function updateStatus(msg, color = '#94A3B8') {
                const el = document.getElementById('loginSelfieStatus');
                if (el) {
                    el.innerHTML = msg;
                    el.style.color = color;
                }
            }

            // Initialize Face Recognition AI Models on Page Load
            async function initBiometrics() {
                const badgeText = document.getElementById('aiBadgeText');
                const badgeSpinner = document.getElementById('aiBadgeSpinner');
                const badgeBox = document.getElementById('aiEngineBadge');

                try {
                    // Load face detection, landmark 68, and recognition models from local server assets
                    const MODEL_DIR = '../assets/models';
                    await faceapi.nets.ssdMobilenetv1.loadFromUri(MODEL_DIR);
                    await faceapi.nets.faceLandmark68Net.loadFromUri(MODEL_DIR);
                    await faceapi.nets.faceRecognitionNet.loadFromUri(MODEL_DIR);
                    isModelLoaded = true;

                    // Compute KYC Reference descriptor
                    badgeText.textContent = '⚡ Extracting KYC facial biometric signature...';
                    const kycImg = document.getElementById('kycReferenceImg');

                    if (!kycImg.complete || kycImg.naturalWidth === 0) {
                        await new Promise((res) => {
                            kycImg.onload = res;
                            kycImg.onerror = res;
                        });
                    }

                    // Extract descriptor from KYC photo
                    let kycDetection = await faceapi.detectSingleFace(kycImg, new faceapi.SsdMobilenetv1Options({ minConfidence: 0.35 }))
                        .withFaceLandmarks()
                        .withFaceDescriptor();

                    // Fallback to tiny face detector if needed
                    if (!kycDetection && faceapi.nets.tinyFaceDetector.isLoaded) {
                        kycDetection = await faceapi.detectSingleFace(kycImg, new faceapi.TinyFaceDetectorOptions({ inputSize: 416, scoreThreshold: 0.3 }))
                            .withFaceLandmarks()
                            .withFaceDescriptor();
                    }

                    if (!kycDetection) {
                        badgeBox.style.background = 'rgba(239, 68, 68, 0.15)';
                        badgeBox.style.borderColor = '#EF4444';
                        badgeBox.style.color = '#FCA5A5';
                        badgeText.textContent = '⚠️ Could not locate face in KYC reference photo. Contact Admin.';
                        badgeSpinner.textContent = '❌';
                        updateStatus('Registered KYC photo has no clear face. Please re-upload KYC photo.', '#EF4444');
                        return;
                    }

                    kycDescriptor = kycDetection.descriptor;
                    badgeBox.style.background = 'rgba(5, 150, 105, 0.15)';
                    badgeBox.style.borderColor = '#059669';
                    badgeBox.style.color = '#34D399';
                    badgeText.textContent = '✓ AI Biometric Engine: READY (1-to-1 Strict Match Enforced)';
                    badgeSpinner.textContent = '🟢';
                    updateStatus('AI models ready. Click "Live Camera" or upload a photo to verify your face.', '#94A3B8');
                } catch (err) {
                    console.error('FaceAPI init error:', err);
                    badgeText.textContent = '⚠️ AI Engine Warning: ' + err.message;
                    badgeSpinner.textContent = '⚠️';
                }
            }

            // Start Camera Stream
            async function startLoginCamera() {
                try {
                    const video = document.getElementById('loginCameraVideo');
                    const camBox = document.getElementById('loginCameraBox');
                    const btnStart = document.getElementById('btnLoginStartCam');
                    const btnSnap = document.getElementById('btnLoginSnap');
                    const previewImg = document.getElementById('loginPhotoPreview');

                    previewImg.style.display = 'none';

                    loginStream = await navigator.mediaDevices.getUserMedia({
                        video: { width: { ideal: 640 }, height: { ideal: 480 }, facingMode: 'user' }
                    });

                    video.srcObject = loginStream;
                    camBox.style.display = 'block';
                    btnStart.style.display = 'none';
                    btnSnap.style.display = 'inline-flex';
                    updateStatus('Look directly at camera inside the oval reticle and click "Snap Photo".', '#FBBF24');
                } catch (err) {
                    updateStatus('⚠️ Camera access is required for the shift selfie. Please allow camera permission in your browser settings and try again.', '#F87171');
                }
            }

            // Capture Snapshot and Run Biometric AI Comparison
            async function takeLoginSnap() {
                if (isAnalyzing) return;

                const video = document.getElementById('loginCameraVideo');
                const canvas = document.getElementById('loginSelfieCanvas');
                const camBox = document.getElementById('loginCameraBox');
                const previewImg = document.getElementById('loginPhotoPreview');
                const btnSnap = document.getElementById('btnLoginSnap');
                const btnRetake = document.getElementById('btnLoginRetake');
                const hiddenInput = document.getElementById('currentSelfieBase64');

                canvas.width = video.videoWidth || 640;
                canvas.height = video.videoHeight || 480;
                const ctx = canvas.getContext('2d');

                // Mirror image
                ctx.translate(canvas.width, 0);
                ctx.scale(-1, 1);
                ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

                const dataUrl = canvas.toDataURL('image/jpeg', 0.90);
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

                // Run Biometric Face Verification
                await verifyFaceBiometrics(previewImg);
            }

            // Retake snapshot
            function retakeLoginSnap() {
                resetVerification();
                document.getElementById('btnLoginRetake').style.display = 'none';
                startLoginCamera();
            }

            // Reset verification state
            function resetVerification() {
                document.getElementById('currentSelfieBase64').value = '';
                document.getElementById('faceVerifiedInput').value = '0';
                document.getElementById('matchDistanceInput').value = '1.0';
                document.getElementById('matchScoreInput').value = '0';
                document.getElementById('loginPhotoPreview').style.display = 'none';
                document.getElementById('loginPhotoPreview').style.borderColor = '#334155';

                const container = document.getElementById('loginSelfieContainer');
                container.style.borderColor = '#334155';
                container.style.background = '#0F172A';
                container.classList.remove('pulse-verifying');

                const btn = document.getElementById('btnVerifyDuty');
                btn.disabled = true;
                btn.style.background = '#334155';
                btn.style.color = '#94A3B8';
                btn.style.cursor = 'not-allowed';
                btn.style.boxShadow = 'none';
                btn.textContent = 'Capture Selfie to Verify Identity';

                updateStatus('Position your face clearly without helmet or mask.', '#94A3B8');
            }

            // Core Biometric Face Matching Engine
            async function verifyFaceBiometrics(imageElement) {
                const container = document.getElementById('loginSelfieContainer');
                const btn = document.getElementById('btnVerifyDuty');
                const previewImg = document.getElementById('loginPhotoPreview');

                if (!isModelLoaded || !kycDescriptor) {
                    updateStatus('⏳ Biometric models are still initializing. Please wait 3 seconds...', '#FBBF24');
                    btn.disabled = true;
                    btn.textContent = 'AI Models Initializing...';
                    setTimeout(() => verifyFaceBiometrics(imageElement), 2500);
                    return;
                }

                isAnalyzing = true;
                container.classList.add('pulse-verifying');
                btn.disabled = true;
                btn.style.background = '#1E3E62';
                btn.style.color = '#38BDF8';
                btn.style.cursor = 'wait';
                btn.textContent = '🔍 Analyzing Facial Features...';
                updateStatus('🔍 Analyzing face biometric landmarks and comparing with KYC...', '#38BDF8');

                try {
                    // Wait for image to render dimensions
                    if (!imageElement.complete || imageElement.naturalWidth === 0) {
                        await new Promise(r => { imageElement.onload = r; });
                    }

                    // Detect face in captured image
                    let liveDetection = await faceapi.detectSingleFace(imageElement, new faceapi.SsdMobilenetv1Options({ minConfidence: 0.40 }))
                        .withFaceLandmarks()
                        .withFaceDescriptor();

                    if (!liveDetection) {
                        // Retry with tiny detector
                        if (faceapi.nets.tinyFaceDetector.isLoaded) {
                            liveDetection = await faceapi.detectSingleFace(imageElement, new faceapi.TinyFaceDetectorOptions({ inputSize: 416, scoreThreshold: 0.3 }))
                                .withFaceLandmarks()
                                .withFaceDescriptor();
                        }
                    }

                    container.classList.remove('pulse-verifying');

                    if (!liveDetection) {
                        // NO FACE DETECTED
                        previewImg.style.borderColor = '#EF4444';
                        container.style.borderColor = '#EF4444';
                        container.style.background = 'rgba(239, 68, 68, 0.08)';
                        document.getElementById('faceVerifiedInput').value = '0';

                        btn.disabled = true;
                        btn.style.background = '#450A0A';
                        btn.style.color = '#FCA5A5';
                        btn.style.cursor = 'not-allowed';
                        btn.textContent = '❌ No Face Detected in Photo';

                        updateStatus('❌ No clear face detected! Please ensure proper lighting and face the camera directly.', '#EF4444');
                        return;
                    }

                    // Compute Euclidean distance between KYC Face and Live Face
                    const distance = faceapi.euclideanDistance(kycDescriptor, liveDetection.descriptor);
                    // Similarity percentage: 0.0 distance is 100%, 0.48 distance is ~72%, 0.70+ distance is 0%
                    const similarityPct = Math.round(Math.max(0, Math.min(100, (1.0 - (distance / 0.70)) * 100)));

                    console.log('Biometric Analysis Result -> Distance:', distance, 'Similarity:', similarityPct + '%');

                    document.getElementById('matchDistanceInput').value = distance.toFixed(4);
                    document.getElementById('matchScoreInput').value = similarityPct;

                    if (distance <= MATCH_THRESHOLD) {
                        // ==========================================
                        // EXACT PERSON MATCH CONFIRMED (POSITIVE)
                        // ==========================================
                        previewImg.style.borderColor = '#10B981';
                        container.style.borderColor = '#10B981';
                        container.style.background = 'rgba(5, 150, 105, 0.12)';
                        document.getElementById('faceVerifiedInput').value = '1';

                        btn.disabled = false;
                        btn.style.background = '#059669';
                        btn.style.color = '#FFFFFF';
                        btn.style.cursor = 'pointer';
                        btn.style.boxShadow = '0 4px 18px rgba(5, 150, 105, 0.5)';
                        btn.textContent = `✓ Face Verified (${similarityPct}% Match) - Start Duty 🛵 →`;

                        updateStatus(`✓ <strong>IDENTITY VERIFIED (${similarityPct}% Match):</strong> Exact match confirmed with registered profile for <?php echo htmlspecialchars(addslashes($pending['name'])); ?>.`, '#34D399');
                    } else {
                        // ==========================================
                        // FACE MISMATCH - FRAUD ATTEMPT / OTHER PERSON
                        // ==========================================
                        previewImg.style.borderColor = '#EF4444';
                        container.style.borderColor = '#EF4444';
                        container.style.background = 'rgba(239, 68, 68, 0.14)';
                        document.getElementById('faceVerifiedInput').value = '0';

                        btn.disabled = true;
                        btn.style.background = '#EF4444';
                        btn.style.color = '#FFFFFF';
                        btn.style.cursor = 'not-allowed';
                        btn.style.boxShadow = 'none';
                        btn.textContent = '⛔ Access Blocked: Face Mismatch';

                        updateStatus(`⛔ <strong>FACE MISMATCH (${similarityPct}% Match):</strong> The person in front of the camera does NOT match the registered rider profile (<?php echo htmlspecialchars(addslashes($pending['name'])); ?>). Only the exact registered person can login.`, '#EF4444');
                    }
                } catch (err) {
                    console.error('Verification error:', err);
                    updateStatus('Verification processing error: ' + err.message, '#EF4444');
                    btn.disabled = true;
                    btn.textContent = 'Verification Error';
                } finally {
                    isAnalyzing = false;
                }
            }

            function validateBeforeSubmit() {
                const verified = document.getElementById('faceVerifiedInput').value;
                const distance = parseFloat(document.getElementById('matchDistanceInput').value);

                if (verified !== '1' || distance > MATCH_THRESHOLD) {
                    alert('⛔ Biometric Verification Failed: Only the exact registered rider is permitted to log in.');
                    return false;
                }
                return true;
            }

            // Trigger AI loading on window load
            window.addEventListener('load', initBiometrics);
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
                           style="width: 100%; padding: 11px 14px; border: 1px solid #334155; border-radius: 10px; font-size: 14px; background: #0F172A; color: #fff;">
                </div>

                <div style="margin-bottom: 20px;">
                    <label style="font-size: 13px; font-weight: 600; color: #CBD5E1; display: block; margin-bottom: 4px;">Password</label>
                    <input type="password" name="password" id="riderPass" value="Rider@123" required 
                           style="width: 100%; padding: 11px 14px; border: 1px solid #334155; border-radius: 10px; font-size: 14px; background: #0F172A; color: #fff;">
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