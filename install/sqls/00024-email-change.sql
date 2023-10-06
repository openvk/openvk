CREATE TABLE IF NOT EXISTS `email_change_verifications` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `profile` bigint(20) unsigned NOT NULL,
  `key` char(64) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `new_email` varchar(90) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `timestamp` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
