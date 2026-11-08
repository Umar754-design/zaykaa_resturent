-- ===================================================
-- Zayka Food Delivery Platform - Complete Database
-- Multi-role: Customer, Restaurant, Delivery Partner, Admin
-- ===================================================
-- Database: u939972774_zyka_db
-- Generated: 2026-10-21
-- ===================================================

-- Note for Hostinger / cPanel phpMyAdmin:
-- Select your database in Hostinger phpMyAdmin first, then import this file.
-- The CREATE DATABASE line below is commented out to prevent "Access Denied #1044" errors on shared hosting:
-- CREATE DATABASE IF NOT EXISTS `u939972774_zyka_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- USE `u939972774_zyka_db`;

-- ===================================================
-- TABLE SCHEMAS
-- ===================================================

-- 1. Users Table (All roles)
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(150) NOT NULL UNIQUE,
    `phone` VARCHAR(20) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `role` ENUM('customer', 'restaurant', 'delivery', 'admin') DEFAULT 'customer',
    `avatar` VARCHAR(255) DEFAULT 'assets/images/default-avatar.png',
    `wallet_balance` DECIMAL(10,2) DEFAULT 0.00,
    `referral_code` VARCHAR(20) UNIQUE,
    `referred_by` INT DEFAULT NULL,
    `status` ENUM('active', 'suspended', 'pending') DEFAULT 'active',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_users_role` (`role`),
    INDEX `idx_users_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Restaurants Table
CREATE TABLE IF NOT EXISTS `restaurants` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `owner_id` INT NOT NULL,
    `name` VARCHAR(150) NOT NULL,
    `slug` VARCHAR(160) NOT NULL UNIQUE,
    `tagline` VARCHAR(255) DEFAULT 'Authentic Flavours & Quality Cuisine',
    `description` TEXT,
    `logo` VARCHAR(255) DEFAULT 'assets/images/categories/biryani.png',
    `banner` VARCHAR(255) DEFAULT 'assets/images/hero/biryani.png',
    `cuisines` VARCHAR(255) DEFAULT 'North Indian, Biryani, Mughlai',
    `address` VARCHAR(255) NOT NULL,
    `city` VARCHAR(100) DEFAULT 'Dhanbad',
    `pincode` VARCHAR(20) DEFAULT '828305',
    `latitude` DECIMAL(10,8) DEFAULT 23.795700,
    `longitude` DECIMAL(11,8) DEFAULT 86.430400,
    `phone` VARCHAR(20) NOT NULL,
    `email` VARCHAR(150) NOT NULL,
    `opening_time` TIME DEFAULT '10:00:00',
    `closing_time` TIME DEFAULT '23:00:00',
    `status` ENUM('open', 'closed', 'busy') DEFAULT 'open',
    `is_pure_veg` TINYINT(1) DEFAULT 0,
    `rating` DECIMAL(3,2) DEFAULT 4.50,
    `total_ratings` INT DEFAULT 0,
    `delivery_time_min` INT DEFAULT 25,
    `delivery_time_max` INT DEFAULT 40,
    `delivery_radius_km` DECIMAL(5,2) DEFAULT 10.00,
    `min_order_amount` DECIMAL(10,2) DEFAULT 149.00,
    `cost_for_two` DECIMAL(10,2) DEFAULT 350.00,
    `commission_rate` DECIMAL(5,2) DEFAULT 15.00,
    `is_featured` TINYINT(1) DEFAULT 0,
    `is_approved` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`owner_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_restaurants_status` (`status`),
    INDEX `idx_restaurants_approved` (`is_approved`),
    INDEX `idx_restaurants_rating` (`rating`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Categories (Platform-wide)
CREATE TABLE IF NOT EXISTS `categories` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `slug` VARCHAR(120) NOT NULL UNIQUE,
    `image` VARCHAR(255) NOT NULL,
    `description` VARCHAR(255) DEFAULT '',
    `sort_order` INT DEFAULT 0,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Restaurant Menu Categories
CREATE TABLE IF NOT EXISTS `menu_categories` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `restaurant_id` INT NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `sort_order` INT DEFAULT 0,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Menu Items
CREATE TABLE IF NOT EXISTS `menu_items` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `restaurant_id` INT NOT NULL,
    `category_id` INT DEFAULT NULL,
    `menu_category_id` INT DEFAULT NULL,
    `name` VARCHAR(150) NOT NULL,
    `description` TEXT,
    `price` DECIMAL(10,2) NOT NULL,
    `discount_price` DECIMAL(10,2) DEFAULT NULL,
    `is_veg` TINYINT(1) DEFAULT 1,
    `image` VARCHAR(255) DEFAULT 'assets/images/categories/biryani.png',
    `is_bestseller` TINYINT(1) DEFAULT 0,
    `is_available` TINYINT(1) DEFAULT 1,
    `prep_time_minutes` INT DEFAULT 20,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`menu_category_id`) REFERENCES `menu_categories`(`id`) ON DELETE SET NULL,
    INDEX `idx_menu_available` (`is_available`),
    INDEX `idx_menu_veg` (`is_veg`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Item Customization Add-ons
CREATE TABLE IF NOT EXISTS `item_addons` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `item_id` INT NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `is_available` TINYINT(1) DEFAULT 1,
    FOREIGN KEY (`item_id`) REFERENCES `menu_items`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. User Saved Addresses
CREATE TABLE IF NOT EXISTS `user_addresses` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `label` ENUM('Home', 'Work', 'Other') DEFAULT 'Home',
    `address_line` VARCHAR(255) NOT NULL,
    `landmark` VARCHAR(150) DEFAULT '',
    `city` VARCHAR(100) DEFAULT 'Dhanbad',
    `pincode` VARCHAR(20) DEFAULT '828305',
    `latitude` DECIMAL(10,8) DEFAULT 23.796000,
    `longitude` DECIMAL(11,8) DEFAULT 86.431000,
    `is_default` TINYINT(1) DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. Coupons & Promotions
CREATE TABLE IF NOT EXISTS `coupons` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `code` VARCHAR(50) NOT NULL UNIQUE,
    `title` VARCHAR(150) NOT NULL,
    `description` VARCHAR(255) DEFAULT '',
    `discount_type` ENUM('percentage', 'flat') DEFAULT 'percentage',
    `discount_value` DECIMAL(10,2) NOT NULL,
    `max_discount` DECIMAL(10,2) DEFAULT NULL,
    `min_order_amount` DECIMAL(10,2) DEFAULT 199.00,
    `restaurant_id` INT DEFAULT NULL,
    `usage_limit` INT DEFAULT 500,
    `used_count` INT DEFAULT 0,
    `valid_from` DATE NOT NULL,
    `valid_until` DATE NOT NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. Delivery Partners Table
CREATE TABLE IF NOT EXISTS `delivery_partners` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL UNIQUE,
    `vehicle_type` VARCHAR(50) DEFAULT 'Motorcycle',
    `vehicle_number` VARCHAR(50) NOT NULL,
    `driving_license` VARCHAR(100) NOT NULL,
    `is_online` TINYINT(1) DEFAULT 1,
    `current_lat` DECIMAL(10,8) DEFAULT 23.795000,
    `current_lng` DECIMAL(11,8) DEFAULT 86.429000,
    `kyc_status` ENUM('pending', 'approved', 'rejected') DEFAULT 'approved',
    `total_deliveries` INT DEFAULT 0,
    `rating` DECIMAL(3,2) DEFAULT 4.80,
    `earnings_balance` DECIMAL(10,2) DEFAULT 0.00,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. Orders Table
CREATE TABLE IF NOT EXISTS `orders` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `order_number` VARCHAR(50) NOT NULL UNIQUE,
    `user_id` INT NOT NULL,
    `restaurant_id` INT NOT NULL,
    `delivery_partner_id` INT DEFAULT NULL,
    `address_id` INT DEFAULT NULL,
    `delivery_address_json` TEXT NOT NULL,
    `subtotal` DECIMAL(10,2) NOT NULL,
    `addons_total` DECIMAL(10,2) DEFAULT 0.00,
    `tax_amount` DECIMAL(10,2) DEFAULT 0.00,
    `delivery_fee` DECIMAL(10,2) DEFAULT 40.00,
    `platform_fee` DECIMAL(10,2) DEFAULT 5.00,
    `coupon_discount` DECIMAL(10,2) DEFAULT 0.00,
    `coupon_code` VARCHAR(50) DEFAULT NULL,
    `tip_amount` DECIMAL(10,2) DEFAULT 0.00,
    `total_amount` DECIMAL(10,2) NOT NULL,
    `restaurant_earnings` DECIMAL(10,2) DEFAULT 0.00,
    `delivery_earnings` DECIMAL(10,2) DEFAULT 0.00,
    `platform_commission` DECIMAL(10,2) DEFAULT 0.00,
    `payment_method` ENUM('cod', 'online', 'wallet') DEFAULT 'cod',
    `payment_status` ENUM('pending', 'paid', 'failed', 'refunded') DEFAULT 'pending',
    `payment_transaction_id` VARCHAR(100) DEFAULT NULL,
    `order_status` ENUM('placed', 'accepted', 'preparing', 'ready_for_pickup', 'picked_up', 'out_for_delivery', 'delivered', 'cancelled') DEFAULT 'placed',
    `cancellation_reason` VARCHAR(255) DEFAULT NULL,
    `cancelled_by` ENUM('customer', 'restaurant', 'admin') DEFAULT NULL,
    `special_instructions` TEXT DEFAULT NULL,
    `estimated_delivery_time` INT DEFAULT 35,
    `delivery_otp` VARCHAR(10) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`delivery_partner_id`) REFERENCES `delivery_partners`(`id`) ON DELETE SET NULL,
    INDEX `idx_orders_status` (`order_status`),
    INDEX `idx_orders_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 11. Order Items
