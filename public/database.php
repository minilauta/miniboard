<?php
require_once __DIR__ . '/config.php';

function get_db_handle() : PDO {
  global $dbh;

  if ($dbh != null) {
    return $dbh;
  }

  $dbh = new PDO("mysql:host={strval(MB_DB_HOST)};dbname={strval(MB_DB_NAME)}", MB_DB_USER, MB_DB_PASS, [
    PDO::ATTR_PERSISTENT => true
  ]);

  return $dbh;
}
