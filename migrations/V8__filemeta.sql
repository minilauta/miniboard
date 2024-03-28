-- add few columns for file metadata to posts table

ALTER TABLE posts ADD COLUMN IF NOT EXISTS `file_mime` varchar(256) NULL AFTER `file_size_formatted`,
                  ADD INDEX (`file_mime`);

ALTER TABLE posts ADD COLUMN IF NOT EXISTS `audio_album` varchar(256) NULL AFTER `thumb_height`;