CREATE TABLE IF NOT EXISTS `order_items` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `order_id` INT NOT NULL,
    `item_id` INT NOT NULL,
    `item_name` VARCHAR(150) NOT NULL,
    `price` DECIMAL(10,2) NOT NULL,
    `quantity` INT NOT NULL DEFAULT 1,
    `total` DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 12. Order Item Add-ons
CREATE TABLE IF NOT EXISTS `order_item_addons` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `order_item_id` INT NOT NULL,
    `addon_name` VARCHAR(100) NOT NULL,
    `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    FOREIGN KEY (`order_item_id`) REFERENCES `order_items`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 13. Order Status Audit Log
CREATE TABLE IF NOT EXISTS `order_status_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `order_id` INT NOT NULL,
    `status` VARCHAR(50) NOT NULL,
    `notes` VARCHAR(255) DEFAULT '',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 14. Customer Reviews
CREATE TABLE IF NOT EXISTS `reviews` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `order_id` INT NOT NULL,
    `user_id` INT NOT NULL,
    `restaurant_id` INT NOT NULL,
    `delivery_partner_id` INT DEFAULT NULL,
    `restaurant_rating` INT NOT NULL CHECK (`restaurant_rating` BETWEEN 1 AND 5),
    `restaurant_review` TEXT,
    `food_rating` INT DEFAULT 5 CHECK (`food_rating` BETWEEN 1 AND 5),
    `delivery_rating` INT DEFAULT 5 CHECK (`delivery_rating` BETWEEN 1 AND 5),
    `delivery_review` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 15. User Favorites (Wishlist)
