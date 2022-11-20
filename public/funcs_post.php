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

  // escape message HTML entities
  $message = funcs_common_clean_field($input['message']);

  // preprocess message reference links (same board)
  $message = preg_replace_callback('/(^&gt;&gt;)([0-9]+)/m', function ($matches) use ($board_cfg) {
    $post = select_post($board_cfg['id'], intval($matches[2]));

    if ($post) {
      if ($post['parent_id'] === 0) {
        $data_fields = "data-board_id='{$post['board_id']}' data-parent_id='{$post['id']}' data-id='{$post['id']}'";
        return "<a class='reference' {$data_fields} href='/{$post['board_id']}/{$post['id']}/#{$post['id']}'>{$matches[0]}</a>";
      } else {
        $data_fields = "data-board_id='{$post['board_id']}' data-parent_id='{$post['parent_id']}' data-id='{$post['id']}'";
        return "<a class='reference' {$data_fields} href='/{$post['board_id']}/{$post['parent_id']}/#{$post['id']}'>{$matches[0]}</a>";
      }
    }
    
    return $matches[0];
  }, $message);

  // preprocess message reference links (any board)
  $message = preg_replace_callback('/(^&gt;&gt;&gt;)\/([a-z]+)\/([0-9]+)/m', function ($matches) use ($board_cfg) {
    $post = select_post($matches[2], intval($matches[3]));

    if ($post) {
      if ($post['parent_id'] === 0) {
        $data_fields = "data-board_id='{$post['board_id']}' data-parent_id='{$post['id']}' data-id='{$post['id']}'";
        return "<a class='reference' {$data_fields} href='/{$post['board_id']}/{$post['id']}/#{$post['id']}'>{$matches[0]}</a>";
      } else {
        $data_fields = "data-board_id='{$post['board_id']}' data-parent_id='{$post['parent_id']}' data-id='{$post['id']}'";
        return "<a class='reference' {$data_fields} href='/{$post['board_id']}/{$post['parent_id']}/#{$post['id']}'>{$matches[0]}</a>";
      }
    }
    
    return $matches[0];
  }, $message);
  
  // preprocess message quotes
  $message = preg_replace('/(^&gt;)([^&gt;].*)/m', '<span class="quote">$0</span>', $message);

  // preprocess message bbcode
  $message = preg_replace('/\[(b|i|u|s)\](.*?)\[\/\1\]/ms', '<$1>$2</$1>', $message);
  $message = preg_replace('/\[code\](.*?)\[\/code\]/ms', '<pre>$1</pre>', $message);
  $message = preg_replace('/\[quote\](.*?)\[\/quote\]/ms', '<blockquote>$1</blockquote>', $message);
  $message = preg_replace('/\[quote="(.*?)"\](.*?)\[\/quote\]/ms', '<blockquote>$2</blockquote><p>~ $1 ~</p>', $message);

  // convert message line endings
  $message = nl2br($message, false);

  // strip HTML tags inside <pre></pre>
  $message = preg_replace_callback('/\<pre\>(.*?)\<\/pre\>/ms', function ($matches) {
    return '<pre>' . strip_tags($matches[1]) . '</pre>';
  }, $message);

  // get truncated message
  $message_truncated = $message;
  $message_truncated_flag = funcs_common_truncate_string_linebreak($message_truncated, $board_cfg['truncate'], true);

  return [
    'board_id'            => $board_cfg['id'],
    'parent_id'           => $parent_id != null ? $parent_id : 0,
    'name'                => strlen($input['name']) !== 0 ? funcs_common_clean_field($input['name']) : $board_cfg['anonymous'],
    'tripcode'            => null,
    'email'               => funcs_common_clean_field($input['email']),
    'subject'             => funcs_common_clean_field($input['subject']),
    'message'             => $input['message'],
    'message_rendered'    => $message,
    'message_truncated'   => $message_truncated_flag ? $message_truncated : null,
    'password'            => null,
    'file'                => $file['file'],
    'file_hex'            => $file['file_hex'],
    'file_original'       => $file['file_original'],
    'file_size'           => $file['file_size'],
    'file_size_formatted' => $file['file_size_formatted'],
    'image_width'         => $file['image_width'],
    'image_height'        => $file['image_height'],
    'thumb'               => $file['thumb'],
    'thumb_width'         => $file['thumb_width'],
    'thumb_height'        => $file['thumb_height'],
    'spoiler'             => $spoiler_flag,
    'timestamp'           => time(),
    'bumped'              => time(),
    'ip'                  => $ip,
    'stickied'            => 0,
    'moderated'           => 1,
    'country_code'        => null
  ];
}
