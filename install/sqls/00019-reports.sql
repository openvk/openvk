CREATE TABLE IF NOT EXISTS `reports` (
    `id` bigint(20) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `user_id` bigint(20) NOT NULL,
    `target_id` bigint(20) NOT NULL,
    `type` varchar(64) NOT NULL,
    `reason` text NOT NULL,
    `deleted` tinyint(1) NOT NULL DEFAULT '0',
    `created` bigint(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE `reports` ADD INDEX (`id`);
