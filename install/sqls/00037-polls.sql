CREATE TABLE IF NOT EXISTS `polls` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `owner` bigint unsigned NOT NULL,
  `title` text NOT NULL,
  `allows_multiple` bit(1) NOT NULL DEFAULT b'0',
  `is_anonymous` bit(1) NOT NULL DEFAULT b'0',
  `can_revote` bit(1) NOT NULL DEFAULT b'0',
  `until` bigint unsigned DEFAULT NULL,
  `ended` bit(1) NOT NULL DEFAULT b'0',
  `deleted` bit(1) NOT NULL DEFAULT b'0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

CREATE TABLE IF NOT EXISTS `poll_options` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `poll` bigint unsigned NOT NULL,
  `name` varchar(512) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `poll` (`poll`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

CREATE TABLE IF NOT EXISTS `poll_votes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user` bigint unsigned NOT NULL,
  `poll` bigint unsigned NOT NULL,
  `option` bigint unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_option` (`user`,`option`),
  KEY `option` (`option`),
  KEY `poll` (`poll`),
  KEY `user_poll` (`user`,`poll`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
