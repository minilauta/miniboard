<?php
require_once __DIR__ . '/db_handle.php';

function select_hide(string $session_id, string $board_id, int $post_id): array|bool {
  $dbh = get_db_handle();
  $sth = $dbh->prepare('SELECT * FROM hides WHERE session_id = :session_id AND board_id = :board_id AND post_id = :post_id');
  $sth->execute([
    'session_id' => $session_id,
    'board_id' => $board_id,
    'post_id' => $post_id
  ]);
  return $sth->fetch();
}

function insert_hide($hide): int {
  $dbh = get_db_handle();
  $sth = $dbh->prepare('
    INSERT INTO hides (
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
  $sth->execute($hide);
  return intval($dbh->lastInsertId());
}

function delete_hide($hide): int {
  $dbh = get_db_handle();
  $sth = $dbh->prepare('DELETE FROM hides WHERE session_id = :session_id AND board_id = :board_id AND post_id = :post_id');
  $sth->execute([
    'session_id' => $hide['session_id'],
    'board_id' => $hide['board_id'],
    'post_id' => $hide['post_id']
  ]);
  return $sth->rowCount();
}

function insert_hides(array $hides): int {
  if (count($hides) === 0) {
    return 0;
  }

  $dbh = get_db_handle();
  $inserted = 0;
  foreach (array_chunk($hides, 1000) as $chunk) {
    $values = [];
    $params = [];
    foreach ($chunk as $i => $hide) {
      $values[] = '(:session_id_' . $i . ', :board_id_' . $i . ', :post_id_' . $i . ', :timestamp_' . $i . ')';
      $params['session_id_' . $i] = $hide['session_id'];
      $params['board_id_' . $i] = $hide['board_id'];
      $params['post_id_' . $i] = $hide['post_id'];
      $params['timestamp_' . $i] = $hide['timestamp'];
    }
    $sth = $dbh->prepare('
      INSERT INTO hides (
        session_id,
        board_id,
        post_id,
        timestamp
      )
      VALUES ' . implode(',', $values) . '
      ON DUPLICATE KEY UPDATE timestamp = VALUES(timestamp)
    ');
    if ($sth->execute($params) !== TRUE) {
      throw new DbException('insert_hides failed to insert ' . count($chunk) . ' hides');
    }
    $inserted += count($chunk);
  }
  return $inserted;
}

function select_hides_by_parent_id(string $board_id, int $thread_id): array {
  $dbh = get_db_handle();
  $sth = $dbh->prepare('
    SELECT h.session_id, h.post_id, h.timestamp
    FROM hides h
    INNER JOIN posts p ON p.board_id = h.board_id AND p.post_id = h.post_id
    WHERE h.board_id = :board_id AND (p.post_id = :post_id OR p.parent_id = :parent_id)
  ');
  $sth->execute([
    'board_id' => $board_id,
    'post_id' => $thread_id,
    'parent_id' => $thread_id
  ]);
  return $sth->fetchAll();
}

function delete_hides_by_parent_id(string $board_id, int $thread_id): int {
  $dbh = get_db_handle();
  $sth = $dbh->prepare('
    DELETE FROM hides
    WHERE board_id = :board_id AND post_id IN (
      SELECT post_id FROM posts
      WHERE board_id = :board_id_2 AND (post_id = :post_id OR parent_id = :parent_id)
    )
  ');
  $sth->execute([
    'board_id' => $board_id,
    'board_id_2' => $board_id,
    'post_id' => $thread_id,
    'parent_id' => $thread_id
  ]);
  return $sth->rowCount();
}
