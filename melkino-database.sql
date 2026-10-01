-- ============================================================
-- ملکینو — فایل دیتابیس (اسکیما)
-- ============================================================
-- این فایل «کامل» است: هم جدول‌ها را می‌سازد (CREATE TABLE IF NOT
-- EXISTS) و هم در بخش پایانی، ستون‌های جاافتادهٔ جدول‌های «موجود» را
-- با دستورهای شرطیِ بی‌خطر اضافه می‌کند (فقط اگر ستون وجود نداشته
-- باشد؛ وگرنه هیچ کاری نمی‌کند). یکسان‌سازی collation جدول‌های
-- مقایسه هم در همان بخش انجام می‌شود.
--
-- یعنی:
--   * import روی دیتابیس موجود → هیچ داده‌ای پاک نمی‌شود
--   * چند بار import کردن → بی‌خطر (idempotent)
--   * هم MySQL و هم MariaDB پشتیبانی می‌شوند
--   * دیگر نیازی به اجرای melkino-migrate.php نیست؛ این فایل
--     همان کارها (ستون‌ها + collation) را خودش انجام می‌دهد
--
-- import: از phpMyAdmin هاست (تب Import) یا خط فرمان:
--   mysql -u USER -p DBNAME < melkino-database.sql
-- ============================================================

SET NAMES utf8mb4;

