<?php
// ===================================================
// Contact Us - Zayka Platform
// ===================================================
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

$success = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $subject = trim($_POST['subject'] ?? 'Contact Form Inquiry');
    $message = trim($_POST['message'] ?? '');

    if ($name && $email && $message) {
        // If user is logged in, attach user_id, else default to admin notification
        $uId = is_logged_in() ? current_user()['id'] : 1;
        $ticketNum = 'MSG-' . date('Ymd') . '-' . rand(100, 999);
        $stmt = $pdo->prepare("INSERT INTO support_tickets (ticket_number, user_id, role, subject, message, status, priority) 
                               VALUES (?, ?, 'customer', ?, ?, 'open', 'low')");
        $stmt->execute([$ticketNum, $uId, "Contact Message from $name: $subject", "Email: $email\n\n$message"]);
        $success = true;
    }
}

include 'includes/header.php';
?>

<div style="background: #FAF8F2; min-height: 85vh; padding: 50px 0 80px;">
    <div class="container" style="max-width: 900px;">
        <div style="text-align: center; margin-bottom: 36px;">
            <h1 style="font-family: 'Playfair Display', serif; font-size: 36px; color: var(--primary-green); margin: 0 0 6px;">
                Contact Zaykaa
            </h1>
            <p style="color: #666; font-size: 14px; margin: 0;">We’d love to hear from you. Get in touch with our culinary & support team.</p>
        </div>

        <?php if ($success): ?>
            <div style="background: #DEF7EC; border: 1px solid #046C4E; border-radius: 12px; padding: 18px; color: #046C4E; font-weight: 600; text-align: center; margin-bottom: 24px;">
                ✓ Thank you for reaching out, <?php echo htmlspecialchars($_POST['name']); ?>! Your message has been received. Our team will get back to you within 24 hours.
            </div>
        <?php endif; ?>

        <div style="display: flex; gap: 30px; flex-wrap: wrap;">
            <!-- Contact Info -->
            <div style="flex: 1; min-width: 280px; background: #063F2B; color: #fff; border-radius: 18px; padding: 36px; display: flex; flex-direction: column; justify-content: space-between;">
                <div>
                    <h3 style="font-family: 'Playfair Display', serif; font-size: 24px; color: var(--gold); margin-top: 0;">
                        Zaykaa Flagship
                    </h3>
                    <p style="opacity: 0.85; font-size: 14px; line-height: 1.6;">
                        Visit our dine-in restaurant or reach our central customer care desk.
                    </p>
                    
                    <div style="margin-top: 30px; display: flex; flex-direction: column; gap: 18px; font-size: 14px;">
                        <div>
                            <span style="color: var(--gold); font-weight: bold; display: block;">📍 Address</span>
                            Mahuda More, Katras Road, Dhanbad, Jharkhand – 828305
                        </div>
                        <div>
                            <span style="color: var(--gold); font-weight: bold; display: block;">📞 Hotline</span>
                            +91 96935 17942
                        </div>
                        <div>
                            <span style="color: var(--gold); font-weight: bold; display: block;">✉️ Email</span>
                            info@zaykaa.com / support@zayka.com
                        </div>
                        <div>
                            <span style="color: var(--gold); font-weight: bold; display: block;">⏰ Operating Hours</span>
                            10:00 AM – 11:30 PM (All 7 Days)
                        </div>
                    </div>
                </div>

                <div style="margin-top: 30px; font-size: 12px; opacity: 0.7;">
                    Fast response guaranteed within 2 hours during working hours.
                </div>
            </div>

            <!-- Contact Form -->
            <div style="flex: 1.5; min-width: 300px; background: #fff; border-radius: 18px; border: 1px solid var(--light-border); padding: 36px; box-shadow: var(--shadow-sm);">
                <h3 style="font-family: 'Playfair Display', serif; font-size: 22px; color: var(--primary-green); margin-top: 0; margin-bottom: 20px;">
                    Send Us a Message
                </h3>

                <form method="POST">
                    <div style="margin-bottom: 14px;">
                        <label style="font-size: 13px; font-weight: 600; color: #444; display: block; margin-bottom: 4px;">Your Name *</label>
                        <input type="text" name="name" required placeholder="John Doe" 
                               style="width: 100%; padding: 10px; border: 1px solid var(--light-border); border-radius: 8px; font-size: 14px;">
                    </div>

                    <div style="margin-bottom: 14px;">
                        <label style="font-size: 13px; font-weight: 600; color: #444; display: block; margin-bottom: 4px;">Email Address *</label>
                        <input type="email" name="email" required placeholder="you@example.com" 
                               style="width: 100%; padding: 10px; border: 1px solid var(--light-border); border-radius: 8px; font-size: 14px;">
                    </div>

                    <div style="margin-bottom: 14px;">
                        <label style="font-size: 13px; font-weight: 600; color: #444; display: block; margin-bottom: 4px;">Subject *</label>
                        <input type="text" name="subject" required placeholder="Catering inquiry, feedback, partnership" 
                               style="width: 100%; padding: 10px; border: 1px solid var(--light-border); border-radius: 8px; font-size: 14px;">
                    </div>

                    <div style="margin-bottom: 20px;">
                        <label style="font-size: 13px; font-weight: 600; color: #444; display: block; margin-bottom: 4px;">Message *</label>
                        <textarea name="message" required placeholder="Write your message here..." 
                                  style="width: 100%; height: 110px; padding: 10px; border: 1px solid var(--light-border); border-radius: 8px; font-size: 14px;"></textarea>
                    </div>

                    <button type="submit" style="width: 100%; padding: 14px; background: var(--primary-green); color: #fff; border-radius: 25px; font-weight: 700; font-size: 15px; border: none; cursor: pointer;">
                        Send Message ✉️
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
