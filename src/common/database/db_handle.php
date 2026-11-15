<?php
require_once __DIR__ . '/../config.php';

function get_db_handle(): PDO {
  global $dbh;

  if ($dbh != null) {
    return $dbh;
  }

  $mb_db_host = MB_DB_HOST;
  $mb_db_name = MB_DB_NAME;

  $dbh = new \PDO("mysql:host={$mb_db_host};dbname={$mb_db_name}", MB_DB_USER, MB_DB_PASS, [
    \PDO::ATTR_PERSISTENT => 'miniboard_database',
    \PDO::ATTR_EMULATE_PREPARES => false,
    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
    \PDO::ATTR_AUTOCOMMIT => 1,
  ]);

  return $dbh;
}

function get_db_handle_import(array $db_creds): PDO {
  $mb_db_host = MB_DB_HOST;

  $dbh = new PDO("mysql:host={$mb_db_host};dbname={$db_creds['db_name']}", $db_creds['db_user'], $db_creds['db_pass'], [
    PDO::ATTR_PERSISTENT => true,
    PDO::ATTR_EMULATE_PREPARES => false
  ]);

  return $dbh;
}
