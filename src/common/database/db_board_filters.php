<?php
require_once __DIR__ . '/db_handle.php';

function select_board_filters(string $session_id): array {
  $dbh = get_db_handle();
  $sth = $dbh->prepare('SELECT board_id FROM board_filters WHERE session_id = :session_id');
  $sth->execute(['session_id' => $session_id]);
  return array_column($sth->fetchAll(), 'board_id');
}

function replace_board_filters(string $session_id, array $board_ids): void {
  $dbh = get_db_handle();
  $sth = $dbh->prepare('DELETE FROM board_filters WHERE session_id = :session_id');
  $sth->execute(['session_id' => $session_id]);

  if (count($board_ids) === 0) {
    return;
  }

  $values = [];
  $params = [];
  foreach ($board_ids as $i => $board_id) {
    $values[] = '(:session_id_' . $i . ', :board_id_' . $i . ')';
    $params['session_id_' . $i] = $session_id;
    $params['board_id_' . $i] = $board_id;
  }
  $sth = $dbh->prepare('INSERT INTO board_filters (session_id, board_id) VALUES ' . implode(',', $values));
  $sth->execute($params);
}
