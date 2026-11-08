<?php
// ===================================================
// Upgraded Header Component - Zayka Platform
// ===================================================
require_once __DIR__ . '/functions.php';
$currentUser = current_user();
$currentCity = $_SESSION['user_city'] ?? 'Dhanbad';
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Zaykaa Café & Restaurant | Good Food Good Mood</title>
    <meta name="description" content="Order authentic biryani, kebabs, pure veg dishes, and artisanal beverages online from Zaykaa Café & Restaurant.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;500;600;700&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="preload" as="image" href="assets/images/hero/biryani.png" fetchpriority="high">
    <link rel="stylesheet" href="assets/css/style.css?v=mobile-fix-2">
    <link rel="stylesheet" href="assets/css/responsive.css?v=mobile-fix-2">
</head>
<body>
    <div id="toastContainer"></div>

    <!-- Header / Navbar -->
    <header class="header" id="header">
        <nav class="navbar">
            <div class="container navbar-container">
                <!-- Left: Brand Logo & Location Pill -->
                <div class="navbar-left">
                    <!-- Brand Logo -->
                    <a href="index.php" class="navbar-brand" aria-label="Zaykaa Home">
                        <div class="brand-crest">
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M12 3v3"/>
                                <path d="M5 16a7 7 0 0 1 14 0Z"/>
                                <path d="M4 19h16"/>
                                <circle cx="12" cy="11" r="1" fill="currentColor"/>
                            </svg>
                        </div>
                        <div class="brand-text-group">
                            <span class="brand-title">Zaykaa</span>
                            <span class="brand-tagline">CAFÉ & RESTAURANT</span>
                        </div>
                    </a>

                    <!-- Location Selector Pill -->
                    <button type="button" class="nav-location-pill" onclick="openModal('locationModal')" aria-label="Select delivery city">
                        <span class="location-icon-wrap">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/>
                                <circle cx="12" cy="10" r="3"/>
                            </svg>
                        </span>
                        <span class="location-text-wrap">
                            <span class="location-label">DELIVER TO</span>
                            <span class="location-city" id="headerLocationName"><?php echo htmlspecialchars($currentCity); ?></span>
                        </span>
                        <svg class="location-chevron" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="6 9 12 15 18 9"></polyline>
                        </svg>
                    </button>
                </div>

                <!-- Center: Search Input Bar (Desktop) -->
                <div class="navbar-center">
                    <form action="search.php" method="GET" class="nav-search-form" role="search" id="desktopSearchForm">
                        <span class="nav-search-icon">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="11" cy="11" r="8"></circle>
                                <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                            </svg>
                        </span>
                        <input type="text" name="query" id="desktopSearchInput" class="nav-search-input" 
                               placeholder="Search dishes, biryani, kebabs, drinks..." 
                               value="<?php echo htmlspecialchars($_GET['query'] ?? ''); ?>"
                               autocomplete="off" aria-autocomplete="list" aria-controls="desktopSearchDropdown">
                        <button type="submit" class="nav-search-submit" aria-label="Search">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="5" y1="12" x2="19" y2="12"></line>
                                <polyline points="12 5 19 12 12 19"></polyline>
                            </svg>
                        </button>
                        <div class="search-autocomplete-dropdown" id="desktopSearchDropdown" role="listbox"></div>
                    </form>
                </div>
                
                <!-- Right: Navigation Menu, Cart, User Auth, Mobile Toggle -->
                <div class="navbar-right">
                    <!-- Desktop Nav Links -->
                    <ul class="nav-links-list">
                        <li><a href="index.php" class="nav-item-link <?php echo $currentPage === 'index.php' ? 'active' : ''; ?>">Home</a></li>
                        <li><a href="menu.php" class="nav-item-link <?php echo $currentPage === 'menu.php' ? 'active' : ''; ?>">Menu</a></li>
                        <li><a href="about.php" class="nav-item-link <?php echo $currentPage === 'about.php' ? 'active' : ''; ?>">About</a></li>
                        <li><a href="contact.php" class="nav-item-link <?php echo $currentPage === 'contact.php' ? 'active' : ''; ?>">Contact</a></li>
                    </ul>

                    <!-- Cart Icon Button -->
                    <a href="cart.php" class="nav-cart-btn" aria-label="View Shopping Cart">
                        <span class="cart-icon-shell">
                            <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z"></path>
                                <path d="M3 6h18"></path>
                                <path d="M16 10a4 4 0 0 1-8 0"></path>
                            </svg>
                            <span class="cart-count-badge">0</span>
                        </span>
                        <span class="cart-btn-label">Cart</span>
                    </a>

                    <!-- User Auth Button / Dropdown (Desktop) -->
                    <?php if ($currentUser): ?>
                        <div class="user-dropdown-wrap">
                            <button type="button" class="nav-user-btn" id="userMenuBtn" onclick="toggleUserDropdown(event)" aria-haspopup="true" aria-expanded="false">
                                <span class="user-avatar-circle">
                                    <?php echo strtoupper(substr($currentUser['name'], 0, 1)); ?>
                                </span>
                                <span class="user-name-label"><?php echo htmlspecialchars(explode(' ', $currentUser['name'])[0]); ?></span>
                                <svg class="user-chevron-icon" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                    <polyline points="6 9 12 15 18 9"></polyline>
                                </svg>
                            </button>
                            <div id="userMenuDropdown" class="user-dropdown-card" role="menu">
                                <div class="user-dropdown-header">
                                    <div class="user-header-avatar"><?php echo strtoupper(substr($currentUser['name'], 0, 1)); ?></div>
                                    <div class="user-header-details">
                                        <div class="user-header-name"><?php echo htmlspecialchars($currentUser['name']); ?></div>
                                        <div class="user-header-role-badge role-<?php echo htmlspecialchars($currentUser['role']); ?>">
                                            <?php echo htmlspecialchars(ucfirst($currentUser['role'])); ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="user-dropdown-body">
                                    <?php if ($currentUser['role'] === 'customer'): ?>
                                        <a href="orders.php" class="dropdown-item">
                                            <svg class="item-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m7.5 4.27 9 5.15"/><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5"/><path d="M12 22V12"/></svg>
                                            <span>My Orders</span>
                                        </a>
                                        <a href="wallet.php" class="dropdown-item">
                                            <svg class="item-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="20" height="14" x="2" y="5" rx="2"/><line x1="2" x2="22" y1="10" y2="10"/></svg>
                                            <span style="flex:1;">My Wallet</span>
                                            <span class="wallet-badge">₹<?php echo number_format($currentUser['wallet_balance'], 2); ?></span>
                                        </a>
                                        <a href="support.php" class="dropdown-item">
                                            <svg class="item-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                                            <span>Help & Support</span>
                                        </a>
                                        <a href="profile.php" class="dropdown-item">
                                            <svg class="item-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                                            <span>Profile & Addresses</span>
                                        </a>
                                    <?php elseif ($currentUser['role'] === 'restaurant'): ?>
                                        <a href="restaurant/dashboard.php" class="dropdown-item">
                                            <svg class="item-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="18" height="18" x="3" y="3" rx="2"/><path d="M3 9h18"/><path d="M9 21V9"/></svg>
                                            <span>Kitchen Dashboard</span>
                                        </a>
                                        <a href="restaurant/orders.php" class="dropdown-item">
                                            <svg class="item-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 2v4"/><path d="M8 2v4"/><rect width="18" height="18" x="3" y="4" rx="2"/></svg>
                                            <span>Live Orders</span>
                                        </a>
                                    <?php elseif ($currentUser['role'] === 'delivery'): ?>
                                        <a href="delivery/dashboard.php" class="dropdown-item">
                                            <svg class="item-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="18.5" cy="17.5" r="3.5"/><circle cx="5.5" cy="17.5" r="3.5"/><circle cx="15" cy="5" r="1"/><path d="M12 17.5V14l-3-3 4-3 2 3h2"/></svg>
                                            <span>Delivery Dashboard</span>
                                        </a>
                                        <a href="delivery/earnings.php" class="dropdown-item">
                                            <svg class="item-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" x2="12" y1="2" y2="22"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                                            <span>Earnings</span>
                                        </a>
                                    <?php elseif ($currentUser['role'] === 'admin'): ?>
                                        <a href="admin/dashboard.php" class="dropdown-item">
                                            <svg class="item-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
                                            <span>Admin Control Center</span>
                                        </a>
                                    <?php endif; ?>
                                </div>
                                <div class="user-dropdown-footer">
                                    <a href="logout.php" class="dropdown-item item-logout">
                                        <svg class="item-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" x2="9" y1="12" y2="12"/></svg>
                                        <span>Log Out</span>
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="nav-auth-buttons">
                            <a href="login.php" class="nav-btn-signin">Sign In</a>
                            <a href="register.php" class="nav-btn-signup">Sign Up</a>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Mobile Hamburger Toggle -->
                    <button type="button" class="mobile-toggle" id="mobileToggle" aria-label="Toggle Navigation Menu" aria-controls="navMenu" aria-expanded="false">
                        <span></span>
                        <span></span>
                        <span></span>
                    </button>
                </div>
            </div>

            <!-- Mobile Full-Width Search Row (Visible on screens <= 768px) -->
            <div class="header-mobile-search-row">
                <form action="search.php" method="GET" class="mobile-search-form" role="search" id="mobileSearchForm">
                    <span class="mobile-search-icon">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                    </span>
                    <input type="text" name="query" id="mobileSearchInput" class="mobile-search-input" 
                           placeholder="Search dishes, biryani, kebabs, drinks..." 
                           value="<?php echo htmlspecialchars($_GET['query'] ?? ''); ?>"
                           autocomplete="off" aria-autocomplete="list" aria-controls="mobileSearchDropdown">
                    <div class="search-autocomplete-dropdown" id="mobileSearchDropdown" role="listbox"></div>
                </form>
            </div>
        </nav>
    </header>

    <!-- Mobile Drawer Menu (Outside header to prevent stacking context clipping) -->
    <aside class="nav-menu" id="navMenu" aria-label="Mobile navigation" aria-hidden="true">
        <div class="drawer-header">
            <div class="drawer-brand">
                <span class="brand-crest mini">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 3v3"/><path d="M5 16a7 7 0 0 1 14 0Z"/><path d="M4 19h16"/></svg>
                </span>
                <span class="brand-title">Zaykaa</span>
            </div>
            <button type="button" class="drawer-close-btn" id="drawerCloseBtn" onclick="closeMobileMenu()" aria-label="Close menu">✕</button>
        </div>

        <div class="drawer-location-card" onclick="openModal('locationModal')">
            <div class="drawer-loc-info">
                <span class="drawer-loc-label">DELIVERY CITY</span>
                <strong class="drawer-loc-city"><?php echo htmlspecialchars($currentCity); ?></strong>
            </div>
            <span class="drawer-loc-change">Change ▼</span>
        </div>

        <ul class="drawer-links-list">
            <li><a href="index.php" class="drawer-link <?php echo $currentPage === 'index.php' ? 'active' : ''; ?>">Home</a></li>
            <li><a href="menu.php" class="drawer-link <?php echo $currentPage === 'menu.php' ? 'active' : ''; ?>">Menu</a></li>
            <li><a href="about.php" class="drawer-link <?php echo $currentPage === 'about.php' ? 'active' : ''; ?>">About</a></li>
            <li><a href="contact.php" class="drawer-link <?php echo $currentPage === 'contact.php' ? 'active' : ''; ?>">Contact</a></li>
            <li><a href="cart.php" class="drawer-link <?php echo $currentPage === 'cart.php' ? 'active' : ''; ?>">Shopping Cart <span class="cart-count-badge" style="margin-left:auto;">0</span></a></li>
        </ul>

        <div class="drawer-auth-section">
            <?php if ($currentUser): ?>
                <div class="drawer-user-info">
                    <div class="drawer-user-avatar"><?php echo strtoupper(substr($currentUser['name'], 0, 1)); ?></div>
                    <div>
                        <div class="drawer-user-name"><?php echo htmlspecialchars($currentUser['name']); ?></div>
                        <div class="drawer-user-role"><?php echo htmlspecialchars(ucfirst($currentUser['role'])); ?></div>
                    </div>
                </div>
                <div class="drawer-user-links">
                    <?php if ($currentUser['role'] === 'customer'): ?>
                        <a href="orders.php" class="drawer-action-link">My Orders</a>
                        <a href="wallet.php" class="drawer-action-link">My Wallet (₹<?php echo number_format($currentUser['wallet_balance'], 2); ?>)</a>
                        <a href="profile.php" class="drawer-action-link">Profile & Addresses</a>
                    <?php elseif ($currentUser['role'] === 'restaurant'): ?>
                        <a href="restaurant/dashboard.php" class="drawer-action-link">Restaurant Dashboard</a>
                    <?php elseif ($currentUser['role'] === 'delivery'): ?>
                        <a href="delivery/dashboard.php" class="drawer-action-link">Delivery Dashboard</a>
                    <?php elseif ($currentUser['role'] === 'admin'): ?>
                        <a href="admin/dashboard.php" class="drawer-action-link">Admin HQ</a>
                    <?php endif; ?>
                    <a href="logout.php" class="drawer-action-link text-danger">Log Out</a>
                </div>
            <?php else: ?>
                <div class="drawer-guest-btns">
                    <a href="login.php" class="drawer-btn-signin">Sign In</a>
                    <a href="register.php" class="drawer-btn-signup">Sign Up</a>
                </div>
            <?php endif; ?>
        </div>
    </aside>

    <!-- Mobile Drawer Backdrop Overlay (Clear translucent scrim, zero blur) -->
    <div class="nav-backdrop" id="navBackdrop" aria-hidden="true"></div>

    <div class="header-spacer"></div> <!-- Spacer for fixed header -->

    <?php echo render_flash_message(); ?>

    <!-- Location Modal -->
    <div class="zayka-modal-backdrop" id="locationModal">
        <div class="zayka-modal">
            <div class="modal-header">
                <h3>Select Your City / Delivery Location</h3>
                <button class="modal-close" onclick="closeModal('locationModal')">✕</button>
            </div>
            <div class="modal-body">
                <button onclick="detectLocation()" style="width: 100%; display: flex; align-items: center; justify-content: center; gap: 10px; padding: 12px; background: #F8F5EC; border: 1px dashed var(--gold); border-radius: 12px; font-weight: 600; color: var(--primary-green); margin-bottom: 20px;">
                    <span>📍</span> Detect Current GPS Location
                </button>
                <div style="font-size: 13px; color: #888; font-weight: 600; text-transform: uppercase; margin-bottom: 12px;">Popular Delivery Hubs</div>
                <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px;">
                    <?php foreach (['Dhanbad', 'Ranchi', 'Bokaro', 'Jamshedpur', 'Patna', 'Asansol'] as $c): ?>
                        <button onclick="selectCity('<?php echo $c; ?>')" 
                                style="padding: 12px; text-align: left; background: <?php echo ($currentCity === $c) ? '#063F2B' : '#FFFFFF'; ?>; color: <?php echo ($currentCity === $c) ? '#FFFFFF' : '#333'; ?>; border: 1px solid var(--light-border); border-radius: 10px; font-weight: 600; cursor: pointer; transition: 0.2s;">
                            🏙️ <?php echo $c; ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Floating Sticky Cart bar -->
    <div class="floating-cart-bar" id="floatingCartBar" style="display: none;" onclick="window.location.href='cart.php'">
        <span>🛍️ View Cart</span>
        <span class="cart-summary-text">0 items</span>
        <span>→</span>
    </div>

    <script>
    function toggleUserDropdown(e) {
        if (e) e.stopPropagation();
        const dd = document.getElementById('userMenuDropdown');
        const btn = document.getElementById('userMenuBtn');
        if (dd) {
            const isActive = dd.classList.toggle('active');
            if (btn) btn.setAttribute('aria-expanded', isActive ? 'true' : 'false');
        }
    }

    // Close user dropdown if clicked outside
    document.addEventListener('click', function(e) {
        const dd = document.getElementById('userMenuDropdown');
        const btn = document.getElementById('userMenuBtn');
        if (dd && !dd.contains(e.target) && (!btn || !btn.contains(e.target))) {
            dd.classList.remove('active');
            if (btn) btn.setAttribute('aria-expanded', 'false');
        }
    });

    function openMobileMenu() {
        const mobileToggle = document.getElementById('mobileToggle');
        const navMenu = document.getElementById('navMenu');
        const navBackdrop = document.getElementById('navBackdrop');
        if (mobileToggle) {
            mobileToggle.classList.add('active');
            mobileToggle.setAttribute('aria-expanded', 'true');
        }
        if (navMenu) {
            navMenu.classList.add('active');
            navMenu.setAttribute('aria-hidden', 'false');
        }
        if (navBackdrop) {
            navBackdrop.classList.add('active');
            navBackdrop.setAttribute('aria-hidden', 'false');
        }
        document.body.classList.add('mobile-menu-active');
        document.body.style.overflow = 'hidden';
    }

    function closeMobileMenu() {
        const mobileToggle = document.getElementById('mobileToggle');
        const navMenu = document.getElementById('navMenu');
        const navBackdrop = document.getElementById('navBackdrop');
        if (mobileToggle) {
            mobileToggle.classList.remove('active');
            mobileToggle.setAttribute('aria-expanded', 'false');
        }
        if (navMenu) {
            navMenu.classList.remove('active');
            navMenu.setAttribute('aria-hidden', 'true');
        }
        if (navBackdrop) {
            navBackdrop.classList.remove('active');
            navBackdrop.setAttribute('aria-hidden', 'true');
        }
        document.body.classList.remove('mobile-menu-active');
        document.body.style.overflow = '';
    }

    function toggleMobileMenu() {
        const navMenu = document.getElementById('navMenu');
        if (navMenu && navMenu.classList.contains('active')) {
            closeMobileMenu();
        } else {
            openMobileMenu();
        }
    }

    function detectLocation() {
        if ("geolocation" in navigator) {
            navigator.geolocation.getCurrentPosition(
                function(position) {
                    showToast("GPS coordinates acquired! Setting to nearest hub (Dhanbad)", "success");
                    selectCity('Dhanbad');
                },
                function(error) {
                    showToast("GPS access denied. Please choose your city manually.", "warning");
                }
            );
        } else {
            showToast("Geolocation is not supported by your browser.", "warning");
        }
    }
    </script>
