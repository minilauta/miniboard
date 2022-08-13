<?php
require_once __DIR__ . '/config.php';

function get_db_handle() : PDO {
  global $dbh;

  if ($dbh != null) {
    return $dbh;
  }

  $dbh = new PDO("mysql:host=127.0.0.1;dbname=miniboard", MB_DB_USER, MB_DB_PASS, [
    PDO::ATTR_PERSISTENT => true
  ]);

  return $dbh;
}

function select_posts(int $board = NULL, int $parent = 0, int $offset = 0, int $limit = 10) : array {
  $dbh = get_db_handle();
  $sth = $dbh->prepare('
    SELECT * FROM posts
    WHERE board = :board AND parent = :parent
    ORDER BY bumped DESC
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

function insert_post($post) : int {
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
