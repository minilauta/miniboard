<?php

use abeautifulsite\SimpleImage;
use Psr\Http\Message\UploadedFileInterface;

require_once __DIR__ . '/config.php';

function validate_get(array $args) : array {
  $board_cfg = MB_BOARDS[$args['board_id']];
  if (!isset($board_cfg)) {
    return ['error' => 'INVALID_BOARD: ' . $args['board_id']];
  }

  return ['board_cfg' => $board_cfg];
}

function validate_post(array $args, array $params) : array {
  $board_cfg = MB_BOARDS[$args['board_id']];
  if (!isset($board_cfg)) {
    return ['error' => 'INVALID_BOARD: ' . $args['board_id']];
  }

  $validated_fields = ['name', 'email', 'subject', 'message'];
  foreach ($validated_fields as $field) {
    $max_len = $board_cfg['max_' . $field];
    if (strlen($params[$field]) > $max_len) {
      return ['error' => 'FIELD_MAX_LEN_EXCEEDED: ' . $field . '>' . $max_len];
    }
  }

  return ['board_cfg' => $board_cfg];
}

function create_post(array $args, array $params, array $file) : array {
  $board_cfg = MB_BOARDS[$args['board_id']];

  return [
    'board'               => $args['board_id'],
    'parent'              => isset($args['thread_id']) ? $args['thread_id'] : 0,
    'name'                => strlen($params['name']) !== 0 ? $params['name'] : $board_cfg['anonymous'],
    'tripcode'            => 'todo',
    'email'               => $params['email'],
    'subject'             => $params['subject'],
    'message'             => $params['message'],
    'password'            => 'todo',
    'nameblock'           => 'todo',
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
    'ip'                  => '127.0.0.1',
    'stickied'            => 0,
    'moderated'           => 0,
    'country_code'        => 'a1'
  ];
}

function validate_file(UploadedFileInterface $file, array $board_cfg) : array {
  if ($file->getError() !== UPLOAD_ERR_OK) {
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
    return ['error' >= 'FILE_MAX_SIZE_EXCEEDED: ' . $file_size . '>' . $max_bytes];
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

function upload_file(UploadedFileInterface $file, array $file_info, array $file_collisions, array $board_cfg) : array {
  $file_name_client = $file->getClientFilename();

  // either use the uploaded file or an already existing file
  if (empty($file_collisions)) {
    $file_ext = pathinfo($file_name_client, PATHINFO_EXTENSION);
    $file_name = sprintf('%s.%0.8s', bin2hex(random_bytes(8)), $file_ext);
    $file_path = __DIR__ . '/src/' . $file_name;
    $file->moveTo($file_path);
    $file_hex = $file_info['file_md5'];
    $file_size = $file_info['file_size'];
    $file_size_formatted = human_filesize($file_size);
    $thumb_file_name = 'thumb_' . $file_name;
    $thumb_file_path = __DIR__ . '/src/' . $thumb_file_name;
    $thumb_width = $board_cfg['max_width'];
    $thumb_height = $board_cfg['max_height'];

    switch ($file_info['file_mime']) {
      case 'image/jpeg':
      case 'image/pjpeg':
      case 'image/png':
      case 'image/gif':
      case 'image/bmp':
        $image = new \claviska\SimpleImage();
        $image->fromFile($file_path);
        $image_width = $image->getWidth();
        $image_height = $image->getHeight();
        
        // re-calculate thumb dims
        $width_ratio = $thumb_width / $image_width;
        $height_ratio = $thumb_height / $image_height;
        $scale_factor = min($width_ratio, $height_ratio);
        $thumb_width = $image_width * $scale_factor;
        $thumb_height = $image_height * $scale_factor;

        $thumb = new \claviska\SimpleImage();
        $thumb
          ->fromFile($file_path)
          ->thumbnail($thumb_width, $thumb_height, 'center')
          ->toFile($thumb_file_path, $file_info['file_mime'], 100);
        break;
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

function human_filesize(int $bytes, int $dec = 2) : string {
  $size = array('B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB');
  $factor = floor((strlen($bytes) - 1) / 3);

  return sprintf("%.{$dec}f", $bytes / pow(1024, $factor)) . @$size[$factor];
}
