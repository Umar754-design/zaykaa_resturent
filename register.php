<?php
// ===================================================
// User Registration - Zayka Platform
// Multi-Role: Customer, Restaurant Partner, Delivery Partner
// With Mandatory Face Selfie Upload / Camera Capture for Riders
// ===================================================
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

$error = '';
$selectedRole = $_POST['role'] ?? ($_GET['role'] ?? 'customer');
if (!in_array($selectedRole, ['customer', 'restaurant', 'delivery'])) {
    $selectedRole = 'customer';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $role = $_POST['role'] ?? 'customer';
    $referralInput = trim($_POST['referral_code'] ?? '');

    if (!in_array($role, ['customer', 'restaurant', 'delivery'])) {
        $role = 'customer';
    }
    $selectedRole = $role;

    if ($name && $email && $phone && $password) {
        // Check uniqueness
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? OR phone = ?");
        $stmt->execute([$email, $phone]);
        if ($stmt->fetch()) {
            $error = 'Email or phone number is already registered.';
        } else {
            $selfiePath = null;

            // Extra validation for Delivery Partner
            if ($role === 'delivery') {
                $vehNumber = strtoupper(trim($_POST['vehicle_number'] ?? ''));
                $dl = strtoupper(trim($_POST['driving_license'] ?? ''));
                $vehicleType = trim($_POST['vehicle_type'] ?? 'Motorcycle');
                $selfieBase64 = $_POST['selfie_base64'] ?? '';

                if (empty($vehNumber) || empty($dl)) {
                    $error = 'Vehicle registration number and driving license are mandatory for delivery partners.';
                } else {
                    $uploadDir = __DIR__ . '/uploads/riders';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0777, true);
                    }

                    // 1. Live camera capture base64
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

                    // 2. Upload file fallback
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
                        $error = 'Rider Selfie photo is mandatory for Delivery Partner KYC verification. Please capture or upload a clear photo of your face.';
                    }
                }
            }

            if (empty($error)) {
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $newReferralCode = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $name), 0, 4) . rand(100, 999));
                $initialBalance = 100.00; // ₹100 signup credit bonus!
                $userStatus = ($role === 'delivery') ? 'pending' : 'active';
                $avatar = $selfiePath ?: 'assets/images/default-avatar.png';

                $pdo->beginTransaction();
                try {
                    $ins = $pdo->prepare("INSERT INTO users (name, email, phone, password, role, avatar, wallet_balance, referral_code, status) 
                                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $ins->execute([$name, $email, $phone, $hash, $role, $avatar, $initialBalance, $newReferralCode, $userStatus]);
                    $newUserId = $pdo->lastInsertId();

                    $pdo->prepare("INSERT INTO wallet_transactions (user_id, type, amount, description) VALUES (?, 'credit', ?, ?)")
                        ->execute([$newUserId, $initialBalance, 'Welcome bonus credits']);

                    // If role is delivery, create partner record with mandatory KYC selfie
                    if ($role === 'delivery') {
                        $vehNumber = strtoupper(trim($_POST['vehicle_number'] ?? ''));
                        $dl = strtoupper(trim($_POST['driving_license'] ?? ''));
                        $vehicleType = trim($_POST['vehicle_type'] ?? 'Motorcycle');

                        $pdo->prepare("INSERT INTO delivery_partners (user_id, vehicle_type, vehicle_number, driving_license, selfie_image, is_online, kyc_status) 
                                       VALUES (?, ?, ?, ?, ?, 0, 'pending')")
                            ->execute([$newUserId, $vehicleType, $vehNumber, $dl, $selfiePath]);
                    }

                    // If role is restaurant, create default restaurant profile
                    if ($role === 'restaurant') {
                        $restName = trim($_POST['restaurant_name'] ?? ($name . "'s Kitchen"));
                        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $restName), '-'));
                        $pdo->prepare("INSERT INTO restaurants (owner_id, name, slug, phone, email, address, is_approved) VALUES (?, ?, ?, ?, ?, 'Address Pending', 1)")
                            ->execute([$newUserId, $restName, $slug . '-' . rand(10,99), $phone, $email]);
                    }

                    $pdo->commit();

                    // Role-based post registration behavior
                    if ($role === 'delivery') {
                        // Rider accounts require Admin KYC approval before shift login
                        set_flash('success', "🎉 Rider Account Registered Successfully! Your KYC selfie photo has been submitted for Admin approval. Once approved, you can login with your live shift selfie at the Delivery Portal.");
                        header("Location: delivery/login.php");
                        exit;
                    } else {
                        // Auto login for customer and restaurant
                        $_SESSION['user_id'] = $newUserId;
                        $_SESSION['user_role'] = $role;
                        $_SESSION['user_name'] = $name;

                        set_flash('success', "Welcome to Zaykaa, {$name}! You received ₹100 welcome bonus credits.");

                        if ($role === 'restaurant') header("Location: restaurant/dashboard.php");
                        else header("Location: index.php");
                        exit;
                    }

                } catch (Exception $e) {
                    $pdo->rollBack();
                    $error = 'Registration failed: ' . $e->getMessage();
                }
            }
        }
    } else {
        $error = 'Please fill in all required fields.';
    }
}

