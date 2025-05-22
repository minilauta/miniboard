<?php

use Psr\Http\Message\UploadedFileInterface;

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/exception.php';
require_once __DIR__ . '/shared.php';

/**
 * Validate user access to target board.
 */
function funcs_board_check_access(array $board_cfg, ?int $user_role): bool {
  // board does not require role -> allow access
  $req_role = $board_cfg['req_role'];
  if ($req_role == null) {
    return true;
  }

  // board requires role but user has no role -> block access
  if ($user_role == null) {
    return false;
  }

  // board requires role but user role is insufficient -> block access
  if ($user_role > $req_role) {
    return false;
  }

  // board requires role and user role is sufficient -> allow access
  return true;
}

/**
 * Creates a new post object.
 */
function funcs_board_create_post(string $ip, ?string $country, array $board_cfg, ?int $parent_id, ?array $file_info, array $file, array $input): array {
  // handle anonfile flag
  if ($file_info != null && isset($input['anonfile']) && $input['anonfile'] == true) {
    $file['file_original'] = substr(str_shuffle('0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ'), 1, 8) . '.' . $file_info['ext'];
  }

  // render message
  $message = funcs_board_render_message($board_cfg['id'], $parent_id, $input['message'], $board_cfg['truncate']);

  // parse name, additionally handle trip and secure trip
  $name_trip = [$board_cfg['anonymous'], null];
  if (strlen($input['name']) > 0) {
    $name_trip = funcs_common_generate_tripcode($input['name'], MB_TRIPCODE_SALT);
    $name_trip[0] = funcs_common_clean_field($name_trip[0]);
    if ($name_trip[1] != null) {
      $name_trip[1] = funcs_common_clean_field($name_trip[1]);
    }
  }

  $email = funcs_common_clean_field($input['email']);
  $timestamp = time();

  // handle capcode flag
  $role = null;
  if (funcs_manage_is_logged_in() && isset($input['capcode']) && $input['capcode'] == true || $board_cfg['req_role'] != null) {
    $role = funcs_manage_get_role();
  }

  // generate hashed ID
  $hashid = null;
  if (isset($board_cfg['hashid_salt']) && strlen($board_cfg['hashid_salt']) >= 2 && $parent_id > 0) {
    $hashid = funcs_common_generate_hashid($board_cfg['id'], $parent_id, $ip, $board_cfg['hashid_salt']);
  }

  // set nameblock country code if flags enabled OR country code is T1
  $country_nb = null;
  if ($board_cfg['flags'] == true || $country == 't1') {
    $country_nb = $country;
  }

  // render nameblock
  $nameblock = funcs_board_render_nameblock($name_trip[0], $name_trip[1], $email, $hashid, $country_nb, $role, $timestamp);

  return [
    'board_id'            => $board_cfg['id'],
    'parent_id'           => $parent_id != null ? $parent_id : 0,
    'req_role'            => $board_cfg['req_role'],
    'role'                => $role,
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
    'file_rendered'       => $file['file_rendered'],
    'file_hex'            => $file['file_hex'],
    'file_original'       => funcs_common_clean_field($file['file_original']),
    'file_size'           => $file['file_size'],
    'file_size_formatted' => $file['file_size_formatted'],
    'file_mime'           => $file['file_mime'],
    'image_width'         => $file['image_width'],
    'image_height'        => $file['image_height'],
    'thumb'               => $file['thumb'],
    'thumb_width'         => $file['thumb_width'],
    'thumb_height'        => $file['thumb_height'],
    'audio_album'         => $file['audio_album'],
    'embed'               => $file['embed'],
    'timestamp'           => $timestamp,
    'bumped'              => $timestamp,
    'ip'                  => $ip,
    'country'             => $country,
  ];
}

/**
 * Renders the nameblock-prop of a post object.
 */
