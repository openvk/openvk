ALTER TABLE `profiles` ADD COLUMN `birthday_privacy` TINYINT(3) UNSIGNED NOT NULL DEFAULT 0;
UPDATE `profiles` SET `birthday_privacy` = 2 WHERE `birthday` = 0;
UPDATE `profiles` SET `birthday` = NULL WHERE `birthday` = 0;