include 'includes/header.php';
?>

<style>
/* Rider Selfie Component Styles inside Register Page */
.rider-selfie-box {
    background: #FAF8F2;
    border: 2px dashed var(--gold);
    border-radius: 14px;
    padding: 18px;
    text-align: center;
    margin-top: 10px;
    margin-bottom: 16px;
    position: relative;
}
.rider-selfie-box.has-photo {
    border-style: solid;
    border-color: #059669;
    background: #ECFDF5;
}
#regCameraBox {
    width: 100%;
    max-width: 260px;
    aspect-ratio: 4/3;
    border-radius: 12px;
    background: #000;
    margin: 0 auto 12px;
    display: none;
    overflow: hidden;
    position: relative;
}
#regCameraVideo {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transform: scaleX(-1);
}
#regPhotoPreview {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    object-fit: cover;
    border: 3px solid #059669;
    margin: 0 auto 10px;
    display: none;
    box-shadow: 0 4px 12px rgba(5, 150, 105, 0.35);
}
.selfie-action-btn {
    padding: 7px 14px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    border: 1px solid var(--light-border);
    background: #FFFFFF;
    color: #333;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: all 0.2s ease;
}
.selfie-action-btn:hover {
    background: #F1EDE2;
}
.selfie-action-btn.primary {
    background: var(--primary-green);
    color: #FFFFFF;
    border-color: var(--primary-green);
}
</style>

