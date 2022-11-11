<?php

use Psr\Http\Message\UploadedFileInterface;

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';

/**
 * Parses query param key value as an integer.
 * 
 * @param array $query
 * @param array $key
 * @param int $def
 * @param int $min
 * @param int $max
 * @return int
 */
function get_query_param_int(array $query, string $key, int $def = 0, int $min = null, int $max = null) : int {
  if (!isset($query[$key])) {
    return $def;
  }

  $result = intval($query[$key]);

  if ($min !== null) {
    $result = max($min, $result);
  }

  if ($max !== null) {
    $result = min($max, $result);
  }

  return $result;
}

/**
 * Parses query param key value as a string.
 * 
 * @param array $query
 * @param string $key
 * @param string $def
 * @return string
 */
function get_query_param_str(array $query, string $key, string $def = '') : string {
  if (!isset($query[$key])) {
    return $def;
  }

  return $query[$key];
}

/**
 * Validates user input in case of a generic GET/POST/etc request.
 * 
 * @param array @args
 * @return array
 */
function validate_request(array $args) : array {
  $board_id = $args['board_id'];
  if (!isset(MB_BOARDS[$board_id])) {
    return ['error' => 'INVALID_BOARD: ' . $board_id];
  }

  return ['board_cfg' => MB_BOARDS[$board_id]];
}

/**
 * Validates user input in case of POST postform request.
 * 
 * @param array @args
 * @param array $params
 * @return array
 */
function validate_post_postform(array $args, array $params) : array {
  $board_id = $args['board_id'];
  if (!isset(MB_BOARDS[$board_id])) {
    return ['error' => 'INVALID_BOARD: ' . $board_id];
  }

  $board_cfg = MB_BOARDS[$board_id];

  $validated_fields = ['name', 'email', 'subject', 'message'];
  foreach ($validated_fields as $field) {
    $max_len = $board_cfg['max_' . $field];
    if (strlen($params[$field]) > $max_len) {
      return ['error' => 'FIELD_MAX_LEN_EXCEEDED: ' . $field . '>' . $max_len];
    }
  }

  return ['board_cfg' => $board_cfg];
}

/**
 * Creates a hide object that's ready to be saved into database.
 * 
 * @param array $args
 * @param array $params
 * @return array
 */
function create_hide(array $args, array $params) : array {
  $board_cfg = MB_BOARDS[$args['board_id']];

  return [
    'session_id'          => session_id(),
    'board_id'            => $board_cfg['id'],
    'post_id'             => $args['post_id']
  ];
}

/**
 * Validates user input in case of POST reportform request.
 * 
 * @param array @args
 * @param array $params
 * @return array
 */
function validate_post_reportform(array $args, array $params) : array {
  $board_id = $args['board_id'];
  if (!isset(MB_BOARDS[$board_id])) {
    return ['error' => 'INVALID_BOARD: ' . $board_id];
  }

  $board_cfg = MB_BOARDS[$board_id];

  if (!array_key_exists($params['type'], MB_GLOBAL['report_types'])) {
    return ['error' => 'INVALID_REPORT_TYPE: ' . $params['type']];
  }

  return ['board_cfg' => $board_cfg];
}

/**
 * Creates a report object that's ready to be saved into database.
 * 
 * @param array $args
 * @param array $params
 * @param array $post
 * @return array
 */
function create_report(array $args, array $params, array $post) : array {
  $board_cfg = MB_BOARDS[$args['board_id']];

  return [
    'ip'                  => get_client_remote_address($_SERVER),
    'timestamp'           => time(),
    'post_id'             => $post['id'],
    'type'                => MB_GLOBAL['report_types'][$params['type']]
  ];
}

/**
 * Creates a post object that's ready to be saved into database.
 * 
 * @param array $args
 * @param array $params
 * @param array $file
 * @return array
 */
