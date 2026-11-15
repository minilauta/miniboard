<?php
require_once __DIR__ . '/db_handle.php';

function select_post(string $board_id, int $post_id): array|bool {
  $dbh = get_db_handle();
  $sth = $dbh->prepare('SELECT * FROM posts WHERE board_id = :board_id AND post_id = :post_id');
  $sth->execute([
    'board_id' => $board_id,
    'post_id' => $post_id,
  ]);
  return $sth->fetch();
}

function select_last_post_by_ip(string $ip): array|bool {
  $dbh = get_db_handle();
  $sth = $dbh->prepare('
    SELECT * FROM posts
    WHERE ip = INET6_ATON(:ip)
    ORDER BY timestamp DESC
    LIMIT 1
  ');
  $sth->execute(['ip' => $ip]);
  return $sth->fetch();
}

function select_last_thread_by_ip(string $ip): array|bool {
  $dbh = get_db_handle();
  $sth = $dbh->prepare('
    SELECT * FROM posts
    WHERE ip = INET6_ATON(:ip) AND parent_id IS NULL
    ORDER BY timestamp DESC
    LIMIT 1
  ');
  $sth->execute(['ip' => $ip]);
  return $sth->fetch();
}

function select_threads(string $session_id, ?int $user_role, ?string $board_id, bool $desc = true, int $offset = 0, int $limit = 10, bool $hidden = false): array|bool {
  $dbh = get_db_handle();
  $sth = null;
  if ($board_id != null) {
    $sth = $dbh->prepare('
      SELECT
        p.*,
        pin.post_id IS NOT NULL AS pinned
      FROM posts AS p
      LEFT JOIN pins AS pin ON pin.session_id = :session_id_pin AND pin.board_id = p.board_id AND pin.post_id = p.post_id
      WHERE p.board_id = :board_id AND p.parent_id IS NULL AND p.post_id ' . ($hidden === true ? '' : 'NOT') . ' IN (
        SELECT post_id FROM hides WHERE session_id = :session_id AND board_id = p.board_id
      )
      AND (p.req_role IS NULL OR (:user_role_1 IS NOT NULL AND :user_role_2 <= p.req_role))
      ORDER BY pinned DESC, p.stickied DESC, p.bumped ' . ($desc === true ? 'DESC' : 'ASC') . '
      LIMIT :limit OFFSET :offset
    ');
    $sth->execute([
      'session_id_pin' => $session_id,
      'session_id' => $session_id,
      'board_id' => $board_id,
      'user_role_1' => $user_role,
      'user_role_2' => $user_role,
      'limit' => $limit,
      'offset' => $offset
    ]);
  } else {
    $sth = $dbh->prepare('
      SELECT
        p.*,
        pin.post_id IS NOT NULL AS pinned
      FROM posts AS p
      LEFT JOIN pins AS pin ON pin.session_id = :session_id_pin AND pin.board_id = p.board_id AND pin.post_id = p.post_id
      WHERE p.board_id IS NOT NULL AND p.parent_id IS NULL AND p.post_id ' . ($hidden === true ? '' : 'NOT') . ' IN (
        SELECT post_id FROM hides WHERE session_id = :session_id AND board_id = p.board_id
      )
      AND (
        NOT EXISTS (SELECT 1 FROM board_filters WHERE session_id = :session_id_bf1)
        OR p.board_id IN (SELECT board_id FROM board_filters WHERE session_id = :session_id_bf2)
      )
      AND (p.req_role IS NULL OR (:user_role_1 IS NOT NULL AND :user_role_2 <= p.req_role))
      ORDER BY pinned DESC, p.bumped ' . ($desc === true ? 'DESC' : 'ASC') . '
      LIMIT :limit OFFSET :offset
    ');
    $sth->execute([
      'session_id_pin' => $session_id,
      'session_id' => $session_id,
      'session_id_bf1' => $session_id,
      'session_id_bf2' => $session_id,
      'user_role_1' => $user_role,
      'user_role_2' => $user_role,
      'limit' => $limit,
      'offset' => $offset
    ]);
  }
  return $sth->fetchAll();
}

function select_posts(string $session_id, ?int $user_role, string $board_id, int $parent_id, bool $desc = true, int $offset = 0, int $limit = 10): array|bool {
  $dbh = get_db_handle();
  $sth = $dbh->prepare('
    SELECT
      p.*,
      h.post_id IS NOT NULL AS hidden,
      pin.post_id IS NOT NULL AS pinned
    FROM posts AS p
    LEFT JOIN hides AS h ON h.session_id = :session_id AND h.board_id = p.board_id AND h.post_id = p.post_id
    LEFT JOIN pins AS pin ON pin.session_id = :session_id_pin AND pin.board_id = p.board_id AND pin.post_id = p.post_id
    WHERE p.board_id = :board_id AND p.parent_id = :parent_id
    AND (p.req_role IS NULL OR (:user_role_1 IS NOT NULL AND :user_role_2 <= p.req_role))
    ORDER BY pinned DESC, p.stickied DESC, p.bumped ' . ($desc === true ? 'DESC' : 'ASC') . '
    LIMIT :limit OFFSET :offset
  ');
  $sth->execute([
    'session_id' => $session_id,
    'session_id_pin' => $session_id,
    'board_id' => $board_id,
    'parent_id' => $parent_id,
    'user_role_1' => $user_role,
    'user_role_2' => $user_role,
    'limit' => $limit,
    'offset' => $offset
  ]);
  return $sth->fetchAll();
}

function select_replies_after(string $session_id, ?int $user_role, string $board_id, int $parent_id, int $post_id, bool $desc = true, int $offset = 0, int $limit = 10): array|bool {
  $dbh = get_db_handle();
  $sth = $dbh->prepare('
    SELECT
      p.*,
      h.post_id IS NOT NULL AS hidden,
      pin.post_id IS NOT NULL AS pinned
    FROM posts AS p
    LEFT JOIN hides AS h ON h.session_id = :session_id AND h.board_id = p.board_id AND h.post_id = p.post_id
    LEFT JOIN pins AS pin ON pin.session_id = :session_id_pin AND pin.board_id = p.board_id AND pin.post_id = p.post_id
    WHERE p.board_id = :board_id AND p.parent_id = :parent_id AND p.post_id > :post_id
    AND (p.req_role IS NULL OR (:user_role_1 IS NOT NULL AND :user_role_2 <= p.req_role))
    ORDER BY pinned DESC, p.stickied DESC, p.bumped ' . ($desc === true ? 'DESC' : 'ASC') . '
    LIMIT :limit OFFSET :offset
  ');
  $sth->execute([
    'session_id' => $session_id,
    'session_id_pin' => $session_id,
    'board_id' => $board_id,
    'parent_id' => $parent_id,
    'post_id' => $post_id,
    'user_role_1' => $user_role,
    'user_role_2' => $user_role,
    'limit' => $limit,
    'offset' => $offset
  ]);
  return $sth->fetchAll();
}

function select_replies_preview(string $session_id, string $board_id, ?int $parent_id, int $offset = 0, int $limit = 10): array|bool {
  $dbh = get_db_handle();
  $sth = $dbh->prepare('
    SELECT t.* FROM (
      (
        SELECT
          p.*,
          h.post_id IS NOT NULL AS hidden,
          pin.post_id IS NOT NULL AS pinned
        FROM posts AS p
        LEFT JOIN hides AS h ON h.session_id = :session_id_1 AND h.board_id = p.board_id AND h.post_id = p.post_id
        LEFT JOIN pins AS pin ON pin.session_id = :session_id_pin_1 AND pin.board_id = p.board_id AND pin.post_id = p.post_id
        WHERE p.board_id = :board_id_1 AND p.parent_id = :parent_id_1
        ORDER BY p.bumped DESC
        LIMIT :limit OFFSET :offset
      )

      UNION

      (
        SELECT
          p.*,
          h.post_id IS NOT NULL AS hidden,
          1 AS pinned
        FROM posts AS p
        LEFT JOIN hides AS h ON h.session_id = :session_id_2 AND h.board_id = p.board_id AND h.post_id = p.post_id
        INNER JOIN pins AS pin ON pin.session_id = :session_id_pin_2 AND pin.board_id = p.board_id AND pin.post_id = p.post_id
        WHERE p.board_id = :board_id_2 AND p.parent_id = :parent_id_2
        LIMIT :limit_pin
      )
    ) AS t
    ORDER BY t.pinned DESC, t.bumped ASC
  ');
  $sth->execute([
    'session_id_1' => $session_id,
    'session_id_pin_1' => $session_id,
    'board_id_1' => $board_id,
    'parent_id_1' => $parent_id,
    'limit' => $limit,
    'offset' => $offset,
    'session_id_2' => $session_id,
    'session_id_pin_2' => $session_id,
    'board_id_2' => $board_id,
    'parent_id_2' => $parent_id,
    'limit_pin' => $limit,
  ]);
  return $sth->fetchAll();
}

function count_threads(string $session_id, ?string $board_id, bool $hidden = false): int|bool {
  $dbh = get_db_handle();
  $sth = null;
  if ($board_id != null) {
    $sth = $dbh->prepare('
      SELECT COUNT(*) FROM posts
      WHERE board_id = :board_id AND parent_id IS NULL AND post_id ' . ($hidden === true ? '' : 'NOT') . ' IN (
        SELECT post_id FROM hides WHERE session_id = :session_id AND board_id = posts.board_id
      )
    ');
    $sth->execute([
      'board_id' => $board_id,
      'session_id' => $session_id,
    ]);
  } else {
    $sth = $dbh->prepare('
      SELECT COUNT(*) FROM posts
      WHERE parent_id IS NULL AND post_id ' . ($hidden === true ? '' : 'NOT') . ' IN (
        SELECT post_id FROM hides WHERE session_id = :session_id AND board_id = posts.board_id
      )
      AND (
        NOT EXISTS (SELECT 1 FROM board_filters WHERE session_id = :session_id_bf1)
        OR board_id IN (SELECT board_id FROM board_filters WHERE session_id = :session_id_bf2)
      )
    ');
    $sth->execute([
      'session_id' => $session_id,
      'session_id_bf1' => $session_id,
      'session_id_bf2' => $session_id,
    ]);
  }
  return $sth->fetchColumn();
}

function count_posts(string $session_id, ?string $board_id, int $parent_id, bool $hidden = false): int|bool {
  $dbh = get_db_handle();
  $sth = null;
  if ($board_id != null) {
    $sth = $dbh->prepare('
      SELECT COUNT(*) FROM posts
      WHERE board_id = :board_id AND parent_id = :parent_id AND post_id ' . ($hidden === true ? '' : 'NOT') . ' IN (
        SELECT post_id FROM hides WHERE session_id = :session_id AND board_id = posts.board_id
      )
    ');
    $sth->execute([
      'board_id' => $board_id,
      'parent_id' => $parent_id,
      'session_id' => $session_id,
    ]);
  } else {
    $sth = $dbh->prepare('
      SELECT COUNT(*) FROM posts
      WHERE parent_id = :parent_id AND post_id ' . ($hidden === true ? '' : 'NOT') . ' IN (
        SELECT post_id FROM hides WHERE session_id = :session_id AND board_id = posts.board_id
      )
    ');
    $sth->execute([
      'parent_id' => $parent_id,
      'session_id' => $session_id,
    ]);
  }
  return $sth->fetchColumn();
}

function insert_post($post): int|bool {
  $dbh = get_db_handle();
  $post_copy = $post;
  $sth = $dbh->prepare('
    INSERT INTO posts (
      post_id,
      board_id,
      parent_id,
      salt,
      req_role,
      ip,
      timestamp,
      bumped,
      role,
      name,
      tripcode,
      email,
      nameblock,
      subject,
      message,
      message_rendered,
      message_truncated,
      password,
      file,
      file_rendered,
      file_hex,
      file_original,
      file_size,
      file_size_formatted,
      file_mime,
      file_meta,
      image_width,
      image_height,
      thumb,
      thumb_width,
      thumb_height,
      audio_album,
      embed,
      country
    )
    VALUES (
      :post_id,
      :board_id,
      :parent_id,
      :salt,
      :req_role,
      INET6_ATON(:ip),
      :timestamp,
      :bumped,
      :role,
      :name,
      :tripcode,
      :email,
      :nameblock,
      :subject,
      :message,
      :message_rendered,
      :message_truncated,
      :password,
      :file,
      :file_rendered,
      :file_hex,
      :file_original,
      :file_size,
      :file_size_formatted,
      :file_mime,
      :file_meta,
      :image_width,
      :image_height,
      :thumb,
      :thumb_width,
      :thumb_height,
      :audio_album,
      :embed,
      :country
    )
  ');
  if ($sth->execute($post_copy) !== TRUE) {
    return false;
  }

  // get post_id by internal PRIMARY KEY id and return it as last_insert_id
  $id = intval($dbh->lastInsertId());
  $sth = $dbh->prepare('SELECT post_id FROM posts WHERE id = :id');
  $sth->execute(['id' => $id]);
  
  return $sth->fetchColumn();
}

function delete_post(string $board_id, int $post_id): bool {
  $dbh = get_db_handle();
  $sth = $dbh->prepare('DELETE FROM posts WHERE board_id = :board_id AND post_id = :post_id');
  return $sth->execute([
    'board_id' => $board_id,
    'post_id' => $post_id
  ]);
}

// TODO: improve this, shit query, was I drunk??
function bump_thread(string $board_id, int $post_id): bool {
  $dbh = get_db_handle();
  $sth = $dbh->prepare('
    UPDATE posts t
    SET t.bumped = (
      SELECT
        p.timestamp
      FROM posts p
      WHERE
        (
          p.board_id = :board_id_1 AND p.parent_id = :post_id_1
          OR
          p.board_id = :board_id_2 AND p.post_id = :post_id_2 AND p.parent_id IS NULL
        )
        AND p.deleted = 0
      ORDER BY p.timestamp DESC
      LIMIT 1
    )
    WHERE t.board_id = :board_id_3 AND t.post_id = :post_id_3 AND t.parent_id IS NULL
  ');
  return $sth->execute([
    'board_id_1' => $board_id,
    'post_id_1' => $post_id,
    'board_id_2' => $board_id,
    'post_id_2' => $post_id,
    'board_id_3' => $board_id,
    'post_id_3' => $post_id
  ]);
}

function select_files_by_md5(string $file_md5): array|bool {
  $dbh = get_db_handle();
  $sth = $dbh->prepare('
    SELECT 
      post_id,
      file,
      file_rendered,
      file_hex,
      file_original,
      file_size,
      file_size_formatted,
      file_mime,
      file_meta,
      image_width,
      image_height,
      thumb,
      thumb_width,
      thumb_height,
      audio_album
    FROM posts
    WHERE file_hex = :file_md5
  ');
  $sth->execute([
    'file_md5' => $file_md5
  ]);
  return $sth->fetchAll();
}

function select_threads_past_offset(string $board_id, int $offset = 100, int $limit = 100): array|bool {
  $dbh = get_db_handle();
  $sth = $dbh->prepare('
    SELECT
      id,
      post_id,
      board_id
    FROM posts
    WHERE
      board_id = :board_id AND parent_id IS NULL
    ORDER BY stickied DESC, bumped DESC
    LIMIT :limit OFFSET :offset
  ');
  $sth->execute([
    'board_id' => $board_id,
    'limit' => $limit,
    'offset' => $offset
  ]);
  return $sth->fetchAll();
}

function anonymize_posts_after(int $anonymize_after): bool {
  $dbh = get_db_handle();
  $sth = $dbh->prepare('
    UPDATE posts
    SET
      ip = INET6_ATON(\'127.0.0.1\')
    WHERE timestamp < :timelimit
  ');
  return $sth->execute([
    'timelimit' => time() - $anonymize_after,
  ]);
}

function select_all_posts(bool $desc = true, int $offset = 0, int $limit = 10): array|bool {
  $dbh = get_db_handle();
  $sth = $dbh->prepare('
    SELECT
      *,
      INET6_NTOA(ip) AS ip_str
    FROM posts
    WHERE parent_id IS NOT NULL
    ORDER BY timestamp ' . ($desc === true ? 'DESC' : 'ASC') . '
    LIMIT :limit OFFSET :offset
  ');
  $sth->execute([
    'limit' => $limit,
    'offset' => $offset
  ]);
  return $sth->fetchAll();
}

function count_all_posts() : int|bool {
  $dbh = get_db_handle();
  $sth = $dbh->prepare('SELECT COUNT(*) FROM posts WHERE parent_id IS NOT NULL');
  $sth->execute();
  return $sth->fetchColumn();
}

function select_all_threads(bool $desc = true, int $offset = 0, int $limit = 10): array|bool {
  $dbh = get_db_handle();
  $sth = $dbh->prepare('
    SELECT
      *,
      INET6_NTOA(ip) AS ip_str
    FROM posts
    WHERE parent_id IS NULL
    ORDER BY timestamp ' . ($desc === true ? 'DESC' : 'ASC') . '
    LIMIT :limit OFFSET :offset
  ');
  $sth->execute([
    'limit' => $limit,
    'offset' => $offset
  ]);
  return $sth->fetchAll();
}

function count_all_threads(): int|bool {
  $dbh = get_db_handle();
  $sth = $dbh->prepare('SELECT COUNT(*) FROM posts WHERE parent_id IS NULL');
  $sth->execute();
  return $sth->fetchColumn();
}

function select_post_with_replies(string $board_id, int $post_id): array|bool {
  $dbh = get_db_handle();
  $sth = $dbh->prepare('
    SELECT
      p.*
    FROM posts p
    WHERE
      p.board_id = :board_id_1 AND p.post_id = :post_id_1
      OR
      p.board_id = :board_id_2 AND p.parent_id = :post_id_2
    ORDER BY p.post_id DESC
  ');
  $sth->execute([
    'board_id_1' => $board_id,
    'post_id_1' => $post_id,
    'board_id_2' => $board_id,
    'post_id_2' => $post_id
  ]);
  return $sth->fetchAll();
}

function select_thread_posts_for_move(string $board_id, int $thread_id): array|bool {
  $dbh = get_db_handle();
  $sth = $dbh->prepare('
    SELECT
      p.*,
      INET6_NTOA(p.ip) AS ip_str
    FROM posts p
    WHERE
      (p.board_id = :board_id_1 AND p.post_id = :post_id_1)
      OR
      (p.board_id = :board_id_2 AND p.parent_id = :post_id_2)
    ORDER BY p.post_id ASC
  ');
  $sth->execute([
    'board_id_1' => $board_id,
    'post_id_1' => $thread_id,
    'board_id_2' => $board_id,
    'post_id_2' => $thread_id
  ]);
  return $sth->fetchAll();
}

function delete_thread_posts(string $board_id, int $thread_id): bool {
  $dbh = get_db_handle();
  $dbh->exec('SET FOREIGN_KEY_CHECKS = 0');
  try {
    $sth = $dbh->prepare('
      DELETE FROM posts
      WHERE board_id = :board_id AND (post_id = :post_id OR parent_id = :parent_id)
    ');
    $result = $sth->execute([
      'board_id' => $board_id,
      'post_id' => $thread_id,
      'parent_id' => $thread_id
    ]);
  } finally {
    $dbh->exec('SET FOREIGN_KEY_CHECKS = 1');
  }
  return $result;
}

function toggle_post_locked(string $board_id, int $post_id): int {
  $dbh = get_db_handle();
  $sth = $dbh->prepare('
    UPDATE posts
    SET
      locked = !locked
    WHERE board_id = :board_id AND post_id = :post_id AND parent_id IS NULL
  ');
  $sth->execute([
    'board_id' => $board_id,
    'post_id' => $post_id
  ]);
  return $sth->rowCount();
}

function toggle_post_stickied(string $board_id, int $post_id): int {
  $dbh = get_db_handle();
  $sth = $dbh->prepare('
    UPDATE posts
    SET
      stickied = !stickied
    WHERE board_id = :board_id AND post_id = :post_id
  ');
  $sth->execute([
    'board_id' => $board_id,
    'post_id' => $post_id
  ]);
  return $sth->rowCount();
}

function insert_import_posts_tinyib(array $db_creds, string $table_name, string $board_id): int {
  if (!preg_match('/^[a-zA-Z0-9_]+$/', $table_name)) {
    throw new DbException('insert_import_posts_tinyib: invalid table name');
  }
  $dbh = get_db_handle_import($db_creds);
  $sth = $dbh->prepare('
    INSERT INTO ' . MB_DB_NAME . '.posts (
      post_id,
      parent_id,
      board_id,
      req_role,
      ip,
      timestamp,
      bumped,
      name,
      tripcode,
      email,
      nameblock,
      subject,
      message,
      message_rendered,
      message_truncated,
      password,
      file,
      file_rendered,
      file_hex,
      file_original,
      file_size,
      file_size_formatted,
      file_mime,
      image_width,
      image_height,
      thumb,
      thumb_width,
      thumb_height,
      audio_album,
      embed,
      country,
      stickied,
      moderated,
      locked,
      deleted,
      imported
    )
    SELECT
      id,
      parent AS parent_id,
      :board_id AS board_id,
      NULL AS req_role,
      INET6_ATON(\'127.0.0.1\'),
      timestamp,
      bumped,
      name,
      tripcode,
      email,
      nameblock,
      subject,
      message,
      message AS message_rendered,
      NULL AS message_truncated,
      password,
      (CASE
        WHEN file LIKE \'%iframe%\' THEN file
        WHEN file != \'\' THEN CONCAT(\'/src/\', file)
        ELSE \'\'
      END) AS file,
      \'\' AS file_rendered,
      file_hex,
      file_original,
      file_size,
      file_size_formatted,
      NULL,
      image_width,
      image_height,
      CONCAT(\'/src/\', thumb) AS thumb,
      thumb_width,
      thumb_height,
      NULL,
      (CASE
        WHEN file LIKE \'%iframe%\' THEN 1
        ELSE 0
      END) AS embed,
      country_code AS country,
      stickied,
      moderated,
      locked,
      0 AS deleted,
      1 AS imported
    FROM ' . $table_name);
  $sth->execute([
    'board_id' => $board_id
  ]);
  return $sth->rowCount();
}

function select_rebuild_posts(string $board_id): array|bool {
  $dbh = get_db_handle();
  $sth = $dbh->prepare('
    SELECT
      post_id,
      parent_id,
      board_id,
      salt,
      INET6_NTOA(ip) AS ip_str,
      timestamp,
      role,
      name,
      email,
      tripcode,
      message,
      file,
      embed,
      imported,
      country
    FROM posts
    WHERE board_id = :board_id
  ');
  $sth->execute([
    'board_id' => $board_id
  ]);
  return $sth->fetchAll();
}

function update_rebuild_post(array $rebuild_post): bool {
  $dbh = get_db_handle();
  $sth = $dbh->prepare('
    UPDATE posts
    SET
      message_rendered = :message_rendered,
      message_truncated = :message_truncated,
      nameblock = :nameblock,
      file_rendered = :file_rendered
    WHERE
      board_id = :board_id AND post_id = :post_id
  ');
  return $sth->execute($rebuild_post);
}

function update_edit_post(array $post): bool {
  $dbh = get_db_handle();
  $sth = $dbh->prepare('
    UPDATE posts
    SET
      name = :name,
      email = :email,
      subject = :subject,
      message = :message,
      message_rendered = :message_rendered,
      message_truncated = :message_truncated,
      nameblock = :nameblock
    WHERE
      board_id = :board_id AND post_id = :post_id
  ');
  return $sth->execute($post);
}

function clear_post_content(string $board_id, int $post_id, ?string $notice = null): bool {
  $dbh = get_db_handle();
  $sth = $dbh->prepare('
    UPDATE posts
    SET
      name = :name,
      tripcode = NULL,
      email = NULL,
      subject = NULL,
      message = :message,
      message_rendered = :message_rendered,
      message_truncated = NULL,
      nameblock = :nameblock,
      password = NULL
    WHERE
      board_id = :board_id AND post_id = :post_id
  ');
  return $sth->execute([
    'board_id' => $board_id,
    'post_id' => $post_id,
    'name' => '',
    'message' => '',
    'message_rendered' => $notice ?? '',
    'nameblock' => '',
  ]);
}

function clear_post_files(string $board_id, int $post_id): bool {
  $dbh = get_db_handle();
  $sth = $dbh->prepare('
    UPDATE posts
    SET
      file = NULL,
      file_rendered = NULL,
      file_hex = NULL,
      file_original = NULL,
      file_size = NULL,
      file_size_formatted = NULL,
      file_mime = NULL,
      file_meta = NULL,
      image_width = NULL,
      image_height = NULL,
      thumb = NULL,
      thumb_width = NULL,
      thumb_height = NULL,
      audio_album = NULL,
      embed = 0
    WHERE
      board_id = :board_id AND post_id = :post_id
  ');
  return $sth->execute([
    'board_id' => $board_id,
    'post_id' => $post_id,
  ]);
}
