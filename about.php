<?php
// ===================================================
// About Us - Zayka Platform
// ===================================================
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
include 'includes/header.php';
?>

<div style="background: #FAF8F2; min-height: 85vh; padding: 50px 0 80px;">
    <div class="container" style="max-width: 900px;">
        <div style="text-align: center; margin-bottom: 40px;">
            <span style="font-size: 42px;">🍃</span>
            <h1 style="font-family: 'Playfair Display', serif; font-size: 38px; color: var(--primary-green); margin: 10px 0 6px;">
                About Zaykaa
            </h1>
            <p style="font-size: 16px; color: var(--gold); font-weight: 600; text-transform: uppercase; letter-spacing: 2px;">
                Good Food, Good Mood
            </p>
        </div>

        <div style="background: #fff; border-radius: 20px; border: 1px solid var(--light-border); padding: 40px; box-shadow: var(--shadow-sm); line-height: 1.8; color: #444; font-size: 15px;">
            <h2 style="font-family: 'Playfair Display', serif; color: var(--primary-green); font-size: 24px; margin-top: 0;">
                Our Heritage & Culinary Story
            </h2>
            <p>
                Founded at Mahuda More, Dhanbad, Jharkhand, <strong>Zaykaa Café & Restaurant</strong> began with a single vision: to bring authentic Mughlai, Nizami Dum Biryanis, tender chargrilled tandoori specialties, and refreshing artisanal coolers to food enthusiasts in their pure, unadulterated heritage form.
            </p>
            <p>
                Today, Zayka has evolved into a premier culinary destination and express delivery kitchen bringing authentic flavours, diligent delivery partners, and delighted customers together across Jharkhand and Bihar. Every single dish prepared in our kitchens adheres to the strictest benchmarks of culinary hygiene, fresh whole spices, and time-honoured slow-cooking pots (Dum).
            </p>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin: 35px 0; text-align: center;">
                <div style="background: #FAF8F2; border-radius: 12px; padding: 20px; border: 1px solid var(--light-border);">
                    <div style="font-size: 32px; font-weight: 700; color: var(--primary-green); font-family: 'Playfair Display', serif;">10,000+</div>
                    <div style="font-size: 13px; color: #666; font-weight: 600; margin-top: 4px;">Satisfied Diners</div>
                </div>
                <div style="background: #FAF8F2; border-radius: 12px; padding: 20px; border: 1px solid var(--light-border);">
                    <div style="font-size: 32px; font-weight: 700; color: var(--gold); font-family: 'Playfair Display', serif;">4.8 ★</div>
                    <div style="font-size: 13px; color: #666; font-weight: 600; margin-top: 4px;">Average Food Rating</div>
                </div>
                <div style="background: #FAF8F2; border-radius: 12px; padding: 20px; border: 1px solid var(--light-border);">
                    <div style="font-size: 32px; font-weight: 700; color: var(--primary-green); font-family: 'Playfair Display', serif;">28 Mins</div>
                    <div style="font-size: 13px; color: #666; font-weight: 600; margin-top: 4px;">Average Delivery Time</div>
                </div>
            </div>

            <h3 style="font-family: 'Playfair Display', serif; color: var(--primary-green); font-size: 20px;">
                Our 3 Golden Promises
            </h3>
            <ul style="padding-left: 20px; display: flex; flex-direction: column; gap: 8px;">
                <li><strong>100% Fresh Daily Ingredients:</strong> Zero preservatives, fresh chicken and dairy sourced every morning.</li>
                <li><strong>Eco-Friendly Thermal Packaging:</strong> Food arrives piping hot without plastic chemical leakage.</li>
                <li><strong>Fair Platform Payouts:</strong> Ethical commission structures that empower local kitchen chefs and delivery riders.</li>
            </ul>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
