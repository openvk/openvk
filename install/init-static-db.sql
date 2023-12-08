SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

START TRANSACTION;

CREATE TABLE `albums` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `owner` bigint(20) NOT NULL,
  `name` varchar(36) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `description` longtext COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `access_pragma` tinyint(3) UNSIGNED NOT NULL DEFAULT 255,
  `cover_photo` bigint(20) UNSIGNED DEFAULT NULL,
  `special_type` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `created` bigint(20) UNSIGNED NOT NULL,
  `edited` bigint(20) UNSIGNED DEFAULT NULL,
  `deleted` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

CREATE TABLE `album_relations` (
  `collection` bigint(20) UNSIGNED NOT NULL,
  `media` bigint(20) UNSIGNED NOT NULL,
  `index` bigint(20) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

CREATE TABLE `api_tokens` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user` bigint(20) NOT NULL,
  `secret` char(72) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `deleted` bit(1) NOT NULL DEFAULT b'0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

CREATE TABLE `approval_queue` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `model` varchar(128) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `target` bigint(20) NOT NULL,
  `author` bigint(20) UNSIGNED NOT NULL,
  `assignee` bigint(20) UNSIGNED DEFAULT NULL,
  `status` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `created` bigint(20) UNSIGNED NOT NULL,
  `updated` bigint(20) UNSIGNED NOT NULL,
  `deleted` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

CREATE TABLE `attachments` (
  `attachable_type` varchar(64) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `attachable_id` bigint(20) UNSIGNED DEFAULT NULL,
  `target_type` varchar(64) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `target_id` bigint(20) UNSIGNED DEFAULT NULL,
  `index` bigint(20) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

CREATE TABLE `comments` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `owner` bigint(20) NOT NULL,
  `model` varchar(128) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `target` bigint(20) UNSIGNED NOT NULL,
  `created` bigint(20) UNSIGNED NOT NULL,
  `edited` bigint(20) UNSIGNED DEFAULT NULL,
  `content` longtext COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `flags` tinyint(3) UNSIGNED DEFAULT NULL,
  `ad` tinyint(1) NOT NULL DEFAULT 0,
  `deleted` tinyint(1) DEFAULT 0,
  `virtual_id` bigint(20) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

CREATE TABLE `conv_sockets` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `origin` bigint(20) UNSIGNED NOT NULL,
  `modelId` tinyint(3) UNSIGNED NOT NULL,
  `destination` bigint(20) UNSIGNED NOT NULL,
  `open` bit(1) NOT NULL DEFAULT b'1',
  `visible` bit(1) NOT NULL DEFAULT b'1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

CREATE TABLE `event_turnouts` (
  `user` bigint(20) UNSIGNED NOT NULL,
  `event` bigint(20) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

CREATE TABLE `groups` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `about` mediumtext COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `owner` bigint(20) UNSIGNED DEFAULT NULL,
  `shortcode` varchar(36) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `verified` tinyint(1) NOT NULL DEFAULT 0,
  `type` int(10) UNSIGNED DEFAULT 1,
  `closed` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `block_reason` text COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `wall` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

CREATE TABLE `group_coadmins` (
  `user` bigint(20) UNSIGNED NOT NULL,
  `club` bigint(20) UNSIGNED NOT NULL,
  `comment` varchar(36) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `id` bigint(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

CREATE TABLE `ip` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `ip` varbinary(16) NOT NULL,
  `first_seen` bigint(20) UNSIGNED NOT NULL,
  `rate_limit_counter_start` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `rate_limit_counter` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `rate_limit_violation_counter_start` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `rate_limit_violation_counter` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `banned` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

CREATE TABLE `likes` (
  `origin` bigint(20) UNSIGNED NOT NULL,
  `model` varchar(128) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `target` bigint(20) NOT NULL,
  `index` bigint(20) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

CREATE TABLE `messages` (
  `id` bigint(20) NOT NULL,
  `sender_type` varchar(64) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `sender_id` bigint(20) UNSIGNED NOT NULL,
  `recipient_type` varchar(64) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `recipient_id` bigint(20) UNSIGNED NOT NULL,
  `content` longtext COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `created` bigint(20) NOT NULL,
  `edited` bigint(20) DEFAULT NULL,
  `ad` tinyint(1) NOT NULL DEFAULT 0,
  `deleted` tinyint(1) NOT NULL DEFAULT 0,
  `unread` tinyint(1) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

CREATE TABLE `msg_descriptors` (
  `message` bigint(20) UNSIGNED NOT NULL,
  `socket` bigint(20) UNSIGNED NOT NULL,
  `ack` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `ack_time` bigint(20) UNSIGNED DEFAULT NULL,
  `visible` tinyint(3) UNSIGNED NOT NULL DEFAULT 1,
  `index` bigint(20) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

CREATE TABLE `notes` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `owner` bigint(20) NOT NULL,
  `virtual_id` bigint(20) NOT NULL,
  `created` bigint(20) NOT NULL,
  `edited` bigint(20) DEFAULT NULL,
  `name` varchar(256) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `source` longtext COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `cached_content` longtext COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `deleted` tinyint(4) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

CREATE TABLE `number_verification` (
  `user` bigint(20) UNSIGNED NOT NULL,
  `number` varchar(48) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `code` mediumint(9) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

CREATE TABLE `password_resets` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `profile` bigint(20) UNSIGNED NOT NULL,
  `key` char(64) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `timestamp` bigint(20) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

CREATE TABLE `photos` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `owner` bigint(20) NOT NULL,
  `virtual_id` bigint(20) NOT NULL,
  `created` bigint(20) UNSIGNED NOT NULL,
  `edited` bigint(20) UNSIGNED DEFAULT NULL,
  `hash` char(128) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `deleted` tinyint(1) NOT NULL DEFAULT 0,
  `description` longtext COLLATE utf8mb4_unicode_520_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

CREATE TABLE `posts` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `owner` bigint(20) NOT NULL,
  `wall` bigint(20) NOT NULL,
  `virtual_id` bigint(20) UNSIGNED NOT NULL,
  `created` bigint(20) UNSIGNED NOT NULL,
  `edited` bigint(20) UNSIGNED DEFAULT NULL,
  `content` longtext COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `flags` tinyint(3) UNSIGNED DEFAULT NULL,
  `nsfw` tinyint(1) NOT NULL DEFAULT 0,
  `ad` tinyint(1) NOT NULL DEFAULT 0,
  `deleted` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

CREATE TABLE `profiles` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user` varchar(36) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `first_name` varchar(50) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT 'Jane',
  `last_name` varchar(50) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT 'Doe',
  `pseudo` varchar(50) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `info` longtext COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `about` longtext COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `privacy` bigint(20) UNSIGNED NOT NULL DEFAULT 1099511627775,
  `left_menu` bigint(20) UNSIGNED NOT NULL DEFAULT 1099511627775,
  `sex` tinyint(1) NOT NULL DEFAULT 1,
  `type` tinyint(4) NOT NULL DEFAULT 0,
  `phone` varchar(36) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `email` varchar(90) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `coins` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `since` datetime NOT NULL,
  `block_reason` text COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `verified` tinyint(1) NOT NULL DEFAULT 0,
  `reputation` bigint(20) NOT NULL DEFAULT 1000,
  `shortcode` varchar(36) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `registering_ip` varchar(256) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '127.0.0.1',
  `online` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `birthday` bigint(20) DEFAULT 0,
  `hometown` varchar(60) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `polit_views` int(11) DEFAULT 0,
  `marital_status` int(11) DEFAULT 0,
  `email_contact` varchar(128) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `telegram` varchar(32) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `interests` mediumtext COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `fav_music` mediumtext COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `fav_films` mediumtext COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `fav_shows` mediumtext COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `fav_books` mediumtext COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `fav_quote` mediumtext COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `city` varchar(60) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `address` varchar(60) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `style` varchar(36) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT 'ovk',
  `style_avatar` int(11) DEFAULT 0,
  `show_rating` tinyint(1) DEFAULT 1,
  `milkshake` tinyint(1) NOT NULL DEFAULT 0,
  `nsfw_tolerance` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `notification_offset` bigint(20) UNSIGNED DEFAULT 0,
  `deleted` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `microblog` tinyint(3) UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

CREATE TABLE `stickerpacks` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `slug` varchar(36) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `name` varchar(64) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `description` longtext COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `sold` tinyint(4) NOT NULL DEFAULT 0,
  `price` bigint(20) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

CREATE TABLE `stickers` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `hash` char(128) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `emojis` longtext COLLATE utf8mb4_unicode_520_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

CREATE TABLE `sticker_relations` (
  `sticker` bigint(20) UNSIGNED NOT NULL,
  `pack` bigint(20) UNSIGNED NOT NULL,
  `index` bigint(20) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

CREATE TABLE `subscriptions` (
  `follower` bigint(20) UNSIGNED NOT NULL,
  `model` longtext COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `target` bigint(20) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

CREATE TABLE subscriptions_new (
  `handle` bigint(20) UNSIGNED NOT NULL,
  `initiator` bigint(20) UNSIGNED NOT NULL,
  `targetModel` varchar(128) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `targetId` bigint(20) NOT NULL,
  `targetWallHandle` bigint(20) NOT NULL,
  `shortStatus` tinyint(3) UNSIGNED NOT NULL DEFAULT 1,
  `detailedStatus` tinyint(3) UNSIGNED NOT NULL DEFAULT 1,
  `listName` varchar(64) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `created` bigint(20) UNSIGNED NOT NULL,
  `updated` bigint(20) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

CREATE TABLE `tickets` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `type` bigint(20) UNSIGNED NOT NULL,
  `deleted` tinyint(4) NOT NULL DEFAULT 0,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `name` text COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `text` longtext COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `created` bigint(20) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

CREATE TABLE `tickets_comments` (
  `id` int(11) NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `user_type` int(11) NOT NULL DEFAULT 0,
  `text` longtext COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `created` int(11) NOT NULL,
  `deleted` tinyint(4) NOT NULL DEFAULT 0,
  `ticket_id` bigint(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

CREATE TABLE `videos` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `owner` bigint(20) NOT NULL,
  `virtual_id` bigint(20) NOT NULL,
  `created` bigint(20) UNSIGNED NOT NULL,
  `edited` bigint(20) UNSIGNED DEFAULT NULL,
  `hash` char(128) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `link` varchar(64) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `deleted` tinyint(1) NOT NULL DEFAULT 0,
  `description` longtext COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `name` varchar(64) COLLATE utf8mb4_unicode_520_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;


ALTER TABLE `albums`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `album_relations`
  ADD PRIMARY KEY (`index`),
  ADD KEY `album` (`collection`);

ALTER TABLE `api_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `id` (`id`),
  ADD KEY `secret` (`secret`);

ALTER TABLE `approval_queue`
  ADD PRIMARY KEY (`id`) USING BTREE,
  ADD KEY `approval_queue_ibfk_1` (`assignee`);

ALTER TABLE `attachments`
  ADD PRIMARY KEY (`index`);

ALTER TABLE `comments`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `conv_sockets`
  ADD UNIQUE KEY `id` (`id`);

ALTER TABLE `groups`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `shortcode` (`shortcode`);

ALTER TABLE `group_coadmins`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `ip`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `id` (`id`),
  ADD UNIQUE KEY `ip` (`ip`);

ALTER TABLE `likes`
  ADD PRIMARY KEY (`index`);

ALTER TABLE `messages`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `msg_descriptors`
  ADD UNIQUE KEY `index` (`index`);

ALTER TABLE `notes`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `number_verification`
  ADD PRIMARY KEY (`user`);

ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `photos`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `posts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `wall` (`wall`);

ALTER TABLE `profiles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `phone` (`phone`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `shortcode` (`shortcode`),
  ADD KEY `user` (`user`);

ALTER TABLE `stickerpacks`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`);

ALTER TABLE `stickers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `hash` (`hash`);

ALTER TABLE `sticker_relations`
  ADD PRIMARY KEY (`index`);

ALTER TABLE `subscriptions_new`
  ADD PRIMARY KEY (`handle`),
  ADD UNIQUE KEY `handle` (`handle`),
  ADD KEY `initiator_index` (`initiator`),
  ADD KEY `target_index` (`targetModel`,`targetId`),
  ADD KEY `list_index` (`initiator`,`listName`);

ALTER TABLE `tickets`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `tickets_comments`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `videos`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `albums`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `album_relations`
  MODIFY `index` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `api_tokens`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `approval_queue`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `attachments`
  MODIFY `index` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `comments`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `conv_sockets`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `groups`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `group_coadmins`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

ALTER TABLE `ip`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `likes`
  MODIFY `index` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `messages`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

ALTER TABLE `msg_descriptors`
  MODIFY `index` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `notes`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `password_resets`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `photos`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `posts`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `profiles`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `stickerpacks`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `stickers`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `sticker_relations`
  MODIFY `index` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `tickets`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `tickets_comments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `videos`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;


ALTER TABLE `approval_queue`
  ADD CONSTRAINT `approval_queue_ibfk_1` FOREIGN KEY (`assignee`) REFERENCES `profiles` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

COMMIT;

START TRANSACTION;

INSERT INTO `ChandlerGroups` VALUES (NULL, "OVK\\Subteno", NULL);
INSERT INTO `ChandlerACLRelations` VALUES ("ffffffff-ffff-ffff-ffff-ffffffffffff", (SELECT id FROM ChandlerGroups WHERE name = "OVK\\Subteno"), 64);

INSERT INTO `profiles` (`id`, `user`, `first_name`, `last_name`, `pseudo`, `info`, `about`, `status`, `privacy`, `left_menu`, `sex`, `type`, `phone`, `email`, `coins`, `since`, `block_reason`, `verified`, `reputation`, `shortcode`, `registering_ip`, `online`, `birthday`, `hometown`, `polit_views`, `marital_status`, `email_contact`, `telegram`, `interests`, `fav_music`, `fav_films`, `fav_shows`, `fav_books`, `fav_quote`, `city`, `address`, `style`, `style_avatar`, `show_rating`, `milkshake`, `nsfw_tolerance`, `notification_offset`, `deleted`, `microblog`) VALUES ('1', 'ffffffff-ffff-ffff-ffff-ffffffffffff', 'System', 'Administrator', NULL, NULL, NULL, 'Default System Administrator account', '1099511627775', '1099511627775', '0', '0', NULL, 'admin@localhost.localdomain6', '100', '2018-10-31 15:15:15', NULL, '1', '1000', 'sysop', '::1', '0', '0', NULL, '0', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Arcadia Bay', NULL, 'ovk', '0', '1', '0', '0', '0', '0', '0');

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
