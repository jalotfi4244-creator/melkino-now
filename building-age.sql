-- سن بنا: ستون محاسبه از سال ساخت (سال جاری شمسی − سال ساخت)
-- phpMyAdmin — پس از آپلود فایل‌های PHP این اسکریپت را اجرا کنید.

SET @s := (SELECT IF(COUNT(*)=0, 'ALTER TABLE `ads` ADD COLUMN `building_age` INT NULL DEFAULT NULL', 'SELECT 1')
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ads' AND COLUMN_NAME='building_age');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- پر کردن برای سال‌های عددی لاتین (ارقام فارسی در PHP هنگام بارگذاری صفحه محاسبه می‌شود)
UPDATE `ads`
SET `building_age` = (1405 - CAST(`year` AS UNSIGNED))
WHERE (`building_age` IS NULL OR `building_age` = 0)
  AND `year` IS NOT NULL
  AND `year` REGEXP '^(13|14)[0-9]{2}$'
  AND CAST(`year` AS UNSIGNED) BETWEEN 1300 AND 1405;
