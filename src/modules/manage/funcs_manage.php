<?php

require_once __ROOT__ . '/common/config.php';
require_once __ROOT__ . '/common/exception.php';
require_once __ROOT__ . '/common/funcs_common.php';

/**
 * Inserts a message to the management log.
 */
function funcs_manage_log(string $message) {
  $ip = funcs_common_get_client_remote_address(MB_CLOUDFLARE, $_SERVER);
  $username = isset($_SESSION['mb_username']) ? $_SESSION['mb_username'] : null;

  if ($username == null) {
    return;
  }

  insert_log($ip, time(), $_SESSION['mb_username'], $message);
}

/**
 * Checks user login credentials and on success assigns session variables.
 */
function funcs_manage_login(array $account, string $password): bool {
  // reject if passwords do not match
  if (funcs_common_verify_password($password, $account['password']) !== TRUE) {
    return false;
  }

  // set session variables
  $_SESSION['mb_username'] = $account['username'];
  $_SESSION['mb_role'] = $account['role'];

  funcs_manage_log('Logged in');

  return true;
}

/**
 * Destroys user session variables.
 */
function funcs_manage_logout(): bool {
  funcs_manage_log('Logged out');

  // destroy session variables and return success code
  return session_unset();
}

/**
 * Imports data from another MySQL/MariaDB database.
 */
function funcs_manage_import(array $params): string {
  // handle each table type separately
  $inserted = 0;
  $warnings = [];
  switch ($params['table_type']) {
    case MB_IMPORT_TINYIB_ACCOUNTS:
      // execute import
      $inserted = insert_import_accounts_tinyib($params, $params['table_name']);
      break;
    case MB_IMPORT_TINYIB_POSTS:
      // validate params
      if (!array_key_exists($params['board_id'], MB_BOARDS)) {
        $warnings[] = "Target BOARD id '{$params['board_id']}' not found";
      } else {
        // init auto increment table
        init_post_auto_increment($params['board_id']);
  
        // execute import
        $inserted = insert_import_posts_tinyib($params, $params['table_name'], $params['board_id']);
  
        // refresh auto increment table
        refresh_post_auto_increment($params['board_id']);
      }
      break;
    default:
      $warnings[] = "Unsupported table_type '{$params['table_type']}'";
      break;
  }

  // collect warnings
  $warnings = implode('<br>  - ', $warnings);

  $status = "Imported {$inserted} rows to /{$params['board_id']}/ from source db {$params['db_name']} & table {$params['table_name']}";
  if (strlen($warnings) > 0) {
    $status .= "<br>Warnings:<br>- {$warnings}";
  }
  funcs_manage_log($status);
  return $status;
}

/**
 * Rebuilds all data in the database.
 */
function funcs_manage_rebuild(array $params): string {
  // get board config
  $board_cfg = funcs_common_get_board_cfg($params['board_id']);

  // select posts
  $posts = select_rebuild_posts($params['board_id']);

  // rebuild each post
  $processed = 0;
  $total = count($posts);
  $warnings = [];
  foreach ($posts as &$post) {
    // process fields
    $name = $post['name'] !== '' ? $post['name'] : $board_cfg['anonymous'];
    $email = $post['email'];
    $message = $post['message'];
    
    // do extra cleanup for imported data because of raw HTML
    if ($post['imported']) {
      $name = funcs_common_clean_field($name);
      $email = funcs_common_clean_field($email);
      $message = strip_tags($message);
      $message = htmlspecialchars_decode($message, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML401);
    }

    // re-generate hashed ID
    $hashid = null;
    if (isset($board_cfg['hashid_salt']) && strlen($board_cfg['hashid_salt']) >= 2 && $post['parent_id'] > 0) {
      $hashid = funcs_common_generate_hashid($post['salt'], $post['ip_str'], $board_cfg['hashid_salt']);
    }

    // set nameblock country code if flags enabled OR country code is T1
    $country = $post['country'];
    $country_nb = null;
    if ($board_cfg['flags'] == true || $country == 't1') {
      $country_nb = $country;
    }

    // render nameblock and message
    $nameblock = funcs_board_render_nameblock($name, $post['tripcode'], $email, $hashid, $country_nb, $post['role'], $post['timestamp']);
    $message = funcs_board_render_message($params['board_id'], $post['parent_id'], $message, $board_cfg['truncate']);

    // render file
    $file = $post['file'];
    if ($post['embed'] === 1) {
      $file = rawurlencode($file);
    }

    // update post
    $rebuild_post = [
      'post_id' => $post['post_id'],
      'board_id' => $post['board_id'],
      'message_rendered' => $message['rendered'],
      'message_truncated' => $message['truncated'],
      'nameblock' => $nameblock,
      'file_rendered' => $file
    ];
    if (!update_rebuild_post($rebuild_post)) {
      $warnings[] = "Failed to rebuild post /{$post['board_id']}/{$post['post_id']}/";
    }

    $processed++;
  }

  // collect warnings
  $warnings = implode('<br>  - ', $warnings);

  $status = "Rebuilt {$processed}/{$total} posts in /{$params['board_id']}/";
  if (strlen($warnings) > 0) {
    $status .= "<br>Warnings:<br>- {$warnings}";
  }
  funcs_manage_log($status);
  return $status;
}