<div style="background: #FAF8F2; min-height: 85vh; padding: 50px 0;">
    <div class="container" style="max-width: 540px;">
        <div style="background: #FFFFFF; border-radius: 20px; border: 1px solid var(--light-border); padding: 36px; box-shadow: var(--shadow-sm);">
            
            <div style="text-align: center; margin-bottom: 24px;">
                <span style="font-size: 38px;">🎉</span>
                <h1 style="font-family: 'Playfair Display', serif; font-size: 28px; color: var(--primary-green); margin: 6px 0 4px;">
                    Create an Account
                </h1>
                <p style="color: #666; font-size: 14px; margin: 0;">Join Zaykaa as a customer, restaurant, or delivery rider</p>
            </div>

            <?php if ($error): ?>
                <div style="padding: 12px; border-radius: 8px; background: #FDE8E8; color: #9B1C1C; font-size: 13px; font-weight: 600; margin-bottom: 20px; text-align: center; line-height: 1.4;">
                    ⚠️ <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data" id="mainRegisterForm">
                <div style="margin-bottom: 14px;">
                    <label style="font-size: 13px; font-weight: 600; color: #444; display: block; margin-bottom: 4px;">I want to join as</label>
                    <select name="role" id="roleSelector" onchange="toggleRoleFields(this.value)" style="width: 100%; padding: 10px; border: 1px solid var(--light-border); border-radius: 8px; font-size: 14px; background: #fff; font-weight: 600; color: var(--primary-green);">
                        <option value="customer" <?php echo ($selectedRole === 'customer') ? 'selected' : ''; ?>>Customer (Order Food)</option>
                        <option value="restaurant" <?php echo ($selectedRole === 'restaurant') ? 'selected' : ''; ?>>Restaurant Partner (Sell Food)</option>
                        <option value="delivery" <?php echo ($selectedRole === 'delivery') ? 'selected' : ''; ?>>🛵 Delivery Partner (Ride & Earn)</option>
                    </select>
                </div>

                <div style="margin-bottom: 14px;">
                    <label style="font-size: 13px; font-weight: 600; color: #444; display: block; margin-bottom: 4px;">Full Name *</label>
                    <input type="text" name="name" value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>" required placeholder="e.g. Rahul Sharma" 
                           style="width: 100%; padding: 10px; border: 1px solid var(--light-border); border-radius: 8px; font-size: 14px;">
                </div>

                <div style="margin-bottom: 14px;">
                    <label style="font-size: 13px; font-weight: 600; color: #444; display: block; margin-bottom: 4px;">Email Address *</label>
                    <input type="email" name="email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required placeholder="you@example.com" 
                           style="width: 100%; padding: 10px; border: 1px solid var(--light-border); border-radius: 8px; font-size: 14px;">
                </div>

                <div style="margin-bottom: 14px;">
                    <label style="font-size: 13px; font-weight: 600; color: #444; display: block; margin-bottom: 4px;">Phone Number *</label>
                    <input type="tel" name="phone" value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>" required placeholder="+91 98765 43210" 
                           style="width: 100%; padding: 10px; border: 1px solid var(--light-border); border-radius: 8px; font-size: 14px;">
                </div>

                <div style="margin-bottom: 18px;">
                    <label style="font-size: 13px; font-weight: 600; color: #444; display: block; margin-bottom: 4px;">Password *</label>
                    <input type="password" name="password" required placeholder="Create a strong password (min 6 characters)" 
                           style="width: 100%; padding: 10px; border: 1px solid var(--light-border); border-radius: 8px; font-size: 14px;">
                </div>

                <!-- Extra Restaurant Fields -->
                <div id="restFields" style="display: <?php echo ($selectedRole === 'restaurant') ? 'block' : 'none'; ?>; margin-bottom: 14px;">
                    <label style="font-size: 13px; font-weight: 600; color: #444; display: block; margin-bottom: 4px;">Restaurant Name *</label>
                    <input type="text" name="restaurant_name" value="<?php echo htmlspecialchars($_POST['restaurant_name'] ?? ''); ?>" placeholder="e.g. Biryani Junction" 
                           style="width: 100%; padding: 10px; border: 1px solid var(--light-border); border-radius: 8px; font-size: 14px;">
                </div>

                <!-- Extra Rider Fields & Mandatory Selfie -->
                <div id="riderFields" style="display: <?php echo ($selectedRole === 'delivery') ? 'block' : 'none'; ?>; margin-bottom: 16px; border-top: 1px dashed var(--light-border); padding-top: 14px;">
                    <div style="font-size: 13px; font-weight: 700; color: var(--gold); text-transform: uppercase; margin-bottom: 10px;">
                        🛵 Delivery Partner Vehicle & KYC Details
                    </div>

                    <div style="margin-bottom: 12px;">
                        <label style="font-size: 13px; font-weight: 600; color: #444; display: block; margin-bottom: 4px;">Vehicle Type *</label>
                        <select name="vehicle_type" style="width: 100%; padding: 10px; border: 1px solid var(--light-border); border-radius: 8px; font-size: 14px;">
                            <option value="Motorcycle">🏍️ Motorcycle</option>
                            <option value="Scooter">🛵 Scooter</option>
                            <option value="Electric Bike">⚡ Electric Bike (EV)</option>
                            <option value="Bicycle">🚲 Bicycle</option>
                        </select>
                    </div>

                    <div style="margin-bottom: 12px;">
                        <label style="font-size: 13px; font-weight: 600; color: #444; display: block; margin-bottom: 4px;">Vehicle Number *</label>
                        <input type="text" name="vehicle_number" value="<?php echo htmlspecialchars($_POST['vehicle_number'] ?? ''); ?>" placeholder="e.g. JH10-AA-1234" 
                               style="width: 100%; padding: 10px; border: 1px solid var(--light-border); border-radius: 8px; font-size: 14px; text-transform: uppercase;">
                    </div>

                    <div style="margin-bottom: 14px;">
                        <label style="font-size: 13px; font-weight: 600; color: #444; display: block; margin-bottom: 4px;">Driving License Number *</label>
                        <input type="text" name="driving_license" value="<?php echo htmlspecialchars($_POST['driving_license'] ?? ''); ?>" placeholder="e.g. DL-JH10-2022001122" 
                               style="width: 100%; padding: 10px; border: 1px solid var(--light-border); border-radius: 8px; font-size: 14px; text-transform: uppercase;">
                    </div>

                    <!-- Mandatory Rider Selfie Image Upload/Capture -->
                    <div style="margin-bottom: 6px;">
                        <label style="font-size: 13px; font-weight: 700; color: var(--primary-green); display: block; margin-bottom: 4px;">
                            📸 Rider Face Selfie Photo * (Mandatory KYC)
                        </label>
                    </div>

                    <div class="rider-selfie-box" id="regSelfieContainer">
                        <p style="font-size: 12px; color: #666; margin: 0 0 10px;">
                            Take a clear, front-facing selfie of your face for identity verification and Admin KYC approval.
                        </p>

                        <!-- Live Camera Stream Box -->
                        <div id="regCameraBox">
                            <video id="regCameraVideo" autoplay playsinline></video>
                        </div>

                        <!-- Photo Result Preview -->
                        <img id="regPhotoPreview" alt="KYC Selfie Preview">
                        <canvas id="regSelfieCanvas" style="display: none;"></canvas>
                        <input type="hidden" name="selfie_base64" id="regSelfieBase64">

                        <!-- Action Buttons -->
                        <div style="display: flex; justify-content: center; gap: 8px; flex-wrap: wrap;">
                            <button type="button" class="selfie-action-btn primary" id="btnStartRegCam" onclick="openRegisterCamera()">
                                📸 Live Camera Selfie
                            </button>
                            
                            <button type="button" class="selfie-action-btn" id="btnSnapRegCam" onclick="captureRegisterSnap()" style="display: none; background: #059669; color: #fff; border-color: #059669;">
                                ⚡ Snap Photo
                            </button>

                            <button type="button" class="selfie-action-btn" id="btnRetakeRegCam" onclick="retakeRegisterSnap()" style="display: none;">
                                🔄 Retake
                            </button>

                            <label class="selfie-action-btn" style="cursor: pointer;">
                                📁 Choose File
                                <input type="file" name="selfie_file" id="regSelfieFileInput" accept="image/*" capture="user" style="display: none;" onchange="handleRegisterFileSelect(this)">
                            </label>
                        </div>

                        <div id="regSelfieStatusText" style="font-size: 11px; color: #777; margin-top: 8px;">
                            No selfie captured yet. Please capture or upload your photo.
                        </div>
                    </div>
                </div>

                <button type="submit" id="submitRegBtn" style="width: 100%; padding: 14px; background: var(--primary-green); color: #fff; border-radius: 25px; font-weight: 700; font-size: 15px; border: none; cursor: pointer; box-shadow: var(--shadow-sm); transition: transform 0.15s ease;">
                    <span id="submitBtnText">Sign Up & Get ₹100 Bonus →</span>
                </button>
            </form>

            <div style="text-align: center; font-size: 13px; color: #666; margin-top: 20px;">
                Already have an account? <a href="login.php" style="color: var(--primary-green); font-weight: 700;">Sign in</a>
            </div>
            
            <div style="text-align: center; font-size: 12px; color: #888; margin-top: 8px;">
                Are you an approved rider? <a href="delivery/login.php" style="color: var(--gold); font-weight: 600;">Rider Duty Login →</a>
            </div>
        </div>
    </div>
