-- add column ´text´ to boards table
-- used to indicate whether it's a pure text board or not

ALTER TABLE boards ADD COLUMN IF NOT EXISTS `text` tinyint unsigned NOT NULL AFTER `nofileok`,
                  ADD INDEX (`text`);
