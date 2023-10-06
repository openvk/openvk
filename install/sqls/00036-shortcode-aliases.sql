CREATE TABLE `aliases` (
    `id` bigint UNSIGNED NOT NULL,
    `owner_id` bigint NOT NULL,
    `shortcode` varchar(36) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

ALTER TABLE `aliases`
    ADD PRIMARY KEY (`id`);

ALTER TABLE `aliases`
    MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;
