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

function select_file_scanner_hash_by_id(int $id): array|bool {
  $dbh = get_db_handle();
  $sth = $dbh->prepare('SELECT * FROM file_scanner_hashes WHERE id = :id');
  $sth->execute(['id' => $id]);
  return $sth->fetch();
}

function select_file_scanner_hashes_by_sha256(string $sha256_hex): array|bool {
  $dbh = get_db_handle();
  $sth = $dbh->prepare('SELECT * FROM file_scanner_hashes WHERE sha256 = UNHEX(:sha256) ORDER BY id ASC');
  $sth->execute(['sha256' => $sha256_hex]);
  return $sth->fetchAll();
}

function insert_file_scanner_hash(array $row): int {
  $dbh = get_db_handle();
  $sth = $dbh->prepare('
    INSERT IGNORE INTO file_scanner_hashes (
      sha256,
      type,
      content_type,
      comment,
      algorithm,
      hash,
      quality,
      originator,
      upvotes,
      downvotes,
      timestamp
    )
    VALUES (
      UNHEX(:sha256),
      :type,
      :content_type,
      :comment,
      :algorithm,
      UNHEX(:hash),
      :quality,
      :originator,
      0,
      0,
      :timestamp
    )
  ');
  $sth->execute([
    'sha256' => $row['sha256'],
    'type' => $row['type'],
    'content_type' => $row['content_type'],
    'comment' => $row['comment'],
    'algorithm' => $row['algorithm'],
    'hash' => $row['hash'],
    'quality' => $row['quality'],
    'originator' => $row['originator'],
    'timestamp' => time()
  ]);
  return $sth->rowCount();
}

function update_file_scanner_hashes(array $ids, string $content_type, ?string $comment): int {
  if (empty($ids)) {
    return 0;
  }

  $dbh = get_db_handle();
  $in = implode(',', array_fill(0, count($ids), '?'));
  $sth = $dbh->prepare("
    UPDATE file_scanner_hashes
    SET
      content_type = ?,
      comment = ?
    WHERE id IN ({$in})
  ");
  $sth->execute(array_merge([$content_type, $comment], array_map('intval', array_values($ids))));
  return $sth->rowCount();
}

function delete_file_scanner_hashes(array $ids): int {
  if (empty($ids)) {
    return 0;
  }

  $dbh = get_db_handle();
  $in = implode(',', array_fill(0, count($ids), '?'));
  $sth = $dbh->prepare("DELETE FROM file_scanner_hashes WHERE id IN ({$in})");
  $sth->execute(array_map('intval', array_values($ids)));
  return $sth->rowCount();
}