CREATE TABLE IF NOT EXISTS `favorites` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `restaurant_id` INT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_user_fav` (`user_id`, `restaurant_id`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 16. Support Tickets
CREATE TABLE IF NOT EXISTS `support_tickets` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `ticket_number` VARCHAR(50) NOT NULL UNIQUE,
    `user_id` INT NOT NULL,
    `role` ENUM('customer', 'restaurant', 'delivery') DEFAULT 'customer',
    `order_id` INT DEFAULT NULL,
    `subject` VARCHAR(200) NOT NULL,
    `message` TEXT NOT NULL,
    `status` ENUM('open', 'in_progress', 'resolved', 'closed') DEFAULT 'open',
    `priority` ENUM('low', 'medium', 'high') DEFAULT 'medium',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 17. Support Ticket Replies
CREATE TABLE IF NOT EXISTS `ticket_replies` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `ticket_id` INT NOT NULL,
    `user_id` INT NOT NULL,
    `message` TEXT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`ticket_id`) REFERENCES `support_tickets`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 18. Wallet Transactions
CREATE TABLE IF NOT EXISTS `wallet_transactions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `type` ENUM('credit', 'debit') NOT NULL,
    `amount` DECIMAL(10,2) NOT NULL,
    `description` VARCHAR(255) NOT NULL,
    `reference_order_id` INT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 19. Payouts (Restaurants & Delivery Partners)
CREATE TABLE IF NOT EXISTS `payouts` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `entity_type` ENUM('restaurant', 'delivery') NOT NULL,
    `entity_id` INT NOT NULL,
    `amount` DECIMAL(10,2) NOT NULL,
    `status` ENUM('pending', 'processed', 'rejected') DEFAULT 'pending',
    `reference_id` VARCHAR(100) DEFAULT NULL,
    `notes` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 20. Platform Global Settings
CREATE TABLE IF NOT EXISTS `platform_settings` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `setting_key` VARCHAR(100) NOT NULL UNIQUE,
    `setting_value` TEXT NOT NULL,
    `description` VARCHAR(255) DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 21. Promotional Banners
CREATE TABLE IF NOT EXISTS `banners` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `title` VARCHAR(150) NOT NULL,
    `subtitle` VARCHAR(255) DEFAULT '',
    `image` VARCHAR(255) NOT NULL,
    `link` VARCHAR(255) DEFAULT 'menu.php',
    `is_active` TINYINT(1) DEFAULT 1,
    `sort_order` INT DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================================
-- SEED DATA
-- ===================================================

