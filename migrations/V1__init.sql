-- database schema adapted from TinyIB for compatibility
-- TinyIB: https://code.rocketnine.space/tslocum/tinyib

CREATE TABLE IF NOT EXISTS accounts (
  `id` mediumint(7) unsigned NOT NULL auto_increment,
  `username` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` mediumint(7) unsigned NOT NULL,
  `lastactive` int(20) unsigned NOT NULL,
  PRIMARY KEY	(`id`)
);

CREATE TABLE IF NOT EXISTS bans (
  `id` mediumint(7) unsigned NOT NULL auto_increment,
  `ip` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `timestamp` int(20) NOT NULL,
  `expire` int(20) NOT NULL,
  `reason` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY	(`id`),
  KEY `ip` (`ip`)
);

CREATE TABLE IF NOT EXISTS logs (
  `id` mediumint(7) unsigned NOT NULL auto_increment,
  `ip` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `timestamp` int(20) NOT NULL,
  `expire` int(20) NOT NULL,
  `reason` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY	(`id`),
  KEY `ip` (`ip`)
);

CREATE TABLE IF NOT EXISTS posts (
  `id` mediumint(7) unsigned NOT NULL auto_increment,
  `board` mediumint(7) unsigned NOT NULL,
  `parent` mediumint(7) unsigned NOT NULL,
  `timestamp` int(20) NOT NULL,
  `bumped` int(20) NOT NULL,
  `ip` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(75) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `tripcode` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(75) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `nameblock` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `subject` varchar(75) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `file` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_hex` varchar(75) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_original` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_size` int(20) unsigned NOT NULL default '0',
  `file_size_formatted` varchar(75) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `image_width` smallint(5) unsigned NOT NULL default '0',
  `image_height` smallint(5) unsigned NOT NULL default '0',
  `thumb` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `thumb_width` smallint(5) unsigned NOT NULL default '0',
  `thumb_height` smallint(5) unsigned NOT NULL default '0',
  `stickied` tinyint(1) NOT NULL default '0',
  `moderated` tinyint(1) NOT NULL default '1',
  `country_code` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY	(`id`),
  KEY `board` (`board`),
  KEY `parent` (`parent`),
  KEY `bumped` (`bumped`),
  KEY `stickied` (`stickied`),
  KEY `moderated` (`moderated`)
);

CREATE TABLE IF NOT EXISTS reports (
  `id` mediumint(7) unsigned NOT NULL auto_increment,
  `ip` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `post` int(20) NOT NULL,
  PRIMARY KEY	(`id`)
);
