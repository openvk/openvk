CREATE TABLE `ignored_sources` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `owner` bigint(20) UNSIGNED NOT NULL,
  `ignored_source` bigint(20) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