-- 1. Users
INSERT INTO `users` (`id`, `name`, `email`, `phone`, `password`, `role`, `avatar`, `wallet_balance`, `referral_code`, `status`) VALUES
(1, 'Platform Admin', 'admin@zayka.com', '+919999900001', '$2y$10$pexEs3Aiw1wTz3MiSSWeaO./aBPRgFTx7SLIUUN4NM5yaKXdVaND2', 'admin', 'assets/images/logo/logo.png', 1000.00, 'ADMIN01', 'active'),
(2, 'Zaykaa Signature Kitchen', 'restaurant@zayka.com', '+919999900002', '$2y$10$HHK/mRux.xaREZ7KIXKkSuNOjsyoKd.ALp.QOlLU7tPS5pqpozjsK', 'restaurant', 'assets/images/categories/biryani.png', 5420.00, 'ZAYKA01', 'active'),
(3, 'Green Leaf Pure Veg', 'greenleaf@zayka.com', '+919999900003', '$2y$10$HHK/mRux.xaREZ7KIXKkSuNOjsyoKd.ALp.QOlLU7tPS5pqpozjsK', 'restaurant', 'assets/images/categories/roti.png', 3150.00, 'GLEAF02', 'active'),
(4, 'Royal Tandoor & Biryani', 'royaltandoor@zayka.com', '+919999900004', '$2y$10$HHK/mRux.xaREZ7KIXKkSuNOjsyoKd.ALp.QOlLU7tPS5pqpozjsK', 'restaurant', 'assets/images/categories/kabab.png', 4890.00, 'ROYAL03', 'active'),
(5, 'Rahul Sharma (Rider)', 'delivery@zayka.com', '+919999900005', '$2y$10$N2cw0S6GLPgiwYil4sDKaOFDg2sZTj8pmbTKnBIVoMRzYgPJoyyB2', 'delivery', 'assets/images/default-avatar.png', 850.00, 'RIDER01', 'active'),
(6, 'Amit Kumar (Rider)', 'amit.rider@zayka.com', '+919999900006', '$2y$10$N2cw0S6GLPgiwYil4sDKaOFDg2sZTj8pmbTKnBIVoMRzYgPJoyyB2', 'delivery', 'assets/images/default-avatar.png', 620.00, 'RIDER02', 'active'),
(7, 'Priya Singh', 'customer@zayka.com', '+919999900007', '$2y$10$w8hEM8karhRl1PLxtx2G1e94j4b.sKRxM.Wl6FCY.iiJugCY0OC0y', 'customer', 'assets/images/default-avatar.png', 250.00, 'PRIYA77', 'active'),
(8, 'Vikash Verma', 'vikash@gmail.com', '+919999900008', '$2y$10$w8hEM8karhRl1PLxtx2G1e94j4b.sKRxM.Wl6FCY.iiJugCY0OC0y', 'customer', 'assets/images/default-avatar.png', 100.00, 'VIKAS88', 'active');

-- 2. Restaurants
INSERT INTO `restaurants` (`id`, `owner_id`, `name`, `slug`, `tagline`, `description`, `logo`, `banner`, `cuisines`, `address`, `city`, `pincode`, `latitude`, `longitude`, `phone`, `email`, `opening_time`, `closing_time`, `status`, `is_pure_veg`, `rating`, `total_ratings`, `delivery_time_min`, `delivery_time_max`, `delivery_radius_km`, `min_order_amount`, `cost_for_two`, `commission_rate`, `is_featured`, `is_approved`) VALUES
(1, 2, 'Zaykaa Café & Restaurant', 'zaykaa-cafe-restaurant', 'Good Food Good Mood – Authentic Flavours That Stay With You', 'Specializing in Dum Biryanis, succulent Kebabs, and artisanal mocktails prepared with heritage recipes.', 'assets/images/categories/biryani.png', 'assets/images/hero/biryani.png', 'Biryani, Mughlai, North Indian, Kebabs', 'Mahuda More, Katras Road', 'Dhanbad', '828305', 23.795700, 86.430400, '+919693517942', 'info@zaykaa.com', '10:00:00', '23:30:00', 'open', 0, 4.85, 142, 25, 35, 12.00, 149.00, 400.00, 15.00, 1, 1),
(2, 3, 'Green Leaf Pure Veg Delights', 'green-leaf-pure-veg', '100% Pure Vegetarian Heritage Recipes', 'Traditional North & South Indian delicacies, buttery naans, paneer curries, and clean pure vegetarian kitchens.', 'assets/images/categories/roti.png', 'assets/images/categories/roti.png', 'Pure Veg, North Indian, Thalis, South Indian', 'Bank More Main Road, Near City Center', 'Dhanbad', '826001', 23.791200, 86.425100, '+919876543210', 'greenleaf@zayka.com', '08:00:00', '22:30:00', 'open', 1, 4.60, 98, 20, 30, 10.00, 99.00, 300.00, 12.00, 1, 1),
(3, 4, 'Royal Tandoor & Biryani Hub', 'royal-tandoor-biryani-hub', 'Charcoal Grilled Kebabs & Royal Nizami Biryani', 'Famous charcoal clay-oven cooked tandoori platters, Afghani chicken, aromatic basmati biryanis, and chilled coolers.', 'assets/images/categories/kabab.png', 'assets/images/categories/kabab.png', 'Mughlai, Tandoor, Biryani, Street Food', 'Saraidhela Main Road, Opposite Big Bazaar', 'Dhanbad', '828127', 23.812400, 86.446800, '+919812345678', 'royaltandoor@zayka.com', '11:00:00', '23:00:00', 'open', 0, 4.70, 85, 30, 45, 14.00, 199.00, 500.00, 15.00, 1, 1);

-- 3. Categories (Platform-wide)
INSERT INTO `categories` (`id`, `name`, `slug`, `image`, `description`, `sort_order`, `is_active`) VALUES
(1, 'Biryani', 'biryani', 'assets/images/categories/biryani.png', 'Flavourful, slow-cooked royal aromatic rice pots', 1, 1),
(2, 'Kebabs & Starters', 'kebabs', 'assets/images/categories/kabab.png', 'Tender, chargrilled and spiced skewers', 2, 1),
(3, 'Rotis & Breads', 'rotis', 'assets/images/categories/roti.png', 'Soft butter naans, tandoori rotis, and kulchas', 3, 1),
(4, 'Mojitos & Coolers', 'mojitos', 'assets/images/categories/mojitos.png', 'Chilled mint mojitos and botanical mocktails', 4, 1),
(5, 'Milkshakes & Sweets', 'milkshakes', 'assets/images/categories/milkshakes.png', 'Thick creamy shakes, kulfis, and desserts', 5, 1),
(6, 'Fresh Juices', 'juices', 'assets/images/categories/juices.png', 'Cold-pressed seasonal fruit elixirs', 6, 1);

