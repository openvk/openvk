CREATE TABLE `video_relations` (
    `entity` BIGINT(20) NOT NULL, 
    `video` BIGINT(20) UNSIGNED NOT NULL, 
    `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT, 
    PRIMARY KEY (`id`)
) ENGINE = InnoDB CHARSET=utf8mb4 COLLATE utf8mb4_unicode_520_ci;

ALTER TABLE `openvk`.`video_relations` ADD INDEX `entity_video` (`entity`, `video`); 
ALTER TABLE `videos` ADD `length` SMALLINT(5) UNSIGNED NULL DEFAULT NULL AFTER `name`, ADD INDEX `length` (`length`); 
ALTER TABLE `videos` ADD `height` SMALLINT(5) UNSIGNED NULL DEFAULT NULL AFTER `length`, ADD `width` SMALLINT(5) UNSIGNED NULL DEFAULT NULL AFTER `height`; 