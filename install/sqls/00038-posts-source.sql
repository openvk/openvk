ALTER TABLE `posts`
    ADD COLUMN `source` TEXT NULL DEFAULT NULL AFTER `api_source_name`;