-- 4. Restaurant Menu Categories (Zayka)
INSERT INTO `menu_categories` (`id`, `restaurant_id`, `name`, `sort_order`, `is_active`) VALUES
(1, 1, 'Signature Biryanis', 1, 1),
(2, 1, 'Tandoori Starters', 2, 1),
(3, 1, 'Main Course Gravies', 3, 1),
(4, 1, 'Artisanal Mocktails', 4, 1),
(5, 1, 'Paneer Specialities', 5, 1),
(6, 1, 'Pure Veg Breads', 6, 1),
(7, 1, 'Special Thalis', 7, 1),
(8, 1, 'Royal Platters', 8, 1),
(9, 1, 'Dum Biryani Specialties', 9, 1);

-- 5. Menu Items (All belonging to Zayka)
INSERT INTO `menu_items` (`id`, `restaurant_id`, `category_id`, `menu_category_id`, `name`, `description`, `price`, `discount_price`, `is_veg`, `image`, `is_bestseller`, `is_available`, `prep_time_minutes`) VALUES
-- Zaykaa Signature
(1, 1, 1, 1, 'Zaykaa Special Chicken Dum Biryani', 'Fragrant basmati rice layered with succulent marinated chicken, saffron, browned onions and whole spices. Served with creamy mirchi ka salan and burani raita.', 280.00, 249.00, 0, 'assets/images/categories/biryani.png', 1, 1, 25),
(2, 1, 1, 1, 'Hyderabadi Mutton Dum Biryani', 'Slow cooked tender mutton chunks infused with 32 secret spices, sealed in dough for maximum aroma and richness.', 380.00, 349.00, 0, 'assets/images/categories/biryani.png', 1, 1, 30),
(3, 1, 1, 1, 'Shahi Paneer Dum Biryani', 'Golden cottage cheese cubes marinated in saffron yogurt, layered with long grain basmati rice and fresh mint.', 240.00, 219.00, 1, 'assets/images/categories/biryani.png', 1, 1, 20),
(4, 1, 2, 2, 'Galouti Kebab Platter (4 Pcs)', 'Melt-in-mouth smoked minced meat patties infused with rose water and exotic royal spices. Served on mini parathas.', 290.00, 269.00, 0, 'assets/images/categories/kabab.png', 1, 1, 20),
(5, 1, 2, 2, 'Tandoori Murgh (Half)', 'Tender chicken marinated overnight in Kashmiri red chili, mustard oil, hung curd, and roasted in clay oven.', 260.00, 239.00, 0, 'assets/images/categories/kabab.png', 0, 1, 25),
(6, 1, 3, 3, 'Butter Chicken Supreme', 'Charcoal-grilled chicken tikka simmered in a velvety, silky smooth tomato, cashew butter and dried fenugreek gravy.', 320.00, 299.00, 0, 'assets/images/categories/biryani.png', 1, 1, 20),
(7, 1, 3, 3, 'Butter Garlic Naan', 'Refined flour flatbread leavened and charred in tandoor, topped with minced garlic and generous salted butter.', 55.00, NULL, 1, 'assets/images/categories/roti.png', 1, 1, 10),
(8, 1, 4, 4, 'Emerald Mint Mojito', 'Crisp fresh mint leaves muddled with Persian lime, organic cane sugar, sparkling soda and cracked ice.', 120.00, 99.00, 1, 'assets/images/categories/mojitos.png', 1, 1, 5),
(9, 1, 5, 4, 'Royal Mango Kesar Shake', 'Rich Alphonso mango pulp blended with whole milk, saffron strands and pistachio slivers.', 140.00, 120.00, 1, 'assets/images/categories/milkshakes.png', 1, 1, 8),
-- Vegetarian & Chef Specialties
(10, 1, 3, 5, 'Paneer Tikka Butter Masala', 'Charcoal-grilled cottage cheese tikka tossed in an aromatic, rich, cream-laced onion-tomato gravy.', 260.00, 230.00, 1, 'assets/images/categories/roti.png', 1, 1, 20),
(11, 1, 3, 5, 'Dal Makhani Heritage', 'Black lentils slow cooked overnight on charcoal embers with butter, cream, and subtle garlic aroma.', 210.00, 189.00, 1, 'assets/images/categories/roti.png', 1, 1, 25),
(12, 1, 3, 6, 'Amritsari Kulcha with Chole', 'Crispy stuffed potato and spice bread served with spiced Punjabi chickpeas and pickled onions.', 180.00, 159.00, 1, 'assets/images/categories/roti.png', 1, 1, 15),
(13, 1, 3, 7, 'Royal Maharaja Pure Veg Thali', 'Paneer curry, Dal Makhani, Seasonal Subzi, Jeera Rice, 2 Butter Rotis, Gulab Jamun, Salad & Papad.', 299.00, 279.00, 1, 'assets/images/categories/roti.png', 1, 1, 20),
-- Tandoor & Biryani Hub
(14, 1, 2, 8, 'Chicken Malai Tikka', 'Tender boneless chicken morsels steeped in cream, cheese, cardamom, and green chilies, glazed with melted butter.', 270.00, 249.00, 0, 'assets/images/categories/kabab.png', 1, 1, 20),
(15, 1, 2, 8, 'Dahi Ke Kebab (Veg)', 'Golden crisp hung curd patties stuffed with crunchy bell peppers, fresh mint, and toasted pine nuts.', 220.00, 199.00, 1, 'assets/images/categories/kabab.png', 1, 1, 15),
(16, 1, 1, 9, 'Kolkata Style Chicken Biryani', 'Aromatic light biryani layered with succulent chicken, spiced golden potato, and a hard-boiled egg.', 250.00, 229.00, 0, 'assets/images/categories/biryani.png', 1, 1, 25),
(17, 1, 2, 2, 'Burger - Crispy Zaykaa Special', 'Crispy spiced patty with caramelized onions, cheddar cheese, fresh lettuce, and mint mayo in toasted sesame buns.', 149.00, 129.00, 1, 'assets/images/categories/kabab.png', 1, 1, 15),
(18, 1, 5, 4, 'Brownie - Sizzling Walnut Fudge', 'Warm, gooey dark chocolate walnut brownie served on a hot sizzler plate with Belgian chocolate drizzle.', 179.00, 159.00, 1, 'assets/images/categories/milkshakes.png', 1, 1, 10);

