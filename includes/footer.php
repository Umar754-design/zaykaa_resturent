<?php
// ===================================================
// Upgraded Footer Component - Zayka Platform
// ===================================================
?>
    <!-- Mobile Bottom Navigation Bar -->
    <div class="mobile-bottom-bar">
        <a href="index.php" class="bottom-nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'index.php') ? 'active' : ''; ?>">
            <span style="font-size: 18px;">🏠</span>
            <span>Home</span>
        </a>
        <a href="menu.php" class="bottom-nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'menu.php') ? 'active' : ''; ?>">
            <span style="font-size: 18px;">🍽️</span>
            <span>Menu</span>
        </a>
        <a href="cart.php" class="bottom-nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'cart.php') ? 'active' : ''; ?>" style="position: relative;">
            <span style="font-size: 18px;">🛒</span>
            <span class="cart-count-badge" style="display: none; position: absolute; top: -4px; right: 8px; background: #DC2626; color: #fff; font-size: 10px; width: 16px; height: 16px; border-radius: 50%; display: flex; align-items: center; justify-content: center;">0</span>
            <span>Cart</span>
        </a>
        <a href="orders.php" class="bottom-nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'orders.php') ? 'active' : ''; ?>">
            <span style="font-size: 18px;">📦</span>
            <span>Orders</span>
        </a>
        <a href="<?php echo is_logged_in() ? 'profile.php' : 'login.php'; ?>" class="bottom-nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'profile.php') ? 'active' : ''; ?>">
            <span style="font-size: 18px;">👤</span>
            <span>Profile</span>
        </a>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <div class="footer-icon">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/>
                            <circle cx="12" cy="10" r="3"/>
                        </svg>
                    </div>
                    <a href="https://maps.google.com/?q=Mahuda+More,+Katras+Road,+Dhanbad,+Jharkhand+828305" target="_blank" rel="noopener noreferrer" class="footer-link" aria-label="Open restaurant location on Google Maps">
                        <span>Mahuda More, Dhanbad,</span>
                        <span>Jharkhand – 828305</span>
                    </a>
                </div>
                
                <div class="footer-section">
                    <div class="footer-icon">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>
                        </svg>
                    </div>
                    <a href="tel:+919693517942" class="footer-link" aria-label="Call Zaykaa customer care">
                        +91 96935 17942
                    </a>
                </div>
                
                <div class="footer-section">
                    <div class="footer-icon">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                            <polyline points="22,6 12,13 2,6"/>
                        </svg>
                    </div>
                    <a href="mailto:info@zaykaa.com" class="footer-link" aria-label="Email Zaykaa team">
                        info@zaykaa.com
                    </a>
                </div>
                
                <div class="footer-section footer-social-section">
                    <div class="social-icons">
                        <a href="https://facebook.com" target="_blank" rel="noopener noreferrer" class="social-icon" aria-label="Facebook">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/>
                            </svg>
                        </a>
                        <a href="https://instagram.com" target="_blank" rel="noopener noreferrer" class="social-icon" aria-label="Instagram">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="2" y="2" width="20" height="20" rx="5" ry="5"/>
                                <path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"/>
                                <line x1="17.5" y1="6.5" x2="17.51" y2="6.5"/>
                            </svg>
                        </a>
                        <a href="https://wa.me/919693517942" target="_blank" rel="noopener noreferrer" class="social-icon" aria-label="WhatsApp">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-10.826 9.318c-2.59 0-5.028-.749-6.885-2.036l-4.925 1.429 1.317-4.795c-1.435-1.937-2.286-4.336-2.286-6.913 0-6.577 5.346-11.923 11.923-11.923s11.923 5.346 11.923 11.923-5.346 11.923-11.923 11.923"/>
                            </svg>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Partner Links Bar -->
            <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid rgba(255,255,255,0.1); display: flex; flex-wrap: wrap; justify-content: center; gap: 24px; font-size: 13px; color: rgba(255,255,255,0.8);">
                <a href="restaurant/login.php" style="color: var(--light-gold);">🍳 Partner with us (Restaurant Login)</a>
                <a href="delivery/login.php" style="color: var(--light-gold);">🛵 Ride with us (Delivery Partner / Register)</a>
                <a href="admin/login.php" style="color: var(--light-gold);">⚡ Admin Control Center</a>
            </div>
        </div>
    </footer>

    <!-- Copyright Bar -->
    <div class="copyright">
        <div class="container">
            <p>© <?php echo date('Y'); ?> Zaykaa Café & Restaurant Platform. All Rights Reserved.</p>
        </div>
    </div>

    <!-- Scripts -->
    <script src="assets/js/script.js?v=mobile-fix-2"></script>
    <script src="assets/js/zayka-app.js?v=mobile-fix-2"></script>
</body>
</html>
