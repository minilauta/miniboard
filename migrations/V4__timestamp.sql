-- add column ´timestamp´ to hides table
-- used to clean up rows created by old inactive sessions

ALTER TABLE hides ADD COLUMN IF NOT EXISTS `timestamp` int unsigned NOT NULL DEFAULT 0 AFTER `post_id`,
                  ADD INDEX (`timestamp`);