-- 6. Add-ons for Menu Items
INSERT INTO `item_addons` (`item_id`, `name`, `price`, `is_available`) VALUES
(1, 'Extra Burani Raita', 30.00, 1),
(1, 'Extra Chicken Piece (1 Pc)', 60.00, 1),
(1, 'Boiled Egg (1 Pc)', 15.00, 1),
(2, 'Extra Mutton Piece (1 Pc)', 90.00, 1),
(6, 'Extra Gravy Bowl', 50.00, 1),
(6, 'Extra Butter Cube', 20.00, 1),
(7, 'Extra Cheese Topping', 30.00, 1),
(8, 'Add Extra Lime & Mint', 15.00, 1),
(10, 'Double Cheese Glaze', 40.00, 1);

-- 7. User Saved Addresses
INSERT INTO `user_addresses` (`id`, `user_id`, `label`, `address_line`, `landmark`, `city`, `pincode`, `latitude`, `longitude`, `is_default`) VALUES
(1, 7, 'Home', 'Flat 402, Green Valley Apartments, Luby Circular Road', 'Near City Center Mall', 'Dhanbad', '826001', 23.798200, 86.432000, 1),
(2, 7, 'Work', 'Tech Hub Floor 2, Bank More Commercial Complex', 'Above HDFC Bank', 'Dhanbad', '826001', 23.791500, 86.426000, 0),
(3, 8, 'Home', 'House 14B, Shastri Nagar, Dhanbad', 'Near Water Tank', 'Dhanbad', '828104', 23.805000, 86.438000, 1);

-- 8. Coupons
INSERT INTO `coupons` (`id`, `code`, `title`, `description`, `discount_type`, `discount_value`, `max_discount`, `min_order_amount`, `restaurant_id`, `usage_limit`, `used_count`, `valid_from`, `valid_until`, `is_active`) VALUES
(1, 'ZAYKA50', 'Flat 50% OFF up to ₹100', 'Get 50% discount up to ₹100 on your first delicious order with Zayka', 'percentage', 50.00, 100.00, 199.00, NULL, 1000, 42, '2026-01-01', '2027-12-31', 1),
(2, 'FLAT100', 'Flat ₹100 OFF on Orders Above ₹399', 'Enjoy huge savings on large feast orders', 'flat', 100.00, 100.00, 399.00, NULL, 500, 18, '2026-01-01', '2027-12-31', 1),
(3, 'TASTE20', '20% OFF on Zaykaa Signature', 'Exclusive 20% discount on all Zaykaa Signature specials', 'percentage', 20.00, 80.00, 249.00, 1, 300, 12, '2026-01-01', '2027-12-31', 1),
(4, 'FREESHIP', 'Free Delivery', 'Enjoy ₹40 off on delivery charges', 'flat', 40.00, 40.00, 149.00, NULL, 1000, 35, '2026-01-01', '2027-12-31', 1);

-- 9. Delivery Partners
INSERT INTO `delivery_partners` (`id`, `user_id`, `vehicle_type`, `vehicle_number`, `driving_license`, `is_online`, `current_lat`, `current_lng`, `kyc_status`, `total_deliveries`, `rating`, `earnings_balance`) VALUES
(1, 5, 'Hero Splendor Plus', 'JH10-AB-4589', 'DL-JH10-2021008745', 1, 23.796200, 86.431200, 'approved', 84, 4.85, 2450.00),
(2, 6, 'Honda Activa 6G', 'JH10-CZ-8821', 'DL-JH10-2022003412', 1, 23.793000, 86.428000, 'approved', 52, 4.75, 1680.00);