-- ---------- آگهی‌ها ----------
-- نکته: در دیتابیس واقعیِ ملکینو، شناسه‌ی آگهی رشته است
-- (مثل AD-20260906-4691) و ستون عددی جداگانه numeric_id وجود دارد.
CREATE TABLE IF NOT EXISTS `ads` (
  `numeric_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id` VARCHAR(64) NOT NULL,
  `ad_code` VARCHAR(64) NULL,
  `owner_user_id` INT NULL,
  `consultant_id` INT NULL,
  `title` VARCHAR(500) NULL,
  `status` VARCHAR(30) NOT NULL DEFAULT 'pending',
  `transaction_type` VARCHAR(60) NULL,
  `property_type` VARCHAR(60) NULL,
  `gender` VARCHAR(20) NULL,
  `last_name` VARCHAR(120) NULL,
  `phone` VARCHAR(30) NULL,
  `location` VARCHAR(255) NULL,
  `address` VARCHAR(500) NULL,
  `location_received` VARCHAR(10) NULL,
  `latitude` DECIMAL(10,7) NULL,
  `longitude` DECIMAL(10,7) NULL,
  `area` VARCHAR(30) NULL,
  `land_area` VARCHAR(30) NULL,
  `built_area` VARCHAR(30) NULL,
  `rooms` VARCHAR(10) NULL,
  `floor` VARCHAR(10) NULL,
  `year` VARCHAR(10) NULL,
  `price_sell` VARCHAR(40) NULL,
  `price_condition` VARCHAR(40) NULL,
  `deposit` VARCHAR(40) NULL,
  `rent_monthly` VARCHAR(40) NULL,
  `full_rent_enabled` TINYINT(1) NOT NULL DEFAULT 0,
  `full_rent` VARCHAR(40) NULL,
  `total_price` VARCHAR(40) NULL,
  `down_payment` VARCHAR(40) NULL,
  `payment_terms` VARCHAR(500) NULL,
  `has_loan` TINYINT(1) NOT NULL DEFAULT 0,
  `loan_amount` VARCHAR(40) NULL,
  `loan_type` VARCHAR(60) NULL,
  `loan_duration` VARCHAR(60) NULL,
  `loan_bank` VARCHAR(120) NULL,
  `loan_installment` VARCHAR(40) NULL,
  `loan_installments_paid` VARCHAR(20) NULL,
  `loan_notes` VARCHAR(500) NULL,
  `deed_type` VARCHAR(40) NULL,
  `deed_notes` VARCHAR(500) NULL,
  `exchange_types` VARCHAR(255) NULL,
  `display_price` VARCHAR(40) NULL,
  `price_hidden` TINYINT(1) NOT NULL DEFAULT 0,
  `description` TEXT NULL,
  `publish_photos` VARCHAR(10) NOT NULL DEFAULT 'yes',
  `is_vip` TINYINT(1) NOT NULL DEFAULT 0,
  `vip_until` DATETIME NULL,
  `telegram_message_id` INT NULL,
  `telegram_published_at` DATETIME NULL,
  `telegram_channel_id` VARCHAR(120) NULL,
  `tags` TEXT NULL,
  `property_details` LONGTEXT NULL,
  `custom_fields` LONGTEXT NULL,
  `is_not_keyed` TINYINT(1) NOT NULL DEFAULT 0,
  `delivery_date` VARCHAR(30) NULL,
  `vacancy_date` VARCHAR(30) NULL,
  `is_vacant` TINYINT(1) NOT NULL DEFAULT 0,
  `exchange_interested` TINYINT(1) NOT NULL DEFAULT 0,
  `exchange_with` VARCHAR(255) NULL,
  `visit_hours` VARCHAR(255) NULL,
  `is_old` TINYINT(1) NOT NULL DEFAULT 0,
  `is_renovated` TINYINT(1) NOT NULL DEFAULT 0,
  `water_share` VARCHAR(120) NULL,
  `well_name` VARCHAR(120) NULL,
  `user_id` INT NULL,
  `telegram_id` VARCHAR(64) NULL,
  `views` INT NOT NULL DEFAULT 0,
  `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL,
  `published_at` DATETIME NULL,
  `sold_at` DATETIME NULL,
  `rejected_at` DATETIME NULL,
  `archived_at` DATETIME NULL,
  `bale_message_id` VARCHAR(40) NULL,
  `bale_channel_id` VARCHAR(120) NULL,
  `bale_published_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_numeric` (`numeric_id`),
  KEY `idx_status` (`status`),
  KEY `idx_owner_user` (`owner_user_id`),
  KEY `idx_phone` (`phone`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- کاربران ----------
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `telegram_id` VARCHAR(30) NULL,
  `bale_id` VARCHAR(30) NULL,
  `username` VARCHAR(100) NULL,
  `name` VARCHAR(200) NULL,
  `phone` VARCHAR(30) NULL,
  `access_token` VARCHAR(64) NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `first_login` DATETIME NULL,
  `last_login` DATETIME NULL,
  `login_count` INT NOT NULL DEFAULT 0,
  `last_ip` VARCHAR(45) NULL,
  `last_platform` VARCHAR(20) NULL,
  `user_agent` VARCHAR(1000) NULL,
  `photo_url` VARCHAR(500) NULL,
  `language_code` VARCHAR(10) NULL,
  `bale_username` VARCHAR(191) NULL,
  `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL,
  `telegram_first_name` VARCHAR(100) NULL,
  `telegram_last_name` VARCHAR(100) NULL,
  `telegram_username` VARCHAR(100) NULL,
  `telegram_language_code` VARCHAR(10) NULL,
  `telegram_is_premium` TINYINT(1) NOT NULL DEFAULT 0,
  `telegram_photo_url` VARCHAR(500) NULL,
  `telegram_auth_date` DATETIME NULL,
  `last_init_data` TEXT NULL,
  `phone_verified` TINYINT(1) NOT NULL DEFAULT 0,
  `phone_locked` TINYINT(1) NOT NULL DEFAULT 0,
  `first_name` VARCHAR(100) NULL,
  `last_name` VARCHAR(100) NULL,
  `name_locked` TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_tg` (`telegram_id`),
  UNIQUE KEY `uniq_bale` (`bale_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- کدهای یک‌بارمصرفِ ورود با پیامک (request-otp.php / verify-otp.php)
CREATE TABLE IF NOT EXISTS `otp_codes` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `phone` VARCHAR(30) NOT NULL,
  `code` VARCHAR(10) NOT NULL,
  `channel` VARCHAR(20) NOT NULL DEFAULT 'screen',
  `telegram_chat_id` VARCHAR(30) NULL,
  `bale_chat_id` VARCHAR(30) NULL,
  `attempts` INT NOT NULL DEFAULT 0,
  `is_used` TINYINT(1) NOT NULL DEFAULT 0,
  `expires_at` DATETIME NULL,
  `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_otp_phone` (`phone`),
  KEY `idx_otp_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- تصاویر آگهی‌ها ----------
CREATE TABLE IF NOT EXISTS `images` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ad_id` VARCHAR(64) NOT NULL,
  `filename` VARCHAR(500) NOT NULL,
  `storage_path` VARCHAR(500) NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  `is_selected` TINYINT(1) NOT NULL DEFAULT 1,
  `is_primary` TINYINT(1) NOT NULL DEFAULT 0,
  `publish_publicly` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ad` (`ad_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- مقایسه ملک‌ها ----------
CREATE TABLE IF NOT EXISTS `compare_items` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT NULL,
  `telegram_id` VARCHAR(64) NULL,
  `guest_token` VARCHAR(64) NULL,
  `ad_id` VARCHAR(64) NOT NULL,
  `group_no` TINYINT NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_owner` (`user_id`, `telegram_id`, `guest_token`),
  KEY `idx_ad` (`ad_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `compare_groups` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT NULL,
  `telegram_id` VARCHAR(64) NULL,
  `guest_token` VARCHAR(64) NULL,
  `group_no` TINYINT NOT NULL,
  `name` VARCHAR(60) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `idx_owner` (`user_id`, `telegram_id`, `guest_token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- علاقه‌مندی‌ها / اعلان‌ها ----------
CREATE TABLE IF NOT EXISTS `favorites` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT NULL,
  `telegram_id` VARCHAR(64) NULL,
  `ad_id` VARCHAR(64) NULL,
  `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_owner` (`user_id`, `telegram_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `notifications` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT NULL,
  `telegram_id` VARCHAR(64) NULL,
  `request_id` INT UNSIGNED NULL,
  `ad_id` VARCHAR(64) NULL,
  `title` VARCHAR(255) NULL,
  `message` TEXT NULL,
  `type` VARCHAR(40) NULL,
  `url` VARCHAR(500) NULL,
  `broadcast_id` BIGINT UNSIGNED NULL,
  `match_percent` DECIMAL(5,2) NULL,
  `is_read` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_owner` (`user_id`, `telegram_id`, `is_read`),
  KEY `idx_notif_broadcast` (`broadcast_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- اعلان‌های عمومی (برودکست) که از پنل ادمین برای همه ارسال می‌شود
CREATE TABLE IF NOT EXISTS `notification_broadcasts` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(200) NOT NULL,
  `message` TEXT NULL,
  `url` VARCHAR(500) NULL,
  `sent_count` INT NOT NULL DEFAULT 0,
  `created_by` BIGINT UNSIGNED NULL,
  `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- مشاوران ----------
CREATE TABLE IF NOT EXISTS `consultants` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(120) NULL,
  `phone` VARCHAR(30) NULL,
  `telegram_username` VARCHAR(64) NULL,
  `telegram_link` VARCHAR(255) NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `sort_order` INT NOT NULL DEFAULT 100,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `consultant_specialties` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `consultant_id` INT NOT NULL,
  `property_type` VARCHAR(60) NULL,
  `transaction_type` VARCHAR(60) NULL,
  PRIMARY KEY (`id`),
  KEY `idx_consultant` (`consultant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- امکانات ----------
CREATE TABLE IF NOT EXISTS `amenities` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(120) NOT NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ad_amenities` (
  `ad_id` VARCHAR(64) NOT NULL,
  `amenity_id` INT NOT NULL,
  KEY `idx_ad` (`ad_id`),
  KEY `idx_amenity` (`amenity_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- درخواست‌های کاربر ----------
CREATE TABLE IF NOT EXISTS `property_requests` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tracking_code` VARCHAR(40) NULL,
  `user_id` INT NULL,
  `telegram_id` VARCHAR(64) NULL,
  `phone` VARCHAR(30) NULL,
  `gender` VARCHAR(10) NULL,
  `last_name` VARCHAR(120) NULL,
  `transaction_type` VARCHAR(60) NULL,
  `property_type` VARCHAR(60) NULL,
  `location` VARCHAR(255) NULL,
  `urgency` VARCHAR(40) NULL,
  `date_needed` VARCHAR(40) NULL,
  `rahn_kamal` VARCHAR(10) NULL,
  `min_area` VARCHAR(30) NULL,
  `max_area` VARCHAR(30) NULL,
  `min_price` VARCHAR(40) NULL,
  `max_price` VARCHAR(40) NULL,
  `min_deposit` VARCHAR(40) NULL,
  `max_deposit` VARCHAR(40) NULL,
  `min_rent` VARCHAR(40) NULL,
  `max_rent` VARCHAR(40) NULL,
  `min_age` VARCHAR(10) NULL,
  `max_age` VARCHAR(10) NULL,
  `is_not_keyed` TINYINT(1) NOT NULL DEFAULT 0,
  `status` VARCHAR(30) NOT NULL DEFAULT 'new',
  `additional` TEXT NULL,
  `property_details` LONGTEXT NULL,
  `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  KEY `idx_owner` (`user_id`, `telegram_id`),
  KEY `idx_track` (`tracking_code`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `request_amenities` (
  `request_id` INT NOT NULL,
  `amenity_id` INT NOT NULL,
  KEY `idx_req` (`request_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `request_matches` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `request_id` INT NOT NULL,
  `ad_id` VARCHAR(64) NOT NULL,
  `match_percent` DECIMAL(5,2) NULL DEFAULT 0,
  `matched_transaction` VARCHAR(60) NULL,
  `matched_property_type` VARCHAR(60) NULL,
  `location_score` INT NULL,
  `area_score` INT NULL,
  `budget_score` INT NULL,
  `amenities_score` INT NULL,
  `is_notified` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_req` (`request_id`),
  KEY `idx_ad` (`ad_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- ویرایش‌های کاربران ----------
CREATE TABLE IF NOT EXISTS `ad_revisions` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ad_id` VARCHAR(64) NOT NULL,
  `snapshot` LONGTEXT NULL,
  `change_note` TEXT NULL,
  `changed_by_admin_id` INT NULL,
  `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ad` (`ad_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- رویدادهای ورود / توکن‌ها ----------
CREATE TABLE IF NOT EXISTS `login_events` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT NULL,
  `telegram_id` VARCHAR(64) NULL,
  `bale_id` VARCHAR(64) NULL,
  `username` VARCHAR(191) NULL,
  `name` VARCHAR(191) NULL,
  `ip_address` VARCHAR(45) NULL,
  `ip` VARCHAR(45) NULL,
  `user_agent` VARCHAR(1000) NULL,
  `platform` VARCHAR(20) NULL,
  `language_code` VARCHAR(10) NULL,
  `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_login_events_user` (`user_id`),
  KEY `idx_login_events_tg` (`telegram_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `login_tokens` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `token_hash` VARCHAR(128) NOT NULL,
  `telegram_id` VARCHAR(64) NULL,
  `bale_id` VARCHAR(64) NULL,
  `platform` VARCHAR(20) NULL,
  `used` TINYINT(1) NOT NULL DEFAULT 0,
  `expires_at` DATETIME NULL,
  `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_token` (`token_hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- ادمین‌ها ----------
CREATE TABLE IF NOT EXISTS `admins` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(64) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `display_name` VARCHAR(120) NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `updated_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `admin_login_attempts` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ip` VARCHAR(45) NULL,
  `username` VARCHAR(64) NULL,
  `success` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ip_time` (`ip`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- تنظیمات / پشتیبانی ----------
CREATE TABLE IF NOT EXISTS `settings` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `setting_group` VARCHAR(64) NOT NULL DEFAULT 'global',
  `setting_key` VARCHAR(191) NOT NULL,
  `setting_value` LONGTEXT NULL,
  `value_type` VARCHAR(32) NOT NULL DEFAULT 'string',
  `updated_by_admin_id` BIGINT UNSIGNED NULL,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_settings_group_key` (`setting_group`, `setting_key`),
  KEY `idx_settings_group` (`setting_group`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `support_tickets` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT NULL,
  `telegram_id` VARCHAR(64) NULL,
  `name` VARCHAR(120) NULL,
  `phone` VARCHAR(30) NULL,
  `subject` VARCHAR(255) NULL,
  `status` VARCHAR(30) NOT NULL DEFAULT 'open',
  `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `support_messages` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ticket_id` INT NOT NULL,
  `sender_type` VARCHAR(20) NOT NULL DEFAULT 'user',
  `sender_id` VARCHAR(64) NULL,
  `sender_name` VARCHAR(120) NULL,
  `message` TEXT NULL,
  `is_read` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ticket` (`ticket_id`),
  KEY `idx_sender_read` (`sender_type`, `is_read`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- پایان فایل
-- ============================================================

-- ============================================================
-- بخش ۲) ستون‌های جاافتادهٔ جدول‌های «موجود»
-- ============================================================
-- ============================================================
-- 1-13) لاگ انتشار آگهی در کانال‌ها (راند ۱۸)
--   هر بار انتشار (موفق/ناموفق) در تلگرام یا بله یک ردیف ثبت می‌شود
-- ============================================================
CREATE TABLE IF NOT EXISTS `channel_publish_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `ad_id` VARCHAR(40) NOT NULL,
    `platform` VARCHAR(10) NOT NULL,
    `success` TINYINT(1) NOT NULL DEFAULT 0,
    `message_id` VARCHAR(60) NULL,
    `note` VARCHAR(255) NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_cpl_ad` (`ad_id`),
    INDEX `idx_cpl_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- CREATE TABLE IF NOT EXISTS به جدولِ از قبل موجود دست نمی‌زند؛
-- بنابراین ستون‌های جدید (مثل فیلدهای وام) باید با ALTER اضافه
-- شوند. هر دستور زیر «شرطی» است: اول در INFORMATION_SCHEMA نگاه
-- می‌کند و فقط اگر ستون واقعاً وجود نداشت آن را می‌سازد؛ وگرنه
-- هیچ کاری نمی‌کند (DO 0). یعنی:
--   * اجرای این فایل روی دیتابیس موجود هیچ داده‌ای را پاک نمی‌کند
--   * چند بار اجرا کردنش هم بی‌خطر است (idempotent)
--   * هم روی MySQL کار می‌کند و هم MariaDB
-- ============================================================

-- ---------- ads ----------
SET @s := (SELECT IF(COUNT(*)=0, 'ALTER TABLE `ads` ADD COLUMN `owner_user_id` INT NULL', 'DO 0') FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ads' AND COLUMN_NAME='owner_user_id');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
SET @s := (SELECT IF(COUNT(*)=0, 'ALTER TABLE `ads` ADD COLUMN `consultant_id` INT NULL', 'DO 0') FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ads' AND COLUMN_NAME='consultant_id');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
SET @s := (SELECT IF(COUNT(*)=0, 'ALTER TABLE `ads` ADD COLUMN `area` VARCHAR(30) NULL', 'DO 0') FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ads' AND COLUMN_NAME='area');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
SET @s := (SELECT IF(COUNT(*)=0, 'ALTER TABLE `ads` ADD COLUMN `built_area` VARCHAR(30) NULL', 'DO 0') FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ads' AND COLUMN_NAME='built_area');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
SET @s := (SELECT IF(COUNT(*)=0, 'ALTER TABLE `ads` ADD COLUMN `land_area` VARCHAR(30) NULL', 'DO 0') FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ads' AND COLUMN_NAME='land_area');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
SET @s := (SELECT IF(COUNT(*)=0, 'ALTER TABLE `ads` ADD COLUMN `rooms` VARCHAR(10) NULL', 'DO 0') FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ads' AND COLUMN_NAME='rooms');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
SET @s := (SELECT IF(COUNT(*)=0, 'ALTER TABLE `ads` ADD COLUMN `floor` VARCHAR(10) NULL', 'DO 0') FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ads' AND COLUMN_NAME='floor');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
SET @s := (SELECT IF(COUNT(*)=0, 'ALTER TABLE `ads` ADD COLUMN `year` VARCHAR(10) NULL', 'DO 0') FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ads' AND COLUMN_NAME='year');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
SET @s := (SELECT IF(COUNT(*)=0, 'ALTER TABLE `ads` ADD COLUMN `display_price` VARCHAR(40) NULL', 'DO 0') FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ads' AND COLUMN_NAME='display_price');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
SET @s := (SELECT IF(COUNT(*)=0, 'ALTER TABLE `ads` ADD COLUMN `vip_until` DATETIME NULL', 'DO 0') FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ads' AND COLUMN_NAME='vip_until');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
SET @s := (SELECT IF(COUNT(*)=0, 'ALTER TABLE `ads` ADD COLUMN `rejected_at` DATETIME NULL', 'DO 0') FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ads' AND COLUMN_NAME='rejected_at');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
SET @s := (SELECT IF(COUNT(*)=0, 'ALTER TABLE `ads` ADD COLUMN `archived_at` DATETIME NULL', 'DO 0') FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ads' AND COLUMN_NAME='archived_at');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
SET @s := (SELECT IF(COUNT(*)=0, 'ALTER TABLE `ads` ADD COLUMN `bale_message_id` VARCHAR(40) NULL', 'DO 0') FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ads' AND COLUMN_NAME='bale_message_id');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
SET @s := (SELECT IF(COUNT(*)=0, 'ALTER TABLE `ads` ADD COLUMN `bale_channel_id` VARCHAR(120) NULL', 'DO 0') FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ads' AND COLUMN_NAME='bale_channel_id');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
SET @s := (SELECT IF(COUNT(*)=0, 'ALTER TABLE `ads` ADD COLUMN `bale_published_at` DATETIME NULL', 'DO 0') FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ads' AND COLUMN_NAME='bale_published_at');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
SET @s := (SELECT IF(COUNT(*)=0, 'ALTER TABLE `ads` ADD COLUMN `user_id` INT NULL', 'DO 0') FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ads' AND COLUMN_NAME='user_id');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
SET @s := (SELECT IF(COUNT(*)=0, 'ALTER TABLE `ads` ADD COLUMN `telegram_id` VARCHAR(64) NULL', 'DO 0') FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ads' AND COLUMN_NAME='telegram_id');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
SET @s := (SELECT IF(COUNT(*)=0, 'ALTER TABLE `ads` ADD COLUMN `views` INT NOT NULL DEFAULT 0', 'DO 0') FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ads' AND COLUMN_NAME='views');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
SET @s := (SELECT IF(COUNT(*)=0, 'ALTER TABLE `ads` ADD COLUMN `has_loan` TINYINT(1) NOT NULL DEFAULT 0', 'DO 0') FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ads' AND COLUMN_NAME='has_loan');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
SET @s := (SELECT IF(COUNT(*)=0, 'ALTER TABLE `ads` ADD COLUMN `loan_amount` VARCHAR(40) NULL', 'DO 0') FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ads' AND COLUMN_NAME='loan_amount');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
SET @s := (SELECT IF(COUNT(*)=0, 'ALTER TABLE `ads` ADD COLUMN `loan_type` VARCHAR(60) NULL', 'DO 0') FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ads' AND COLUMN_NAME='loan_type');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
SET @s := (SELECT IF(COUNT(*)=0, 'ALTER TABLE `ads` ADD COLUMN `loan_duration` VARCHAR(60) NULL', 'DO 0') FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ads' AND COLUMN_NAME='loan_duration');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
SET @s := (SELECT IF(COUNT(*)=0, 'ALTER TABLE `ads` ADD COLUMN `loan_bank` VARCHAR(120) NULL', 'DO 0') FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ads' AND COLUMN_NAME='loan_bank');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
SET @s := (SELECT IF(COUNT(*)=0, 'ALTER TABLE `ads` ADD COLUMN `loan_installment` VARCHAR(40) NULL', 'DO 0') FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ads' AND COLUMN_NAME='loan_installment');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
SET @s := (SELECT IF(COUNT(*)=0, 'ALTER TABLE `ads` ADD COLUMN `loan_installments_paid` VARCHAR(20) NULL', 'DO 0') FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ads' AND COLUMN_NAME='loan_installments_paid');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
SET @s := (SELECT IF(COUNT(*)=0, 'ALTER TABLE `ads` ADD COLUMN `loan_notes` VARCHAR(500) NULL', 'DO 0') FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ads' AND COLUMN_NAME='loan_notes');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
SET @s := (SELECT IF(COUNT(*)=0, 'ALTER TABLE `ads` ADD COLUMN `deed_type` VARCHAR(40) NULL', 'DO 0') FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ads' AND COLUMN_NAME='deed_type');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
SET @s := (SELECT IF(COUNT(*)=0, 'ALTER TABLE `ads` ADD COLUMN `deed_notes` VARCHAR(500) NULL', 'DO 0') FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ads' AND COLUMN_NAME='deed_notes');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
SET @s := (SELECT IF(COUNT(*)=0, 'ALTER TABLE `ads` ADD COLUMN `exchange_types` VARCHAR(255) NULL', 'DO 0') FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ads' AND COLUMN_NAME='exchange_types');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- ---------- users ----------
SET @s := (SELECT IF(COUNT(*)=0, 'ALTER TABLE `users` ADD COLUMN `bale_id` VARCHAR(30) NULL', 'DO 0') FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='bale_id');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
SET @s := (SELECT IF(COUNT(*)=0, 'ALTER TABLE `users` ADD COLUMN `first_login` DATETIME NULL', 'DO 0') FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='first_login');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
SET @s := (SELECT IF(COUNT(*)=0, 'ALTER TABLE `users` ADD COLUMN `last_login` DATETIME NULL', 'DO 0') FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='last_login');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
SET @s := (SELECT IF(COUNT(*)=0, 'ALTER TABLE `users` ADD COLUMN `login_count` INT NOT NULL DEFAULT 0', 'DO 0') FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='login_count');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
SET @s := (SELECT IF(COUNT(*)=0, 'ALTER TABLE `users` ADD COLUMN `last_ip` VARCHAR(45) NULL', 'DO 0') FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='last_ip');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
SET @s := (SELECT IF(COUNT(*)=0, 'ALTER TABLE `users` ADD COLUMN `last_platform` VARCHAR(20) NULL', 'DO 0') FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='last_platform');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
SET @s := (SELECT IF(COUNT(*)=0, 'ALTER TABLE `users` ADD COLUMN `user_agent` VARCHAR(1000) NULL', 'DO 0') FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='user_agent');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
SET @s := (SELECT IF(COUNT(*)=0, 'ALTER TABLE `users` ADD COLUMN `photo_url` VARCHAR(500) NULL', 'DO 0') FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='photo_url');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
SET @s := (SELECT IF(COUNT(*)=0, 'ALTER TABLE `users` ADD COLUMN `language_code` VARCHAR(10) NULL', 'DO 0') FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='language_code');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
SET @s := (SELECT IF(COUNT(*)=0, 'ALTER TABLE `users` ADD COLUMN `bale_username` VARCHAR(191) NULL', 'DO 0') FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='bale_username');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
SET @s := (SELECT IF(COUNT(*)=0, 'ALTER TABLE `users` ADD COLUMN `updated_at` DATETIME NULL', 'DO 0') FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='updated_at');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
SET @s := (SELECT IF(COUNT(*)=0, 'ALTER TABLE `users` ADD COLUMN `access_token` VARCHAR(64) NULL', 'DO 0') FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='access_token');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- ---------- images ----------
SET @s := (SELECT IF(COUNT(*)=0, 'ALTER TABLE `images` ADD COLUMN `storage_path` VARCHAR(500) NULL', 'DO 0') FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='images' AND COLUMN_NAME='storage_path');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
SET @s := (SELECT IF(COUNT(*)=0, 'ALTER TABLE `images` ADD COLUMN `sort_order` INT NOT NULL DEFAULT 0', 'DO 0') FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='images' AND COLUMN_NAME='sort_order');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
SET @s := (SELECT IF(COUNT(*)=0, 'ALTER TABLE `images` ADD COLUMN `is_selected` TINYINT(1) NOT NULL DEFAULT 1', 'DO 0') FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='images' AND COLUMN_NAME='is_selected');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
SET @s := (SELECT IF(COUNT(*)=0, 'ALTER TABLE `images` ADD COLUMN `is_primary` TINYINT(1) NOT NULL DEFAULT 0', 'DO 0') FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='images' AND COLUMN_NAME='is_primary');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
SET @s := (SELECT IF(COUNT(*)=0, 'ALTER TABLE `images` ADD COLUMN `publish_publicly` TINYINT(1) NOT NULL DEFAULT 1', 'DO 0') FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='images' AND COLUMN_NAME='publish_publicly');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
SET @s := (SELECT IF(COUNT(*)=0, 'ALTER TABLE `images` ADD COLUMN `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP', 'DO 0') FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='images' AND COLUMN_NAME='created_at');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- ---------- compare_items ----------
SET @s := (SELECT IF(COUNT(*)=0, 'ALTER TABLE `compare_items` ADD COLUMN `guest_token` VARCHAR(64) NULL', 'DO 0') FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='compare_items' AND COLUMN_NAME='guest_token');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- ---------- compare_groups ----------
SET @s := (SELECT IF(COUNT(*)=0, 'ALTER TABLE `compare_groups` ADD COLUMN `guest_token` VARCHAR(64) NULL', 'DO 0') FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='compare_groups' AND COLUMN_NAME='guest_token');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- ---------- settings ----------
SET @s := (SELECT IF(COUNT(*)=0, 'ALTER TABLE `settings` ADD COLUMN `setting_group` VARCHAR(64) NOT NULL DEFAULT ''global''', 'DO 0') FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='settings' AND COLUMN_NAME='setting_group');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
SET @s := (SELECT IF(COUNT(*)=0, 'ALTER TABLE `settings` ADD COLUMN `value_type` VARCHAR(32) NOT NULL DEFAULT ''string''', 'DO 0') FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='settings' AND COLUMN_NAME='value_type');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
SET @s := (SELECT IF(COUNT(*)=0, 'ALTER TABLE `settings` ADD COLUMN `updated_by_admin_id` BIGINT UNSIGNED NULL', 'DO 0') FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='settings' AND COLUMN_NAME='updated_by_admin_id');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- ---------- amenities ----------
SET @s := (SELECT IF(COUNT(*)=0, 'ALTER TABLE `amenities` ADD COLUMN `sort_order` INT NOT NULL DEFAULT 0', 'DO 0') FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='amenities' AND COLUMN_NAME='sort_order');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- ---------- notifications ----------
SET @s := (SELECT IF(COUNT(*)=0, 'ALTER TABLE `notifications` ADD COLUMN `url` VARCHAR(500) NULL', 'DO 0') FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='notifications' AND COLUMN_NAME='url');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
SET @s := (SELECT IF(COUNT(*)=0, 'ALTER TABLE `notifications` ADD COLUMN `broadcast_id` BIGINT UNSIGNED NULL', 'DO 0') FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='notifications' AND COLUMN_NAME='broadcast_id');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
SET @s := (SELECT IF(COUNT(*)=0, 'ALTER TABLE `notifications` ADD COLUMN `request_id` INT UNSIGNED NULL', 'DO 0') FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='notifications' AND COLUMN_NAME='request_id');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
SET @s := (SELECT IF(COUNT(*)=0, 'ALTER TABLE `notifications` ADD COLUMN `ad_id` VARCHAR(64) NULL', 'DO 0') FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='notifications' AND COLUMN_NAME='ad_id');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
SET @s := (SELECT IF(COUNT(*)=0, 'ALTER TABLE `notifications` ADD COLUMN `match_percent` DECIMAL(5,2) NULL', 'DO 0') FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='notifications' AND COLUMN_NAME='match_percent');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- ---------- notification_broadcasts ----------
SET @s := (SELECT IF(COUNT(*)=0, 'ALTER TABLE `notification_broadcasts` ADD COLUMN `created_by` BIGINT UNSIGNED NULL', 'DO 0') FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='notification_broadcasts' AND COLUMN_NAME='created_by');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- ============================================================
-- بخش ۳) یکسان‌سازی collation جدول‌های مقایسه با جدول ads
-- (رفع خطای ۱۲۶۷ «Illegal mix of collations» در محیط واقعی)
-- فقط وقتی اجرا می‌شود که collation واقعاً متفاوت باشد.
-- ============================================================
SET @target := (SELECT TABLE_COLLATION FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ads');
SET @cur := (SELECT TABLE_COLLATION FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='compare_items');
SET @s := IF(@target IS NOT NULL AND @cur IS NOT NULL AND @cur <> @target, CONCAT('ALTER TABLE `compare_items` CONVERT TO CHARACTER SET ', SUBSTRING_INDEX(@target, '_', 1), ' COLLATE ', @target), 'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
SET @cur := (SELECT TABLE_COLLATION FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='compare_groups');
SET @s := IF(@target IS NOT NULL AND @cur IS NOT NULL AND @cur <> @target, CONCAT('ALTER TABLE `compare_groups` CONVERT TO CHARACTER SET ', SUBSTRING_INDEX(@target, '_', 1), ' COLLATE ', @target), 'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- راند ۸۳: امتیاز بازدید ملکینو روی آگهی
SET @s := (SELECT IF(COUNT(*)=0, 'ALTER TABLE `ads` ADD COLUMN `melkino_visited` TINYINT(1) NOT NULL DEFAULT 0', 'DO 0') FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ads' AND COLUMN_NAME='melkino_visited');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
SET @s := (SELECT IF(COUNT(*)=0, 'ALTER TABLE `ads` ADD COLUMN `melkino_rating` DECIMAL(3,1) NULL', 'DO 0') FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ads' AND COLUMN_NAME='melkino_rating');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
SET @s := (SELECT IF(COUNT(*)=0, 'ALTER TABLE `ads` ADD COLUMN `melkino_review` TEXT NULL', 'DO 0') FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ads' AND COLUMN_NAME='melkino_review');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- ✅ پایان فایل دیتابیس

-- راند ۴۶: جدول بازخورد تطبیق‌ها (هم‌نوع با request_matches.id)
CREATE TABLE IF NOT EXISTS request_match_feedback (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    request_match_id INT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NULL,
    telegram_id VARCHAR(128) NULL,
    feedback ENUM('like','dislike') NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_rmf_match_user (request_match_id, user_id, telegram_id),
    KEY idx_rmf_match (request_match_id),
    KEY idx_rmf_user (user_id),
    KEY idx_rmf_telegram (telegram_id),
    CONSTRAINT fk_rmf_match FOREIGN KEY (request_match_id) REFERENCES request_matches(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
