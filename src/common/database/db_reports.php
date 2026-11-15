<?php
require_once __DIR__ . '/db_handle.php';

function insert_report($report): int {
  $dbh = get_db_handle();
  $sth = $dbh->prepare('
    INSERT INTO reports (
      ip,
      timestamp,
      board_id,
      post_id,
      type,
      reason
    )
    VALUES (
      INET6_ATON(:ip),
      :timestamp,
      :board_id,
      :post_id,
      :type,
      :reason
    )
  ');
  $sth->execute($report);
  return intval($dbh->lastInsertId());
}

function select_all_reports(bool $desc = true, int $offset = 0, int $limit = 10): array|bool {
  $dbh = get_db_handle();
  $sth = $dbh->prepare('
    SELECT
      r.id AS r_id,
      r.ip AS r_ip,
      r.timestamp AS r_timestamp,
      r.board_id AS r_board_id,
      r.post_id AS r_post_id,
      r.type AS r_type,
      r.reason AS r_reason,
      r.imported AS r_imported,
      INET6_NTOA(r.ip) AS r_ip_str,
      p.*,
      INET6_NTOA(p.ip) AS ip_str
    FROM reports AS r
    INNER JOIN posts AS p ON r.board_id = p.board_id AND r.post_id = p.post_id
    ORDER BY r.timestamp ' . ($desc === true ? 'DESC' : 'ASC') . ', id
    LIMIT :limit OFFSET :offset
  ');
  $sth->execute([
    'limit' => $limit,
    'offset' => $offset
  ]);
  return $sth->fetchAll();
}

function count_all_reports(): int|bool {
  $dbh = get_db_handle();
  $sth = $dbh->prepare('SELECT COUNT(*) FROM reports');
  $sth->execute();
  return $sth->fetchColumn();
}

function delete_reports_by_post_id(string $board_id, int $post_id): int {
  $dbh = get_db_handle();
  $sth = $dbh->prepare('DELETE FROM reports WHERE board_id = :board_id AND post_id = :post_id');
  $sth->execute([
    'board_id' => $board_id,
    'post_id' => $post_id
  ]);
  return $sth->rowCount();
}

function delete_reports_by_parent_id(string $board_id, int $thread_id): int {
  $dbh = get_db_handle();
  $sth = $dbh->prepare('
    DELETE FROM reports
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
