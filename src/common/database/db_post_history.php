<?php
require_once __DIR__ . '/db_handle.php';

function insert_post_history(string $board_id, int $post_id, ?int $parent_id, int $event, ?string $dst_board_id = null, ?int $dst_post_id = null): bool {
  $dbh = get_db_handle();
  $sth = $dbh->prepare('
    INSERT INTO post_history (
      board_id,
      post_id,
      parent_id,
      event,
      timestamp,
      dst_board_id,
      dst_post_id
    )
    VALUES (
      :board_id,
      :post_id,
      :parent_id,
      :event,
      :timestamp,
      :dst_board_id,
      :dst_post_id
    )
    ON DUPLICATE KEY UPDATE
      timestamp = VALUES(timestamp),
      dst_board_id = VALUES(dst_board_id),
      dst_post_id = VALUES(dst_post_id)
  ');
  return $sth->execute([
    'board_id' => $board_id,
    'post_id' => $post_id,
    'parent_id' => $parent_id,
    'event' => $event,
    'timestamp' => time(),
    'dst_board_id' => $dst_board_id,
    'dst_post_id' => $dst_post_id
  ]);
}

function select_post_history(string $board_id, int $post_id): array|bool {
  $dbh = get_db_handle();
  $sth = $dbh->prepare('
    SELECT * FROM post_history
    WHERE board_id = :board_id AND post_id = :post_id
    ORDER BY timestamp DESC
    LIMIT 1
  ');
  $sth->execute([
    'board_id' => $board_id,
    'post_id' => $post_id
  ]);
  return $sth->fetch();
}
