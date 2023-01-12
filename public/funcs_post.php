<?php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/exception.php';

function funcs_post_create(string $ip, array $board_cfg, ?int $parent_id, ?array $file_info, array $file, array $input): array {
  // handle anonfile flag
  if ($file_info != null && isset($input['anonfile']) && $input['anonfile'] == true) {
    $file['file_original'] = substr(str_shuffle('0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ'), 1, 8) . '.' . $file_info['ext'];
  }

  // handle spoiler flag
  $spoiler_flag = isset($input['spoiler']) && $input['spoiler'] == true ? 1 : 0;

  // render message
  $message = funcs_post_render_message($board_cfg['id'], $input['message'], $board_cfg['truncate']);

  // parse name, additionally handle trip and secure trip
  $name_trip = [$board_cfg['anonymous'], null];
  if (strlen($input['name']) > 0) {
    $name_trip = funcs_common_generate_tripcode($input['name'], MB_GLOBAL['tripsalt']);
    $name_trip[0] = funcs_common_clean_field($name_trip[0]);
    if ($name_trip[1] != null) {
      $name_trip[1] = funcs_common_clean_field($name_trip[1]);
    }
  }

  $email = funcs_common_clean_field($input['email']);
  $timestamp = time();

  // render nameblock
  $nameblock = funcs_post_render_nameblock($name_trip[0], $name_trip[1], $email, funcs_common_get_role(), $timestamp);

  return [
    'board_id'            => $board_cfg['id'],
    'parent_id'           => $parent_id != null ? $parent_id : 0,
    'name'                => $name_trip[0],
    'tripcode'            => $name_trip[1],
    'nameblock'           => $nameblock,
    'email'               => funcs_common_clean_field($input['email']),
    'subject'             => funcs_common_clean_field($input['subject']),
    'message'             => $input['message'],
    'message_rendered'    => $message['rendered'],
    'message_truncated'   => $message['truncated'],
    'password'            => (isset($input['password']) && strlen($input['password']) > 0) ? funcs_common_hash_password($input['password']) : null,
    'file'                => $file['file'],
    'file_hex'            => $file['file_hex'],
    'file_original'       => funcs_common_clean_field($file['file_original']),
    'file_size'           => $file['file_size'],
    'file_size_formatted' => $file['file_size_formatted'],
    'image_width'         => $file['image_width'],
    'image_height'        => $file['image_height'],
    'thumb'               => $file['thumb'],
    'thumb_width'         => $file['thumb_width'],
    'thumb_height'        => $file['thumb_height'],
    'spoiler'             => $spoiler_flag,
    'timestamp'           => $timestamp,
    'bumped'              => $timestamp,
    'ip'                  => $ip,
    'country_code'        => null
  ];
}

function funcs_post_render_nameblock(string $name, ?string $tripcode, ?string $email, ?int $role, int $timestamp): string {
  $nameblock = '';

  if (isset($email) && strlen($email) > 0) {
    $nameblock .= "<a href='mailto:{$email}'><span class='post-name'>{$name}</span></a>";
  } else {
    $nameblock .= "<span class='post-name'>{$name}</span>";
  }

  if (isset($tripcode) && strlen($tripcode) > 0) {
    $nameblock .= "<span class='post-trip'>!{$tripcode}</span>";
  }

  $nameblock .= "\n";

  switch ($role) {
    case MB_ROLE_SUPERADMIN:
    case MB_ROLE_ADMIN:
      $nameblock .= '<span class="post-cap cap-admin">## Admin</span>';
      break;
    case MB_ROLE_MODERATOR:
      $nameblock .= '<span class="post-cap cap-mod">## Mod</span>';
      break;
    default: break;
  }

  $nameblock .= "\n<span class='post-datetime'>" . strftime(MB_GLOBAL['datefmt'], $timestamp) . "</span>\n";

  return $nameblock;
}

function funcs_post_render_message(string $board_id, string $input, int $truncate): array {
  // escape message HTML entities
  $message = funcs_common_clean_field($input);

  // preprocess message reference links (same board)
  $message = preg_replace_callback('/(^&gt;&gt;)([0-9]+)/m', function ($matches) use ($board_id) {
    $post = select_post($board_id, intval($matches[2]));

    if ($post) {
      if ($post['parent_id'] === 0) {
        $data_fields = "data-board_id='{$post['board_id']}' data-parent_id='{$post['post_id']}' data-id='{$post['post_id']}'";
        return "<a class='reference' {$data_fields} href='/{$post['board_id']}/{$post['post_id']}/#{$post['board_id']}-{$post['post_id']}'>{$matches[0]}</a>";
      } else {
        $data_fields = "data-board_id='{$post['board_id']}' data-parent_id='{$post['parent_id']}' data-id='{$post['post_id']}'";
        return "<a class='reference' {$data_fields} href='/{$post['board_id']}/{$post['parent_id']}/#{$post['board_id']}-{$post['post_id']}'>{$matches[0]}</a>";
      }
    }
    
    return $matches[0];
  }, $message);

  // preprocess message reference links (any board)
  $message = preg_replace_callback('/(^&gt;&gt;&gt;)\/([a-z]+)\/([0-9]+)/m', function ($matches) {
    $post = select_post($matches[2], intval($matches[3]));

    if ($post) {
      if ($post['parent_id'] === 0) {
        $data_fields = "data-board_id='{$post['board_id']}' data-parent_id='{$post['post_id']}' data-id='{$post['post_id']}'";
        return "<a class='reference' {$data_fields} href='/{$post['board_id']}/{$post['post_id']}/#{$post['board_id']}-{$post['post_id']}'>{$matches[0]}</a>";
      } else {
        $data_fields = "data-board_id='{$post['board_id']}' data-parent_id='{$post['parent_id']}' data-id='{$post['post_id']}'";
        return "<a class='reference' {$data_fields} href='/{$post['board_id']}/{$post['parent_id']}/#{$post['board_id']}-{$post['post_id']}'>{$matches[0]}</a>";
      }
    }
    
    return $matches[0];
  }, $message);
  
  // preprocess message quotes
  $message = preg_replace('/(^&gt;)(?!&gt;)([^\r\n]+)/m', '<span class="quote">$0</span>', $message);

  // preprocess message bbcode
  $message = preg_replace('/\[(b|i|u|s)\](.*?)\[\/\1\]/ms', '<$1>$2</$1>', $message);
  $message = preg_replace('/\[code\](.*?)\[\/code\]/ms', '<pre>$1</pre>', $message);
  $message = preg_replace('/\[spoiler\](.*?)\[\/spoiler\]/ms', '<span class="spoiler">$1</span>', $message);

  // convert message line endings
  $message = nl2br($message, false);

  // strip HTML tags inside <pre></pre>
  $message = preg_replace_callback('/\<pre\>(.*?)\<\/pre\>/ms', function ($matches) {
    return '<pre>' . strip_tags($matches[1]) . '</pre>';
  }, $message);

  // get truncated message
  $message_truncated = $message;
  $message_truncated_flag = funcs_common_truncate_string_linebreak($message_truncated, $truncate, true);

  return [
    'rendered'  => $message,
    'truncated' => $message_truncated_flag ? $message_truncated : null
  ];
}
