CREATE TABLE IF NOT EXISTS `links` (
  `id` bigint(20) unsigned NOT NULL,
  `owner` bigint(20) NOT NULL,
  `title` varchar(128) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `description` varchar(128) COLLATE utf8mb4_unicode_520_ci,
  `url` varchar(128) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `icon_hash` char(128) COLLATE utf8mb4_unicode_520_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

ALTER TABLE `links`
  ADD PRIMARY KEY (`id`),
  ADD KEY `owner` (`owner`);

ALTER TABLE `links`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;
