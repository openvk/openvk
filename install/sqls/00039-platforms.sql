ALTER TABLE `profiles`
    ADD COLUMN `client_name` TEXT NULL DEFAULT NULL AFTER `backdrop_2`;

ALTER TABLE `posts`
    ADD COLUMN `api_source_name` TEXT NULL DEFAULT NULL AFTER `anonymous`;

ALTER TABLE `api_tokens`
    ADD COLUMN `platform` TEXT NULL DEFAULT NULL AFTER `deleted`;
