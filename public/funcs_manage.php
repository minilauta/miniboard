<?php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/exception.php';
require_once __DIR__ . '/funcs_common.php';

function funcs_manage_login(array $account, string $password): bool {
  // reject if passwords do not match
  if (funcs_common_verify_password($password, $account['password']) !== TRUE) {
    return false;
  }

  // set session variables
  $_SESSION['mb_username'] = $account['username'];
  $_SESSION['mb_role'] = $account['role'];

  return true;
}

function funcs_manage_is_logged_in(): bool {
  return isset($_SESSION['mb_username']) && isset($_SESSION['mb_role']);
}

function funcs_manage_logout(): bool {
  // destroy session variables and return success code
  return session_unset();
}

function funcs_manage_import(array $params): string {
  // handle each table type separately
  switch ($params['tabletype']) {
    case TINYIB_POSTS:
      // validate params
      if (!array_key_exists($params['boardid'], MB_BOARDS)) {
        return "Target BOARD id '{$params['boardid']}' not found";
      }

      // execute import
      $inserted = insert_import_posts_tinyib($params, $params['tablename'], $params['boardid']);
      
      return "Inserted {$inserted} rows from target database '{$params['dbname']}' table '{$params['tablename']}' successfully";
    default:
      return "Unsupported table_type '{$params['tabletype']}'";
  }
}