function funcs_board_render_nameblock(string $name, ?string $tripcode, ?string $email, ?string $hashid, ?string $country, ?int $role, int $timestamp): string {
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

  if (isset($hashid) && strlen($hashid) > 0 && $role == null) {
    $nameblock .= "<span class='post-hashid'>(ID: <span class='post-hashid-hash'>{$hashid}</span>)</span>";
    $nameblock .= "\n";
  }

  if (isset($country) && strlen($country) > 0) {
    $nameblock .= "<span class='flag flag-{$country}' title='{$country}'></span>";
    $nameblock .= "\n";
  }

  switch ($role) {
    case MB_ROLE_SUPERADMIN:
    case MB_ROLE_ADMIN:
      $nameblock .= '<span class="post-cap cap-admin">## Admin</span>';
      break;
    case MB_ROLE_MODERATOR:
      $nameblock .= '<span class="post-cap cap-mod">## Mod</span>';
      break;
    default:
      break;
  }

  $nameblock .= "\n<span class='post-datetime'>" . date(MB_DATEFORMAT, $timestamp) . "</span>\n";

  return $nameblock;
}

/**
 * Renders the message_rendered and message_truncated -props of a post object.
 */
function funcs_board_render_message(string $board_id, ?int $parent_id, string $input, int $truncate): array {
  // escape message HTML entities
  $message = funcs_common_clean_field($input);

  // break long words
  $message = funcs_common_break_long_words($message, max_len: 256);

  // preprocess message reference links (same board)
  $message = preg_replace_callback('/(&gt;&gt;)([0-9]{1,16})/m', function ($matches) use ($board_id, $parent_id) {
    $post = select_post($board_id, intval($matches[2]));

    if ($post) {
      $post_parent_id = ($post['parent_id'] === 0) ? $post['post_id'] : $post['parent_id'];
      $post_text_suffix = ($post_parent_id !== $parent_id) ? ' (Cross-thread)' : '';
      
      $data_fields = "data-board_id='{$post['board_id']}' data-parent_id='{$post_parent_id}' data-id='{$post['post_id']}'";
      return "<a class='reference' {$data_fields} href='/{$post['board_id']}/{$post_parent_id}/#{$post['board_id']}-{$post['post_id']}'>{$matches[0]}{$post_text_suffix}</a>";
    }

    return $matches[0];
  }, $message);

  // preprocess message reference links (any board)
  $message = preg_replace_callback('/(&gt;&gt;&gt;)\/([a-z]{1,10})\/([0-9]{1,16})/m', function ($matches) {
    $post = select_post($matches[2], intval($matches[3]));

    if ($post) {
      $post_parent_id = ($post['parent_id'] === 0) ? $post['post_id'] : $post['parent_id'];

      $data_fields = "data-board_id='{$post['board_id']}' data-parent_id='{$post_parent_id}' data-id='{$post['post_id']}'";
      return "<a class='reference' {$data_fields} href='/{$post['board_id']}/{$post_parent_id}/#{$post['board_id']}-{$post['post_id']}'>{$matches[0]}</a>";
    }

    return $matches[0];
  }, $message);

  // preprocess message links
  $message = preg_replace(REGEX_MATCH_URL, '<a href="$0" target="_blank">$0</a>', $message);

  // preprocess message quotes
  $message = preg_replace('/(^&gt;)(?!&gt;)([^\r\n]+)/m', '<span class="quote">$0</span>', $message);

  // preprocess message bbcode
  $message = preg_replace('/\[b\](.*?)\[\/b\]/ims', '<b>$1</b>', $message);
  $message = preg_replace('/\[i\](.*?)\[\/i\]/ims', '<i>$1</i>', $message);
  $message = preg_replace('/\[u\](.*?)\[\/u\]/ims', '<u>$1</u>', $message);
  $message = preg_replace('/\[s\](.*?)\[\/s\]/ims', '<s>$1</s>', $message);
  $message = preg_replace('/\[sup\](.*?)\[\/sup\]/ims', '<sup>$1</sup>', $message);
  $message = preg_replace('/\[sub\](.*?)\[\/sub\]/ims', '<sub>$1</sub>', $message);
  $message = preg_replace('/\[code\](.*?)\[\/code\]/ims', '<pre>$1</pre>', $message);
  $message = preg_replace('/\[spoiler\](.*?)\[\/spoiler\]/ims', '<span class="spoiler">$1</span>', $message);

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

/**
 * Validates an uploaded file for errors/abuse.
 */
function funcs_board_validate_upload(UploadedFileInterface $input, bool $no_file_ok, bool $spoiler, array $mime_types, int $max_bytes): ?array {
  // check errors
  $error = $input->getError();
  if ($error === UPLOAD_ERR_NO_FILE && $no_file_ok) {
    return null;
  } else if ($error !== UPLOAD_ERR_OK) {
    throw new AppException('funcs_board', 'validate_upload', "file upload error: {$error}", SC_BAD_REQUEST);
  }

  // get temp file handle
  $tmp_file = $input->getStream()->getMetadata('uri');
  $cli_file = $input->getClientFilename();

  // validate MIME type
  $finfo = finfo_open(FILEINFO_MIME);
  $file_mime = explode(';', finfo_file($finfo, $tmp_file))[0];
  finfo_close($finfo);

  // NOTE: mp3 files are tricky! check for id3v1 and id3v2 tags if finfo_file fails...
  $file_ext_pathinfo = pathinfo($input->getClientFilename(), PATHINFO_EXTENSION);
  if ($file_mime === 'application/octet-stream' && $file_ext_pathinfo === 'mp3') {
    $get_id3 = new getID3;
    $id3_info = $get_id3->analyze($tmp_file);
    if (isset($id3_info['id3v1']) || isset($id3_info['id3v2'])) {
      $file_mime = 'audio/mpeg';
    }
  }
  // NOTE: med files do not contain mime-type in magic.mgc database
  else if ($file_mime === 'application/octet-stream' && $file_ext_pathinfo === 'med') {
    $file_handle = fopen($tmp_file, 'rb');
    $mmd_id = fread($file_handle, 4);
    fclose($file_handle);
    if ($mmd_id != false && in_array($mmd_id, ['MMD0', 'MMD1', 'MMD2', 'MMD3'])) {
      $file_mime = 'audio/x-mod';
    }
  }
  
  if (!isset($mime_types[$file_mime])) {
    throw new AppException('funcs_board', 'validate_upload', "file mime type invalid: {$file_mime}", SC_BAD_REQUEST);
  }
  
  // save to tmp file, terminate stream
  $tmp_file_name = time() . substr(microtime(), 2, 3);
  $tmp_file_dir = sys_get_temp_dir() . '/';
  $tmp_file_path = $tmp_file_dir . $tmp_file_name;
  $input->moveTo($tmp_file_path);

  // calculate md5 hash
  $file_md5 = ($spoiler === true ? 'spoiler/' : '') . md5_file($tmp_file_path);

  // convert avif to png
  if ($file_mime === 'image/avif') {
    $tmp_image = new Imagick($tmp_file_path);
    $tmp_image->setImageFormat('png');
    $tmp_image->writeImage($tmp_file_path);
    $file_mime = 'image/png';
  // convert mkv to mp4 (copy data only)
  } else if ($file_mime === 'video/x-matroska') {
    $new_file_path = funcs_board_video_copy_codec($tmp_file_path, 'mp4');
    unlink($tmp_file_path);
    if ($new_file_path != null) {
      $tmp_file_path = $new_file_path;
      $file_mime = 'video/mp4';
    } else {
      throw new AppException('funcs_board', 'validate_upload', "unable to copy video/audio codec data from matroska container to mp4 container", SC_BAD_REQUEST);
    }
  }

  $file_exts = $mime_types[$file_mime];

  if (count($file_exts) > 1) {
    $file_ext_idx = array_search(strtolower($file_ext_pathinfo), $file_exts);

    if ($file_ext_idx === false) {
      unlink($tmp_file_path);
      throw new AppException('funcs_board', 'validate_upload', "file extension invalid: {$file_ext_pathinfo}", SC_BAD_REQUEST);
    }

    $file_ext = $file_exts[$file_ext_idx];
  } else {
    $file_ext = $file_exts[0];
  }

  // validate file size
  $file_size = filesize($tmp_file_path);
  if ($file_size > $max_bytes) {
    unlink($tmp_file_path);
    throw new AppException('funcs_board', 'validate_upload', "file size exceeds limit: {$file_size} bytes > {$max_bytes} bytes", SC_BAD_REQUEST);
  }

  return [
    'tmp'            => $tmp_file_path,
    'cli'            => $cli_file,
    'mime'           => $file_mime,
    'ext'            => $file_ext,
    'size'           => $file_size,
    'md5'            => $file_md5
  ];
}

/**
 * Processes an uploaded file, stores the file in a persistent path and returns an array containing the results.
 */
function funcs_board_execute_upload(?array $file_info, array $file_collisions, bool $spoiler, int $max_w = 250, int $max_h = 250): array {
  // return if no file was uploaded
  if ($file_info == null) {
    return [
      'file'                => '',
      'file_rendered'       => '',
      'file_hex'            => '',
      'file_original'       => '',
      'file_size'           => 0,
      'file_size_formatted' => '',
      'file_mime'           => null,
      'image_width'         => 0,
      'image_height'        => 0,
      'thumb'               => '',
      'thumb_width'         => 0,
      'thumb_height'        => 0,
      'audio_album'         => null,
      'embed'               => 0
    ];
  }

  // either use the uploaded file or an already existing file
  if (empty($file_collisions)) {
    $file_name = time() . substr(microtime(), 2, 3) . '.' . $file_info['ext'];
    $file_dir = '/src/';
    $file_path = __DIR__ . $file_dir . $file_name;
    if (!rename($file_info['tmp'], $file_path)) {
      unlink($file_info['tmp']);
      throw new AppException('funcs_board', 'execute_upload', "failed to move the uploaded tmp file to a persistent location", SC_INTERNAL_ERROR);
    }
    $file_hex = $file_info['md5'];
    $file_size = $file_info['size'];
    $file_size_formatted = funcs_common_human_filesize($file_size);
    $file_mime = $file_info['mime'];
    $thumb_file_name = 'thumb_' . $file_name . '.png';
    $thumb_dir = '/src/';
    $thumb_file_path = __DIR__ . $thumb_dir . $thumb_file_name;
    $album_file_name = null;
    $album_dir = '/src/';

    // run convert to auto-orient images based on EXIF-data
    switch ($file_info['mime']) {
      case 'image/jpeg':
      case 'image/pjpeg':
      case 'image/png':
      case 'image/webp':
        $convert_status = funcs_board_auto_orient($file_path);
        if ($convert_status !== 0) {
          unlink($file_path);
          throw new AppException('funcs_board', 'execute_upload', "failed to auto-orient image file, convert status: {$convert_status}", SC_INTERNAL_ERROR);
        }
        break;
    }

    // run exiftool on supported file extensions
    switch ($file_info['mime']) {
      case 'image/jpeg':
      case 'image/pjpeg':
      case 'image/png':
      case 'image/gif':
      case 'image/tiff':
      case 'image/webp':
        $exiftool_status = funcs_board_strip_metadata($file_path);
        if ($exiftool_status !== 0) {
          unlink($file_path);
          throw new AppException('funcs_board', 'execute_upload', "failed to strip metadata from file, exiftool status: {$exiftool_status}", SC_INTERNAL_ERROR);
        }
        break;
      // NOTE: these are allowed to fail, not fully supported by exiftool
      case 'image/bmp':
      case 'image/x-ms-bmp':
      case 'video/mp4':
      case 'video/webm':
        funcs_board_strip_metadata($file_path);
        break;
    }

    // generate thumbnail based on file extension
    switch ($file_info['mime']) {
      case 'image/jpeg':
      case 'image/pjpeg':
      case 'image/png':
      case 'image/gif':
      case 'image/bmp':
      case 'image/x-ms-bmp':
      case 'image/tiff':
      case 'image/webp':
      case 'application/pdf':
        $generated_thumb = funcs_board_generate_thumbnail($file_path, $spoiler, false, 'png', $thumb_file_path, $max_w, $max_h);
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

        $generated_thumb = funcs_board_generate_thumbnail($thumb_file_path, $spoiler, true, 'png', $thumb_file_path, $max_w, $max_h);
        $image_width = $generated_thumb['image_width'];
        $image_height = $generated_thumb['image_height'];
        $thumb_width = $generated_thumb['thumb_width'];
        $thumb_height = $generated_thumb['thumb_height'];
        break;
      case 'audio/mpeg':
      case 'audio/wav':
      case 'audio/x-wav':
      case 'audio/vnd.wave':
      case 'audio/flac':
      case 'audio/opus':
      case 'audio/ogg':
        $album_file_path = __DIR__ . $album_dir . 'album_' . $file_name;
        $album_file_result = null;
        if ($file_info['mime'] === 'audio/mpeg') {
          $album_file_result = funcs_board_get_mp3_album_art($file_path, $album_file_path);
        } else {
          $album_file_result = funcs_board_get_audio_album_art($file_path, $album_file_path);
        }
       
        if ($album_file_result != null) {
          $album_file_name = 'album_' . $file_name . '.' . $album_file_result['ext'];
          $generated_thumb = funcs_board_generate_thumbnail($album_file_result['file_path'], $spoiler, true, 'png', $thumb_file_path, $max_w, $max_h);
          $image_width = $generated_thumb['image_width'];
          $image_height = $generated_thumb['image_height'];
          $thumb_width = $generated_thumb['thumb_width'];
          $thumb_height = $generated_thumb['thumb_height'];
        } else {
          $thumb_file_name = 'audio.png';
          $thumb_dir = '/static/';
          $image_width = 0;
          $image_height = 0;
          $thumb_width = 250;
          $thumb_height = 250;
        }
        break;
      case 'audio/x-mod':
      case 'audio/x-s3m':
        $thumb_file_name = 'mod.png';
        $thumb_dir = '/static/';
        $image_width = 0;
        $image_height = 0;
        $thumb_width = 250;
        $thumb_height = 250;
        break;
      case 'application/x-shockwave-flash':
        $thumb_file_name = 'swf.png';
        $thumb_dir = '/static/';
        $image_width = 0;
        $image_height = 0;
        $thumb_width = 250;
        $thumb_height = 250;
        break;
      default:
        unlink($file_path);
        throw new AppException('funcs_board', 'execute_upload', "file MIME type unsupported: {$file_info['mime']}", SC_INTERNAL_ERROR);
    }
  } else {
    unlink($file_info['tmp']);
    
    $file_name = $file_collisions[0]['file'];
    $file_dir = '';
    $file_hex = $file_collisions[0]['file_hex'];
    $file_size = $file_collisions[0]['file_size'];
    $file_size_formatted = $file_collisions[0]['file_size_formatted'];
    $file_mime = $file_collisions[0]['file_mime'];
    $image_width = $file_collisions[0]['image_width'];
    $image_height = $file_collisions[0]['image_height'];
    $thumb_file_name = $file_collisions[0]['thumb'];
    $thumb_dir = '';
    $thumb_width = $file_collisions[0]['thumb_width'];
    $thumb_height = $file_collisions[0]['thumb_height'];
    $album_dir = '';
    $album_file_name = $file_collisions[0]['audio_album'];
  }

  return [
    'file'                => $file_dir . $file_name,
    'file_rendered'       => $file_dir . $file_name,
    'file_hex'            => $file_hex,
    'file_original'       => $file_info['cli'],
    'file_size'           => $file_size,
    'file_size_formatted' => $file_size_formatted,
    'file_mime'           => $file_mime,
    'image_width'         => $image_width,
    'image_height'        => $image_height,
    'thumb'               => $thumb_dir . $thumb_file_name,
    'thumb_width'         => $thumb_width,
    'thumb_height'        => $thumb_height,
    'audio_album'         => isset($album_file_name) ? $album_dir . $album_file_name : null,
    'embed'               => 0
  ];
}

/**
 * Auto-orients the image based on EXIF data
 */
function funcs_board_auto_orient(string $file_path): int {
  // check if convert is available
  $convert_output = '';
  $convert_status = 1;
  exec('convert -version', $convert_output, $convert_status);
  if ($convert_status !== 0) {
    throw new AppException('funcs_board', 'auto_orient', 'convert not installed', SC_INTERNAL_ERROR);
  }

  // execute convert to process the image
  $convert_output = '';
  $convert_status = 1;
  exec('convert ' . escapeshellarg($file_path) . ' -auto-orient ' . escapeshellarg($file_path), $convert_output, $convert_status);

  return $convert_status;
}

/**
 * Strips any metadata from input file using exiftool.
 */
function funcs_board_strip_metadata(string $file_path): int {
  // check if exiftool is available
  $exiftool_output = '';
  $exiftool_status = 1;
  exec('exiftool -ver', $exiftool_output, $exiftool_status);
  if ($exiftool_status !== 0) {
    throw new AppException('funcs_board', 'strip_metadata', 'exiftool not installed', SC_INTERNAL_ERROR);
  }

  // execute exiftool to strip any metadata
  $exiftool_output = '';
  $exiftool_status = 1;
  exec('exiftool -All= -overwrite_original_in_place -m ' . escapeshellarg($file_path), $exiftool_output, $exiftool_status);

  return $exiftool_status;
}

/**
 * Generates a thumbnail from input file.
 */
function funcs_board_generate_thumbnail(string $file_path, bool $spoiler, bool $player, string $thumb_ext, string $thumb_path, int $thumb_width, int $thumb_height): array {
  $file_ext_pathinfo = pathinfo($file_path, PATHINFO_EXTENSION);
  
  $image = null;
  if ($file_ext_pathinfo === 'pdf') {
    $image = new Imagick($file_path . '[0]'); // NOTE: load 1st page only
    $image->setImageBackgroundColor('#FFFFFF');
    $image->setImageAlphaChannel(Imagick::ALPHACHANNEL_REMOVE);
  } else {
    $image = new Imagick($file_path . '[0-9]'); // NOTE: load frames 0-9 (10 frames max)

    if ($file_ext_pathinfo === 'gif') {
      $image = $image->coalesceImages();
      if ($image->count() > 1) {
        $image->next();
        $image = $image->current();
      }
    }
  }

  $image_width = $image->getImageWidth();
  $image_height = $image->getImageHeight();

  // re-calculate thumb dims
  $width_ratio = $thumb_width / $image_width;
  $height_ratio = $thumb_height / $image_height;
  $scale_factor = min($width_ratio, $height_ratio);
  $thumb_width = floor($image_width * $scale_factor);
  $thumb_height = floor($image_height * $scale_factor);

  $image->thumbnailImage($thumb_width, $thumb_height);
  $image->setImagePage($thumb_width, $thumb_height, 0, 0);
  if ($spoiler) {
    $image->gaussianBlurImage(32, 16);
    $image->modulateImage(50.0, 50.0, 100.0);
    $image_spoiler = new Imagick(__DIR__ . '/static/spoiler.png');
    $image_spoiler_x = 0.5 * ($thumb_width - $image_spoiler->getImageWidth());
    $image_spoiler_y = 0.5 * ($thumb_height - $image_spoiler->getImageHeight());
    $image->compositeImage($image_spoiler, Imagick::COMPOSITE_ATOP, (int) $image_spoiler_x, (int) $image_spoiler_y);
  }
  if ($player) {
    $image_player = new Imagick(__DIR__ . '/static/player.png');
    $image_player_x = 0.5 * ($thumb_width - $image_player->getImageWidth());
    $image_player_y = 0.5 * ($thumb_height - $image_player->getImageHeight());
    $image->compositeImage($image_player, Imagick::COMPOSITE_ATOP, (int) $image_player_x, (int) $image_player_y);
  }
  $image->setImageFormat($thumb_ext);
  $image->writeImage($thumb_path);
  
  return [
    'image_width'   => $image_width,
    'image_height'  => $image_height,
    'thumb_width'   => $thumb_width,
    'thumb_height'  => $thumb_height
  ];
}

/**
 * Extracts album art from input AUDIO file metadata.
 */
function funcs_board_get_audio_album_art(string $file_path, string $output_path): ?array {
  // check if ffmpeg is available
  $ffmpeg_output = '';
  $ffmpeg_status = 1;
  exec('ffmpeg -version', $ffmpeg_output, $ffmpeg_status);
  if ($ffmpeg_status !== 0) {
    throw new AppException('funcs_board', 'get_audio_album_art', 'ffmepg not installed', SC_INTERNAL_ERROR);
  }

  // execute ffmpeg to extract album art data
  $output_path .= ".png";
  $cmd = "ffmpeg -i " . escapeshellarg($file_path) . " -pix_fmt rgb8 -vf 'scale=300:-1' -an  " . escapeshellarg($output_path);
  $stdout = $res_code = null;
  exec($cmd, $stdout, $res_code);

  if (!file_exists($output_path)) {
    return null;
  } else if ($res_code !== 0) {
    unlink($output_path);
    return null;
  }

  return [
    'ext' => 'png',
    'file_path' => $output_path,
  ];
}

/**
 * Extracts album art (jpg or png) from input MP3 file metadata.
 */
function funcs_board_get_mp3_album_art(string $file_path, string $output_path): ?array {
  // get file info
  $get_id3 = new getID3;
  $id3_info = $get_id3->analyze($file_path);

  // extract album art data
  $album_mime = null;
  $album_path = null;
  $album_ext = null;
  if (isset($id3_info['comments']['picture'][0])) {
    $album_mime = $id3_info['comments']['picture'][0]['image_mime'];
    switch ($album_mime) {
      case 'image/jpeg':
      case 'image/pjpeg':
        $album_ext = 'jpg';
        break;
      case 'image/png':
        $album_ext = 'png';
        break;
      default:
        break;
    }

    if ($album_ext != null) {
      $album_data = $id3_info['comments']['picture'][0]['data'];
      $album_path = "{$output_path}.{$album_ext}";
      if (!file_put_contents($album_path, $album_data)) {
        return null;
      }
    }
  }

  if ($album_path == null) {
    return null;
  }

  return [
    'ext' => $album_ext,
    'file_path' => $album_path,
  ];
}

/**
 * Copies video container streams to a new container.
 */
function funcs_board_video_copy_codec(string $file_path, string $target_ext): ?string {
  // check if ffmpeg is available
  $ffmpeg_output = '';
  $ffmpeg_status = 1;
  exec('ffmpeg -version', $ffmpeg_output, $ffmpeg_status);
  if ($ffmpeg_status !== 0) {
    throw new AppException('funcs_board', 'video_copy_codec', 'ffmepg not installed', SC_INTERNAL_ERROR);
  }

  // execute ffmpeg to copy codec data
  $output_path = $file_path . '.' . $target_ext;
  $cmd = 'ffmpeg -i ' . escapeshellarg($file_path) . ' -codec copy ' . escapeshellarg($output_path);
  $stdout = $res_code = null;
  exec($cmd, $stdout, $res_code);
  
  if (!file_exists($output_path)) {
    return null;
  } else if ($res_code !== 0) {
    unlink($output_path);
    return null;
  }

  return $output_path;
}

function funcs_board_execute_embed(string $url, array $embed_types, int $max_w = 250, int $max_h = 250): ?array {
  // parse embed URL
  $url_parsed = parse_url($url);

  // validate host
  if (!array_key_exists($url_parsed['host'], $embed_types)) {
    throw new AppException('funcs_board', 'execute_embed', "embed url host unsupported: {$url_parsed['host']}", SC_INTERNAL_ERROR);
  }

  $embed_type = $embed_types[$url_parsed['host']];

  // fetch data
  $curl = curl_init();
  curl_setopt($curl, CURLOPT_URL, $embed_type . $url);
  curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
  $response = curl_exec($curl);
  curl_close($curl);
  $response = json_decode($response, true);

  // save thumbnail
  $thumb_file_name_tmp = time() . substr(microtime(), 2, 3);
  $thumb_dir_tmp = sys_get_temp_dir() . '/';
  $thumb_file_path_tmp = $thumb_dir_tmp . $thumb_file_name_tmp;
  file_put_contents($thumb_file_path_tmp, funcs_common_url_get_contents($response['thumbnail_url']));

  // process thumbnail
  $thumb_file_name = 'thumb_' . $thumb_file_name_tmp . '.png';
  $thumb_dir = '/src/';
  $thumb_file_path = __DIR__ . $thumb_dir . $thumb_file_name;
  $generated_thumb = funcs_board_generate_thumbnail($thumb_file_path_tmp, false, true, 'png', $thumb_file_path, $max_w, $max_h);
  $image_width = $generated_thumb['image_width'];
  $image_height = $generated_thumb['image_height'];
  $thumb_width = $generated_thumb['thumb_width'];
  $thumb_height = $generated_thumb['thumb_height'];

  return [
    'file'                => $response['html'],
    'file_rendered'       => rawurlencode($response['html']),
    'file_hex'            => funcs_common_clean_field($url),
    'file_original'       => funcs_common_clean_field($response['title']),
    'file_size'           => null,
    'file_size_formatted' => null,
    'file_mime'           => null,
    'image_width'         => $image_width,
    'image_height'        => $image_height,
    'thumb'               => $thumb_dir . $thumb_file_name,
    'thumb_width'         => $thumb_width,
    'thumb_height'        => $thumb_height,
    'audio_album'         => null,
    'embed'               => 1
  ];
}

function funcs_board_csam_scanner_check(array $file): ?array {
  // send file to CSAM-scanner microservice
  $target_file_path = __DIR__ . $file['file'];
  $finfo = finfo_open(FILEINFO_MIME);
  $target_file_mime = explode(';', finfo_file($finfo, $target_file_path))[0];
  finfo_close($finfo);
  $curl = curl_init();
  curl_setopt($curl, CURLOPT_URL, 'http://' . CSAM_SCANNER_HOST . ':8000/check');
  curl_setopt($curl, CURLOPT_POST, true);
  curl_setopt($curl, CURLOPT_POSTFIELDS, [
    'input' => new CURLFile($target_file_path, $target_file_mime, $file['file_original'])
  ]);
  curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
  $response = curl_exec($curl);
  curl_close($curl);

  if ($response) {
    return json_decode($response, true);
  }

  return null;
}

/**
 * Creates a new hide object.
 */
function funcs_board_create_hide(string $session_id, string $board_id, int $post_id): array {
  return [
    'session_id' => $session_id,
    'board_id'   => $board_id,
    'post_id'    => $post_id,
    'timestamp'  => time()
  ];
}

/**
 * Validates report form fields based on a set of simple validation rules, throws on errors.
 */
function funcs_board_validate_report(array $input, array $types) {
  if (!isset($input['type'])) {
    throw new AppException('funcs_report', 'validate_fields', 'required field type is NULL', SC_BAD_REQUEST);
  }

  if (!array_key_exists($input['type'], $types)) {
    throw new AppException('funcs_report', 'validate_fields', "field type value {$input['type']} is invalid", SC_BAD_REQUEST);
  }
}

/**
 * Creates a new report object.
 */
function funcs_board_create_report(string $ip, string $board_id, int $post_id, int $type, array $types): array {
  return [
    'ip'        => $ip,
    'timestamp' => time(),
    'board_id'  => $board_id,
    'post_id'   => $post_id,
    'type'      => $types[$type]
  ];
}
