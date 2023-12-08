ALTER TABLE `posts` ADD `suggested` TINYINT(2) UNSIGNED NOT NULL DEFAULT '0' AFTER `deleted`; 
ALTER TABLE `posts` ADD INDEX `wall_deleted_suggested` (`wall`, `deleted`, `suggested`); 
