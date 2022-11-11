<?php

use Psr\Http\Message\UploadedFileInterface;

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/exception.php';

/**
 * Takes a temp uploaded file and validates it.
 * 
 * @param array $board
 * @param UploadedFileInterface $file
 * @return array
 */
function funcs_post_validate_file(array $board, UploadedFileInterface $file) : array {
  if ($file->getError() === UPLOAD_ERR_NO_FILE) {
    return [
      'no_file' => true
    ];
  } else if ($file->getError() !== UPLOAD_ERR_OK) {
    return ['error' => 'UPLOAD_ERR: ' . $file->getError()];
  }

  // get temp file handle
  $tmp_file = $file->getStream()->getMetadata('uri');

  // validate MIME type
  $finfo = finfo_open(FILEINFO_MIME);
  $file_mime = explode(';', finfo_file($finfo, $tmp_file))[0];
  finfo_close($finfo);
  $file_mime_ext_type = $board_cfg['mime_ext_types'][$file_mime];
  if (!isset($file_mime_ext_type)) {
    return ['error' => 'INVALID_MIME_TYPE: ' . $file_mime];
  }

  // validate file size
  $file_size = filesize($tmp_file);
  $max_bytes = $board_cfg['maxkb'] * 1000;
  if ($file_size > $max_bytes) {
    return ['error' => 'FILE_MAX_SIZE_EXCEEDED: ' . $file_size . '>' . $max_bytes];
  }

  // calculate md5 hash
  $file_md5 = md5_file($tmp_file);

  return [
    'tmp_file'            => $tmp_file,
    'file_mime'           => $file_mime,
    'file_size'           => $file_size,
    'file_md5'            => $file_md5
  ];
}

/**
 * Takes a temp uploaded file, processes it, generates thumbnail, etc. and saves result to a persistent location.
 * 
 * @param UploadedFileInterface $file
 * @param array $file_info
 * @param array $file_collisions
 * @param array $board_cfg
 * @return array
 */
function upload_file(UploadedFileInterface $file, array $file_info, array $file_collisions, array $board_cfg) : array {
  // no file was uploaded
  if (isset($file_info['no_file'])) {
    return [
      'file'                => '',
      'file_hex'            => '',
      'file_original'       => '',
      'file_size'           => 0,
      'file_size_formatted' => '',
      'image_width'         => 0,
      'image_height'        => 0,
      'thumb'               => '',
      'thumb_width'         => 0,
      'thumb_height'        => 0
    ];
  }

  $file_name_client = $file->getClientFilename();

  // either use the uploaded file or an already existing file
  if (empty($file_collisions)) {
    $file_ext = pathinfo($file_name_client, PATHINFO_EXTENSION);
    $file_name = time() . substr(microtime(), 2, 3) . '.' . $file_ext;
    $file_path = __DIR__ . '/src/' . $file_name;
    $file->moveTo($file_path);
    $file_hex = $file_info['file_md5'];
    $file_size = $file_info['file_size'];
    $file_size_formatted = human_filesize($file_size);
    $thumb_file_name = 'thumb_' . $file_name . '.png';
    $thumb_file_path = __DIR__ . '/src/' . $thumb_file_name;
    $thumb_width = $board_cfg['max_width'];
    $thumb_height = $board_cfg['max_height'];

    switch ($file_info['file_mime']) {
      case 'image/jpeg':
      case 'image/pjpeg':
      case 'image/png':
      case 'image/gif':
      case 'image/bmp':
      case 'image/webp':
        $generated_thumb = generate_thumbnail($file_path, 'image/png', $thumb_file_path, $thumb_width, $thumb_height);
        $image_width = $generated_thumb['image_width'];
        $image_height = $generated_thumb['image_height'];
        $thumb_width = $generated_thumb['thumb_width'];
        $thumb_height = $generated_thumb['thumb_height'];
        break;
      case 'video/mp4':
      case 'video/webm':
        $ffprobe = FFMpeg\FFProbe::create();
        $video_duration = $ffprobe
          ->format($file_path)
          ->get('duration');

        $ffmpeg = FFMpeg\FFMpeg::create();
        $video = $ffmpeg->open($file_path);
        $video
          ->frame(FFMpeg\Coordinate\TimeCode::fromSeconds($video_duration / 4))
          ->save($thumb_file_path);

        $generated_thumb = generate_thumbnail($thumb_file_path, 'image/png', $thumb_file_path, $thumb_width, $thumb_height);
        $image_width = $generated_thumb['image_width'];
        $image_height = $generated_thumb['image_height'];
        $thumb_width = $generated_thumb['thumb_width'];
        $thumb_height = $generated_thumb['thumb_height'];
        break;
      default:
        unlink($file_path);
        return ['error' => 'UNSUPPORTED_MIME_TYPE: ' . $file_info['file_mime']];
    }
  } else {
    $file_name = $file_collisions[0]['file'];
    $file_hex = $file_collisions[0]['file_hex'];
    $file_size = $file_collisions[0]['file_size'];
    $file_size_formatted = $file_collisions[0]['file_size_formatted'];
    $image_width = $file_collisions[0]['image_width'];
    $image_height = $file_collisions[0]['image_height'];
    $thumb_file_name = $file_collisions[0]['thumb'];
    $thumb_width = $file_collisions[0]['thumb_width'];
    $thumb_height = $file_collisions[0]['thumb_height'];
  }

  return [
    'file'                => $file_name,
    'file_hex'            => $file_hex,
    'file_original'       => $file_name_client,
    'file_size'           => $file_size,
    'file_size_formatted' => $file_size_formatted,
    'image_width'         => $image_width,
    'image_height'        => $image_height,
    'thumb'               => $thumb_file_name,
    'thumb_width'         => $thumb_width,
    'thumb_height'        => $thumb_height
  ];
}

