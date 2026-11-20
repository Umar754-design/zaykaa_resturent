<?php
// ===================================================
// Homepage - Zaykaa Café & Restaurant Platform
// App-style home: service tabs, search + veg toggle,
// offer cards, quick rails, categories and all dishes.
// ===================================================
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

$pageStyles = ['assets/css/home.css?v=3'];
include 'includes/header.php';

$restaurant = get_main_restaurant();
$deliveryMin = (int) ($restaurant['delivery_time_min'] ?? 25);
$deliveryMax = (int) ($restaurant['delivery_time_max'] ?? 35);
$isOpen = ($restaurant['status'] ?? 'open') === 'open';

$categories = $pdo->query("SELECT * FROM categories WHERE is_active = 1 ORDER BY sort_order ASC")->fetchAll();
$categoryImages = [];
foreach ($categories as $cat) {
    $categoryImages[$cat['slug']] = $cat['image'];
}

$items = $pdo->query("SELECT m.*, c.name AS cat_name, c.slug AS cat_slug
                      FROM menu_items m
                      LEFT JOIN categories c ON m.category_id = c.id
                      WHERE m.is_available = 1
                      ORDER BY m.is_bestseller DESC, m.id ASC")->fetchAll();

$coupons = $pdo->query("SELECT * FROM coupons WHERE is_active = 1 AND valid_until >= CURDATE() ORDER BY discount_value DESC LIMIT 6")->fetchAll();
$banners = $pdo->query("SELECT * FROM banners WHERE is_active = 1 ORDER BY sort_order ASC LIMIT 4")->fetchAll();

// Rails
$topRated = array_slice(array_values(array_filter($items, fn($i) => $i['is_bestseller'])), 0, 10);
if (!$topRated) {
    $topRated = array_slice($items, 0, 10);
}
$quickItems = array_values(array_filter($items, fn($i) => (int) $i['prep_time_minutes'] <= 15));
usort($quickItems, fn($a, $b) => (int) $a['prep_time_minutes'] <=> (int) $b['prep_time_minutes']);

$firstName = $currentUser ? explode(' ', trim($currentUser['name']))[0] : '';
$hour = (int) date('G');
$greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');

function home_cat_image($slug, $map, $fallback = 'assets/images/categories/biryani.png') {
    return $map[$slug] ?? $fallback;
}

function home_offer_label($item) {
    $price = (float) $item['price'];
    $deal = (float) ($item['discount_price'] ?? 0);
    if ($deal > 0 && $deal < $price) {
        return ['big' => '₹' . (int) round($price - $deal) . ' OFF', 'small' => 'NOW AT ₹' . (int) round($deal)];
    }
    return ['big' => 'AT ₹' . (int) round($price), 'small' => 'FRESHLY MADE'];
}

function home_rail_card($item) {
    $offer = home_offer_label($item);
    ?>
    <article class="rail-card" data-veg="<?php echo (int) $item['is_veg']; ?>">
        <a href="dish.php?id=<?php echo (int) $item['id']; ?>" class="rail-card-media">
            <img src="<?php echo e($item['image']); ?>" alt="<?php echo e($item['name']); ?>" loading="lazy">
            <span class="rail-card-tag">
                <span class="<?php echo $item['is_veg'] ? 'veg-icon' : 'non-veg-icon'; ?>"></span>
                <?php echo $item['is_bestseller'] ? 'Bestseller' : e($item['cat_name'] ?? 'Special'); ?>
            </span>
            <span class="rail-card-offer">
                <strong><?php echo e($offer['big']); ?></strong>
                <small><?php echo e($offer['small']); ?></small>
            </span>
        </a>
        <button type="button" class="rail-card-add" onclick="addToCart(<?php echo (int) $item['id']; ?>)" aria-label="Add <?php echo e($item['name']); ?> to cart">+</button>
        <div class="rail-card-body">
            <a href="dish.php?id=<?php echo (int) $item['id']; ?>" class="rail-card-name"><?php echo e($item['name']); ?></a>
            <span class="rail-card-meta"><?php echo (int) $item['prep_time_minutes']; ?> mins · <?php echo e($item['cat_name'] ?? ''); ?></span>
        </div>
    </article>
    <?php
}
?>

<main class="home" id="homeApp">

    <!-- ============ HERO BAND ============ -->
    <section class="home-hero">
        <div class="home-wrap home-hero-grid">

            <div class="home-hero-main">
                <p class="home-greeting">
                    <?php echo e($greeting); ?><?php echo $firstName ? ', ' . e($firstName) : ''; ?> 👋
                </p>
                <h1 class="home-headline">What would you like to <span>eat today?</span></h1>

                <!-- Search + Veg toggle -->
                <div class="home-search-row">
                    <form action="search.php" method="GET" class="home-search" id="homeSearchForm" role="search">
                        <svg class="home-search-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                        <label class="home-search-field">
                            <input type="text" name="query" id="homeSearchInput" autocomplete="off" aria-label="Search dishes">
                            <span class="home-search-placeholder" aria-hidden="true">
                                Search for
                                <span class="home-search-words">
                                    <span>'Biryani'</span><span>'Kebabs'</span><span>'Mojito'</span><span>'Paneer'</span><span>'Shakes'</span>
                                </span>
                            </span>
                        </label>
                        <button type="button" class="home-mic" id="homeMicBtn" aria-label="Search by voice" hidden>
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="2" width="6" height="12" rx="3"/><path d="M5 10a7 7 0 0 0 14 0"/><path d="M12 17v5"/></svg>
                        </button>
                        <div class="search-autocomplete-dropdown" id="homeSearchDropdown" role="listbox"></div>
                    </form>
                    <button type="button" class="home-veg-toggle" id="homeVegToggle" role="switch" aria-checked="false">
                        <span class="home-veg-label">VEG</span>
                        <span class="home-veg-switch"><span></span></span>
                    </button>
                </div>

                <!-- Filter chips -->
                <nav class="home-chips" aria-label="Quick filters">
                    <button type="button" class="home-chip active" data-filter="all">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="6" cy="6" r="3"/><circle cx="18" cy="6" r="3"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="18" r="3"/></svg>
                        All
                    </button>
                    <button type="button" class="home-chip" data-filter="offers">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20.6 13.4 13.4 20.6a2 2 0 0 1-2.8 0L2 12V2h10l8.6 8.6a2 2 0 0 1 0 2.8Z"/><circle cx="7" cy="7" r="1.5" fill="currentColor"/></svg>
                        Offers
                    </button>
                    <button type="button" class="home-chip" data-filter="bestseller">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="m12 2 3.1 6.3 6.9 1-5 4.9 1.2 6.8-6.2-3.2L5.8 21 7 14.2 2 9.3l6.9-1Z"/></svg>
                        Bestsellers
                    </button>
                    <button type="button" class="home-chip" data-filter="quick">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M13 2 3 14h9l-1 8 10-12h-9z"/></svg>
                        Quick Bites
                    </button>
                </nav>
            </div>

            <!-- Service tabs -->
            <nav class="home-tabs" aria-label="Order options">
                <a href="#allDishes" class="home-tab active">
                    <span class="home-tab-img"><img src="<?php echo e(home_cat_image('biryani', $categoryImages)); ?>" alt=""></span>
                    <span class="home-tab-badge"><?php echo $deliveryMin; ?>–<?php echo $deliveryMax; ?> mins</span>
                    <span class="home-tab-label">Delivery</span>
                </a>
                <a href="search.php?pure_veg=1" class="home-tab">
                    <span class="home-tab-img"><img src="<?php echo e(home_cat_image('rotis', $categoryImages)); ?>" alt=""></span>
                    <span class="home-tab-label">Pure Veg</span>
                </a>
                <a href="menu.php?category=mojitos" class="home-tab">
                    <span class="home-tab-img"><img src="<?php echo e(home_cat_image('mojitos', $categoryImages)); ?>" alt=""></span>
                    <span class="home-tab-label">Drinks</span>
                </a>
                <a href="menu.php?category=milkshakes" class="home-tab">
                    <span class="home-tab-img"><img src="<?php echo e(home_cat_image('milkshakes', $categoryImages)); ?>" alt=""></span>
                    <span class="home-tab-label">Desserts</span>
                </a>
            </nav>
        </div>

        <!-- Offer showcase -->
        <div class="home-wrap">
            <div class="home-showcase-title">
                <span class="home-showcase-kicker">Zaykaa Specials</span>
                <h2>Good Food <em>Good Mood</em></h2>
                <span class="home-status <?php echo $isOpen ? 'is-open' : 'is-closed'; ?>">
                    <span></span><?php echo $isOpen ? 'Open now · Delivering in ' . $deliveryMin . '–' . $deliveryMax . ' mins' : 'Currently closed'; ?>
                </span>
            </div>

            <div class="home-arches" id="offers">
                <?php foreach ($coupons as $idx => $cp):
                    $value = $cp['discount_type'] === 'percentage'
                        ? (int) $cp['discount_value'] . '% OFF'
                        : '₹' . (int) $cp['discount_value'] . ' OFF';
                    $imgs = array_values($categoryImages);
                    $img = $imgs[$idx % max(1, count($imgs))] ?? 'assets/images/categories/biryani.png';
                ?>
                    <button type="button" class="home-arch" onclick="copyCouponCode(<?php echo e(js_value($cp['code'])); ?>)">
                        <span class="home-arch-top">Get</span>
                        <span class="home-arch-value"><?php echo e($value); ?></span>
                        <span class="home-arch-code">Code <?php echo e($cp['code']); ?></span>
                        <span class="home-arch-bowl"><img src="<?php echo e($img); ?>" alt="" loading="lazy"></span>
                    </button>
                <?php endforeach; ?>
                <?php foreach ($banners as $bn): ?>
                    <a href="<?php echo e(safe_redirect_target($bn['link'] ?: 'menu.php', 'menu.php')); ?>" class="home-arch home-arch-alt">
                        <span class="home-arch-top"><?php echo e($bn['title']); ?></span>
                        <span class="home-arch-sub"><?php echo e($bn['subtitle']); ?></span>
                        <span class="home-arch-bowl"><img src="<?php echo e($bn['image']); ?>" alt="" loading="lazy"></span>
                    </a>
                <?php endforeach; ?>
            </div>

            <div class="home-specials-row">
                <span class="home-specials-label">Our specialities</span>
                <div class="home-specials-list">
                    <?php foreach ($categories as $cat): ?>
                        <a href="menu.php?category=<?php echo urlencode($cat['slug']); ?>" class="home-special" title="<?php echo e($cat['name']); ?>">
                            <img src="<?php echo e($cat['image']); ?>" alt="<?php echo e($cat['name']); ?>" loading="lazy">
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </section>

    <!-- ============ RAILS ============ -->
    <section class="home-section">
        <div class="home-wrap">
            <div class="home-segment" role="tablist">
                <button type="button" class="home-segment-btn active" role="tab" aria-selected="true" data-rail="top">Top Rated</button>
                <button type="button" class="home-segment-btn" role="tab" aria-selected="false" data-rail="quick">Ready in 15 Mins</button>
            </div>

            <div class="home-rail active" data-rail-panel="top">
                <?php foreach ($topRated as $item) { home_rail_card($item); } ?>
            </div>
            <div class="home-rail" data-rail-panel="quick">
                <?php if ($quickItems): foreach ($quickItems as $item) { home_rail_card($item); } else: ?>
                    <p class="home-empty">No quick bites right now — check back soon!</p>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- ============ CATEGORIES ============ -->
    <section class="home-section">
        <div class="home-wrap">
            <div class="home-section-head">
                <h2>What's on your mind?</h2>
                <a href="menu.php">See all</a>
            </div>
            <div class="home-cats">
                <?php foreach ($categories as $cat): ?>
                    <a href="menu.php?category=<?php echo urlencode($cat['slug']); ?>" class="home-cat">
                        <span class="home-cat-img"><img src="<?php echo e($cat['image']); ?>" alt="" loading="lazy"></span>
                        <span class="home-cat-name"><?php echo e($cat['name']); ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- ============ COUPONS ============ -->
    <?php if ($coupons): ?>
    <section class="home-section">
        <div class="home-wrap">
            <div class="home-section-head">
                <h2>Offers for you</h2>
                <span class="home-section-note">Tap to copy code</span>
            </div>
            <div class="home-coupons">
                <?php foreach ($coupons as $cp): ?>
                    <button type="button" class="home-coupon" onclick="copyCouponCode(<?php echo e(js_value($cp['code'])); ?>)">
                        <span class="home-coupon-icon">%</span>
                        <span class="home-coupon-text">
                            <strong><?php echo e($cp['title']); ?></strong>
                            <small>Use <b><?php echo e($cp['code']); ?></b> · Min order <?php echo format_price($cp['min_order_amount']); ?></small>
                        </span>
                    </button>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- ============ ALL DISHES ============ -->
    <section class="home-section" id="allDishes">
        <div class="home-wrap">
            <div class="home-section-head">
                <h2><span id="dishListTitle">All dishes</span> <small id="dishCount">(<?php echo count($items); ?>)</small></h2>
                <a href="menu.php">Full menu</a>
            </div>

            <div class="home-dishes" id="dishList">
                <?php foreach ($items as $item):
                    $offer = home_offer_label($item);
                    $hasDeal = (float) $item['discount_price'] > 0 && (float) $item['discount_price'] < (float) $item['price'];
                    $pct = $hasDeal ? (int) round((1 - $item['discount_price'] / $item['price']) * 100) : 0;
                ?>
                    <article class="home-dish"
                             data-veg="<?php echo (int) $item['is_veg']; ?>"
                             data-offers="<?php echo $hasDeal ? 1 : 0; ?>"
                             data-bestseller="<?php echo (int) $item['is_bestseller']; ?>"
                             data-quick="<?php echo (int) $item['prep_time_minutes'] <= 15 ? 1 : 0; ?>">
                        <a href="dish.php?id=<?php echo (int) $item['id']; ?>" class="home-dish-media">
                            <img src="<?php echo e($item['image']); ?>" alt="<?php echo e($item['name']); ?>" loading="lazy">
                            <?php if ($hasDeal): ?>
                                <span class="home-dish-offer"><?php echo $pct; ?>% OFF</span>
                            <?php endif; ?>
                        </a>
                        <div class="home-dish-body">
                            <div class="home-dish-top">
                                <span class="<?php echo $item['is_veg'] ? 'veg-icon' : 'non-veg-icon'; ?>" title="<?php echo $item['is_veg'] ? 'Veg' : 'Non-veg'; ?>"></span>
                                <?php if ($item['is_bestseller']): ?><span class="home-dish-best">★ Bestseller</span><?php endif; ?>
                            </div>
                            <a href="dish.php?id=<?php echo (int) $item['id']; ?>" class="home-dish-name"><?php echo e($item['name']); ?></a>
                            <span class="home-dish-meta">
                                <?php echo e($item['cat_name'] ?? ''); ?> · <?php echo (int) $item['prep_time_minutes']; ?> mins
                                <?php if (!empty($item['serving_size'])): ?> · <?php echo e($item['serving_size']); ?><?php endif; ?>
                            </span>
                            <div class="home-dish-foot">
                                <span class="home-dish-price">
                                    <?php echo format_price($hasDeal ? $item['discount_price'] : $item['price']); ?>
                                    <?php if ($hasDeal): ?><s><?php echo format_price($item['price']); ?></s><?php endif; ?>
                                </span>
                                <button type="button" class="home-add" onclick="addToCart(<?php echo (int) $item['id']; ?>)">ADD</button>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
            <p class="home-empty" id="dishEmpty" hidden>No dishes match this filter. Try turning off VEG or picking another filter.</p>
        </div>
    </section>
</main>

<script>
(function () {
    const root = document.getElementById('homeApp');
    if (!root) return;

    const store = {
        get(k) { try { return localStorage.getItem(k); } catch (e) { return null; } },
        set(k, v) { try { localStorage.setItem(k, v); } catch (e) {} }
    };

    // ---- Search placeholder hide on typing ----
    const input = document.getElementById('homeSearchInput');
    const ph = root.querySelector('.home-search-placeholder');
    const syncPh = () => ph.classList.toggle('hidden', input.value.length > 0);
    input.addEventListener('input', syncPh);
    syncPh();

    // ---- Voice search (only where supported) ----
    const Rec = window.SpeechRecognition || window.webkitSpeechRecognition;
    const mic = document.getElementById('homeMicBtn');
    if (Rec && mic) {
        mic.hidden = false;
        mic.addEventListener('click', () => {
            const rec = new Rec();
            rec.lang = 'en-IN';
            mic.classList.add('listening');
            rec.onresult = (ev) => {
                input.value = ev.results[0][0].transcript;
                syncPh();
                document.getElementById('homeSearchForm').submit();
            };
            rec.onend = () => mic.classList.remove('listening');
            rec.onerror = () => { mic.classList.remove('listening'); showToast('Voice search unavailable. Please type instead.', 'warning'); };
            rec.start();
        });
    }

    // ---- Filters: veg toggle + chips ----
    const vegBtn = document.getElementById('homeVegToggle');
    const chips = root.querySelectorAll('.home-chip');
    const dishes = root.querySelectorAll('.home-dish');
    const railCards = root.querySelectorAll('.rail-card');
    const titles = { all: 'All dishes', offers: 'Dishes on offer', bestseller: 'Bestsellers', quick: 'Quick bites (≤ 15 mins)' };
    let vegOnly = store.get('zaykaVegOnly') === '1';
    let filter = 'all';

    function apply() {
        vegBtn.classList.toggle('on', vegOnly);
        vegBtn.setAttribute('aria-checked', vegOnly ? 'true' : 'false');
        let shown = 0;
        dishes.forEach(d => {
            const ok = (!vegOnly || d.dataset.veg === '1') && (filter === 'all' || d.dataset[filter] === '1');
            d.hidden = !ok;
            if (ok) shown++;
        });
        railCards.forEach(c => { c.hidden = vegOnly && c.dataset.veg !== '1'; });
        document.getElementById('dishCount').textContent = '(' + shown + ')';
        document.getElementById('dishListTitle').textContent = (vegOnly ? 'Veg · ' : '') + titles[filter];
        document.getElementById('dishEmpty').hidden = shown > 0;
    }

    vegBtn.addEventListener('click', () => {
        vegOnly = !vegOnly;
        store.set('zaykaVegOnly', vegOnly ? '1' : '0');
        apply();
        showToast(vegOnly ? 'Showing veg dishes only 🌿' : 'Showing all dishes', 'info');
    });

    chips.forEach(chip => chip.addEventListener('click', () => {
        chips.forEach(c => c.classList.toggle('active', c === chip));
        filter = chip.dataset.filter;
        apply();
        if (filter !== 'all') {
            document.getElementById('allDishes').scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }));

    // ---- Rail segment toggle ----
    const segBtns = root.querySelectorAll('.home-segment-btn');
    segBtns.forEach(btn => btn.addEventListener('click', () => {
        segBtns.forEach(b => {
            const on = b === btn;
            b.classList.toggle('active', on);
            b.setAttribute('aria-selected', on ? 'true' : 'false');
        });
        root.querySelectorAll('.home-rail').forEach(p => p.classList.toggle('active', p.dataset.railPanel === btn.dataset.rail));
    }));

    apply();
})();

function copyCouponCode(code) {
    const done = () => showToast('Coupon ' + code + ' copied! Apply it at checkout.', 'success');
    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(code).then(done, done);
    } else {
        const t = document.createElement('textarea');
        t.value = code; document.body.appendChild(t); t.select();
        try { document.execCommand('copy'); } catch (e) {}
        t.remove(); done();
    }
}
</script>

<?php include 'includes/footer.php'; ?>