</div>

<script>
let regCameraStream = null;

function toggleRoleFields(role) {
    document.getElementById('restFields').style.display = (role === 'restaurant') ? 'block' : 'none';
    document.getElementById('riderFields').style.display = (role === 'delivery') ? 'block' : 'none';

    const btnText = document.getElementById('submitBtnText');
    if (role === 'delivery') {
        btnText.textContent = 'Register as Delivery Partner 🛵 →';
    } else if (role === 'restaurant') {
        btnText.textContent = 'Register Restaurant Partner 🍳 →';
    } else {
        btnText.textContent = 'Sign Up & Get ₹100 Bonus →';
    }
}

// Initial trigger on page load
document.addEventListener('DOMContentLoaded', function() {
    toggleRoleFields(document.getElementById('roleSelector').value);

    // Form submit validation for Delivery Partner
    const form = document.getElementById('mainRegisterForm');
    if (form) {
        form.addEventListener('submit', function(e) {
            const role = document.getElementById('roleSelector').value;
            if (role === 'delivery') {
                const b64 = (document.getElementById('regSelfieBase64').value || '').trim();
                const fileInput = document.getElementById('regSelfieFileInput');
                const hasFile = fileInput && fileInput.files && fileInput.files.length > 0;

                if (!b64 && !hasFile) {
                    e.preventDefault();
                    alert('📸 Rider Selfie photo is mandatory for Delivery Partner registration! Please snap a live photo or choose a selfie image.');
                    const container = document.getElementById('regSelfieContainer');
                    if (container) {
                        container.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        container.style.border = '2px solid #DC2626';
                    }
                    return false;
                }
            }
        });
    }
});

