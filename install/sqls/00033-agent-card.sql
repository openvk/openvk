
ALTER TABLE `support_names` ADD `id` bigint(20) UNSIGNED NOT NULL FIRST;

ALTER TABLE `support_names`
    ADD UNIQUE KEY `id` (`id`);

ALTER TABLE `support_names`
    MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;
COMMIT;