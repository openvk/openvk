CREATE TABLE `bans`
(
    `id`               bigint(20) UNSIGNED NOT NULL,
    `user`             bigint(20) UNSIGNED NOT NULL,
    `initiator`        bigint(20) UNSIGNED NOT NULL,
    `iat`              bigint(20) UNSIGNED NOT NULL,
    `exp`              bigint(20) NOT NULL,
    `time`             bigint(20) NOT NULL,
    `reason`           text COLLATE utf8mb4_unicode_ci NOT NULL,
    `removed_manually` tinyint(1) DEFAULT 0,
    `removed_by`       bigint(20) UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `bans`
    ADD PRIMARY KEY (`id`);

ALTER TABLE `bans`
    MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;
COMMIT;