// Camera handlers
async function openRegisterCamera() {
    try {
        const video = document.getElementById('regCameraVideo');
        const camBox = document.getElementById('regCameraBox');
        const btnStart = document.getElementById('btnStartRegCam');
        const btnSnap = document.getElementById('btnSnapRegCam');
        const previewImg = document.getElementById('regPhotoPreview');
        const statusText = document.getElementById('regSelfieStatusText');

        previewImg.style.display = 'none';

        regCameraStream = await navigator.mediaDevices.getUserMedia({
            video: { width: { ideal: 640 }, height: { ideal: 480 }, facingMode: 'user' }
        });

        video.srcObject = regCameraStream;
        camBox.style.display = 'block';
        btnStart.style.display = 'none';
        btnSnap.style.display = 'inline-flex';
        statusText.textContent = 'Position your face clearly inside the frame and click "Snap Photo".';
        statusText.style.color = '#B45309';
    } catch (err) {
        alert('Camera access could not be started (' + err.message + '). Please use the "Choose File" option.');
    }
}

function captureRegisterSnap() {
    const video = document.getElementById('regCameraVideo');
    const canvas = document.getElementById('regSelfieCanvas');
    const camBox = document.getElementById('regCameraBox');
    const previewImg = document.getElementById('regPhotoPreview');
    const btnSnap = document.getElementById('btnSnapRegCam');
    const btnRetake = document.getElementById('btnRetakeRegCam');
    const hiddenBase64 = document.getElementById('regSelfieBase64');
    const statusText = document.getElementById('regSelfieStatusText');
    const container = document.getElementById('regSelfieContainer');

    canvas.width = video.videoWidth || 640;
    canvas.height = video.videoHeight || 480;
    const ctx = canvas.getContext('2d');

    // Mirror image for selfie natural view
    ctx.translate(canvas.width, 0);
    ctx.scale(-1, 1);
    ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

    const dataUrl = canvas.toDataURL('image/jpeg', 0.88);
    hiddenBase64.value = dataUrl;

    previewImg.src = dataUrl;
    previewImg.style.display = 'block';

    if (regCameraStream) {
        regCameraStream.getTracks().forEach(t => t.stop());
        regCameraStream = null;
    }

    camBox.style.display = 'none';
    btnSnap.style.display = 'none';
    btnRetake.style.display = 'inline-flex';

    container.classList.add('has-photo');
    statusText.textContent = '✓ Live KYC Selfie captured successfully!';
    statusText.style.color = '#047857';
}

function retakeRegisterSnap() {
    document.getElementById('regSelfieBase64').value = '';
    document.getElementById('regSelfieFileInput').value = '';
    document.getElementById('regPhotoPreview').style.display = 'none';
    document.getElementById('btnRetakeRegCam').style.display = 'none';
    document.getElementById('regSelfieContainer').classList.remove('has-photo');
    openRegisterCamera();
}

function handleRegisterFileSelect(input) {
    if (input.files && input.files[0]) {
        const file = input.files[0];
        const reader = new FileReader();

        reader.onload = function(e) {
            const previewImg = document.getElementById('regPhotoPreview');
            const statusText = document.getElementById('regSelfieStatusText');
            const camBox = document.getElementById('regCameraBox');
            const btnStart = document.getElementById('btnStartRegCam');
            const btnSnap = document.getElementById('btnSnapRegCam');
            const btnRetake = document.getElementById('btnRetakeRegCam');
            const container = document.getElementById('regSelfieContainer');

            if (regCameraStream) {
                regCameraStream.getTracks().forEach(t => t.stop());
                regCameraStream = null;
            }

            camBox.style.display = 'none';
            btnSnap.style.display = 'none';
            btnStart.style.display = 'inline-flex';
            btnRetake.style.display = 'inline-flex';

            previewImg.src = e.target.result;
            previewImg.style.display = 'block';
            document.getElementById('regSelfieBase64').value = ''; // Handled by $_FILES

            container.classList.add('has-photo');
            statusText.textContent = '✓ Selfie photo uploaded: ' + file.name;
            statusText.style.color = '#047857';
        };

        reader.readAsDataURL(file);
    }
}
</script>

<?php include 'includes/footer.php'; ?>
