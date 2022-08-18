<?php
require_once __DIR__ . '/config.php';

function get_db_handle() : PDO {
  global $dbh;

  if ($dbh != null) {
    return $dbh;
  }

  $dbh = new PDO("mysql:host=127.0.0.1;dbname=miniboard", MB_DB_USER, MB_DB_PASS, [
    PDO::ATTR_PERSISTENT => true,
    PDO::ATTR_EMULATE_PREPARES => false
  ]);

  return $dbh;
}

function select_post(string $board = NULL, int $id) : array|bool {
  $dbh = get_db_handle();
  $sth = $dbh->prepare('
    SELECT * FROM posts
    WHERE board = :board AND id = :id
  ');
  $sth->execute([
    'board' => $board,
    'id' => $id
  ]);
  return $sth->fetch();
}

function select_posts(string $board = NULL, int $parent = 0, bool $desc = true, int $offset = 0, int $limit = 10) : array|bool {
  $dbh = get_db_handle();
  $sth = $dbh->prepare('
    SELECT * FROM posts
    WHERE board = :board AND parent = :parent
    ORDER BY bumped ' . ($desc === true ? 'DESC' : 'ASC') . '
    LIMIT :limit OFFSET :offset
  ');
  $sth->execute([
    'board' => $board,
    'parent' => $parent,
    'limit' => $limit,
    'offset' => $offset
  ]);
  return $sth->fetchAll();
}

function select_posts_preview(string $board = NULL, int $parent = 0, int $offset = 0, int $limit = 10) : array|bool {
  $dbh = get_db_handle();
  $sth = $dbh->prepare('
    SELECT t.* FROM (
      SELECT * FROM posts
      WHERE board = :board AND parent = :parent
      ORDER BY bumped DESC
      LIMIT :limit OFFSET :offset
    ) AS t
    ORDER BY bumped ASC
  ');
  $sth->execute([
    'board' => $board,
    'parent' => $parent,
    'limit' => $limit,
    'offset' => $offset
  ]);
  return $sth->fetchAll();
}

function insert_post($post) : int|bool {
  $dbh = get_db_handle();
  $sth = $dbh->prepare('
    INSERT INTO posts (
      board,
      parent,
      timestamp,
      bumped,
      ip,
      name,
      tripcode,
      email,
      nameblock,
      subject,
      message,
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
      stickied,
      moderated,
      country_code
    )
    VALUES (
      :board,
      :parent,
      :timestamp,
      :bumped,
      :ip,
      :name,
      :tripcode,
      :email,
      :nameblock,
      :subject,
      :message,
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
      :stickied,
      :moderated,
      :country_code
    )
  ');
  $sth->execute($post);
  return $dbh->lastInsertId();
}

function bump_thread(string $board = NULL, int $id) : bool {
  $dbh = get_db_handle();
  $sth = $dbh->prepare('
    UPDATE posts
    SET bumped = ' . time() . '
    WHERE board = :board AND id = :id
  ');
  return $sth->execute([
    'board' => $board,
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
      thumb_height
    FROM posts
    WHERE file_hex = :file_md5
  ');
  $sth->execute([
    'file_md5' => $file_md5
  ]); 
  return $sth->fetchAll();
}
