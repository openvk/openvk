-- Apply these two commands if you installed OpenVK before 12th November 2023 OR if it's just doesn't work out of box, then apply this file again
-- DROP TABLE `audios`;
-- DROP TABLE `audio_relations`;

CREATE TABLE IF NOT EXISTS `audios` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `owner` bigint unsigned NOT NULL,
  `virtual_id` bigint unsigned NOT NULL,
  `created` bigint unsigned NOT NULL,
  `edited` bigint unsigned DEFAULT NULL,
  `hash` char(128) NOT NULL,
  `length` smallint unsigned NOT NULL,
  `segment_size` decimal(20,6) NOT NULL DEFAULT '6.000000' COMMENT 'Size in seconds of each segment',
  `kid` binary(16) NOT NULL,
  `key` binary(16) NOT NULL,
  `token` binary(28) NOT NULL COMMENT 'Key to access original file',
  `listens` bigint unsigned NOT NULL DEFAULT '0',
  `performer` varchar(256) NOT NULL,
  `name` varchar(256) NOT NULL,
  `lyrics` text,
  `genre` enum('Blues','Big Band','Classic Rock','Chorus','Country','Easy Listening','Dance','Acoustic','Disco','Humour','Funk','Speech','Grunge','Chanson','Hip-Hop','Opera','Jazz','Chamber Music','Metal','Sonata','New Age','Symphony','Oldies','Booty Bass','Other','Primus','Pop','Porn Groove','R&B','Satire','Rap','Slow Jam','Reggae','Club','Rock','Tango','Techno','Samba','Industrial','Folklore','Alternative','Ballad','Ska','Power Ballad','Death Metal','Rhythmic Soul','Pranks','Freestyle','Soundtrack','Duet','Euro-Techno','Punk Rock','Ambient','Drum Solo','Trip-Hop','A Cappella','Vocal','Euro-House','Jazz+Funk','Dance Hall','Fusion','Goa','Trance','Drum & Bass','Classical','Club-House','Instrumental','Hardcore','Acid','Terror','House','Indie','Game','BritPop','Sound Clip','Negerpunk','Gospel','Polsk Punk','Noise','Beat','AlternRock','Christian Gangsta Rap','Bass','Heavy Metal','Soul','Black Metal','Punk','Crossover','Space','Contemporary Christian','Meditative','Christian Rock','Instrumental Pop','Merengue','Instrumental Rock','Salsa','Ethnic','Thrash Metal','Gothic','Anime','Darkwave','JPop','Techno-Industrial','Synthpop','Electronic','Abstract','Pop-Folk','Art Rock','Eurodance','Baroque','Dream','Bhangra','Southern Rock','Big Beat','Comedy','Breakbeat','Cult','Chillout','Gangsta Rap','Downtempo','Top 40','Dub','Christian Rap','EBM','Pop / Funk','Eclectic','Jungle','Electro','Native American','Electroclash','Cabaret','Emo','New Wave','Experimental','Psychedelic','Garage','Rave','Global','Showtunes','IDM','Trailer','Illbient','Lo-Fi','Industro-Goth','Tribal','Jam Band','Acid Punk','Krautrock','Acid Jazz','Leftfield','Polka','Lounge','Retro','Math Rock','Musical','New Romantic','Rock & Roll','Nu-Breakz','Hard Rock','Post-Punk','Folk','Post-Rock','Folk-Rock','Psytrance','National Folk','Shoegaze','Swing','Space Rock','Fast Fusion','Trop Rock','Bebob','World Music','Latin','Neoclassical','Revival','Audiobook','Celtic','Audio Theatre','Bluegrass','Neue Deutsche Welle','Avantgarde','Podcast','Gothic Rock','Indie Rock','Progressive Rock','G-Funk','Psychedelic Rock','Dubstep','Symphonic Rock','Garage Rock','Slow Rock','Psybient','Psychobilly','Touhou') DEFAULT NULL,
  `explicit` tinyint(1) NOT NULL DEFAULT '0',
  `withdrawn` tinyint(1) NOT NULL DEFAULT '0',
  `processed` tinyint unsigned NOT NULL DEFAULT '0',
  `checked` bigint NOT NULL DEFAULT '0' COMMENT 'Last time the audio availability was checked',
  `unlisted` tinyint(1) NOT NULL DEFAULT '0',
  `deleted` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `owner_virtual_id` (`owner`,`virtual_id`),
  KEY `genre` (`genre`),
  KEY `unlisted` (`unlisted`),
  KEY `listens` (`listens`),
  KEY `deleted` (`deleted`),
  KEY `length` (`length`),
  KEY `listens_genre` (`listens`,`genre`),
  FULLTEXT KEY `performer_name` (`performer`,`name`),
  FULLTEXT KEY `lyrics` (`lyrics`),
  FULLTEXT KEY `performer` (`performer`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

CREATE TABLE IF NOT EXISTS `audio_listens` (
  `entity` bigint NOT NULL,
  `audio` bigint unsigned NOT NULL,
  `time` bigint unsigned NOT NULL,
  `index` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT 'Workaround for Nette DBE bug',
  `playlist` bigint(20) UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`index`),
  KEY `audio` (`audio`),
  KEY `user` (`entity`) USING BTREE,
  KEY `user_time` (`entity`,`time`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

CREATE TABLE IF NOT EXISTS `audio_relations` (
  `entity` bigint NOT NULL,
  `audio` bigint unsigned NOT NULL,
  `index` bigint unsigned NOT NULL AUTO_INCREMENT,
  PRIMARY KEY (`index`),
  KEY `user` (`entity`) USING BTREE,
  KEY `entity_audio` (`entity`,`audio`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

CREATE TABLE IF NOT EXISTS `playlists` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `owner` bigint NOT NULL,
  `name` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `description` varchar(2048) DEFAULT NULL,
  `cover_photo_id` bigint unsigned DEFAULT NULL,
  `length` int unsigned NOT NULL DEFAULT '0',
  `special_type` tinyint unsigned NOT NULL DEFAULT '0',
  `created` bigint unsigned DEFAULT NULL,
  `listens` bigint(20) unsigned NOT NULL DEFAULT 0,
  `edited` bigint unsigned DEFAULT NULL,
  `deleted` tinyint unsigned DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `owner_deleted` (`owner`,`deleted`),
  FULLTEXT KEY `title_description` (`name`,`description`),
  FULLTEXT KEY `title` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

CREATE TABLE IF NOT EXISTS `playlist_imports` (
  `entity` bigint NOT NULL,
  `playlist` bigint unsigned NOT NULL,
  `index` bigint unsigned NOT NULL AUTO_INCREMENT,
  PRIMARY KEY (`index`) USING BTREE,
  KEY `user` (`entity`) USING BTREE,
  KEY `entity_audio` (`entity`,`playlist`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

CREATE TABLE IF NOT EXISTS `playlist_relations` (
  `collection` bigint unsigned NOT NULL,
  `media` bigint unsigned NOT NULL,
  `index` bigint unsigned NOT NULL AUTO_INCREMENT,
  PRIMARY KEY (`index`) USING BTREE,
  KEY `playlist` (`collection`) USING BTREE,
  KEY `audio` (`media`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

ALTER TABLE `groups` ADD `everyone_can_upload_audios` TINYINT(1) NOT NULL DEFAULT '0' AFTER `backdrop_2`; 
ALTER TABLE `profiles` ADD `last_played_track` BIGINT(20) UNSIGNED NULL DEFAULT NULL AFTER `client_name`, ADD `audio_broadcast_enabled` TINYINT(1) UNSIGNED NOT NULL DEFAULT '0' AFTER `last_played_track`; 
ALTER TABLE `groups` ADD `last_played_track` BIGINT(20) UNSIGNED NULL DEFAULT NULL AFTER `everyone_can_upload_audios`, ADD `audio_broadcast_enabled` TINYINT(1) UNSIGNED NOT NULL DEFAULT '0' AFTER `last_played_track`; 
