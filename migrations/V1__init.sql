-- database schema adapted from TinyIB for compatibility
-- TinyIB: https://code.rocketnine.space/tslocum/tinyib

CREATE TABLE IF NOT EXISTS accounts (
  `id` int unsigned NOT NULL auto_increment,
  `username` varchar(256) NOT NULL,
  `password` text NOT NULL,
  `role` tinyint unsigned NOT NULL,
  `lastactive` int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `username` (`username`),
  KEY `role` (`role`)
)
CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bans (
  `id` int unsigned NOT NULL auto_increment,
  `ip` varbinary(16) NOT NULL,
  `timestamp` int unsigned NOT NULL,
  `expire` int unsigned NOT NULL,
  `reason` varchar(256) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `ip` (`ip`)
)
CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS logs (
  `id` int unsigned NOT NULL auto_increment,
  `ip` varbinary(16) NOT NULL,
  `timestamp` int unsigned NOT NULL,
  `message` varchar(256) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `ip` (`ip`)
)
CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS posts (
  `id` int unsigned NOT NULL auto_increment,
  `parent_id` int unsigned NOT NULL,
  `board_id` varchar(8) NOT NULL,
  `timestamp` int unsigned NOT NULL,
  `bumped` int unsigned NOT NULL,
  `ip` varbinary(16) NOT NULL,
  `name` varchar(75) NOT NULL,
  `tripcode` varchar(24) NULL,
  `email` varchar(75) NULL,
  `subject` varchar(75) NULL,
  `message` text NOT NULL,
  `message_rendered` text NOT NULL,
  `message_truncated` text NULL,
  `password` text NULL,
  `file` varchar(256) NULL,
  `file_hex` varchar(75) NULL,
  `file_original` varchar(256) NULL,
  `file_size` int unsigned NULL,
  `file_size_formatted` varchar(75) NULL,
  `image_width` smallint unsigned NULL,
  `image_height` smallint unsigned NULL,
  `thumb` varchar(256) NULL,
  `thumb_width` smallint(5) unsigned NULL,
  `thumb_height` smallint(5) unsigned NULL,
  `stickied` tinyint NOT NULL default 0,
  `moderated` tinyint NOT NULL default 1,
  `country_code` varchar(3) NULL,
  PRIMARY KEY	(`id`),
  KEY `parent_id` (`parent_id`),
  KEY `board_id` (`board_id`),
  KEY `bumped` (`bumped`),
  KEY `stickied` (`stickied`),
  KEY `moderated` (`moderated`)
)
CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS hides (
  `session_id` varchar(64) NOT NULL,
  `board_id` varchar(8) NOT NULL,
  `post_id` int unsigned NOT NULL,
  PRIMARY KEY (`session_id`, `board_id`, `post_id`)
)
CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS reports (
  `id` int unsigned NOT NULL auto_increment,
  `ip` varbinary(16) NOT NULL,
  `timestamp` int unsigned NOT NULL,
  `post_id` int unsigned NOT NULL,
  `type` text NOT NULL,
  PRIMARY KEY	(`id`),
  KEY `post_id` (`post_id`)
)
CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
