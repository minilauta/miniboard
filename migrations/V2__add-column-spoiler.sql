-- thumbnail spoilers as simple boolean flag

ALTER TABLE posts
ADD COLUMN `spoiler` tinyint NOT NULL default 0 AFTER `thumb_height`;
