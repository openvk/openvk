CREATE TABLE `support_names` ( 
    `agent` BIGINT UNSIGNED NOT NULL,
    `name` VARCHAR(512) NOT NULL,
    `icon` VARCHAR(1024) NULL DEFAULT NULL, 
    `numerate` BOOLEAN NOT NULL DEFAULT FALSE, 
    PRIMARY KEY (`agent`)
) ENGINE = InnoDB;