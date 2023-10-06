CREATE TABLE `noSpam_templates`
(
    `id`       bigint(20) UNSIGNED                 NOT NULL,
    `user`     bigint(20) UNSIGNED                 NOT NULL,
    `model`    longtext COLLATE utf8mb4_unicode_ci NOT NULL,
    `regex`    longtext COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `request`  longtext COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `ban_type` tinyint(4)                          NOT NULL,
    `count`    bigint(20)                          NOT NULL,
    `time`     bigint(20) UNSIGNED                 NOT NULL,
    `items`    longtext COLLATE utf8mb4_unicode_ci NOT NULL,
    `rollback` tinyint(1)                          DEFAULT NULL
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;

ALTER TABLE `noSpam_templates`
    ADD PRIMARY KEY (`id`);

ALTER TABLE `noSpam_templates`
    MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;
