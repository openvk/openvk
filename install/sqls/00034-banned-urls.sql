CREATE TABLE `links_banned` (
    `id` bigint UNSIGNED NOT NULL,
    `domain` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL,
    `regexp_rule` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL,
    `reason` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci,
    `initiator` bigint UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

ALTER TABLE `links_banned`
    ADD PRIMARY KEY (`id`);

ALTER TABLE `links_banned`
    MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;
