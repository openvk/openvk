ALTER TABLE `profiles` ADD `allow_anon_comments` BOOLEAN NULL DEFAULT NULL AFTER `client_name`;
ALTER TABLE `groups` ADD `allow_anon_comments` BOOLEAN NULL DEFAULT NULL AFTER `backdrop_2`;
