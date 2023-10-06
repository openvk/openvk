CREATE TABLE IF NOT EXISTS `apps` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `owner` bigint unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `avatar_hash` char(8) DEFAULT NULL,
  `news` bigint DEFAULT NULL,
  `address` varchar(1024) NOT NULL,
  `coins` decimal(20,6) NOT NULL DEFAULT '0.000000',
  `enabled` bit(1) NOT NULL DEFAULT b'0',
  PRIMARY KEY (`id`),
  KEY `owner` (`owner`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

CREATE TABLE IF NOT EXISTS `app_users` (
  `app` bigint unsigned NOT NULL,
  `user` bigint unsigned NOT NULL,
  `access` smallint unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`app`,`user`),
  KEY `app` (`app`),
  KEY `user` (`user`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
