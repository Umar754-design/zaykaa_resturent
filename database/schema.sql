-- ===================================================
-- Zayka Food Delivery Platform Database Schema
-- Multi-role: Customer, Restaurant, Delivery Partner, Admin
-- ===================================================

CREATE DATABASE IF NOT EXISTS `zayka_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `zayka_db`;

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
    `serving_size` VARCHAR(100) DEFAULT NULL,
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
    `selfie_image` VARCHAR(255) DEFAULT NULL,
    `current_selfie` VARCHAR(255) DEFAULT NULL,
    `current_selfie_at` DATETIME DEFAULT NULL,
    `is_online` TINYINT(1) DEFAULT 1,
    `current_lat` DECIMAL(10,8) DEFAULT 23.795000,
    `current_lng` DECIMAL(11,8) DEFAULT 86.429000,
    `kyc_status` ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
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
    `serving_size` VARCHAR(100) DEFAULT NULL,
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
