<?php
require_once __DIR__ . '/db_handle.php';

function select_pin(string $session_id, string $board_id, int $post_id): array|bool {
  $dbh = get_db_handle();
  $sth = $dbh->prepare('SELECT * FROM pins WHERE session_id = :session_id AND board_id = :board_id AND post_id = :post_id');
  $sth->execute([
    'session_id' => $session_id,
    'board_id' => $board_id,
    'post_id' => $post_id
  ]);
  return $sth->fetch();
}

function insert_pin($pin): int {
  $dbh = get_db_handle();
  $sth = $dbh->prepare('
    INSERT INTO pins (
      session_id,
      board_id,
      post_id,
      timestamp
    )
    VALUES (
      :session_id,
      :board_id,
      :post_id,
      :timestamp
    )
  ');
  $sth->execute($pin);
  return intval($dbh->lastInsertId());
}

function delete_pin($pin): int {
  $dbh = get_db_handle();
  $sth = $dbh->prepare('DELETE FROM pins WHERE session_id = :session_id AND board_id = :board_id AND post_id = :post_id');
  $sth->execute([
    'session_id' => $pin['session_id'],
    'board_id' => $pin['board_id'],
    'post_id' => $pin['post_id']
  ]);
  return $sth->rowCount();
}