-- 10. Sample Orders
INSERT INTO `orders` (`id`, `order_number`, `user_id`, `restaurant_id`, `delivery_partner_id`, `address_id`, `delivery_address_json`, `subtotal`, `addons_total`, `tax_amount`, `delivery_fee`, `platform_fee`, `coupon_discount`, `coupon_code`, `tip_amount`, `total_amount`, `restaurant_earnings`, `delivery_earnings`, `platform_commission`, `payment_method`, `payment_status`, `payment_transaction_id`, `order_status`, `special_instructions`, `estimated_delivery_time`, `delivery_otp`, `created_at`) VALUES
-- 1. Completed order
(1, 'ZYK-20260908-0142', 7, 1, 1, 1, '{"label":"Home","address_line":"Flat 402, Green Valley Apartments, Luby Circular Road","city":"Dhanbad","pincode":"826001"}', 548.00, 45.00, 29.65, 40.00, 5.00, 100.00, 'ZAYKA50', 20.00, 587.65, 474.30, 55.00, 58.35, 'online', 'paid', 'TXN_ZAYKA_998124', 'delivered', 'Please send extra spicy with lemon wedges', 30, '4892', DATE_SUB(NOW(), INTERVAL 1 DAY)),
-- 2. Live active order (Out for Delivery)
(2, 'ZYK-20260909-0205', 7, 1, 1, 1, '{"label":"Home","address_line":"Flat 402, Green Valley Apartments, Luby Circular Road","city":"Dhanbad","pincode":"826001"}', 348.00, 30.00, 18.90, 40.00, 5.00, 0.00, NULL, 30.00, 471.90, 321.30, 65.00, 55.00, 'cod', 'pending', NULL, 'out_for_delivery', 'Ring doorbell twice please', 25, '7231', DATE_SUB(NOW(), INTERVAL 18 MINUTE)),
-- 3. Preparing order
(3, 'ZYK-20260909-0210', 8, 2, NULL, 3, '{"label":"Home","address_line":"House 14B, Shastri Nagar, Dhanbad","city":"Dhanbad","pincode":"828104"}', 419.00, 0.00, 20.95, 40.00, 5.00, 40.00, 'FREESHIP', 0.00, 444.95, 368.72, 40.00, 36.23, 'online', 'paid', 'TXN_ZAYKA_998331', 'preparing', 'No onion garlic in dal makhani', 35, '3198', DATE_SUB(NOW(), INTERVAL 10 MINUTE));

-- 11. Order Items
INSERT INTO `order_items` (`id`, `order_id`, `item_id`, `item_name`, `price`, `quantity`, `total`) VALUES
(1, 1, 1, 'Zaykaa Special Chicken Dum Biryani', 249.00, 1, 249.00),
(2, 1, 6, 'Butter Chicken Supreme', 299.00, 1, 299.00),
(3, 2, 1, 'Zaykaa Special Chicken Dum Biryani', 249.00, 1, 249.00),
(4, 2, 8, 'Emerald Mint Mojito', 99.00, 1, 99.00),
(5, 3, 10, 'Paneer Tikka Butter Masala', 230.00, 1, 230.00),
(6, 3, 11, 'Dal Makhani Heritage', 189.00, 1, 189.00);

-- 12. Order Add-ons
INSERT INTO `order_item_addons` (`id`, `order_item_id`, `addon_name`, `price`) VALUES
(1, 1, 'Extra Burani Raita', 30.00),
(2, 1, 'Boiled Egg (1 Pc)', 15.00),
(3, 3, 'Extra Burani Raita', 30.00);

-- 13. Order Status Audit Logs
INSERT INTO `order_status_logs` (`order_id`, `status`, `notes`, `created_at`) VALUES
(1, 'placed', 'Order placed by customer via Online Payment', DATE_SUB(NOW(), INTERVAL 26 HOUR)),
(1, 'accepted', 'Restaurant accepted order (Prep time: 25 mins)', DATE_SUB(NOW(), INTERVAL 25 HOUR)),
(1, 'preparing', 'Chef started preparation', DATE_SUB(NOW(), INTERVAL 25 HOUR)),
(1, 'ready_for_pickup', 'Food packed and ready for pickup', DATE_SUB(NOW(), INTERVAL 24 HOUR)),
(1, 'picked_up', 'Rider Rahul Sharma picked up the package', DATE_SUB(NOW(), INTERVAL 24 HOUR)),
(1, 'out_for_delivery', 'Rider on the way to customer address', DATE_SUB(NOW(), INTERVAL 24 HOUR)),
(1, 'delivered', 'Order successfully verified via OTP and delivered', DATE_SUB(NOW(), INTERVAL 23 HOUR)),
(2, 'placed', 'Order placed by customer (COD)', DATE_SUB(NOW(), INTERVAL 20 MINUTE)),
(2, 'accepted', 'Restaurant accepted order', DATE_SUB(NOW(), INTERVAL 18 MINUTE)),
(2, 'preparing', 'Kitchen preparing dishes', DATE_SUB(NOW(), INTERVAL 15 MINUTE)),
(2, 'ready_for_pickup', 'Order packed and handover ready', DATE_SUB(NOW(), INTERVAL 8 MINUTE)),
(2, 'picked_up', 'Rider Rahul Sharma picked up order', DATE_SUB(NOW(), INTERVAL 5 MINUTE)),
(2, 'out_for_delivery', 'Rider is 1.2 km away from destination', DATE_SUB(NOW(), INTERVAL 3 MINUTE)),
(3, 'placed', 'Order placed by customer', DATE_SUB(NOW(), INTERVAL 12 MINUTE)),
(3, 'accepted', 'Restaurant accepted order (Prep time: 30 mins)', DATE_SUB(NOW(), INTERVAL 10 MINUTE)),
(3, 'preparing', 'Dishes being freshly prepared', DATE_SUB(NOW(), INTERVAL 5 MINUTE));

