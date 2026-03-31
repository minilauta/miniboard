<?php

require_once __ROOT__ . '/common/config.php';
require_once __ROOT__ . '/common/exception.php';
require_once __ROOT__ . '/common/funcs_common.php';
require_once __ROOT__ . '/models/post_history.php';

use minichan\models\PostEvent;

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
    if (isset($board_cfg['hashid_salt']) && strlen($board_cfg['hashid_salt']) >= 2) {
      $hashid = funcs_common_generate_hashid($post['salt'], $post['ip_str'], $board_cfg['hashid_salt']);
    }

    // set nameblock country code if flags enabled OR country code is T1/VPN
    $country = $post['country'];
    $country_nb = null;
    if ($board_cfg['flags'] == true || $country == 't1' || $country == 'vpn') {
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

    $post = select_post($selected_board_id, $selected_post_id);

    // delete post and replies, files, etc...
    $warnings = array_merge($warnings, funcs_common_delete_post($selected_board_id, $selected_post_id));
    $processed++;

    // record post history after deletion
    if ($post != null) {
      insert_post_history($selected_board_id, $selected_post_id, $post['parent_id'], PostEvent::DeletedAdmin->value);
    }
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

function funcs_manage_ban(array $select, int $duration, string $reason, bool $capture = false): string {
  // escape reason HTML entities
  $reason = funcs_common_clean_field($reason);

  // ban each poster
  $processed = 0;
  foreach ($select as $val) {
    // parse board id and post id
    $selected_parsed = explode('/', $val);
    $selected_board_id = $selected_parsed[0];
    $selected_post_id = intval($selected_parsed[1]);

    // build post preview for the ban record if capture is enabled
    $post_preview = null;
    if ($capture) {
      $post = select_post($selected_board_id, $selected_post_id);
      if ($post) {
        // copy thumbnail to /src/bans/ so it survives post deletion
        $thumb = null;
        if (!empty($post['thumb'])) {
          if (str_contains($post['thumb'], '/static/')) {
            $thumb = $post['thumb'];
          } else {
            $bans_dir = __PUBLIC__ . '/src/bans';
            if (!is_dir($bans_dir)) {
              mkdir($bans_dir, 0755, true);
            }
            $ext = pathinfo($post['thumb'], PATHINFO_EXTENSION);
            $ban_thumb_name = "ban_{$post['board_id']}_{$post['post_id']}_" . time() . ".{$ext}";
            $ban_thumb_path = $bans_dir . '/' . $ban_thumb_name;
            $src_path = __PUBLIC__ . $post['thumb'];
            if (is_file($src_path) && copy($src_path, $ban_thumb_path)) {
              $thumb = '/src/bans/' . $ban_thumb_name;
            }
          }
        }

        $post_preview = [
          'board_id'         => $post['board_id'],
          'post_id'          => $post['post_id'],
          'subject'          => $post['subject'],
          'nameblock'        => $post['nameblock'],
          'message_rendered' => $post['message_rendered'],
          'thumb'            => $thumb,
          'thumb_width'      => $thumb !== null ? $post['thumb_width'] : null,
          'thumb_height'     => $thumb !== null ? $post['thumb_height'] : null,
        ];
      }
    }

    // ban poster by board id and post id
    $processed += ban_poster_by_post_id($selected_board_id, $selected_post_id, $duration, $reason, $post_preview);

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

function funcs_manage_edit_post(array $input): string {
  // fetch existing post
  $post = select_post($input['board_id'], $input['post_id']);
  if (!$post) {
    throw new \AppException('funcs_manage', 'edit_post', "post /{$input['board_id']}/{$input['post_id']}/ not found", SC_NOT_FOUND);
  }

  // get board config
  $board_cfg = funcs_common_get_board_cfg($input['board_id']);

  // render message
  $message = funcs_board_render_message($input['board_id'], $post['parent_id'], $input['message'], $board_cfg['truncate']);
  if (empty($input['silent'])) {
    $notice_text = funcs_common_clean_field($input['notice'] ?? '');
    $notice = strlen($notice_text) > 0
      ? '<br><br><span class="edited">(POST EDITED BY A MODERATOR: ' . $notice_text . ')</span>'
      : '<br><br><span class="edited">(POST EDITED BY A MODERATOR)</span>';
    $message['rendered'] .= $notice;
    if (isset($message['truncated'])) {
      $message['truncated'] .= $notice;
    }
  }

  // re-generate hashed ID
  $hashid = null;
  if (isset($board_cfg['hashid_salt']) && strlen($board_cfg['hashid_salt']) >= 2) {
    $ip_str = inet_ntop($post['ip']);
    $hashid = funcs_common_generate_hashid($post['salt'], $ip_str, $board_cfg['hashid_salt']);
  }

  // set nameblock country code if flags enabled OR country code is T1/VPN
  $country_nb = null;
  if ($board_cfg['flags'] == true || $post['country'] == 't1' || $post['country'] == 'vpn') {
    $country_nb = $post['country'];
  }

  // clean name, use board default if empty
  $name = strlen($input['name']) > 0 ? funcs_common_clean_field($input['name']) : $board_cfg['anonymous'];
  $email = funcs_common_clean_field($input['email']);

  // render nameblock
  $nameblock = funcs_board_render_nameblock($name, $post['tripcode'], $email, $hashid, $country_nb, $post['role'], $post['timestamp']);

  // update post
  $update = [
    'board_id' => $input['board_id'],
    'post_id' => $input['post_id'],
    'name' => $name,
    'email' => $email,
    'subject' => funcs_common_clean_field($input['subject']),
    'message' => $input['message'],
    'message_rendered' => $message['rendered'],
    'message_truncated' => $message['truncated'],
    'nameblock' => $nameblock,
  ];
  if (!update_edit_post($update)) {
    throw new \AppException('funcs_manage', 'edit_post', "failed to update post /{$input['board_id']}/{$input['post_id']}/", SC_INTERNAL_ERROR);
  }

  // handle file deletion
  $warnings = [];
  if (isset($input['delfile']) && $input['delfile'] && strlen($post['file'] ?? '') > 0) {
    $warnings = funcs_common_delete_post_files($post);

    if (!clear_post_files($input['board_id'], $input['post_id'])) {
      $warnings[] = "Failed to clear file columns";
    }
  }

  $status = "Edited post /{$input['board_id']}/{$input['post_id']}/";
  if (count($warnings) > 0) {
    $status .= "<br>Warnings:<br>- " . implode('<br>- ', $warnings);
  }
  funcs_manage_log($status);
  return $status;
}

function funcs_manage_move_thread(string $src_board_id, int $thread_id, string $dst_board_id): string {
  // validate boards
  $src_cfg = funcs_common_get_board_cfg($src_board_id);
  $dst_cfg = funcs_common_get_board_cfg($dst_board_id);
  if ($src_board_id === $dst_board_id) {
    throw new \AppException('funcs_manage', 'move_thread', 'source and destination boards are the same', SC_BAD_REQUEST);
  }

  // fetch thread posts ordered by post_id ASC
  $posts = select_thread_posts_for_move($src_board_id, $thread_id);
  if (!$posts || count($posts) === 0) {
    throw new \AppException('funcs_manage', 'move_thread', "thread /{$src_board_id}/{$thread_id}/ not found", SC_NOT_FOUND);
  }

  // verify first post is OP
  if ($posts[0]['parent_id'] !== null) {
    throw new \AppException('funcs_manage', 'move_thread', "post /{$src_board_id}/{$thread_id}/ is not a thread", SC_BAD_REQUEST);
  }

  // generate new IDs on destination board
  init_post_auto_increment($dst_board_id);
  $id_map = [];
  foreach ($posts as $post) {
    $id_map[$post['post_id']] = generate_post_auto_increment($dst_board_id);
  }
  $new_op_id = $id_map[$thread_id];

  // rewrite raw messages and prepare posts for reinsertion
  foreach ($posts as &$post) {
    $post['message'] = funcs_manage_rewrite_message_references($post['message'], $src_board_id, $id_map);
  }
  unset($post);

  // perform move in transaction
  $dbh = get_db_handle();
  $dbh->beginTransaction();
  try {
    // delete originals
    delete_thread_posts($src_board_id, $thread_id);

    // insert on destination board
    foreach ($posts as $post) {
      $new_post_id = $id_map[$post['post_id']];
      $new_parent_id = ($post['parent_id'] === null) ? null : $new_op_id;

      // re-render message for destination board context
      $message = funcs_board_render_message($dst_board_id, $new_parent_id, $post['message'], $dst_cfg['truncate']);

      // re-generate hashid with destination board's salt
      $hashid = null;
      if (isset($dst_cfg['hashid_salt']) && strlen($dst_cfg['hashid_salt']) >= 2) {
        $hashid = funcs_common_generate_hashid($post['salt'], $post['ip_str'], $dst_cfg['hashid_salt']);
      }

      // set nameblock country code if flags enabled OR country code is T1/VPN
      $country_nb = null;
      if ($dst_cfg['flags'] == true || $post['country'] == 't1' || $post['country'] == 'vpn') {
        $country_nb = $post['country'];
      }

      // re-render nameblock
      $name = $post['name'] !== '' ? $post['name'] : $dst_cfg['anonymous'];
      $nameblock = funcs_board_render_nameblock($name, $post['tripcode'], $post['email'], $hashid, $country_nb, $post['role'], $post['timestamp']);

      // re-render file
      $file_rendered = $post['file'];
      if ($post['embed'] === 1) {
        $file_rendered = rawurlencode($file_rendered);
      }

      $new_post = [
        'post_id'             => $new_post_id,
        'board_id'            => $dst_board_id,
        'parent_id'           => $new_parent_id,
        'salt'                => $post['salt'],
        'req_role'            => $dst_cfg['req_role'],
        'role'                => $post['role'],
        'name'                => $post['name'],
        'tripcode'            => $post['tripcode'],
        'nameblock'           => $nameblock,
        'email'               => $post['email'],
        'subject'             => $post['subject'],
        'message'             => $post['message'],
        'message_rendered'    => $message['rendered'],
        'message_truncated'   => $message['truncated'],
        'password'            => $post['password'],
        'file'                => $post['file'],
        'file_rendered'       => $file_rendered,
        'file_hex'            => $post['file_hex'],
        'file_original'       => $post['file_original'],
        'file_size'           => $post['file_size'],
        'file_size_formatted' => $post['file_size_formatted'],
        'file_mime'           => $post['file_mime'],
        'file_meta'           => $post['file_meta'],
        'image_width'         => $post['image_width'],
        'image_height'        => $post['image_height'],
        'thumb'               => $post['thumb'],
        'thumb_width'         => $post['thumb_width'],
        'thumb_height'        => $post['thumb_height'],
        'audio_album'         => $post['audio_album'],
        'embed'               => $post['embed'],
        'timestamp'           => $post['timestamp'],
        'bumped'              => $post['bumped'],
        'ip'                  => $post['ip_str'],
        'country'             => $post['country'],
      ];

      if (insert_post($new_post) === false) {
        throw new \AppException('funcs_manage', 'move_thread', "failed to insert post {$new_post_id} on /{$dst_board_id}/", SC_INTERNAL_ERROR);
      }
    }

    // preserve sticky/locked state on the new OP
    if ($posts[0]['stickied'] === 1) {
      toggle_post_stickied($dst_board_id, $new_op_id);
    }
    if ($posts[0]['locked'] === 1) {
      toggle_post_locked($dst_board_id, $new_op_id);
    }

    // append system message
    funcs_manage_create_system_post($dst_board_id, $new_op_id, "Thread moved from >>>/{$src_board_id}/");

    $dbh->commit();
  } catch (\Exception $e) {
    $dbh->rollBack();
    throw $e;
  }

  // record post history for redirect
  insert_post_history($src_board_id, $thread_id, null, PostEvent::Moved->value, $dst_board_id, $new_op_id);

  // refresh auto increment tables
  refresh_post_auto_increment($src_board_id);
  refresh_post_auto_increment($dst_board_id);

  $post_count = count($posts);
  $status = "Moved thread /{$src_board_id}/{$thread_id}/ ({$post_count} posts) to /{$dst_board_id}/{$new_op_id}/";
  funcs_manage_log($status);
  return $status;
}

/**
 * Rewrites post references in a raw message for thread move.
 */
function funcs_manage_rewrite_message_references(string $message, string $src_board_id, array $id_map): string {
  $message = preg_replace_callback('/>>>\/(' . preg_quote($src_board_id, '/') . ')\/([0-9]{1,16})/', function ($m) use ($id_map) {
    $old_id = intval($m[2]);
    if (isset($id_map[$old_id])) {
      return '>>' . $id_map[$old_id];
    }
    return $m[0];
  }, $message);

  $message = preg_replace_callback('/>>([0-9]{1,16})/', function ($m) use ($src_board_id, $id_map) {
    $old_id = intval($m[1]);
    if (isset($id_map[$old_id])) {
      return '>>' . $id_map[$old_id];
    }
    return '>>>/' . $src_board_id . '/' . $old_id;
  }, $message);

  return $message;
}

/**
 * Creates and inserts a system post as a reply to a thread.
 */
function funcs_manage_create_system_post(string $board_id, int $parent_id, string $message): int {
  $board_cfg = funcs_common_get_board_cfg($board_id);
  $parent = select_post($board_id, $parent_id);
  if (!$parent) {
    throw new \AppException('funcs_manage', 'create_system_post', "thread /{$board_id}/{$parent_id}/ not found", SC_NOT_FOUND);
  }

  $post_id = generate_post_auto_increment($board_id);
  $timestamp = time();
  $name = $board_cfg['anonymous'];
  $nameblock = funcs_board_render_nameblock($name, null, null, null, null, MB_ROLE_SYSTEM, $timestamp);
  $message_rendered = funcs_board_render_message($board_id, $parent_id, $message, $board_cfg['truncate']);

  $post = [
    'post_id'             => $post_id,
    'board_id'            => $board_id,
    'parent_id'           => $parent_id,
    'salt'                => $parent['salt'],
    'req_role'            => $board_cfg['req_role'],
    'role'                => MB_ROLE_SYSTEM,
    'name'                => $name,
    'tripcode'            => null,
    'nameblock'           => $nameblock,
    'email'               => null,
    'subject'             => null,
    'message'             => $message,
    'message_rendered'    => $message_rendered['rendered'],
    'message_truncated'   => $message_rendered['truncated'],
    'password'            => null,
    'file'                => null,
    'file_rendered'       => null,
    'file_hex'            => null,
    'file_original'       => null,
    'file_size'           => null,
    'file_size_formatted' => null,
    'file_mime'           => null,
    'file_meta'           => null,
    'image_width'         => null,
    'image_height'        => null,
    'thumb'               => null,
    'thumb_width'         => null,
    'thumb_height'        => null,
    'audio_album'         => null,
    'embed'               => 0,
    'timestamp'           => $timestamp,
    'bumped'              => $timestamp,
    'ip'                  => '127.0.0.1',
    'country'             => null,
  ];

  $result = insert_post($post);
  if ($result === false) {
    throw new \AppException('funcs_manage', 'create_system_post', "failed to insert system post on /{$board_id}/", SC_INTERNAL_ERROR);
  }

  return $post_id;
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
    $target_file_path = __PUBLIC__ . $target_post['file'];
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
