-- deleted post as simple boolean flag

ALTER TABLE posts
    ADD COLUMN `deleted` tinyint NOT NULL default 0 AFTER `moderated`,
    ADD INDEX (`deleted`);
