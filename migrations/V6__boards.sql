-- add table `boards` to database
-- used to configure board specific settings on database side

CREATE TABLE IF NOT EXISTS boards (
  `id` varchar(8) NOT NULL,
  `name` varchar(32) NOT NULL,
  `description` varchar(128) NULL,
  `type` varchar(8) NOT NULL,
  `anonymous` varchar(32) NOT NULL,
  `hashid_salt` varchar(128) NULL,
  `nsfw` tinyint unsigned NOT NULL,
  `hidden` tinyint unsigned NOT NULL,
  `role` tinyint unsigned NULL,
  `alwaysnoko` tinyint unsigned NOT NULL,
  `threads_per_page` smallint unsigned NOT NULL,
  `threads_per_catalog_page` smallint unsigned NOT NULL,
  `posts_per_preview` tinyint unsigned NOT NULL,
  `truncate` smallint unsigned NOT NULL,
  `max_threads` smallint unsigned NOT NULL,
  `max_replies` smallint unsigned NOT NULL,
  `maxkb` int unsigned NOT NULL,
  `nofileok` tinyint unsigned NOT NULL,
  `max_width` tinyint unsigned NOT NULL,
  `max_height` tinyint unsigned NOT NULL,
  `post_fields` json NOT NULL,
  `mime_types` json NOT NULL,
  `embed_types` json NOT NULL,
  PRIMARY KEY (`id`),
  KEY `type` (`type`),
  KEY `nsfw` (`nsfw`),
  KEY `hidden` (`hidden`),
  KEY `role` (`role`),
  CHECK (JSON_VALID(`post_fields`)),
  CHECK (JSON_VALID(`mime_types`)),
  CHECK (JSON_VALID(`embed_types`))
) ENGINE=InnoDB
CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
