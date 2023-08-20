<?php
require_once __DIR__ . '/config.php';

/**
 * Returns a handle to the database connection context.
 */
function get_db_handle(): PDO {
  global $dbh;

  if ($dbh != null) {
    return $dbh;
  }

  $mb_db_host = MB_DB_HOST;
  $mb_db_name = MB_DB_NAME;

  $dbh = new PDO("mysql:host={$mb_db_host};dbname={$mb_db_name}", MB_DB_USER, MB_DB_PASS, [
    PDO::ATTR_PERSISTENT => true,
    PDO::ATTR_EMULATE_PREPARES => false
  ]);

  return $dbh;
}

// ROOT/general related functions below
// ----------------------------

function select_site_stats(): array|bool {
  $dbh = get_db_handle();
  $sth = $dbh->prepare('
    SELECT
      MAX(sq1.total_posts) AS total_posts,
      MAX(sq1.current_posts) AS current_posts,
      MAX(sq1.unique_posters) AS unique_posters,
      MAX(sq1.imported_posts) AS imported_posts,
      MAX(sq1.current_files) AS current_files,
      MAX(sq1.active_content) AS active_content
    FROM (
      SELECT
        SUM(sq11.total_posts) AS total_posts,
        NULL AS current_posts,
        NULL AS unique_posters,
        NULL AS imported_posts,
        NULL AS current_files,
        NULL AS active_content
      FROM (
        SELECT
          board_id AS board_id,
          MAX(post_id) AS total_posts
        FROM posts
        GROUP BY board_id
      ) AS sq11
      
      UNION ALL

      SELECT
        NULL AS total_posts,
        COUNT(*) AS current_posts,
        NULL AS unique_posters,
        NULL AS imported_posts,
        NULL AS current_files,
        NULL AS active_content
      FROM posts
      
      UNION ALL

      SELECT
        NULL AS total_posts,
        NULL AS current_posts,
        COUNT(DISTINCT(ip)) AS unique_posters,
        NULL AS imported_posts,
        NULL AS current_files,
        NULL AS active_content
      FROM posts
      
      UNION ALL

      SELECT
        NULL AS total_posts,
        NULL AS current_posts,
        NULL AS unique_posters,
        COUNT(*) AS imported_posts,
        NULL AS current_files,
        NULL AS active_content
      FROM posts
      WHERE imported = 1

      UNION ALL

      SELECT
        NULL AS total_posts,
        NULL AS current_posts,
        NULL AS unique_posters,
        NULL AS imported_posts,
        COUNT(DISTINCT(file_hex)) AS current_files,
        NULL AS active_content
      FROM posts

      UNION ALL
      
      SELECT
        NULL AS total_posts,
        NULL AS current_posts,
        NULL AS unique_posters,
        NULL AS imported_posts,
        NULL AS current_files,
        SUM(sq12.file_size) AS active_content
      FROM (
        SELECT
          file_hex,
          file_size
        FROM posts
        GROUP BY file_hex
      ) AS sq12
    ) AS sq1
  ');
  $sth->execute();
  return $sth->fetch();
}

function cleanup_bans(): int {
  $dbh = get_db_handle();
  $sth = $dbh->prepare('DELETE FROM bans WHERE expire < :now');
  $sth->execute([
    'now' => time()
  ]);
  return $sth->rowCount();
}

// POST related functions below
// ----------------------------

function init_post_auto_increment(string $board_id) {
  $dbh = get_db_handle();
  $tbl = 'posts_' . $board_id . '_serial';
  $sth = $dbh->prepare('
    CREATE TABLE IF NOT EXISTS ' . $tbl . ' (
      `id` int unsigned NOT NULL auto_increment,
      `timestamp` int unsigned NOT NULL,
      PRIMARY KEY(`id`)
    ) ENGINE=InnoDB
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
  ');
  if ($sth->execute() !== TRUE) {
    throw new DbException('init_post_auto_increment failed to create table `' . $tbl . '`');
  }
}

function generate_post_auto_increment(string $board_id): int {
  $dbh = get_db_handle();
  $tbl = 'posts_' . $board_id . '_serial';
  $sth = $dbh->prepare('
    INSERT INTO ' . $tbl . ' (
      timestamp
    )
    VALUES (
      :timestamp
    )
  ');
  if ($sth->execute(['timestamp' => time()]) !== TRUE) {
    throw new DbException('generate_post_auto_increment failed to generate post ID for table `' . $tbl . '`');
  }
  return intval($dbh->lastInsertId());
}

function refresh_post_auto_increment(string $board_id) {
  $dbh = get_db_handle();

  // get next auto_increment id
  $sth = $dbh->prepare('SELECT MAX(post_id) FROM posts WHERE board_id = :board_id');
  $sth->execute(['board_id' => $board_id]);
  $auto_increment_id = $sth->fetchColumn();
  if ($auto_increment_id == NULL) {
    $auto_increment_id = 1;
  } else {
    $auto_increment_id++;
  }

  // set next auto_increment id
  $tbl = 'posts_' . $board_id . '_serial';
  $sth = $dbh->prepare('ALTER TABLE ' . $tbl . ' AUTO_INCREMENT = ' . $auto_increment_id);
  if ($sth->execute() !== TRUE) {
    throw new DbException('refresh_post_auto_increment failed to alter table `' . $tbl . '`');
  }
}

function select_post(string $board_id, int $post_id, bool $deleted = false): array|bool {
  $dbh = get_db_handle();
  $sth = $dbh->prepare('SELECT * FROM posts WHERE board_id = :board_id AND post_id = :post_id AND deleted = :deleted');
  $sth->execute([
    'board_id' => $board_id,
    'post_id' => $post_id,
    'deleted' => $deleted ? 1 : 0
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

function select_posts(array $params): array|bool
{

  $getParam = function($key, $default=null, bool $is_required=true) use(&$params) {
    if ($is_required && !array_key_exists($key, $params)) throw new Exception(("select_posts(): required arg '$key' missing!"));
    if (array_key_exists($key, $params)) return $params[$key];
    return $default;
  };

  $session_id     = (string) $getParam("session_id");
  $user_role      = (int) $getParam("user_role");
  $board_id       = (string) $getParam("board_id");
  $parent_id      = (int) $getParam("parent_id", 0, false);
  $desc           = (bool) $getParam("desc", true, false);
  $offset         = (int) $getParam("offset", 0, false);
  $limit          = (int) $getParam("limit", 10, false);
  $hidden         = (bool) $getParam("hidden", false, false);
  $deleted        = (bool) $getParam("deleted", false, false);
  $search         = (string) $getParam("search", "", false);

  $dbh = get_db_handle();
  $sth = null;
  if ($board_id != null) {
    $sth = $dbh->prepare('
      SELECT * FROM posts
      WHERE board_id = :board_id AND parent_id = :parent_id AND post_id ' . ($hidden === true ? '' : 'NOT') . ' IN (
        SELECT post_id FROM hides WHERE session_id = :session_id AND board_id = posts.board_id
      ) AND deleted = :deleted
      AND (
        req_role IS NULL OR (:user_role_1 IS NOT NULL AND :user_role_2 <= req_role)
      )
      AND (subject LIKE :search_subject OR message LIKE :search_message)
      ORDER BY stickied DESC, bumped ' . ($desc === true ? 'DESC' : 'ASC') . '
      LIMIT :limit OFFSET :offset
    ');
    $sth->execute([
      'session_id' => $session_id,
      'board_id' => $board_id,
      'parent_id' => $parent_id,
      'deleted' => $deleted ? 1 : 0,
      'user_role_1' => $user_role,
      'user_role_2' => $user_role,
      'limit' => $limit,
      'offset' => $offset,
      'search_subject' => "%" . $search . "%",
      'search_message' => "%" . $search . "%",
    ]);
  } else {
    $sth = $dbh->prepare('
      SELECT * FROM posts
      WHERE parent_id = :parent_id AND post_id ' . ($hidden === true ? '' : 'NOT') . ' IN (
        SELECT post_id FROM hides WHERE session_id = :session_id AND board_id = posts.board_id
      ) AND deleted = :deleted
      AND (
        req_role IS NULL OR (:user_role_1 IS NOT NULL AND :user_role_2 <= req_role)
      )
      AND (subject LIKE :search_subject OR message LIKE :search_message)
      ORDER BY bumped ' . ($desc === true ? 'DESC' : 'ASC') . '
      LIMIT :limit OFFSET :offset
    ');
    $sth->execute([
      'session_id' => $session_id,
      'parent_id' => $parent_id,
      'deleted' => $deleted ? 1 : 0,
      'user_role_1' => $user_role,
      'user_role_2' => $user_role,
      'limit' => $limit,
      'offset' => $offset,
      'search_subject' => "%" . $search . "%",
      'search_message' => "%" . $search . "%",
    ]);
  }
  return $sth->fetchAll();
}

function select_posts_preview(string $session_id, string $board_id, int $parent_id = 0, int $offset = 0, int $limit = 10, bool $deleted = false): array|bool {
  $dbh = get_db_handle();
  $sth = $dbh->prepare('
    SELECT t.* FROM (
      SELECT * FROM posts
      WHERE board_id = :board_id AND parent_id = :parent_id AND post_id NOT IN (
        SELECT post_id FROM hides WHERE session_id = :session_id AND board_id = posts.board_id
      ) AND deleted = :deleted
      ORDER BY bumped DESC
      LIMIT :limit OFFSET :offset
    ) AS t
    ORDER BY bumped ASC
  ');
  $sth->execute([
    'session_id' => $session_id,
    'board_id' => $board_id,
    'parent_id' => $parent_id,
    'deleted' => $deleted ? 1 : 0,
    'limit' => $limit,
    'offset' => $offset
  ]);
  return $sth->fetchAll();
}

function count_posts(string $session_id, ?string $board_id, int $parent_id, bool $hidden = false, bool $deleted = false): int|bool {
  $dbh = get_db_handle();
  $sth = null;
  if ($board_id != null) {
    $sth = $dbh->prepare('
      SELECT COUNT(*) FROM posts
      WHERE board_id = :board_id AND parent_id = :parent_id AND post_id ' . ($hidden === true ? '' : 'NOT') . ' IN (
        SELECT post_id FROM hides WHERE session_id = :session_id AND board_id = posts.board_id
      ) AND deleted = :deleted
    ');
    $sth->execute([
      'session_id' => $session_id,
      'board_id' => $board_id,
      'parent_id' => $parent_id,
      'deleted' => $deleted ? 1 : 0
    ]);
  } else {
    $sth = $dbh->prepare('
      SELECT COUNT(*) FROM posts
      WHERE parent_id = :parent_id AND post_id ' . ($hidden === true ? '' : 'NOT') . ' IN (
        SELECT post_id FROM hides WHERE session_id = :session_id AND board_id = posts.board_id
      ) AND deleted = :deleted
    ');
    $sth->execute([
      'session_id' => $session_id,
      'parent_id' => $parent_id,
      'deleted' => $deleted ? 1 : 0
    ]);
  }
  return $sth->fetchColumn();
}

function insert_post($post): int|bool {
  $dbh = get_db_handle();
  $sth = $dbh->prepare('
    INSERT INTO posts (
      post_id,
      board_id,
      parent_id,
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
      image_width,
      image_height,
      thumb,
      thumb_width,
      thumb_height,
      embed,
      country
    )
    VALUES (
      :post_id,
      :board_id,
      :parent_id,
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
      :image_width,
      :image_height,
      :thumb,
      :thumb_width,
      :thumb_height,
      :embed,
      :country
    )
  ');
  if ($sth->execute($post) !== TRUE) {
    return false;
  }

  // get post_id by internal PRIMARY KEY id and return it as last_insert_id
  $id = intval($dbh->lastInsertId());
  $sth = $dbh->prepare('SELECT post_id FROM posts WHERE id = :id');
  $sth->execute(['id' => $id]);
  
  return $sth->fetchColumn();
}

function delete_post(string $board_id, int $post_id, bool $delete = false): bool {
  $dbh = get_db_handle();
  $sth = null;
  if ($delete) {
    $sth = $dbh->prepare('DELETE FROM posts WHERE board_id = :board_id AND post_id = :post_id');
  } else {
    $sth = $dbh->prepare('UPDATE posts SET deleted = 1 WHERE board_id = :board_id AND post_id = :post_id');
  }
  return $sth->execute([
    'board_id' => $board_id,
    'post_id' => $post_id
  ]);
}

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
          p.board_id = :board_id_2 AND p.post_id = :post_id_2 AND p.parent_id = 0
        )
        AND p.deleted = 0
      ORDER BY p.timestamp DESC
      LIMIT 1
    )
    WHERE t.board_id = :board_id_3 AND t.post_id = :post_id_3 AND t.parent_id = 0
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
      image_width,
      image_height,
      thumb,
      thumb_width,
      thumb_height
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
      board_id = :board_id AND parent_id = 0
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

// HIDE related functions below
// ----------------------------

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

// REPORT related functions below
// ------------------------------

function insert_report($report): int {
  $dbh = get_db_handle();
  $sth = $dbh->prepare('
    INSERT INTO reports (
      ip,
      timestamp,
      board_id,
      post_id,
      type
    )
    VALUES (
      INET6_ATON(:ip),
      :timestamp,
      :board_id,
      :post_id,
      :type
    )
  ');
  $sth->execute($report);
  return intval($dbh->lastInsertId());
}

// MANAGE related functions below
// ------------------------------

function select_account(string $username): array|bool {
  $dbh = get_db_handle();
  $sth = $dbh->prepare('SELECT * FROM accounts WHERE username = :username');
  $sth->execute([
    'username' => $username
  ]);
  return $sth->fetch();
}

function select_all_accounts(bool $desc = true, int $offset = 0, int $limit = 10): array|bool {
  $dbh = get_db_handle();
  $sth = $dbh->prepare('
    SELECT
      *
    FROM accounts
    ORDER BY id ' . ($desc === true ? 'DESC' : 'ASC') . '
    LIMIT :limit OFFSET :offset
  ');
  $sth->execute([
    'limit' => $limit,
    'offset' => $offset
  ]);
  return $sth->fetchAll();
}

function count_all_accounts(): int|bool {
  $dbh = get_db_handle();
  $sth = $dbh->prepare('SELECT COUNT(*) FROM accounts');
  $sth->execute();
  return $sth->fetchColumn();
}

function update_account(array $account): bool {
  $dbh = get_db_handle();
  $sth = $dbh->prepare('
    UPDATE accounts
    SET
      password = :password,
      role = :role,
      lastactive = :lastactive
    WHERE id = :id
  ');
  return $sth->execute([
    'password' => $account['password'],
    'role' => $account['role'],
    'lastactive' => $account['lastactive'],
    'id' => $account['id']
  ]);
}

function select_all_bans(bool $desc = true, int $offset = 0, int $limit = 10): array|bool {
  $dbh = get_db_handle();
  $sth = $dbh->prepare('
    SELECT
      *,
      INET6_NTOA(ip) AS ip_str
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
      reason = :reason
    WHERE id = :id
  ');
  return $sth->execute([
    'timestamp' => $ban['timestamp'],
    'expire' => $ban['expire'],
    'reason' => $ban['reason'],
    'id' => $ban['id']
  ]);
}

function select_all_posts(bool $desc = true, int $offset = 0, int $limit = 10): array|bool {
  $dbh = get_db_handle();
  $sth = $dbh->prepare('
    SELECT
      *,
      INET6_NTOA(ip) AS ip_str
    FROM posts
    WHERE parent_id != 0
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
  $sth = $dbh->prepare('SELECT COUNT(*) FROM posts');
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
    WHERE parent_id = 0
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
  $sth = $dbh->prepare('SELECT COUNT(*) FROM posts WHERE parent_id = 0');
  $sth->execute();
  return $sth->fetchColumn();
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
  ');
  $sth->execute([
    'board_id_1' => $board_id,
    'post_id_1' => $post_id,
    'board_id_2' => $board_id,
    'post_id_2' => $post_id
  ]);
  return $sth->fetchAll();
}

function ban_poster_by_post_id(string $board_id, int $post_id, int $duration, string $reason): int {
  $dbh = get_db_handle();
  $sth = $dbh->prepare('
    INSERT IGNORE INTO bans (
      ip,
      timestamp,
      expire,
      reason
    )
    VALUES (
      (SELECT ip FROM posts WHERE board_id = :board_id AND post_id = :post_id),
      :timestamp,
      :expire,
      :reason
    )
  ');
  $sth->execute([
    'board_id' => $board_id,
    'post_id' => $post_id,
    'timestamp' => time(),
    'expire' => time() + $duration,
    'reason' => $reason
  ]);
  $affected = $sth->rowCount();

  if ($affected > 0) {
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
  }
  return $affected;
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

function toggle_post_locked(string $board_id, int $post_id): int {
  $dbh = get_db_handle();
  $sth = $dbh->prepare('
    UPDATE posts
    SET
      locked = !locked
    WHERE board_id = :board_id AND post_id = :post_id AND parent_id = 0
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
    WHERE board_id = :board_id AND post_id = :post_id AND parent_id = 0
  ');
  $sth->execute([
    'board_id' => $board_id,
    'post_id' => $post_id
  ]);
  return $sth->rowCount();
}

function select_all_logs(bool $desc = true, int $offset = 0, int $limit = 10): array|bool {
  $dbh = get_db_handle();
  $sth = $dbh->prepare('
    SELECT
      *,
      INET6_NTOA(ip) AS ip_str
    FROM logs
    ORDER BY timestamp ' . ($desc === true ? 'DESC' : 'ASC') . '
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

function insert_log(string $ip, int $timestamp, string $username, string $message): int|bool {
  $dbh = get_db_handle();
  $sth = $dbh->prepare('
    INSERT INTO logs (
      ip,
      timestamp,
      username,
      message
    )
    VALUES (
      INET6_ATON(:ip),
      :timestamp,
      :username,
      :message
    )
  ');
  $result = $sth->execute([
    'ip' => $ip,
    'timestamp' => $timestamp,
    'username' => $username,
    'message' => $message
  ]);
  if ($result !== TRUE) {
    return false;
  }
  return intval($dbh->lastInsertId());
}

// MANAGE/IMPORT related functions below
// -------------------------------------

function get_db_handle_import(array $db_creds): PDO {
  $mb_db_host = MB_DB_HOST;

  $dbh = new PDO("mysql:host={$mb_db_host};dbname={$db_creds['db_name']}", $db_creds['db_user'], $db_creds['db_pass'], [
    PDO::ATTR_PERSISTENT => true,
    PDO::ATTR_EMULATE_PREPARES => false
  ]);

  return $dbh;
}

function insert_import_accounts_tinyib(array $db_creds, string $table_name): int {
  $dbh = get_db_handle_import($db_creds);
  $sth = $dbh->prepare('
    INSERT INTO ' . MB_DB_NAME . '.accounts (
      username,
      password,
      role,
      lastactive,
      imported
    )
    SELECT
      username,
      password,
      role,
      lastactive,
      1 AS imported
    FROM ' . $table_name);
  $sth->execute();
  return $sth->rowCount();
}

function insert_import_posts_tinyib(array $db_creds, string $table_name, string $board_id): int {
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
      image_width,
      image_height,
      thumb,
      thumb_width,
      thumb_height,
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
      image_width,
      image_height,
      CONCAT(\'/src/\', thumb) AS thumb,
      thumb_width,
      thumb_height,
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


// MANAGE/REBUILD related functions below
// -------------------------------------

function select_rebuild_posts(string $board_id): array|bool {
  $dbh = get_db_handle();
  $sth = $dbh->prepare('
    SELECT
      post_id,
      parent_id,
      board_id,
      INET6_NTOA(ip) AS ip_str,
      timestamp,
      role,
      name,
      email,
      tripcode,
      message,
      file,
      embed,
      imported
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


// BAN related functions below
// ------------------------------

function select_ban(string $ip): array|bool {
  $dbh = get_db_handle();
  $sth = $dbh->prepare('
    SELECT expire, reason FROM bans
    WHERE ip = INET6_ATON(:ip) AND :now < expire
  ');
  $sth->execute([
    'ip' => $ip,
    'now' => time()
  ]);
  return $sth->fetch();
}
