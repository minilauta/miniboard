<?php
require_once __DIR__ . '/db_handle.php';

function select_all_logs(bool $desc = true, int $offset = 0, int $limit = 10): array|bool {
  $dbh = get_db_handle();
  $sth = $dbh->prepare('
    SELECT
      *
    FROM logs
    ORDER BY id ' . ($desc === true ? 'DESC' : 'ASC') . '
    LIMIT :limit OFFSET :offset
  ');
  $sth->execute([
    'limit' => $limit,
    'offset' => $offset
  ]);
  return $sth->fetchAll();
}

function count_all_logs(): int|bool {
  $dbh = get_db_handle();
  $sth = $dbh->prepare('SELECT COUNT(*) FROM logs');
  $sth->execute();
  return $sth->fetchColumn();
}

function insert_log(int $timestamp, string $username, string $message): int|bool {
  $dbh = get_db_handle();
  $sth = $dbh->prepare('
    INSERT INTO logs (
      timestamp,
      username,
      message
    )
    VALUES (
      :timestamp,
      :username,
      :message
    )
  ');
  $result = $sth->execute([
    'timestamp' => $timestamp,
    'username' => $username,
    'message' => $message
  ]);
  if ($result !== TRUE) {
    return false;
  }
  return intval($dbh->lastInsertId());
}
