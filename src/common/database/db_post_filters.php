<?php
require_once __DIR__ . '/db_handle.php';

function select_all_post_filters(bool $desc = true, int $offset = 0, int $limit = 10): array|bool {
  $dbh = get_db_handle();
  $sth = $dbh->prepare('
    SELECT
      *
    FROM post_filters
    ORDER BY id ' . ($desc === true ? 'DESC' : 'ASC') . '
    LIMIT :limit OFFSET :offset
  ');
  $sth->execute([
    'limit' => $limit,
    'offset' => $offset
  ]);
  return $sth->fetchAll();
}

function count_all_post_filters(): int|bool {
  $dbh = get_db_handle();
  $sth = $dbh->prepare('SELECT COUNT(*) FROM post_filters');
  $sth->execute();
  return $sth->fetchColumn();
}

function select_post_filter_by_id(int $id): array|bool {
  $dbh = get_db_handle();
  $sth = $dbh->prepare('SELECT * FROM post_filters WHERE id = :id');
  $sth->execute(['id' => $id]);
  return $sth->fetch();
}

function select_enabled_post_filters(string $board_id): array|bool {
  $dbh = get_db_handle();
  $sth = $dbh->prepare('
    SELECT
      *
    FROM post_filters
    WHERE enabled = 1
      AND (board_id IS NULL OR board_id = :board_id)
    ORDER BY id ASC
  ');
  $sth->execute(['board_id' => $board_id]);
  return $sth->fetchAll();
}

function insert_post_filter(array $filter): int {
  $dbh = get_db_handle();
  $sth = $dbh->prepare('
    INSERT INTO post_filters (
      board_id,
      match_type,
      pattern,
      action,
      replacement,
      duration,
      reason,
      comment,
      enabled,
      timestamp
    )
    VALUES (
      :board_id,
      :match_type,
      :pattern,
      :action,
      :replacement,
      :duration,
      :reason,
      :comment,
      :enabled,
      :timestamp
    )
  ');
  $sth->execute([
    'board_id' => $filter['board_id'],
    'match_type' => $filter['match_type'],
    'pattern' => $filter['pattern'],
    'action' => $filter['action'],
    'replacement' => $filter['replacement'],
    'duration' => $filter['duration'],
    'reason' => $filter['reason'],
    'comment' => $filter['comment'],
    'enabled' => $filter['enabled'],
    'timestamp' => time()
  ]);
  return intval($dbh->lastInsertId());
}

function update_post_filter(array $filter): bool {
  $dbh = get_db_handle();
  $sth = $dbh->prepare('
    UPDATE post_filters
    SET
      board_id = :board_id,
      match_type = :match_type,
      pattern = :pattern,
      action = :action,
      replacement = :replacement,
      duration = :duration,
      reason = :reason,
      comment = :comment,
      enabled = :enabled
    WHERE id = :id
  ');
  return $sth->execute([
    'board_id' => $filter['board_id'],
    'match_type' => $filter['match_type'],
    'pattern' => $filter['pattern'],
    'action' => $filter['action'],
    'replacement' => $filter['replacement'],
    'duration' => $filter['duration'],
    'reason' => $filter['reason'],
    'comment' => $filter['comment'],
    'enabled' => $filter['enabled'],
    'id' => $filter['id']
  ]);
}

function delete_post_filter(int $id): bool {
  $dbh = get_db_handle();
  $sth = $dbh->prepare('DELETE FROM post_filters WHERE id = :id');
  return $sth->execute(['id' => $id]);
}
