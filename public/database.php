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

function select_post(string $board_id, int $id) : array|bool {
  $dbh = get_db_handle();
  $sth = $dbh->prepare('
    SELECT * FROM posts
    WHERE board_id = :board_id AND id = :id
  ');
  $sth->execute([
    'board_id' => $board_id,
    'id' => $id
  ]);
  return $sth->fetch();
}

function select_posts(string $session_id, string $board_id, int $parent_id = 0, bool $desc = true, int $offset = 0, int $limit = 10, bool $hidden = false) : array|bool {
  $dbh = get_db_handle();
  $sth = null;
  if ($board_id !== 'main') {
    $sth = $dbh->prepare('
      SELECT * FROM posts
      WHERE board_id = :board_id_outer AND parent_id = :parent_id AND id ' . ($hidden === true ? '' : 'NOT') . ' IN (
        SELECT post_id FROM hides WHERE session_id = :session_id AND board_id = :board_id_inner
      )
      ORDER BY bumped ' . ($desc === true ? 'DESC' : 'ASC') . '
      LIMIT :limit OFFSET :offset
    ');
    $sth->execute([
      'session_id' => $session_id,
      'board_id_outer' => $board_id,
      'board_id_inner' => $board_id,
      'parent_id' => $parent_id,
      'limit' => $limit,
      'offset' => $offset
    ]);
  } else {
    $sth = $dbh->prepare('
      SELECT * FROM posts
      WHERE parent_id = :parent_id AND id ' . ($hidden === true ? '' : 'NOT') . ' IN (
        SELECT post_id FROM hides WHERE session_id = :session_id
      )
      ORDER BY bumped ' . ($desc === true ? 'DESC' : 'ASC') . '
      LIMIT :limit OFFSET :offset
    ');
    $sth->execute([
      'session_id' => $session_id,
      'parent_id' => $parent_id,
      'limit' => $limit,
      'offset' => $offset
    ]);
  }
  return $sth->fetchAll();
}

function select_posts_preview(string $session_id, string $board_id, int $parent_id = 0, int $offset = 0, int $limit = 10) : array|bool {
  $dbh = get_db_handle();
  $sth = $dbh->prepare('
    SELECT t.* FROM (
      SELECT * FROM posts
      WHERE board_id = :board_id_outer AND parent_id = :parent_id AND id NOT IN (
        SELECT post_id FROM hides WHERE session_id = :session_id AND board_id = :board_id_inner
      )
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
    'limit' => $limit,
    'offset' => $offset
  ]);
  return $sth->fetchAll();
}

function count_posts(string $session_id, string $board_id, int $parent_id, bool $hidden = false) : int|bool {
  $dbh = get_db_handle();
  $sth = null;
  if ($board_id !== 'main') {
    $sth = $dbh->prepare('
      SELECT COUNT(*) FROM posts
      WHERE board_id = :board_id_outer AND parent_id = :parent_id AND id ' . ($hidden === true ? '' : 'NOT') . ' IN (
        SELECT post_id FROM hides WHERE session_id = :session_id AND board_id = :board_id_inner
      )
    ');
    $sth->execute([
      'session_id' => $session_id,
      'board_id_outer' => $board_id,
      'board_id_inner' => $board_id,
      'parent_id' => $parent_id
    ]);
  } else {
    $sth = $dbh->prepare('
      SELECT COUNT(*) FROM posts
      WHERE parent_id = :parent_id AND id ' . ($hidden === true ? '' : 'NOT') . ' IN (
        SELECT post_id FROM hides WHERE session_id = :session_id
      )
    ');
    $sth->execute([
      'session_id' => $session_id,
      'parent_id' => $parent_id
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
      :country_code
    )
  ');
  $sth->execute($post);
  return $dbh->lastInsertId();
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
