-- resize column ´nameblock´ in posts table

ALTER TABLE posts MODIFY COLUMN `nameblock` VARCHAR(1024);