/**
 * Creates a post object that's ready to be saved into database.
 * 
 * @param array $board
 * @param array $params
 * @param array $file
 * @return array
 */
function funcs_post_create_post(array $board, int $parent_id, array $params, array $file) : array {
  // escape message HTML entities
  $message = funcs_common_clean_field($params['message']);

  // preprocess message reference links (same board)
  $message = preg_replace_callback('/(^&gt;&gt;)([0-9]+)/m', function ($matches) use ($board) {
    $post = select_post($board['id'], intval($matches[2]));

    if ($post) {
      if ($post['parent'] === 0) {
        return "<a class='reference' href='/{$post['board_id']}/{$post['id']}/#{$post['id']}'>{$matches[0]}</a>";
      } else {
        return "<a class='reference' href='/{$post['board_id']}/{$post['parent_id']}/#{$post['id']}'>{$matches[0]}</a>";
      }
    }
    
    return $matches[0];
  }, $message);

  // preprocess message reference links (any board)
  $message = preg_replace_callback('/(^&gt;&gt;&gt;)\/([a-z]+)\/([0-9]+)/m', function ($matches) {
    $post = select_post($matches[2], intval($matches[3]));

    if ($post) {
      if ($post['parent'] === 0) {
        return "<a class='reference' href='/{$post['board_id']}/{$post['id']}/#{$post['id']}'>{$matches[0]}</a>";
      } else {
        return "<a class='reference' href='/{$post['board_id']}/{$post['parent_id']}/#{$post['id']}'>{$matches[0]}</a>";
      }
    }
    
    return $matches[0];
  }, $message);
  
  // preprocess message quotes
  $message = preg_replace('/(^&gt;)([a-zA-Z0-9,.-;:_ ]+)/m', '<span class="quote">$0</span>', $message);

  // preprocess message bbcode
  $message = preg_replace('/\[(b|i|u|s)\](.*?)\[\/\1\]/ms', '<$1>$2</$1>', $message);
  $message = preg_replace('/\[code\](.*?)\[\/code\]/ms', '<pre>$1</pre>', $message);

  // convert message line endings
  $message = nl2br($message, false);

  // strip HTML tags inside <pre></pre>
  $message = preg_replace_callback('/\<pre\>(.*?)\<\/pre\>/ms', function ($matches) {
    return '<pre>' . strip_tags($matches[1]) . '</pre>';
  }, $message);

  // get truncated message
  $message_truncated = $message;
  $message_truncated_flag = funcs_post_truncate_message_linebreak($message_truncated, $board['truncate'], TRUE);

  return [
    'board_id'            => $board['id'],
    'parent_id'           => $parent_id,
    'name'                => strlen($params['name']) !== 0 ? funcs_common_clean_field($params['name']) : $board['anonymous'],
    'tripcode'            => null,
    'email'               => funcs_common_clean_field($params['email']),
    'subject'             => funcs_common_clean_field($params['subject']),
    'message'             => $params['message'],
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
    'timestamp'           => time(),
    'bumped'              => time(),
    'ip'                  => funcs_common_get_client_remote_address($_SERVER),
    'stickied'            => 0,
    'moderated'           => 1,
    'country_code'        => null
  ];
}


/**
 * Truncates a message if it is too long for eg. catalog page.
 *
 * @param string $message Message to truncate.
 * @param int $length Length the message should be truncated to.
 * @return string
 */
function funcs_post_truncate_message(string $message, int $length): string
{
  if (strlen($message) > $length) {
    return trim(substr(string: $message, offset: 0, length: $length)) . '...';
  } else {
    return $message;
  }
}

/**
 * Truncates a message to N line breaks (\n or <br>).
 * 
 * @param string &$input Message to truncate.
 * @param int $br_count Line break count the message should be truncated to.
 * @param bool $handle_html Terminate HTML elements if they were cut on truncate?
 * @return bool
 */
function funcs_post_truncate_message_linebreak(string &$input, int $br_count = 15, bool $handle_html = TRUE): bool {
  // exit early if nothing to truncate
  if (substr_count($input, '<br>') + substr_count($input, "\n") <= $br_count)
      return FALSE;

  // get number of line breaks and their offsets
  $br_offsets_func = function(string $haystack, string $needle, int $offset) {
    $result = array();
    for ($i = $offset; $i < strlen($haystack); $i++) {
      $pos = strpos($haystack, $needle, $i);
      if ($pos !== False) {
        $offset = $pos;
        if ($offset >= $i) {
          $i = $offset;
          $result[] = $offset;
        }
      }
    }
    return $result;
  };
  $br_offsets = array_merge($br_offsets_func($input, '<br>', 0), $br_offsets_func($input, "\n", 0));
  sort($br_offsets);

  // truncate simply via line break threshold
  $input = substr($input, 0, $br_offsets[$br_count - 1]);

  // handle HTML elements in-case termination fails
  if ($handle_html) {
    $open_tags = [];

    preg_match_all('/(<\/?([\w+]+)[^>]*>)?([^<>]*)/', $input, $matches, PREG_SET_ORDER);
    foreach ($matches as $match) {
      if (preg_match('/br/i', $match[2]))
        continue;
      
      if (preg_match('/<[\w]+[^>]*>/', $match[0])) {
        array_unshift($open_tags, $match[2]);
      }
    }

    foreach ($open_tags as $open_tag) {
      $input .= '</' . $open_tag . '>';
    }
  }

  return TRUE;
}
