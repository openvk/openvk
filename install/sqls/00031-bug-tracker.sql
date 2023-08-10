/* bugs */

CREATE TABLE `bugs` (
  `id` bigint(20) NOT NULL,
  `reporter` bigint(20) NOT NULL,
  `title` varchar(255) NOT NULL,
  `text` varchar(1000) NOT NULL,
  `product_id` bigint(20) NOT NULL,
  `status` int(11) NOT NULL DEFAULT 0,
  `priority` int(11) NOT NULL DEFAULT 2,
  `device` varchar(255) NOT NULL,
  `reproduced` bigint(20) NOT NULL DEFAULT 0,
  `deleted` tinyint(1) DEFAULT NULL,
  `created` bigint(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE `bugs`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `bugs`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;
COMMIT;

/* bt_products */

CREATE TABLE `bt_products` (
  `id` bigint(20) NOT NULL,
  `creator_id` bigint(20) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` varchar(10000) NOT NULL,
  `created` bigint(20) NOT NULL,
  `closed` tinyint(1) DEFAULT 0,
  `private` tinyint(4) DEFAULT 0,
  `deleted` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE `bt_products`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `bt_products`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;
COMMIT;

/* bt_comments */

CREATE TABLE `bt_comments` (
  `id` bigint(20) NOT NULL,
  `report` bigint(20) NOT NULL,
  `author` bigint(20) NOT NULL,
  `is_moder` tinyint(1) DEFAULT NULL,
  `is_hidden` tinyint(1) DEFAULT NULL,
  `text` longtext NOT NULL,
  `label` varchar(50) NOT NULL,
  `point_actions` bigint(20) DEFAULT NULL,
  `created` bigint(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE `bt_comments`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `bt_comments`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;
COMMIT;

/* bt_products_access */

CREATE TABLE `bt_products_access` (
  `id` bigint(20) NOT NULL,
  `created` bigint(20) NOT NULL,
  `tester` bigint(20) NOT NULL,
  `product` bigint(20) NOT NULL,
  `moderator` bigint(20) NOT NULL,
  `access` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE `bt_products_access`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `bt_products_access`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;
COMMIT;

ALTER TABLE `profiles` ADD `block_in_bt_reason` TEXT NOT NULL AFTER `block_in_support_reason`;

INSERT INTO `chandlergroups` (`id`, `name`, `color`) VALUES ('599342ce-240a-11ed-92bc-5254002d4243', 'Bugtracker Moderators', NULL);
INSERT INTO `chandleraclrelations` (`user`, `group`, `priority`) VALUES ('ffffffff-ffff-ffff-ffff-ffffffffffff', '599342ce-240a-11ed-92bc-5254002d4243', '64');
INSERT INTO `chandleraclgroupspermissions` (`group`, `model`, `context`, `permission`, `status`) VALUES ('599342ce-240a-11ed-92bc-5254002d4243', 'openvk\\Web\\Models\\Repositories\\BugtrackerReports', NULL, 'admin', '1');