-- ============================================================
-- ملکینو — ساختار کامل دیتابیس (جدول‌های خالی، بدون داده)
-- نسخهٔ ۶ — ستون‌ها و جدول‌ها دقیقاً مطابق کد فعلی
-- ایمپورت در phpMyAdmin هاست (MySQL / MariaDB — از جمله InfinityFree)
-- قابل اجرای مجدد است (CREATE TABLE IF NOT EXISTS)
-- ============================================================
SET NAMES utf8mb4;
SET foreign_key_checks = 0;

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
  `created_by_telegram_id` VARCHAR(30) NULL,
  `default_image_no` TINYINT NULL DEFAULT NULL,
  `melkino_visited` TINYINT(1) NOT NULL DEFAULT 0,
  `melkino_rating` DECIMAL(3,1) NULL,
  `melkino_review` TEXT NULL,
  `building_age` INT NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_numeric` (`numeric_id`),
  KEY `idx_status` (`status`),
  KEY `idx_owner_user` (`owner_user_id`),
  KEY `idx_phone` (`phone`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  `eitaa_id` VARCHAR(64) NULL,
  `eitaa_username` VARCHAR(191) NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_tg` (`telegram_id`),
  UNIQUE KEY `uniq_bale` (`bale_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  `code_hash` VARCHAR(128) NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_otp_phone` (`phone`),
  KEY `idx_otp_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

CREATE TABLE IF NOT EXISTS compare_items (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id INT NULL,
  telegram_id VARCHAR(64) NULL,
  guest_token VARCHAR(64) NULL,
  ad_id VARCHAR(64) NOT NULL,
  group_no TINYINT NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_owner (user_id, telegram_id),
  KEY idx_guest (guest_token),
  KEY idx_ad (ad_id),
  PRIMARY KEY (`id`),
  KEY `idx_owner` (`user_id`, `telegram_id`, `guest_token`),
  KEY `idx_ad` (`ad_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS compare_groups (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id INT NULL,
  telegram_id VARCHAR(64) NULL,
  guest_token VARCHAR(64) NULL,
  group_no TINYINT NOT NULL,
  name VARCHAR(60) NOT NULL DEFAULT '',
  KEY idx_owner (user_id, telegram_id),
  KEY idx_guest (guest_token),
  PRIMARY KEY (`id`),
  KEY `idx_owner` (`user_id`, `telegram_id`, `guest_token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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
  `read_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_owner` (`user_id`, `telegram_id`, `is_read`),
  KEY `idx_notif_broadcast` (`broadcast_id`),
  KEY idx_broadcast (broadcast_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notification_broadcasts (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(255) NOT NULL,
  message TEXT NOT NULL,
  url VARCHAR(500) NULL,
  sent_count INT NOT NULL DEFAULT 0,
  created_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_created (created_at),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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

CREATE TABLE IF NOT EXISTS property_requests (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  tracking_code VARCHAR(40) NULL,
  user_id INT NULL,
  telegram_id VARCHAR(64) NULL,
  phone VARCHAR(30) NULL,
  gender VARCHAR(10) NULL,
  last_name VARCHAR(120) NULL,
  transaction_type VARCHAR(60) NULL,
  property_type VARCHAR(60) NULL,
  location VARCHAR(255) NULL,
  urgency VARCHAR(40) NULL,
  date_needed VARCHAR(40) NULL,
  rahn_kamal VARCHAR(10) NULL,
  min_area VARCHAR(30) NULL,
  max_area VARCHAR(30) NULL,
  min_price VARCHAR(40) NULL,
  max_price VARCHAR(40) NULL,
  min_deposit VARCHAR(40) NULL,
  max_deposit VARCHAR(40) NULL,
  min_rent VARCHAR(40) NULL,
  max_rent VARCHAR(40) NULL,
  min_age VARCHAR(10) NULL,
  max_age VARCHAR(10) NULL,
  is_not_keyed TINYINT(1) NOT NULL DEFAULT 0,
  status VARCHAR(30) NOT NULL DEFAULT 'new',
  additional TEXT NULL,
  property_details LONGTEXT NULL,
  created_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL,
  PRIMARY KEY (id),
  KEY idx_owner (user_id, telegram_id),
  KEY idx_track (tracking_code),
  KEY idx_status (status),
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

CREATE TABLE IF NOT EXISTS request_matches (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  request_id INT NOT NULL,
  ad_id VARCHAR(64) NOT NULL,
  match_percent DECIMAL(5,2) NULL DEFAULT 0,
  matched_transaction VARCHAR(60) NULL,
  matched_property_type VARCHAR(60) NULL,
  location_score INT NULL,
  area_score INT NULL,
  budget_score INT NULL,
  amenities_score INT NULL,
  is_notified TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_req (request_id),
  KEY idx_ad (ad_id),
  PRIMARY KEY (`id`),
  KEY `idx_req` (`request_id`),
  KEY `idx_ad` (`ad_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

CREATE TABLE IF NOT EXISTS login_events (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT NULL,
  telegram_id VARCHAR(64) NULL,
  bale_id VARCHAR(64) NULL,
  username VARCHAR(191) NULL,
  name VARCHAR(191) NULL,
  ip_address VARCHAR(45) NULL,
  ip VARCHAR(45) NULL,
  user_agent VARCHAR(1000) NULL,
  platform VARCHAR(20) NULL,
  language_code VARCHAR(10) NULL,
  created_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  `eitaa_id` VARCHAR(64) NULL,
  PRIMARY KEY (id),
  KEY idx_login_events_user (user_id),
  KEY idx_login_events_tg (telegram_id),
  PRIMARY KEY (`id`),
  KEY `idx_login_events_user` (`user_id`),
  KEY `idx_login_events_tg` (`telegram_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS login_tokens (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  token CHAR(64) NOT NULL,
  user_id BIGINT UNSIGNED NULL,
  telegram_id VARCHAR(191) NULL,
  bale_id VARCHAR(191) NULL,
  user_agent VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  first_used_at DATETIME NULL,
  expires_at DATETIME NOT NULL,
  `token_hash` VARCHAR(128) NOT NULL,
  `platform` VARCHAR(20) NULL,
  `used` TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY uq_login_tokens_token (token),
  KEY idx_login_tokens_user (user_id),
  KEY idx_login_tokens_expires (expires_at),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_token` (`token_hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

CREATE TABLE IF NOT EXISTS settings (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  setting_group VARCHAR(64) NOT NULL DEFAULT 'global',
  setting_key VARCHAR(191) NOT NULL,
  setting_value LONGTEXT NULL,
  value_type VARCHAR(32) NOT NULL DEFAULT 'string',
  updated_by_admin_id BIGINT UNSIGNED NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                        ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_settings_group_key (setting_group, setting_key),
  KEY idx_settings_group (setting_group),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_settings_group_key` (`setting_group`, `setting_key`),
  KEY `idx_settings_group` (`setting_group`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS support_tickets (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT NULL,
  telegram_id VARCHAR(64) NULL,
  phone VARCHAR(30) NULL,
  name VARCHAR(120) NULL,
  subject VARCHAR(255) NULL,
  status VARCHAR(30) NOT NULL DEFAULT 'open',
  created_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL,
  PRIMARY KEY (id),
  KEY idx_support_status (status),
  KEY idx_support_updated_at (updated_at),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB
                DEFAULT CHARSET=utf8mb4
                COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS support_messages (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  ticket_id INT NOT NULL,
  sender_type VARCHAR(20) NOT NULL DEFAULT 'user',
  sender_id VARCHAR(64) NULL,
  sender_name VARCHAR(120) NULL,
  message TEXT NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_support_messages_ticket (ticket_id),
  KEY idx_sender_read (sender_type, is_read),
  PRIMARY KEY (`id`),
  KEY `idx_ticket` (`ticket_id`),
  KEY `idx_sender_read` (`sender_type`, `is_read`)
) ENGINE=InnoDB
                DEFAULT CHARSET=utf8mb4
                COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS channel_publish_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  ad_id VARCHAR(40) NOT NULL,
  platform VARCHAR(10) NOT NULL,
  success TINYINT(1) NOT NULL DEFAULT 0,
  message_id VARCHAR(60) NULL,
  note VARCHAR(255) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_cpl_ad (ad_id),
  INDEX idx_cpl_created (created_at),
  INDEX `idx_cpl_ad` (`ad_id`),
  INDEX `idx_cpl_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS request_match_feedback (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  request_match_id INT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NULL,
  telegram_id VARCHAR(128) NULL,
  feedback ENUM('like','dislike') NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_rmf_match_user
                (request_match_id, user_id, telegram_id),
  KEY idx_rmf_match
                (request_match_id),
  KEY idx_rmf_user
                (user_id),
  KEY idx_rmf_telegram
                (telegram_id),
  UNIQUE KEY uq_rmf_match_user (request_match_id, user_id, telegram_id),
  KEY idx_rmf_match (request_match_id),
  KEY idx_rmf_user (user_id),
  KEY idx_rmf_telegram (telegram_id)
) ENGINE=InnoDB
        DEFAULT CHARSET=utf8mb4
        COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS partnership_requests (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  code VARCHAR(24) NULL,
  user_id INT NULL,
  owner_name VARCHAR(120) NOT NULL DEFAULT '',
  phone VARCHAR(30) NOT NULL DEFAULT '',
  status VARCHAR(30) NOT NULL DEFAULT 'pending',
  property_type VARCHAR(60) NOT NULL DEFAULT '',
  title VARCHAR(500) NOT NULL DEFAULT '',
  area VARCHAR(30) NOT NULL DEFAULT '',
  current_status VARCHAR(60) NOT NULL DEFAULT '',
  photos LONGTEXT NULL,
  city VARCHAR(120) NOT NULL DEFAULT '',
  neighborhood VARCHAR(120) NOT NULL DEFAULT '',
  address VARCHAR(1000) NOT NULL DEFAULT '',
  latitude DECIMAL(10,7) NULL,
  longitude DECIMAL(10,7) NULL,
  location_source VARCHAR(20) NULL,
  passage_width VARCHAR(20) NOT NULL DEFAULT '',
  land_width VARCHAR(20) NOT NULL DEFAULT '',
  br_count VARCHAR(30) NOT NULL DEFAULT '',
  direction VARCHAR(10) NOT NULL DEFAULT '',
  building_age VARCHAR(10) NOT NULL DEFAULT '',
  current_floors VARCHAR(10) NOT NULL DEFAULT '',
  current_units VARCHAR(10) NOT NULL DEFAULT '',
  current_parkings VARCHAR(10) NOT NULL DEFAULT '',
  capacity_known TINYINT(1) NOT NULL DEFAULT 1,
  density VARCHAR(20) NOT NULL DEFAULT '',
  occupancy_rate VARCHAR(20) NOT NULL DEFAULT '',
  buildable_floors VARCHAR(10) NOT NULL DEFAULT '',
  buildable_area VARCHAR(20) NOT NULL DEFAULT '',
  buildable_units VARCHAR(10) NOT NULL DEFAULT '',
  permit_status VARCHAR(40) NOT NULL DEFAULT '',
  permit_number VARCHAR(60) NOT NULL DEFAULT '',
  permit_date VARCHAR(30) NOT NULL DEFAULT '',
  permit_floors VARCHAR(10) NOT NULL DEFAULT '',
  permit_area VARCHAR(20) NOT NULL DEFAULT '',
  owner_share VARCHAR(10) NOT NULL DEFAULT '',
  builder_share VARCHAR(10) NOT NULL DEFAULT '',
  balaghz VARCHAR(30) NOT NULL DEFAULT '',
  balaghz_amount VARCHAR(40) NOT NULL DEFAULT '',
  division_method VARCHAR(40) NOT NULL DEFAULT '',
  unit_shares LONGTEXT NULL,
  partner_parkings VARCHAR(10) NOT NULL DEFAULT '',
  partner_storage VARCHAR(10) NOT NULL DEFAULT '',
  duration VARCHAR(60) NOT NULL DEFAULT '',
  funding VARCHAR(60) NOT NULL DEFAULT '',
  value_from VARCHAR(40) NOT NULL DEFAULT '',
  value_to VARCHAR(40) NOT NULL DEFAULT '',
  notes TEXT NULL,
  deed_status VARCHAR(40) NOT NULL DEFAULT '',
  deed_kind VARCHAR(40) NOT NULL DEFAULT '',
  owners_count VARCHAR(10) NOT NULL DEFAULT '',
  occupancy VARCHAR(40) NOT NULL DEFAULT '',
  legal_status LONGTEXT NULL,
  doc_deed VARCHAR(500) NOT NULL DEFAULT '',
  doc_permit VARCHAR(500) NOT NULL DEFAULT '',
  doc_endjob VARCHAR(500) NOT NULL DEFAULT '',
  doc_other LONGTEXT NULL,
  completeness TINYINT UNSIGNED NOT NULL DEFAULT 0,
  admin_note VARCHAR(1000) NOT NULL DEFAULT '',
  created_at DATETIME NULL,
  updated_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_partnership_code (code),
  KEY idx_part_status (status),
  KEY idx_part_created (created_at),
  KEY idx_part_user (user_id),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_partnership_code` (`code`),
  KEY `idx_part_status` (`status`),
  KEY `idx_part_created` (`created_at`),
  KEY `idx_part_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sms_outbox (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  goal VARCHAR(30) NOT NULL DEFAULT 'manual',
  phone VARCHAR(20) NOT NULL,
  body TEXT NOT NULL,
  meta_json TEXT NULL,
  line VARCHAR(30) NULL,
  rec_id VARCHAR(64) NULL,
  status VARCHAR(15) NOT NULL DEFAULT 'queued',
  fail_reason VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  sent_at DATETIME NULL,
  KEY idx_goal (goal, status),
  KEY idx_phone (phone, created_at),
  KEY idx_created (created_at),
  PRIMARY KEY (`id`),
  KEY `idx_goal` (`goal`, `status`),
  KEY `idx_phone` (`phone`, `created_at`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sms_optouts (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  phone VARCHAR(20) NOT NULL,
  scope VARCHAR(15) NOT NULL DEFAULT 'all',
  source VARCHAR(60) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_phone_scope (phone, scope),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_phone_scope` (`phone`, `scope`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS saved_searches (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id INT NULL,
  phone VARCHAR(20) NULL,
  title VARCHAR(160) NULL,
  tx VARCHAR(60) NULL,
  property_type VARCHAR(60) NULL,
  district VARCHAR(120) NULL,
  min_price VARCHAR(40) NULL,
  max_price VARCHAR(40) NULL,
  min_area VARCHAR(30) NULL,
  max_area VARCHAR(30) NULL,
  rooms VARCHAR(10) NULL,
  notify TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_notified_at DATETIME NULL,
  KEY idx_user (user_id),
  KEY idx_notify (notify),
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_notify` (`notify`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ad_views (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT NULL,
  telegram_id VARCHAR(64) NULL,
  bale_id VARCHAR(64) NULL,
  ad_id VARCHAR(64) NOT NULL,
  ad_title VARCHAR(255) NULL,
  viewed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_user (user_id),
  KEY idx_ad (ad_id),
  KEY idx_viewed (viewed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_phone_audit (
  id INT AUTO_INCREMENT PRIMARY KEY,
  admin_id INT NULL,
  admin_username VARCHAR(100) NULL,
  user_id INT NOT NULL,
  action VARCHAR(50) NOT NULL,
  old_phone VARCHAR(30) NULL,
  new_phone VARCHAR(30) NULL,
  ip_address VARCHAR(45) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_apa_user (user_id),
  INDEX idx_apa_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ads_history (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  ad_id VARCHAR(64) NOT NULL,
  action VARCHAR(60) NOT NULL,
  detail VARCHAR(500) NULL,
  actor VARCHAR(120) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_ah_ad (ad_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS assistant_chat (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  role VARCHAR(20) NOT NULL,
  message MEDIUMTEXT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS assistant_evidence (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  insight_id INT UNSIGNED NOT NULL,
  fact_text VARCHAR(400) NOT NULL,
  source_table VARCHAR(64) NULL,
  source_id VARCHAR(64) NULL,
  KEY idx_insight (insight_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS assistant_insights (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  run_id INT UNSIGNED NOT NULL,
  fingerprint VARCHAR(80) NOT NULL,
  type VARCHAR(40) NOT NULL,
  type_label VARCHAR(80) NOT NULL,
  priority VARCHAR(20) NOT NULL,
  confidence DECIMAL(4,2) NOT NULL DEFAULT 0,
  user_id INT NULL,
  ad_id VARCHAR(64) NULL,
  request_id INT NULL,
  visit_id INT NULL,
  phone VARCHAR(30) NULL,
  person_name VARCHAR(160) NULL,
  tracking_code VARCHAR(40) NULL,
  ad_title VARCHAR(255) NULL,
  title VARCHAR(190) NOT NULL,
  what_happened TEXT NULL,
  why_it_matters TEXT NULL,
  interpretation TEXT NULL,
  action TEXT NULL,
  signals VARCHAR(500) NULL,
  score INT NULL,
  last_activity DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_run (run_id),
  KEY idx_fp (fingerprint),
  KEY idx_prio (priority),
  KEY idx_type (type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS assistant_runs (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  started_at DATETIME NOT NULL,
  finished_at DATETIME NOT NULL,
  views_n INT NOT NULL DEFAULT 0,
  requests_n INT NOT NULL DEFAULT 0,
  matches_n INT NOT NULL DEFAULT 0,
  ads_n INT NOT NULL DEFAULT 0,
  favorites_n INT NOT NULL DEFAULT 0,
  visits_n INT NOT NULL DEFAULT 0,
  users_n INT NOT NULL DEFAULT 0,
  insights_n INT NOT NULL DEFAULT 0,
  notes TEXT NULL,
  KEY idx_finished (finished_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS comm_audit (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  action VARCHAR(80) NOT NULL,
  detail TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS comm_automations (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(160) NOT NULL,
  event_key VARCHAR(40) NOT NULL,
  recipient_type VARCHAR(40) NOT NULL DEFAULT 'admin',
  recipient_phone VARCHAR(20) NULL,
  body TEXT NOT NULL,
  timing VARCHAR(20) NOT NULL DEFAULT 'digest',
  interval_min INT NOT NULL DEFAULT 30,
  min_count INT NOT NULL DEFAULT 1,
  enabled TINYINT(1) NOT NULL DEFAULT 1,
  last_run_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS comm_campaigns (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(160) NOT NULL,
  body TEXT NOT NULL,
  segment_id INT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'draft',
  recipients_n INT NOT NULL DEFAULT 0,
  sent_n INT NOT NULL DEFAULT 0,
  fail_n INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  sent_at DATETIME NULL,
  `scheduled_at` DATETIME NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS comm_clicks (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  tracking_id INT UNSIGNED NOT NULL,
  clicked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_t (tracking_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS comm_contact_tags (
  contact_id INT UNSIGNED NOT NULL,
  tag_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (contact_id, tag_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS comm_contacts (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  phone VARCHAR(20) NOT NULL,
  user_id INT NULL,
  first_name VARCHAR(100) NULL,
  last_name VARCHAR(120) NULL,
  name VARCHAR(160) NULL,
  roles_suggested VARCHAR(255) NULL,
  roles_verified VARCHAR(255) NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'active',
  source VARCHAR(40) NULL,
  properties_n INT NOT NULL DEFAULT 0,
  requests_n INT NOT NULL DEFAULT 0,
  visits_n INT NOT NULL DEFAULT 0,
  views_n INT NOT NULL DEFAULT 0,
  last_activity DATETIME NULL,
  last_sms_at DATETIME NULL,
  city VARCHAR(80) NULL,
  district VARCHAR(120) NULL,
  telegram_id VARCHAR(64) NULL,
  bale_id VARCHAR(64) NULL,
  telegram_username VARCHAR(100) NULL,
  bale_username VARCHAR(191) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL,
  UNIQUE KEY uq_phone (phone),
  KEY idx_status (status),
  KEY idx_act (last_activity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS comm_deferred (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  phone VARCHAR(20) NOT NULL,
  body TEXT NOT NULL,
  contact_id INT NULL,
  campaign_id INT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'WAITING',
  send_after DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_st (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS comm_export_log (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  scope VARCHAR(40) NULL,
  rows_n INT NOT NULL DEFAULT 0,
  format VARCHAR(10) NOT NULL DEFAULT 'xlsx',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS comm_followups (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  contact_id INT UNSIGNED NOT NULL,
  title VARCHAR(190) NOT NULL,
  due_date DATE NULL,
  priority VARCHAR(20) NOT NULL DEFAULT 'MEDIUM',
  notes TEXT NULL,
  related_ad VARCHAR(64) NULL,
  related_request INT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'open',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_c (contact_id),
  KEY idx_due (due_date, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS comm_messages (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  phone VARCHAR(20) NOT NULL,
  contact_id INT NULL,
  body TEXT NOT NULL,
  campaign_id INT NULL,
  template_id INT NULL,
  success TINYINT(1) NOT NULL DEFAULT 0,
  result_message VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_phone (phone),
  KEY idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS comm_notes (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  contact_id INT UNSIGNED NOT NULL,
  body TEXT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_c (contact_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS comm_segments (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  criteria TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS comm_settings (
  k VARCHAR(80) NOT NULL PRIMARY KEY,
  v TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS comm_tags (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(80) NOT NULL,
  UNIQUE KEY uq_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS comm_templates (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  body TEXT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS comm_tracking_links (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  short_code VARCHAR(16) NOT NULL,
  destination_url VARCHAR(500) NOT NULL,
  contact_id INT NULL,
  campaign_id INT NULL,
  message_id INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_code (short_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS lead_sms_log (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  kind VARCHAR(40) NOT NULL,
  request_id INT NULL,
  ad_id VARCHAR(64) NULL,
  user_id INT NULL,
  phone VARCHAR(30) NOT NULL,
  message TEXT NOT NULL,
  success TINYINT(1) NOT NULL DEFAULT 0,
  result_message VARCHAR(255) NULL,
  admin_id INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_kind (kind, created_at),
  KEY idx_phone (phone)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `melkino_audit_log` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `actor_type` VARCHAR(20) NOT NULL DEFAULT 'admin',
  `actor_id` VARCHAR(64) NULL,
  `actor_name` VARCHAR(120) NULL,
  `action` VARCHAR(80) NOT NULL,
  `entity` VARCHAR(60) NULL,
  `entity_id` VARCHAR(64) NULL,
  `details` TEXT NULL,
  `ip_address` VARCHAR(45) NULL,
  `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_action` (`action`),
  KEY `idx_entity` (`entity`, `entity_id`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `melkino_rate_limits` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `bucket` VARCHAR(190) NOT NULL,
  `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_bucket_time` (`bucket`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS promotions (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  title VARCHAR(255) NOT NULL DEFAULT '',
  image_url VARCHAR(500) NOT NULL DEFAULT '',
  link_url VARCHAR(500) NOT NULL DEFAULT '',
  button_text VARCHAR(100) NOT NULL DEFAULT 'مشاهده',
  description VARCHAR(1000) NOT NULL DEFAULT '',
  placement VARCHAR(50) NOT NULL DEFAULT 'all',
  position_after INT UNSIGNED NOT NULL DEFAULT 3,
  repeat_every INT UNSIGNED NOT NULL DEFAULT 0,
  start_date DATETIME NULL,
  end_date DATETIME NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  views INT UNSIGNED NOT NULL DEFAULT 0,
  clicks INT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS visit_requests (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  ad_id VARCHAR(64) NOT NULL,
  ad_title VARCHAR(500) NULL,
  user_id INT NULL,
  telegram_id VARCHAR(64) NULL,
  phone VARCHAR(30) NULL,
  name VARCHAR(200) NULL,
  preferred_date DATE NOT NULL,
  preferred_date_fa VARCHAR(40) NULL,
  weekday VARCHAR(40) NULL,
  time_slot VARCHAR(20) NOT NULL DEFAULT 'morning',
  alternative_datetime TEXT NULL,
  advertiser_last_name VARCHAR(120) NULL,
  advertiser_phone VARCHAR(30) NULL,
  ad_snapshot LONGTEXT NULL,
  status VARCHAR(50) NOT NULL DEFAULT 'new',
  admin_note TEXT NULL,
  created_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL,
  `archived` TINYINT(1) NOT NULL DEFAULT 0,
  `requester_name` VARCHAR(120) NULL,
  `requester_phone` VARCHAR(30) NULL,
  PRIMARY KEY (id),
  KEY idx_ad (ad_id),
  KEY idx_owner (user_id, telegram_id),
  KEY idx_phone (phone),
  KEY idx_status (status),
  KEY idx_date (preferred_date),
  KEY idx_vr_track (tracking_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `location_change_log` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ad_id` VARCHAR(64) NOT NULL,
  `user_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `old_latitude` DECIMAL(10,7) NULL DEFAULT NULL,
  `old_longitude` DECIMAL(10,7) NULL DEFAULT NULL,
  `new_latitude` DECIMAL(10,7) NULL DEFAULT NULL,
  `new_longitude` DECIMAL(10,7) NULL DEFAULT NULL,
  `change_source` VARCHAR(40) NOT NULL DEFAULT '',
  `changed_by` VARCHAR(120) NOT NULL DEFAULT '',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_lcl_ad` (`ad_id`, `id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- حساب ادمین اولیه (برای اولین ورود به پنل)
-- نام کاربری: admin
-- رمز عبور:   Melkino@1404
-- ⚠️ بلافاصله پس از اولین ورود، از پنل ادمین → تب «تغییر رمز»
--    رمز را عوض کنید. این ردیف اگر از قبل موجود باشد دوباره ساخته
--    نمی‌شود (username یکتاست) و رمز حساب موجود را تغییر نمی‌دهد.
-- ============================================================
INSERT INTO admins (username, password_hash, display_name, is_active, updated_at)
SELECT 'admin', '$2y$10$FKjXyDwlsvqsS6j1R38YL.p9wX4gBLl7AKfRfIwbqlvFRrvBGXRbe', 'مدیر ملکینو', 1, NOW()
WHERE NOT EXISTS (SELECT 1 FROM admins WHERE username = 'admin');