/**
 * Refreshes boards table row in the database.
 */
function funcs_manage_refresh(array $params): string {
  // get board config
  $board_cfg = funcs_common_get_board_cfg($params['board_id']);

  // refresh board
  $result = insert_refresh_board($board_cfg);

  $status = "Refreshed board: /{$board_cfg['id']}/";
  funcs_manage_log($status);
  return $status;
}

/**
 * Deletes all selected posts from filesystem and database.
 */
function funcs_manage_delete(array $select): string {
  // delete each post and replies
  $processed = 0;
  $warnings = [];
  foreach ($select as $val) {
    // parse board id and post id
    $selected_parsed = explode('/', $val);
    $selected_board_id = $selected_parsed[0];
    $selected_post_id = intval($selected_parsed[1]);

    // delete post and replies, files, etc...
    $warnings = array_merge($warnings, funcs_common_delete_post($selected_board_id, $selected_post_id));
    $processed++;
  }

  // collect warnings
  $warnings = implode('<br>  - ', $warnings);

  $status = "Deleted {$processed} posts";
  if (strlen($warnings) > 0) {
    $status .= "<br>Warnings:<br>- {$warnings}";
  }
  funcs_manage_log($status);
  return $status;
}

function funcs_manage_ban(array $select, int $duration, string $reason): string {
  // escape reason HTML entities
  $reason = funcs_common_clean_field($reason);

  // ban each poster
  $processed = 0;
  foreach ($select as $val) {
    // parse board id and post id
    $selected_parsed = explode('/', $val);
    $selected_board_id = $selected_parsed[0];
    $selected_post_id = intval($selected_parsed[1]);

    // ban poster by board id and post id
    $processed += ban_poster_by_post_id($selected_board_id, $selected_post_id, $duration, $reason);

    // delete remaining reports
    delete_reports_by_post_id($selected_board_id, $selected_post_id);
  }

  $status = "Banned {$processed} posters";
  funcs_manage_log($status);
  return $status;
}

function funcs_manage_approve(array $select): string {
  // delete each report
  $processed = 0;
  foreach ($select as $val) {
    // parse board id and post id
    $selected_parsed = explode('/', $val);
    $selected_board_id = $selected_parsed[0];
    $selected_post_id = intval($selected_parsed[1]);

    // delete all reports by board id and post id
    $processed += delete_reports_by_post_id($selected_board_id, $selected_post_id);
  }

  $status = "Approved {$processed} reports";
  funcs_manage_log($status);
  return $status;
}

function funcs_manage_toggle_lock(array $select): string {
  // lock/unlock each post
  $processed = 0;
  foreach ($select as $val) {
    // parse board id and post id
    $selected_parsed = explode('/', $val);
    $selected_board_id = $selected_parsed[0];
    $selected_post_id = intval($selected_parsed[1]);

    // lock/unlock all posts by board id and post id
    $processed += toggle_post_locked($selected_board_id, $selected_post_id);
  }

  $status = "Toggled lock state for {$processed} posts";
  funcs_manage_log($status);
  return $status;
}

function funcs_manage_toggle_sticky(array $select): string {
  // sticky/unsticky each post
  $processed = 0;
  foreach ($select as $val) {
    // parse board id and post id
    $selected_parsed = explode('/', $val);
    $selected_board_id = $selected_parsed[0];
    $selected_post_id = intval($selected_parsed[1]);

    // sticky/unsticky all posts by board id and post id
    $processed += toggle_post_stickied($selected_board_id, $selected_post_id);
  }

  $status = "Toggled sticky state for {$processed} posts";
  funcs_manage_log($status);
  return $status;
}

function funcs_manage_csam_scanner_cp(array $select): string {
  // mark each post
  $processed = 0;
  foreach ($select as $val) {
    // parse board id and post id
    $selected_parsed = explode('/', $val);
    $selected_board_id = $selected_parsed[0];
    $selected_post_id = intval($selected_parsed[1]);

    // select target post
    $target_post = select_post($selected_board_id, $selected_post_id, false);

    // send file to CSAM-scanner microservice
    $target_file_path = __DIR__ . $target_post['file'];
    $finfo = finfo_open(FILEINFO_MIME);
    $target_file_mime = explode(';', finfo_file($finfo, $target_file_path))[0];
    finfo_close($finfo);
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, 'http://' . CSAM_SCANNER_HOST . ':8000/cp');
    curl_setopt($curl, CURLOPT_POST, true);
    curl_setopt($curl, CURLOPT_POSTFIELDS, [
      'input' => new CURLFile($target_file_path, $target_file_mime, $target_post['file_original'])
    ]);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($curl);
    curl_close($curl);

    if ($response) {
      $processed++;
    }
  }

  $status = "Marked content as CSAM for {$processed} posts";
  funcs_manage_log($status);
  return $status;
}
