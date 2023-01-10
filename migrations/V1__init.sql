-- database schema adapted from TinyIB for compatibility
-- TinyIB: https://code.rocketnine.space/tslocum/tinyib

CREATE TABLE IF NOT EXISTS accounts (
  `id` int unsigned NOT NULL auto_increment,
  `username` varchar(256) NOT NULL,
  `password` text NOT NULL,
  `role` tinyint unsigned NOT NULL,
  `lastactive` int unsigned NOT NULL,
  `imported` tinyint NOT NULL default 0,
  PRIMARY KEY (`id`),
  KEY `username` (`username`),
  KEY `role` (`role`),
  KEY `imported` (`imported`)
) ENGINE=InnoDB
CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bans (
  `id` int unsigned NOT NULL auto_increment,
  `ip` varbinary(16) NOT NULL,
  `timestamp` int unsigned NOT NULL,
  `expire` int unsigned NOT NULL,
  `reason` varchar(256) NOT NULL,
  `imported` tinyint NOT NULL default 0,
  PRIMARY KEY (`id`),
  KEY `ip` (`ip`),
  KEY `imported` (`imported`)
) ENGINE=InnoDB
CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS logs (
  `id` int unsigned NOT NULL auto_increment,
  `ip` varbinary(16) NOT NULL,
  `timestamp` int unsigned NOT NULL,
  `message` varchar(256) NOT NULL,
  `imported` tinyint NOT NULL default 0,
  PRIMARY KEY (`id`),
  KEY `ip` (`ip`),
  KEY `imported` (`imported`)
) ENGINE=InnoDB
CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS posts (
  `id` int unsigned NOT NULL auto_increment,
  `parent_id` int unsigned NOT NULL,
  `board_id` varchar(8) NOT NULL,
  `ip` varbinary(16) NOT NULL,
  `timestamp` int unsigned NOT NULL,
  `bumped` int unsigned NOT NULL,
  `name` varchar(75) NOT NULL,
  `nameblock` varchar(256) NOT NULL,
  `tripcode` varchar(24) NULL,
  `email` varchar(75) NULL,
  `subject` varchar(75) NULL,
  `message` text NOT NULL,
  `message_rendered` text NOT NULL,
  `message_truncated` text NULL,
  `password` text NULL,
  `file` varchar(1024) NULL,
  `file_hex` varchar(75) NULL,
  `file_original` varchar(256) NULL,
  `file_size` int unsigned NULL,
  `file_size_formatted` varchar(75) NULL,
  `image_width` smallint unsigned NULL,
  `image_height` smallint unsigned NULL,
  `thumb` varchar(256) NULL,
  `thumb_width` smallint(5) unsigned NULL,
  `thumb_height` smallint(5) unsigned NULL,
  `country_code` varchar(3) NULL,
  `spoiler` tinyint NOT NULL default 0,
  `stickied` tinyint NOT NULL default 0,
  `moderated` tinyint NOT NULL default 1,
  `locked` tinyint NOT NULL default 0,
  `deleted` tinyint NOT NULL default 0,
  `imported` tinyint NOT NULL default 0,
  PRIMARY KEY	(`id`),
  UNIQUE KEY (`board_id`, `id`),
  KEY `parent_id` (`parent_id`),
  KEY `bumped` (`bumped`),
  KEY `stickied` (`stickied`),
  KEY `moderated` (`moderated`),
  KEY `locked` (`locked`),
  KEY `imported` (`imported`)
) ENGINE=InnoDB
CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS hides (
  `session_id` varchar(64) NOT NULL,
  `board_id` varchar(8) NOT NULL,
  `post_id` int unsigned NOT NULL,
  PRIMARY KEY (`session_id`, `board_id`, `post_id`)
) ENGINE=InnoDB
CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS reports (
  `id` int unsigned NOT NULL auto_increment,
  `ip` varbinary(16) NOT NULL,
  `timestamp` int unsigned NOT NULL,
  `board_id` varchar(8) NOT NULL,
  `post_id` int unsigned NOT NULL,
  `type` text NOT NULL,
  `imported` tinyint NOT NULL default 0,
  PRIMARY KEY	(`id`),
  KEY `board_id` (`board_id`),
  KEY `post_id` (`post_id`),
  KEY `imported` (`imported`)
) ENGINE=InnoDB
CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
