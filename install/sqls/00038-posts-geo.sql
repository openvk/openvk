ALTER TABLE `posts`
    ADD `geo`     LONGTEXT       NULL DEFAULT NULL AFTER `deleted`,
    ADD `geo_lat` DECIMAL(12, 8) NULL DEFAULT NULL AFTER `geo`,
    ADD `geo_lon` DECIMAL(12, 8) NULL DEFAULT NULL AFTER `geo_lat`;
