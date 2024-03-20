-- add column ´flags´ to boards table
-- used to show poster country flags

ALTER TABLE boards ADD COLUMN IF NOT EXISTS `flags` tinyint unsigned NOT NULL AFTER `hidden`,
                  ADD INDEX (`flags`);
