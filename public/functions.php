<?php
use Psr\Http\Message\UploadedFileInterface;

function upload_file(UploadedFileInterface $file) : array {
  if ($file->getError() !== UPLOAD_ERR_OK) {
    return NULL;
  }

  $file_name_client = $file->getClientFilename();
  $file_ext = pathinfo($file_name_client, PATHINFO_EXTENSION);
  $file_name = sprintf('%s.%0.8s', bin2hex(random_bytes(8)), $file_ext);
  $file_path = __DIR__ . '/src/' . $file_name;
  $file->moveTo($file_path);
  $file_size = filesize($file_path);
  $file_size_formatted = human_filesize($file_size);

  return [
    'file'                => $file_name,
    'file_hex'            => 'todo',
    'file_original'       => $file_name_client,
    'file_size'           => $file_size,
    'file_size_formatted' => $file_size_formatted,
    'image_width'         => 128,
    'image_height'        => 128,
    'thumb'               => 'todo',
    'thumb_width'         => 64,
    'thumb_height'        => 64
  ];
}

function human_filesize(int $bytes, int $dec = 2) : string {
  $size = array('B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB');
  $factor = floor((strlen($bytes) - 1) / 3);

  return sprintf("%.{$dec}f", $bytes / pow(1024, $factor)) . @$size[$factor];
}

function create_post(array $args, array $params, array $file) : array {
  return [
    'board'               => $args['board_id'],
    'parent'              => 0,
    'name'                => $params['name'],
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
