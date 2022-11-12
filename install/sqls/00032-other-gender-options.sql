ALTER TABLE `profiles` DROP `sex`;
ALTER TABLE `profiles` ADD `gender` VARCHAR(50) NULL DEFAULT NULL AFTER `left_menu`;
