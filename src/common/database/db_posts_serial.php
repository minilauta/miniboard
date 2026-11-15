<?php
require_once __DIR__ . '/db_handle.php';

function init_post_auto_increment(string $board_id): void {
  if (!preg_match('/^[a-z0-9_]+$/', $board_id)) {
    throw new DbException('init_post_auto_increment: invalid board_id');
  }
  $dbh = get_db_handle();
  $tbl = 'posts_' . $board_id . '_serial';
  $sth = $dbh->prepare('
    CREATE TABLE IF NOT EXISTS ' . $tbl . ' (
      `id` int unsigned NOT NULL auto_increment,
      `timestamp` int unsigned NOT NULL,
      PRIMARY KEY(`id`)
    ) ENGINE=InnoDB
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
  ');
  if ($sth->execute() !== TRUE) {
    throw new DbException('init_post_auto_increment failed to create table `' . $tbl . '`');
  }
}

function generate_post_auto_increment(string $board_id): int {
  if (!preg_match('/^[a-z0-9_]+$/', $board_id)) {
    throw new DbException('generate_post_auto_increment: invalid board_id');
  }
  $dbh = get_db_handle();
  $tbl = 'posts_' . $board_id . '_serial';
  $sth = $dbh->prepare('
    INSERT INTO ' . $tbl . ' (
      timestamp
    )
    VALUES (
      :timestamp
    )
  ');
  if ($sth->execute(['timestamp' => time()]) !== TRUE) {
    throw new DbException('generate_post_auto_increment failed to generate post ID for table `' . $tbl . '`');
  }
  return intval($dbh->lastInsertId());
}

function refresh_post_auto_increment(string $board_id): void {
  if (!preg_match('/^[a-z0-9_]+$/', $board_id)) {
    throw new DbException('refresh_post_auto_increment: invalid board_id');
  }
  $dbh = get_db_handle();

  // get next auto_increment id
  $sth = $dbh->prepare('SELECT MAX(post_id) FROM posts WHERE board_id = :board_id');
  $sth->execute(['board_id' => $board_id]);
  $auto_increment_id = $sth->fetchColumn();
  if ($auto_increment_id == NULL) {
    $auto_increment_id = 1;
  } else {
    $auto_increment_id++;
  }

  // set next auto_increment id
  $tbl = 'posts_' . $board_id . '_serial';
  $sth = $dbh->prepare('ALTER TABLE ' . $tbl . ' AUTO_INCREMENT = ' . $auto_increment_id);
  if ($sth->execute() !== TRUE) {
    throw new DbException('refresh_post_auto_increment failed to alter table `' . $tbl . '`');
  }
}