function create_post(array $args, array $params, array $file) : array {
  $board_cfg = MB_BOARDS[$args['board_id']];

  // escape message HTML entities
  $message = clean_field($params['message']);

  // preprocess message reference links (same board)
  $message = preg_replace_callback('/(^&gt;&gt;)([0-9]+)/m', function ($matches) use ($board_cfg) {
    $post = select_post($board_cfg['id'], intval($matches[2]));

    if ($post) {
      if ($post['parent_id'] === 0) {
        return "<a class='reference' href='/{$board_cfg['id']}/{$post['id']}/#{$post['id']}'>{$matches[0]}</a>";
      } else {
        return "<a class='reference' href='/{$board_cfg['id']}/{$post['parent_id']}/#{$post['id']}'>{$matches[0]}</a>";
      }
    }
    
    return $matches[0];
  }, $message);

  // preprocess message reference links (any board)
  $message = preg_replace_callback('/(^&gt;&gt;&gt;)\/([a-z]+)\/([0-9]+)/m', function ($matches) use ($board_cfg) {
    $post = select_post($matches[2], intval($matches[3]));

    if ($post) {
      if ($post['parent_id'] === 0) {
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
  $message_truncated_flag = truncate_message_linebreak($message_truncated, $board_cfg['truncate'], TRUE);

  return [
    'board_id'            => $board_cfg['id'],
    'parent_id'           => isset($args['thread_id']) && is_numeric($args['thread_id']) ? $args['thread_id'] : 0,
    'name'                => strlen($params['name']) !== 0 ? clean_field($params['name']) : $board_cfg['anonymous'],
    'tripcode'            => null,
    'email'               => clean_field($params['email']),
    'subject'             => clean_field($params['subject']),
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
    'ip'                  => get_client_remote_address($_SERVER),
    'stickied'            => 0,
    'moderated'           => 1,
    'country_code'        => null
  ];
}

/**
 * Escapes an user submitted text field before it's stored in database.
 * 
 * @param string $field
 * @return string
 */
function clean_field(string $field) : string {
  return htmlspecialchars($field, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML401, 'UTF-8');
}

/**
 * Takes a temp uploaded file and validates it.
 * 
 * @param UploadedFileInterface $file
 * @param array $board_cfg
 * @return array
 */
function validate_file(UploadedFileInterface $file, array $board_cfg) : array {
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
 * Turns a size in bytes to a human readable string representation.
 * 
 * @return string
 */
function human_filesize(int $bytes, int $dec = 2) : string {
  $size = array('B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB');
  $factor = floor((strlen($bytes) - 1) / 3);

  return sprintf("%.{$dec}f", $bytes / pow(1024, $factor)) . @$size[$factor];
}

/**
 * Generates a thumbnail based on existing image.
 * 
 * @param string $file_path
 * @param string $file_mime
 * @param string $thumb_path
 * @param int $thumb_width
 * @param int $thumb_height
 * @return array
 */
function generate_thumbnail(string $file_path, string $file_mime, string $thumb_path, int $thumb_width, int $thumb_height) : array {
  $image = new \claviska\SimpleImage();
  $image->fromFile($file_path);
  $image_width = $image->getWidth();
  $image_height = $image->getHeight();

  // re-calculate thumb dims
  $width_ratio = $thumb_width / $image_width;
  $height_ratio = $thumb_height / $image_height;
  $scale_factor = min($width_ratio, $height_ratio);
  $thumb_width = floor($image_width * $scale_factor);
  $thumb_height = floor($image_height * $scale_factor);

  $image
    ->thumbnail($thumb_width, $thumb_height, 'center')
    ->toFile($thumb_path, $file_mime, 100);
  
  return [
    'image_width'   => $image_width,
    'image_height'  => $image_height,
    'thumb_width'   => $thumb_width,
    'thumb_height'  => $thumb_height
  ];
}

/**
 * Truncates a message if it is too long for eg. catalog page.
 *
 * @param string $message
 * @param int $length
 * @return string
 */
function truncate_message(string $message, int $length): string
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
 * @param string &$input
 * @param int $br_count
 * @param bool $handle_html
 * @return bool
 */
function truncate_message_linebreak(string &$input, int $br_count = 15, bool $handle_html = TRUE) : bool {
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

/**
 * Returns client remote IPv4 or IPv6 address.
 * 
 * @return string
 */
function get_client_remote_address(array $server) {
  if (MB_GLOBAL['cloudflare'] && isset($server['HTTP_CF_CONNECTING_IP'])) {
    return $server['HTTP_CF_CONNECTING_IP'];
  }

  return $server['REMOTE_ADDR'];
}
