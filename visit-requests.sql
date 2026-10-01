-- درخواست بازدید ملک — phpMyAdmin
-- preferred_date همیشه میلادی (DATE) ذخیره می‌شود؛ نمایش در سایت شمسی است.

CREATE TABLE IF NOT EXISTS `visit_requests` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ad_id` VARCHAR(64) NOT NULL,
  `ad_title` VARCHAR(500) NULL,
  `user_id` INT NULL,
  `telegram_id` VARCHAR(64) NULL,
  `phone` VARCHAR(30) NULL,
  `name` VARCHAR(200) NULL,
  `preferred_date` DATE NOT NULL COMMENT 'میلادی Y-m-d',
  `preferred_date_fa` VARCHAR(40) NULL COMMENT 'برچسب نمایش شمسی',
  `weekday` VARCHAR(40) NULL,
  `time_slot` VARCHAR(20) NOT NULL DEFAULT 'morning',
  `alternative_datetime` TEXT NULL,
  `advertiser_last_name` VARCHAR(120) NULL,
  `advertiser_phone` VARCHAR(30) NULL,
  `ad_snapshot` LONGTEXT NULL,
  `status` VARCHAR(50) NOT NULL DEFAULT 'new',
  `tracking_code` VARCHAR(30) NULL,
  `archived` TINYINT(1) NOT NULL DEFAULT 0,
  `admin_note` TEXT NULL,
  `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  KEY `idx_ad` (`ad_id`),
  KEY `idx_owner` (`user_id`, `telegram_id`),
  KEY `idx_phone` (`phone`),
  KEY `idx_status` (`status`),
  KEY `idx_date` (`preferred_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @s := (SELECT IF(COUNT(*)=0, 'ALTER TABLE `visit_requests` ADD COLUMN `alternative_datetime` TEXT NULL', 'SELECT 1') FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='visit_requests' AND COLUMN_NAME='alternative_datetime');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @s := (SELECT IF(COUNT(*)=0, 'ALTER TABLE `visit_requests` ADD COLUMN `advertiser_last_name` VARCHAR(120) NULL', 'SELECT 1') FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='visit_requests' AND COLUMN_NAME='advertiser_last_name');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @s := (SELECT IF(COUNT(*)=0, 'ALTER TABLE `visit_requests` ADD COLUMN `advertiser_phone` VARCHAR(30) NULL', 'SELECT 1') FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='visit_requests' AND COLUMN_NAME='advertiser_phone');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @s := (SELECT IF(COUNT(*)=0, 'ALTER TABLE `visit_requests` ADD COLUMN `ad_snapshot` LONGTEXT NULL', 'SELECT 1') FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='visit_requests' AND COLUMN_NAME='ad_snapshot');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @s := (SELECT IF(COUNT(*)=0, 'ALTER TABLE `visit_requests` ADD COLUMN `tracking_code` VARCHAR(30) NULL', 'SELECT 1') FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='visit_requests' AND COLUMN_NAME='tracking_code');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @s := (SELECT IF(COUNT(*)=0, 'ALTER TABLE `visit_requests` ADD COLUMN `archived` TINYINT(1) NOT NULL DEFAULT 0', 'SELECT 1') FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='visit_requests' AND COLUMN_NAME='archived');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

ALTER TABLE `visit_requests` MODIFY COLUMN `status` VARCHAR(50) NOT NULL DEFAULT 'new';
ALTER TABLE `visit_requests` MODIFY COLUMN `preferred_date` DATE NOT NULL COMMENT 'میلادی Y-m-d';

SET @s := (SELECT IF(COUNT(*)=0, 'ALTER TABLE `visit_requests` ADD INDEX `idx_vr_track` (`tracking_code`)', 'SELECT 1') FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='visit_requests' AND INDEX_NAME='idx_vr_track');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

UPDATE `visit_requests` SET `status` = 'new' WHERE `status` IS NULL OR `status` = '';
UPDATE `visit_requests` SET `tracking_code` = CONCAT('VR-', LPAD(id, 5, '0')) WHERE `tracking_code` IS NULL OR `tracking_code` = '';
