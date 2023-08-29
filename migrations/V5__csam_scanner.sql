-- add table `csam_scanner` to database
-- used to identify potential csam material by perceptual hashing

CREATE TABLE IF NOT EXISTS csam_scanner (
  `id` int unsigned NOT NULL auto_increment,
  `algorithm` varchar(32) NOT NULL,
  `type` varchar(32) NOT NULL,
  `sha256` binary(32) NOT NULL,
  `hash` blob NOT NULL,
  `quality` int unsigned NOT NULL,
  `originator` varchar(32) NOT NULL,
  `upvotes` int unsigned NOT NULL,
  `downvotes` int unsigned NOT NULL,
  `timestamp` int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `algorithm` (`algorithm`),
  KEY `type` (`type`),
  KEY `sha256` (`sha256`),
  KEY `hash` (`hash`),
  KEY `originator` (`originator`),
  KEY `timestamp` (`timestamp`)
) ENGINE=InnoDB
CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
