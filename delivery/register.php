<?php
// ===================================================
// Delivery Partner Registration - Create Rider Account
// With Mandatory Face Selfie Upload / Live Camera Capture
// ===================================================
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

if (is_logged_in() && has_role(['delivery', 'admin'])) {
    header("Location: dashboard.php");
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $vehicleType = trim($_POST['vehicle_type'] ?? 'Motorcycle');
    $vehicleNumber = strtoupper(trim($_POST['vehicle_number'] ?? ''));
    $drivingLicense = strtoupper(trim($_POST['driving_license'] ?? ''));
    $selfieBase64 = $_POST['selfie_base64'] ?? '';

    // Validation
    if (empty($name) || empty($email) || empty($phone) || empty($password) || empty($vehicleNumber) || empty($drivingLicense)) {
        $error = 'All fields marked with an asterisk (*) are mandatory.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } elseif ($password !== $confirmPassword) {
        $error = 'Password and confirmation password do not match.';
    } else {
        // Check uniqueness of Email and Phone
        $checkStmt = $pdo->prepare("SELECT id FROM users WHERE email = ? OR phone = ?");
        $checkStmt->execute([$email, $phone]);
        if ($checkStmt->fetch()) {
            $error = 'A user with this email or phone number is already registered.';
        } else {
            // Process Selfie Image
            $selfiePath = null;
            $uploadDir = __DIR__ . '/../uploads/riders';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            // 1. Check live camera capture base64
            if (!empty($selfieBase64) && preg_match('/^data:image\/(\w+);base64,/', $selfieBase64)) {
                $base64Clean = substr($selfieBase64, strpos($selfieBase64, ',') + 1);
                $decoded = base64_decode($base64Clean);
                if ($decoded !== false) {
                    $filename = 'kyc_selfie_' . time() . '_' . bin2hex(random_bytes(5)) . '.jpg';
                    if (file_put_contents($uploadDir . '/' . $filename, $decoded)) {
                        $selfiePath = 'uploads/riders/' . $filename;
                    }
                }
            }

            // 2. Check file upload fallback
            if (!$selfiePath && !empty($_FILES['selfie_file']['tmp_name']) && is_uploaded_file($_FILES['selfie_file']['tmp_name'])) {
                $ext = strtolower(pathinfo($_FILES['selfie_file']['name'], PATHINFO_EXTENSION));
                if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
                    $ext = 'jpg';
                }
                $filename = 'kyc_selfie_' . time() . '_' . bin2hex(random_bytes(5)) . '.' . $ext;
                if (move_uploaded_file($_FILES['selfie_file']['tmp_name'], $uploadDir . '/' . $filename)) {
                    $selfiePath = 'uploads/riders/' . $filename;
                }
            }

            if (!$selfiePath) {
                $error = 'Rider Selfie photo is mandatory for KYC verification. Please capture or upload a clear photo of your face.';
            } else {
                try {
                    $pdo->beginTransaction();

                    // Insert User record
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $uStmt = $pdo->prepare("INSERT INTO users (name, email, phone, password, role, avatar, status) VALUES (?, ?, ?, ?, 'delivery', ?, 'pending')");
                    $uStmt->execute([$name, $email, $phone, $hash, $selfiePath]);
                    $userId = $pdo->lastInsertId();

                    // Insert Delivery Partner record
                    $dpStmt = $pdo->prepare("INSERT INTO delivery_partners (user_id, vehicle_type, vehicle_number, driving_license, selfie_image, is_online, kyc_status) VALUES (?, ?, ?, ?, ?, 0, 'pending')");
                    $dpStmt->execute([$userId, $vehicleType, $vehicleNumber, $drivingLicense, $selfiePath]);

                    $pdo->commit();
                    $success = 'Your rider registration application has been submitted successfully! Your account is currently pending Admin KYC approval. Once approved, you can login with your live shift selfie and start delivering.';
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $error = 'Registration failed due to a database error: ' . $e->getMessage();
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Delivery Partner Account | Zaykaa Fleet</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/responsive.css">
    <style>
        .selfie-box-wrap {
            background: #0F172A;
            border: 2px dashed #334155;
            border-radius: 14px;
            padding: 20px;
            text-align: center;
            margin-bottom: 20px;
            position: relative;
        }
        .selfie-box-wrap.has-photo {
            border-style: solid;
            border-color: #059669;
        }
        #cameraPreviewBox {
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
        #cameraVideo {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transform: scaleX(-1); /* Mirror view for selfie */
        }
        #photoResultPreview {
            width: 140px;
            height: 140px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #059669;
            margin: 0 auto 10px;
            display: none;
            box-shadow: 0 4px 14px rgba(5, 150, 105, 0.4);
        }
        .selfie-opt-btn {
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            border: 1px solid #334155;
            background: #1E293B;
            color: #E2E8F0;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s ease;
        }
        .selfie-opt-btn:hover {
            background: #334155;
            color: #FFFFFF;
        }
        .selfie-opt-btn.primary {
            background: #059669;
            color: #FFFFFF;
            border-color: #059669;
        }
    </style>
</head>
<body style="background: #0F172A; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; padding: 30px 15px; font-family: 'Poppins', sans-serif;">
    <div style="background: #1E293B; border-radius: 20px; padding: 36px 28px; width: 100%; max-width: 580px; box-shadow: 0 10px 30px rgba(0,0,0,0.5); border: 1px solid rgba(255,255,255,0.1); color: #fff;">
        
        <div style="text-align: center; margin-bottom: 24px;">
            <span style="font-size: 42px;">🛵</span>
            <h1 style="color: #fff; margin: 8px 0 4px; font-size: 24px; font-weight: 700;">Join Zaykaa Delivery Fleet</h1>
            <p style="color: #94A3B8; font-size: 13px; margin: 0;">Register your rider profile with mandatory face selfie KYC</p>
        </div>

        <?php if ($error): ?>
            <div style="background: rgba(239, 68, 68, 0.2); color: #F87171; border: 1px solid #EF4444; padding: 12px; border-radius: 10px; font-size: 13px; margin-bottom: 20px; font-weight: 600;">
                ⚠️ <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div style="background: rgba(16, 185, 129, 0.2); color: #34D399; border: 1px solid #10B981; padding: 16px; border-radius: 12px; font-size: 14px; margin-bottom: 24px; text-align: center; line-height: 1.5;">
                <div style="font-size: 28px; margin-bottom: 8px;">🎉</div>
                <strong>Registration Submitted!</strong><br>
                <?php echo htmlspecialchars($success); ?>
                <div style="margin-top: 18px;">
                    <a href="login.php" style="display: inline-block; padding: 10px 24px; background: #059669; color: #fff; border-radius: 20px; font-weight: 700; text-decoration: none; font-size: 14px;">
                        Go to Rider Login →
                    </a>
                </div>
            </div>
        <?php else: ?>

            <form method="POST" enctype="multipart/form-data" id="riderRegisterForm">
                
                <!-- Section 1: Personal Info -->
                <div style="font-size: 12px; font-weight: 700; color: #38BDF8; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 12px; border-bottom: 1px solid #334155; padding-bottom: 6px;">
                    1. Rider Personal Details
                </div>

                <div style="margin-bottom: 14px;">
                    <label style="font-size: 13px; font-weight: 600; color: #CBD5E1; display: block; margin-bottom: 4px;">Full Name *</label>
                    <input type="text" name="name" value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>" required placeholder="e.g. Rahul Sharma"
                           style="width: 100%; padding: 10px 14px; border: 1px solid #334155; border-radius: 8px; font-size: 14px; background: #0F172A; color: #fff;">
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 14px;">
                    <div>
                        <label style="font-size: 13px; font-weight: 600; color: #CBD5E1; display: block; margin-bottom: 4px;">Mobile Number *</label>
                        <input type="tel" name="phone" value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>" required placeholder="e.g. 9876543210"
                               style="width: 100%; padding: 10px 14px; border: 1px solid #334155; border-radius: 8px; font-size: 14px; background: #0F172A; color: #fff;">
                    </div>
                    <div>
                        <label style="font-size: 13px; font-weight: 600; color: #CBD5E1; display: block; margin-bottom: 4px;">Email Address *</label>
                        <input type="email" name="email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required placeholder="e.g. rahul@example.com"
                               style="width: 100%; padding: 10px 14px; border: 1px solid #334155; border-radius: 8px; font-size: 14px; background: #0F172A; color: #fff;">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 20px;">
                    <div>
                        <label style="font-size: 13px; font-weight: 600; color: #CBD5E1; display: block; margin-bottom: 4px;">Create Password *</label>
                        <input type="password" name="password" required placeholder="Min 6 characters"
                               style="width: 100%; padding: 10px 14px; border: 1px solid #334155; border-radius: 8px; font-size: 14px; background: #0F172A; color: #fff;">
                    </div>
                    <div>
                        <label style="font-size: 13px; font-weight: 600; color: #CBD5E1; display: block; margin-bottom: 4px;">Confirm Password *</label>
                        <input type="password" name="confirm_password" required placeholder="Re-enter password"
                               style="width: 100%; padding: 10px 14px; border: 1px solid #334155; border-radius: 8px; font-size: 14px; background: #0F172A; color: #fff;">
                    </div>
                </div>

                <!-- Section 2: Vehicle & License -->
                <div style="font-size: 12px; font-weight: 700; color: #38BDF8; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 12px; border-bottom: 1px solid #334155; padding-bottom: 6px;">
                    2. Vehicle & Driving License
                </div>

                <div style="margin-bottom: 14px;">
                    <label style="font-size: 13px; font-weight: 600; color: #CBD5E1; display: block; margin-bottom: 4px;">Vehicle Type *</label>
                    <select name="vehicle_type" style="width: 100%; padding: 10px 14px; border: 1px solid #334155; border-radius: 8px; font-size: 14px; background: #0F172A; color: #fff;">
                        <option value="Motorcycle">🏍️ Motorcycle</option>
                        <option value="Scooter">🛵 Scooter</option>
                        <option value="Electric Bike">⚡ Electric Bike (EV)</option>
                        <option value="Bicycle">🚲 Bicycle</option>
                    </select>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 20px;">
                    <div>
                        <label style="font-size: 13px; font-weight: 600; color: #CBD5E1; display: block; margin-bottom: 4px;">Vehicle Reg. Number *</label>
                        <input type="text" name="vehicle_number" value="<?php echo htmlspecialchars($_POST['vehicle_number'] ?? ''); ?>" required placeholder="e.g. JH10-AB-1234"
                               style="width: 100%; padding: 10px 14px; border: 1px solid #334155; border-radius: 8px; font-size: 14px; background: #0F172A; color: #fff; text-transform: uppercase;">
                    </div>
                    <div>
                        <label style="font-size: 13px; font-weight: 600; color: #CBD5E1; display: block; margin-bottom: 4px;">Driving License (DL) *</label>
                        <input type="text" name="driving_license" value="<?php echo htmlspecialchars($_POST['driving_license'] ?? ''); ?>" required placeholder="e.g. DL-JH10-2022-098"
                               style="width: 100%; padding: 10px 14px; border: 1px solid #334155; border-radius: 8px; font-size: 14px; background: #0F172A; color: #fff; text-transform: uppercase;">
                    </div>
                </div>

                <!-- Section 3: Rider Selfie Photo (Mandatory) -->
                <div style="font-size: 12px; font-weight: 700; color: #38BDF8; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 12px; border-bottom: 1px solid #334155; padding-bottom: 6px;">
                    3. Rider Face Selfie Photo * (Mandatory KYC)
                </div>

                <div class="selfie-box-wrap" id="selfieContainer">
                    <p style="font-size: 13px; color: #94A3B8; margin: 0 0 12px;">
                        Capture or upload a clear, front-facing selfie of your face. Sunglasses, helmets, or masks are not allowed.
                    </p>

                    <!-- Live Video View -->
                    <div id="cameraPreviewBox">
                        <video id="cameraVideo" autoplay playsinline></video>
                    </div>

                    <!-- Photo Preview Thumbnail -->
                    <img id="photoResultPreview" alt="Rider Selfie Preview">

                    <canvas id="selfieCanvas" style="display: none;"></canvas>
                    <input type="hidden" name="selfie_base64" id="selfieBase64">

                    <!-- Actions -->
                    <div style="display: flex; justify-content: center; gap: 10px; flex-wrap: wrap;">
                        <button type="button" class="selfie-opt-btn primary" id="btnStartCamera" onclick="openRiderCamera()">
                            📸 Live Camera Selfie
                        </button>
                        
                        <button type="button" class="selfie-opt-btn" id="btnCaptureSnap" onclick="captureRiderSnap()" style="display: none; background: #10B981; border-color: #10B981; color: #fff;">
                            ⚡ Snap Photo
                        </button>
                        
                        <button type="button" class="selfie-opt-btn" id="btnRetakeSnap" onclick="retakeRiderSnap()" style="display: none;">
                            🔄 Retake
                        </button>

                        <label class="selfie-opt-btn" style="cursor: pointer;">
                            📁 Upload File
                            <input type="file" name="selfie_file" id="selfieFileInput" accept="image/*" capture="user" style="display: none;" onchange="handleSelfieFileSelect(this)">
                        </label>
                    </div>

                    <div id="selfieStatusText" style="font-size: 12px; color: #CBD5E1; margin-top: 10px;">
                        No selfie captured yet. Please capture or upload your photo.
                    </div>
                </div>

                <button type="submit" id="submitRegBtn" style="width: 100%; padding: 14px; background: #059669; color: #fff; border-radius: 25px; font-weight: 700; border: none; cursor: pointer; font-size: 15px; box-shadow: 0 4px 14px rgba(5, 150, 105, 0.4); transition: transform 0.15s ease;">
                    Submit Application & Register 🛵 →
                </button>
            </form>

        <?php endif; ?>

        <div style="margin-top: 24px; text-align: center; font-size: 13px; color: #94A3B8;">
            Already registered as a delivery partner? 
            <a href="login.php" style="color: #38BDF8; font-weight: 600; text-decoration: none; margin-left: 4px;">Sign In to Duty →</a>
        </div>
        <div style="margin-top: 10px; text-align: center; font-size: 12px;">
            <a href="../index.php" style="color: #64748B; text-decoration: none;">← Return to Main Website</a>
        </div>
    </div>

    <script>
    let cameraStream = null;

    async function openRiderCamera() {
        try {
            const video = document.getElementById('cameraVideo');
            const previewBox = document.getElementById('cameraPreviewBox');
            const btnStart = document.getElementById('btnStartCamera');
            const btnSnap = document.getElementById('btnCaptureSnap');
            const resultImg = document.getElementById('photoResultPreview');
            const statusText = document.getElementById('selfieStatusText');

            resultImg.style.display = 'none';

            cameraStream = await navigator.mediaDevices.getUserMedia({
                video: { width: { ideal: 640 }, height: { ideal: 480 }, facingMode: 'user' }
            });

            video.srcObject = cameraStream;
            previewBox.style.display = 'block';
            btnStart.style.display = 'none';
            btnSnap.style.display = 'inline-flex';
            statusText.textContent = 'Position your face inside the frame and click "Snap Photo".';
            statusText.style.color = '#FBBF24';
        } catch (err) {
            alert('Camera access could not be started (' + err.message + '). Please use the "Upload File" button instead.');
        }
    }

    function captureRiderSnap() {
        const video = document.getElementById('cameraVideo');
        const canvas = document.getElementById('selfieCanvas');
        const previewBox = document.getElementById('cameraPreviewBox');
        const resultImg = document.getElementById('photoResultPreview');
        const btnSnap = document.getElementById('btnCaptureSnap');
        const btnRetake = document.getElementById('btnRetakeSnap');
        const hiddenBase64 = document.getElementById('selfieBase64');
        const statusText = document.getElementById('selfieStatusText');
        const container = document.getElementById('selfieContainer');

        canvas.width = video.videoWidth || 640;
        canvas.height = video.videoHeight || 480;
        const ctx = canvas.getContext('2d');

        // Mirror horizontal canvas draw
        ctx.translate(canvas.width, 0);
        ctx.scale(-1, 1);
        ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

        const dataUrl = canvas.toDataURL('image/jpeg', 0.88);
        hiddenBase64.value = dataUrl;

        resultImg.src = dataUrl;
        resultImg.style.display = 'block';

        // Stop stream
        if (cameraStream) {
            cameraStream.getTracks().forEach(t => t.stop());
            cameraStream = null;
        }

        previewBox.style.display = 'none';
        btnSnap.style.display = 'none';
        btnRetake.style.display = 'inline-flex';

        container.classList.add('has-photo');
        statusText.textContent = '✓ Live selfie captured successfully!';
        statusText.style.color = '#34D399';
    }

    function retakeRiderSnap() {
        document.getElementById('selfieBase64').value = '';
        document.getElementById('selfieFileInput').value = '';
        document.getElementById('photoResultPreview').style.display = 'none';
        document.getElementById('btnRetakeSnap').style.display = 'none';
        document.getElementById('selfieContainer').classList.remove('has-photo');
        openRiderCamera();
    }

    function handleSelfieFileSelect(input) {
        if (input.files && input.files[0]) {
            const file = input.files[0];
            const reader = new FileReader();

            reader.onload = function(e) {
                const resultImg = document.getElementById('photoResultPreview');
                const statusText = document.getElementById('selfieStatusText');
                const btnStart = document.getElementById('btnStartCamera');
                const btnSnap = document.getElementById('btnCaptureSnap');
                const previewBox = document.getElementById('cameraPreviewBox');
                const btnRetake = document.getElementById('btnRetakeSnap');
                const container = document.getElementById('selfieContainer');

                if (cameraStream) {
                    cameraStream.getTracks().forEach(t => t.stop());
                    cameraStream = null;
                }

                previewBox.style.display = 'none';
                btnSnap.style.display = 'none';
                btnStart.style.display = 'inline-flex';
                btnRetake.style.display = 'inline-flex';

                resultImg.src = e.target.result;
                resultImg.style.display = 'block';
                document.getElementById('selfieBase64').value = ''; // File input handles it

                container.classList.add('has-photo');
                statusText.textContent = '✓ Selfie file uploaded: ' + file.name;
                statusText.style.color = '#34D399';
            };

            reader.readAsDataURL(file);
        }
    }
    </script>
</body>
</html>
