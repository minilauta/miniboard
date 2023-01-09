<?php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/exception.php';
require_once __DIR__ . '/funcs_common.php';

function funcs_manage_login(string $username, string $password, string $password_db): bool {
  // reject if passwords do not match
  if (funcs_common_verify_password($password, $password_db) !== TRUE) {
    return false;
  }

  // set session variables
  $_SESSION['mb_username'] = $username;

  return true;
}