-- 14. Customer Reviews
INSERT INTO `reviews` (`order_id`, `user_id`, `restaurant_id`, `delivery_partner_id`, `restaurant_rating`, `restaurant_review`, `food_rating`, `delivery_rating`, `delivery_review`, `created_at`) VALUES
(1, 7, 1, 1, 5, 'The chicken biryani was bursting with aroma and perfectly tender! Delivered super hot in eco-friendly packaging. Highly recommended!', 5, 5, 'Rider was polite, punctual, and wore a safety helmet.', DATE_SUB(NOW(), INTERVAL 23 HOUR));

-- 15. Favorites
INSERT INTO `favorites` (`user_id`, `restaurant_id`) VALUES
(7, 1),
(7, 2),
(8, 1);

-- 16. Support Tickets
INSERT INTO `support_tickets` (`id`, `ticket_number`, `user_id`, `role`, `order_id`, `subject`, `message`, `status`, `priority`) VALUES
(1, 'TCK-2026-001', 7, 'customer', 1, 'Inquiry regarding coupon applicability', 'Hi team, does the ZAYKA50 coupon apply on weekends too? Just wanted to confirm for my family gathering.', 'resolved', 'low'),
(2, 'TCK-2026-002', 8, 'customer', 3, 'Special packaging request', 'Can you please ensure the curries are packed in spill-proof containers?', 'in_progress', 'medium');

INSERT INTO `ticket_replies` (`id`, `ticket_id`, `user_id`, `message`) VALUES
(1, 1, 1, 'Hello Priya! Yes, the ZAYKA50 discount code is valid 7 days a week, including weekends! Happy ordering.'),
(2, 2, 1, 'Hello Vikash! We have informed the restaurant to double seal the containers with leakproof tape.');

-- 17. Wallet Transactions
INSERT INTO `wallet_transactions` (`user_id`, `type`, `amount`, `description`, `reference_order_id`) VALUES
(7, 'credit', 200.00, 'Welcome bonus credits for joining Zayka', NULL),
(7, 'credit', 50.00, 'Referral cash-back bonus from friend signup', NULL),
(8, 'credit', 100.00, 'Welcome bonus credits', NULL);

-- 18. Promotional Banners
INSERT INTO `banners` (`id`, `title`, `subtitle`, `image`, `link`, `is_active`, `sort_order`) VALUES
(1, 'Festive Biryani Festival', 'Get Flat 50% OFF with code ZAYKA50 on all Dum Biryanis', 'assets/images/hero/biryani.png', 'search.php?query=biryani', 1, 1),
(2, 'Pure Veg Delicacies', 'Authentic Paneer & Dal Makhani from Top Rated Kitchens', 'assets/images/categories/roti.png', 'search.php?pure_veg=1', 1, 2),
(3, 'Summer Coolers & Shakes', 'Beat the heat with artisanal Mojitos & Shakes', 'assets/images/categories/mojitos.png', 'search.php?query=mojito', 1, 3);

-- 19. Platform Settings
INSERT INTO `platform_settings` (`setting_key`, `setting_value`, `description`) VALUES
('platform_name', 'Zaykaa Food Delivery', 'Platform branding name'),
('support_email', 'support@zayka.com', 'Customer helpdesk email'),
('support_phone', '+91 96935 17942', 'Platform support hotline'),
('base_delivery_fee', '30.00', 'Base delivery fee for first 3 km in INR'),
('per_km_fee', '10.00', 'Additional delivery fee per km beyond 3 km in INR'),
('platform_service_fee', '5.00', 'Platform convenience fee per order in INR'),
('gst_tax_rate_percent', '5.00', 'Restaurant food GST rate percentage (5%)'),
('default_commission_percent', '15.00', 'Default platform commission on restaurant subtotal'),
('rider_delivery_base_pay', '35.00', 'Base payment to delivery partner per trip in INR'),
('rider_per_km_pay', '8.00', 'Additional payment to delivery partner per km in INR'),
('max_delivery_radius_km', '15.00', 'Maximum ordering distance in kilometers'),
('cod_enabled', '1', 'Allow Cash on Delivery (1: Yes, 0: No)'),
('online_payment_enabled', '1', 'Allow Online Payments (1: Yes, 0: No)'),
('payment_gateway_mode', 'simulation', 'Payment gateway mode: simulation or razorpay');

-- ===================================================
-- END OF DATABASE SETUP
-- ===================================================
