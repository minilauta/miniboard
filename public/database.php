<?php
require_once __DIR__ . '/config.php';

function get_db_handle() : PDO {
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

// POST related functions below
// ----------------------------

function select_post(string $board_id, int $id, bool $deleted = false) : array|bool {
  $dbh = get_db_handle();
  $sth = $dbh->prepare('
    SELECT * FROM posts
    WHERE board_id = :board_id AND id = :id AND deleted = :deleted
  ');
  $sth->execute([
    'board_id' => $board_id,
    'id' => $id,
    'deleted' => $deleted ? 1 : 0
  ]);
  return $sth->fetch();
}

function select_posts(string $session_id, ?string $board_id, int $parent_id = 0, bool $desc = true, int $offset = 0, int $limit = 10, bool $hidden = false, bool $deleted = false) : array|bool {
  $dbh = get_db_handle();
  $sth = null;
  if ($board_id != null) {
    $sth = $dbh->prepare('
      SELECT * FROM posts
      WHERE board_id = :board_id_outer AND parent_id = :parent_id AND id ' . ($hidden === true ? '' : 'NOT') . ' IN (
        SELECT post_id FROM hides WHERE session_id = :session_id AND board_id = :board_id_inner
      ) AND deleted = :deleted
      ORDER BY bumped ' . ($desc === true ? 'DESC' : 'ASC') . '
      LIMIT :limit OFFSET :offset
    ');
    $sth->execute([
      'session_id' => $session_id,
      'board_id_outer' => $board_id,
      'board_id_inner' => $board_id,
      'parent_id' => $parent_id,
      'deleted' => $deleted ? 1 : 0,
      'limit' => $limit,
      'offset' => $offset
    ]);
  } else {
    $sth = $dbh->prepare('
      SELECT * FROM posts
      WHERE parent_id = :parent_id AND id ' . ($hidden === true ? '' : 'NOT') . ' IN (
        SELECT post_id FROM hides WHERE session_id = :session_id
      ) AND deleted = :deleted
      ORDER BY bumped ' . ($desc === true ? 'DESC' : 'ASC') . '
      LIMIT :limit OFFSET :offset
    ');
    $sth->execute([
      'session_id' => $session_id,
      'parent_id' => $parent_id,
      'deleted' => $deleted ? 1 : 0,
      'limit' => $limit,
      'offset' => $offset
    ]);
  }
  return $sth->fetchAll();
}

function select_posts_preview(string $session_id, string $board_id, int $parent_id = 0, int $offset = 0, int $limit = 10, bool $deleted = false) : array|bool {
  $dbh = get_db_handle();
  $sth = $dbh->prepare('
    SELECT t.* FROM (
      SELECT * FROM posts
      WHERE board_id = :board_id_outer AND parent_id = :parent_id AND id NOT IN (
        SELECT post_id FROM hides WHERE session_id = :session_id AND board_id = :board_id_inner
      ) AND deleted = :deleted
      ORDER BY bumped DESC
      LIMIT :limit OFFSET :offset
    ) AS t
    ORDER BY bumped ASC
  ');
  $sth->execute([
    'session_id' => $session_id,
    'board_id_outer' => $board_id,
    'board_id_inner' => $board_id,
    'parent_id' => $parent_id,
    'deleted' => $deleted ? 1 : 0,
    'limit' => $limit,
    'offset' => $offset
  ]);
  return $sth->fetchAll();
}

function count_posts(string $session_id, ?string $board_id, int $parent_id, bool $hidden = false,  bool $deleted = false) : int|bool {
  $dbh = get_db_handle();
  $sth = null;
  if ($board_id != null) {
    $sth = $dbh->prepare('
      SELECT COUNT(*) FROM posts
      WHERE board_id = :board_id_outer AND parent_id = :parent_id AND id ' . ($hidden === true ? '' : 'NOT') . ' IN (
        SELECT post_id FROM hides WHERE session_id = :session_id AND board_id = :board_id_inner
      ) AND deleted = :deleted
    ');
    $sth->execute([
      'session_id' => $session_id,
      'board_id_outer' => $board_id,
      'board_id_inner' => $board_id,
      'parent_id' => $parent_id,
      'deleted' => $deleted ? 1 : 0
    ]);
  } else {
    $sth = $dbh->prepare('
      SELECT COUNT(*) FROM posts
      WHERE parent_id = :parent_id AND id ' . ($hidden === true ? '' : 'NOT') . ' IN (
        SELECT post_id FROM hides WHERE session_id = :session_id
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

function insert_post($post) : int|bool {
  $dbh = get_db_handle();
  $sth = $dbh->prepare('
    INSERT INTO posts (
      board_id,
      parent_id,
      ip,
      timestamp,
      bumped,
      name,
      tripcode,
      email,
      subject,
      message,
      message_rendered,
      message_truncated,
      password,
      file,
      file_hex,
      file_original,
      file_size,
      file_size_formatted,
      image_width,
      image_height,
      thumb,
      thumb_width,
      thumb_height,
      spoiler,
      stickied,
      moderated,
      deleted,
      country_code
    )
    VALUES (
      :board_id,
      :parent_id,
      INET6_ATON(:ip),
      :timestamp,
      :bumped,
      :name,
      :tripcode,
      :email,
      :subject,
      :message,
      :message_rendered,
      :message_truncated,
      :password,
      :file,
      :file_hex,
      :file_original,
      :file_size,
      :file_size_formatted,
      :image_width,
      :image_height,
      :thumb,
      :thumb_width,
      :thumb_height,
      :spoiler,
      :stickied,
      :moderated,
      :deleted,
      :country_code
    )
  ');
  $sth->execute($post);
  return $dbh->lastInsertId();
}

function delete_post(string $board_id, int $id, bool $delete = false) : bool {
  $dbh = get_db_handle();
  $sth = null;
  if ($delete) {
    $sth = $dbh->prepare('
      DELETE FROM posts
      WHERE board_id = :board_id AND id = :id
    ');
  } else {
    $sth = $dbh->prepare('
      UPDATE posts
      SET deleted = 1
      WHERE board_id = :board_id AND id = :id
    ');
  }
  return $sth->execute([
    'board_id' => $board_id,
    'id' => $id
  ]);
}

function bump_thread(string $board_id, int $id) : bool {
  $dbh = get_db_handle();
  $sth = $dbh->prepare('
    UPDATE posts
    SET bumped = ' . time() . '
    WHERE board_id = :board_id AND id = :id
  ');
  return $sth->execute([
    'board_id' => $board_id,
    'id' => $id
  ]);
}

function select_files_by_md5(string $file_md5) : array|bool {
  $dbh = get_db_handle();
  $sth = $dbh->prepare('
    SELECT 
      id,
      file,
      file_hex,
      file_original,
      file_size,
      file_size_formatted,
      image_width,
      image_height,
      thumb,
      thumb_width,
      thumb_height,
      spoiler
    FROM posts
    WHERE file_hex = :file_md5
  ');
  $sth->execute([
    'file_md5' => $file_md5
  ]); 
  return $sth->fetchAll();
}


// HIDE related functions below
// ----------------------------

function select_hide(string $session_id, string $board_id, int $post_id) : array|bool {
  $dbh = get_db_handle();
  $sth = $dbh->prepare('
    SELECT * FROM hides
    WHERE session_id = :session_id AND board_id = :board_id AND post_id = :post_id
  ');
  $sth->execute([
    'session_id' => $session_id,
    'board_id' => $board_id,
    'post_id' => $post_id
  ]);
  return $sth->fetch();
}

function insert_hide($hide) : int|bool {
  $dbh = get_db_handle();
  $sth = $dbh->prepare('
    INSERT INTO hides (
      session_id,
      board_id,
      post_id
    )
    VALUES (
      :session_id,
      :board_id,
      :post_id
    )
  ');
  $sth->execute($hide);
  return $dbh->lastInsertId();
}

function delete_hide($hide) : int {
  $dbh = get_db_handle();
  $sth = $dbh->prepare('
    DELETE FROM hides
    WHERE session_id = :session_id AND board_id = :board_id AND post_id = :post_id
  ');
  $sth->execute([
    'session_id' => $hide['session_id'],
    'board_id' => $hide['board_id'],
    'post_id' => $hide['post_id']
  ]);
  return $sth->rowCount();
}


// REPORT related functions below
// ------------------------------

function insert_report($report) : int|bool {
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
  return $dbh->lastInsertId();
}


// MANAGE related functions below
// ------------------------------

function select_account(string $username): array|bool {
  $dbh = get_db_handle();
  $sth = $dbh->prepare('
    SELECT * FROM accounts
    WHERE username = :username
  ');
  $sth->execute([
    'username' => $username
  ]);
  return $sth->fetch();
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


// MANAGE/IMPORT related functions below
// -------------------------------------

function get_db_handle_import(array $db_creds) : PDO {
  $mb_db_host = MB_DB_HOST;

  $dbh = new PDO("mysql:host={$mb_db_host};dbname={$db_creds['dbname']}", $db_creds['dbuser'], $db_creds['dbpass'], [
    PDO::ATTR_PERSISTENT => true,
    PDO::ATTR_EMULATE_PREPARES => false
  ]);

  return $dbh;
}

function insert_import_posts_tinyib(array $db_creds, string $table_name, string $board_id) {
  $dbh = get_db_handle_import($db_creds);
  $sth = $dbh->prepare('
    INSERT INTO ' . MB_DB_NAME . '.posts (
      id,
      board_id,
      parent_id,
      ip,
      timestamp,
      bumped,
      name,
      tripcode,
      email,
      subject,
      message,
      message_rendered,
      message_truncated,
      password,
      file,
      file_hex,
      file_original,
      file_size,
      file_size_formatted,
      image_width,
      image_height,
      thumb,
      thumb_width,
      thumb_height,
      spoiler,
      stickied,
      moderated,
      deleted,
      country_code
    )
    SELECT
      id,
      :board_id AS board_id,
      parent AS parent_id,
      INET6_ATON(\'127.0.0.1\'),
      timestamp,
      bumped,
      name,
      tripcode,
      email,
      subject,
      message,
      message AS message_rendered,
      NULL AS message_truncated,
      password,
      CAST(file AS varchar(1028)) AS file,
      file_hex,
      file_original,
      file_size,
      file_size_formatted,
      image_width,
      image_height,
      thumb,
      thumb_width,
      thumb_height,
      0 AS spoiler,
      stickied,
      moderated,
      0 AS deleted,
      country_code
    FROM ' . $table_name);
  $sth->execute([
    'board_id' => $board_id
  ]);
  return $sth->rowCount();
}


// MANAGE/REBUILD related functions below
// -------------------------------------

function select_rebuild_posts(string $board_id) : array|bool {
  $dbh = get_db_handle();
  $sth = $dbh->prepare('
    SELECT id, board_id, message FROM posts
    WHERE board_id = :board_id
  ');
  $sth->execute([
    'board_id' => $board_id
  ]);
  return $sth->fetchAll();
}

function update_rebuild_post(array $rebuild_post) : bool {
  $dbh = get_db_handle();
  $sth = $dbh->prepare('
    UPDATE posts
    SET
      message_rendered = :message_rendered,
      message_truncated = :message_truncated
    WHERE
      id = :id AND board_id = :board_id
  ');
  return $sth->execute($rebuild_post);
}
