<?php
require_once __DIR__ . '/db_handle.php';

function select_expired_ban_thumbs(): array {
  $dbh = get_db_handle();
  $sth = $dbh->prepare('SELECT post_thumb FROM bans WHERE expire < :now AND post_thumb IS NOT NULL');
  $sth->execute(['now' => time()]);
  return $sth->fetchAll(\PDO::FETCH_COLUMN);
}

function cleanup_bans(): int {
  $dbh = get_db_handle();
  $sth = $dbh->prepare('DELETE FROM bans WHERE expire < :now');
  $sth->execute([
    'now' => time()
  ]);
  return $sth->rowCount();
}

function select_all_bans(bool $desc = true, int $offset = 0, int $limit = 10): array|bool {
  $dbh = get_db_handle();
  $sth = $dbh->prepare('
    SELECT
      *,
      INET6_NTOA(ip) AS ip_str,
      INET6_NTOA(ip_end) AS ip_end_str
    FROM bans
    ORDER BY id ' . ($desc === true ? 'DESC' : 'ASC') . '
    LIMIT :limit OFFSET :offset
  ');
  $sth->execute([
    'limit' => $limit,
    'offset' => $offset
  ]);
  return $sth->fetchAll();
}

function select_ban_by_id(int $id): array|bool {
  $dbh = get_db_handle();
  $sth = $dbh->prepare('
    SELECT
      *,
      INET6_NTOA(ip) AS ip_str,
      INET6_NTOA(ip_end) AS ip_end_str
    FROM bans
    WHERE id = :id
  ');
  $sth->execute(['id' => $id]);
  return $sth->fetch();
}

function insert_ban(string $ip_start, string $ip_end, int $duration, string $reason, ?string $comment = null): int {
  $dbh = get_db_handle();
  $sth = $dbh->prepare('
    INSERT INTO bans (ip, ip_end, timestamp, expire, reason, comment)
    VALUES (INET6_ATON(:ip_start), INET6_ATON(:ip_end), :timestamp, :expire, :reason, :comment)
  ');
  $sth->execute([
    'ip_start' => $ip_start,
    'ip_end' => $ip_end,
    'timestamp' => time(),
    'expire' => time() + $duration,
    'reason' => $reason,
    'comment' => $comment
  ]);
  return $sth->rowCount();
}

function delete_ban(int $id): bool {
  $dbh = get_db_handle();
  $sth = $dbh->prepare('DELETE FROM bans WHERE id = :id');
  return $sth->execute(['id' => $id]);
}

function count_all_bans(): int|bool {
  $dbh = get_db_handle();
  $sth = $dbh->prepare('SELECT COUNT(*) FROM bans');
  $sth->execute();
  return $sth->fetchColumn();
}

function update_ban(array $ban): bool {
  $dbh = get_db_handle();
  $sth = $dbh->prepare('
    UPDATE bans
    SET
      timestamp = :timestamp,
      expire = :expire,
      reason = :reason,
      comment = :comment
    WHERE id = :id
  ');
  return $sth->execute([
    'timestamp' => $ban['timestamp'],
    'expire' => $ban['expire'],
    'reason' => $ban['reason'],
    'comment' => $ban['comment'] ?? null,
    'id' => $ban['id']
  ]);
}

function ban_poster_by_post_id(string $board_id, int $post_id, int $duration, string $reason, ?array $post_preview = null, ?string $ip_start = null, ?string $ip_end = null, ?string $comment = null): int {
  $dbh = get_db_handle();

  // by default the banned ip is read straight from the post; when an explicit
  // range is supplied (subnet / WHOIS network ban) store ip .. ip_end instead.
  if ($ip_start !== null) {
    $ip_expr = 'INET6_ATON(:ip_start)';
    $ip_end_expr = 'INET6_ATON(:ip_end)';
    $ip_params = ['ip_start' => $ip_start, 'ip_end' => $ip_end ?? $ip_start];
  } else {
    $ip_expr = '(SELECT ip FROM posts WHERE board_id = :board_id AND post_id = :post_id)';
    $ip_end_expr = 'NULL';
    $ip_params = ['board_id' => $board_id, 'post_id' => $post_id];
  }

  // the post reference and its exact timestamp are always stored so they survive
  // the post being deleted; the visual preview fields only when it was captured.
  $sth = $dbh->prepare("
    INSERT INTO bans (
      ip,
      ip_end,
      timestamp,
      expire,
      reason,
      comment,
      post_board_id,
      post_id,
      post_timestamp,
      post_subject,
      post_nameblock,
      post_message_rendered,
      post_thumb,
      post_thumb_width,
      post_thumb_height
    )
    VALUES (
      {$ip_expr},
      {$ip_end_expr},
      :timestamp,
      :expire,
      :reason,
      :comment,
      :p_board_id,
      :p_post_id,
      (SELECT timestamp FROM posts WHERE board_id = :ts_board_id AND post_id = :ts_post_id),
      :post_subject,
      :post_nameblock,
      :post_message_rendered,
      :post_thumb,
      :post_thumb_width,
      :post_thumb_height
    )
  ");
  $sth->execute(array_merge($ip_params, [
    'timestamp' => time(),
    'expire' => time() + $duration,
    'reason' => $reason,
    'comment' => $comment,
    'p_board_id' => $board_id,
    'p_post_id' => $post_id,
    'ts_board_id' => $board_id,
    'ts_post_id' => $post_id,
    'post_subject' => $post_preview['subject'] ?? null,
    'post_nameblock' => $post_preview['nameblock'] ?? null,
    'post_message_rendered' => $post_preview['message_rendered'] ?? null,
    'post_thumb' => $post_preview['thumb'] ?? null,
    'post_thumb_width' => $post_preview['thumb_width'] ?? null,
    'post_thumb_height' => $post_preview['thumb_height'] ?? null,
  ]));
  $affected = $sth->rowCount();

  $banmsg = '<br><br><span class="banned">(USER WAS BANNED FOR THIS POST';
  if (strlen($reason) > 0) {
    $banmsg .= ': ' . $reason . ')';
  } else {
    $banmsg .= ')';
  }
  $banmsg .= '</span>';

  $sth = $dbh->prepare('
    UPDATE posts
    SET
      message_rendered = concat(message_rendered, :banmsg)
    WHERE board_id = :board_id AND post_id = :post_id
  ');
  $sth->execute([
    'banmsg' => $banmsg,
    'board_id' => $board_id,
    'post_id' => $post_id
  ]);
  
  return $affected;
}

function select_ban(string $ip): array|bool {
  $dbh = get_db_handle();
  $sth = $dbh->prepare('
    SELECT expire, reason FROM bans
    WHERE INET6_ATON(:ip_a) BETWEEN ip AND COALESCE(ip_end, ip)
      AND LENGTH(ip) = LENGTH(INET6_ATON(:ip_b))
      AND :now < expire
    ORDER BY expire DESC
    LIMIT 1
  ');
  $sth->execute([
    'ip_a' => $ip,
    'ip_b' => $ip,
    'now' => time()
  ]);
  return $sth->fetch();
}
