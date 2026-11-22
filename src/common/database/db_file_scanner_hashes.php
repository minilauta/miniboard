<?php
require_once __DIR__ . '/db_handle.php';

function select_all_file_scanner_hashes(bool $desc = true, int $offset = 0, int $limit = 10): array|bool {
  $dbh = get_db_handle();
  $sth = $dbh->prepare('
    SELECT
      *
    FROM file_scanner_hashes
    ORDER BY id ' . ($desc === true ? 'DESC' : 'ASC') . '
    LIMIT :limit OFFSET :offset
  ');
  $sth->execute([
    'limit' => $limit,
    'offset' => $offset
  ]);
  return $sth->fetchAll();
}

function count_all_file_scanner_hashes(): int|bool {
  $dbh = get_db_handle();
  $sth = $dbh->prepare('SELECT COUNT(*) FROM file_scanner_hashes');
  $sth->execute();
  return $sth->fetchColumn();
}
