<?php

require_once __ROOT__ . '/common/config.php';
require_once __ROOT__ . '/common/exception.php';

const FUNCS_ARCHIVE_ADD_PUBLIC_EXCLUDE = [
  'robots.txt',
  'favicon.ico',
  'src',
  'dist',
  'js',
  'icons',
  'plugins',
  'vendor',
  'banners',
  'err_404',
];

function funcs_archive_add_public(\ZipArchive $zip): void {
  $dirs = new \RecursiveDirectoryIterator(__PUBLIC__, \FilesystemIterator::SKIP_DOTS);
  $filtered = new \RecursiveCallbackFilterIterator($dirs, function ($current) {
    return 
      $current->getExtension() !== 'php' &&
      !in_array($current->getFilename(), FUNCS_ARCHIVE_ADD_PUBLIC_EXCLUDE, true)
      ;
  });

  foreach (new \RecursiveIteratorIterator($filtered) as $path => $info) {
    if ($info->isFile()) {
      $zip->addFile($path, substr($path, strlen(__PUBLIC__) + 1));
    }
  }
}

function funcs_archive_add_src(\ZipArchive $zip, array $posts): void {
  $files = array_unique(array_merge([], ...array_map(function (array $post) {
    if (!isset($post['file']) || empty($post['file']) || $post['embed'] !== 0) {
      return [];
    }

    return [
      $post['file'],
      $post['thumb'],
      $post['audio_album'],
    ];
  }, $posts)));

  foreach ($files as $file) {
    if ($file == null || strpos($file, '..') !== false) {
      continue;
    }

    $file_src = __PUBLIC__ . $file;
    if (is_file($file_src)) {
      $zip->addFile($file_src, ltrim($file, '/'));
    }
  }
}

const FUNCS_ARCHIVE_JSON_POST_FIELDS = [
  'post_id',
  'parent_id',
  'board_id',
  'timestamp',
  'nameblock',
  'email',
  'subject',
  'message_rendered',
  'file_rendered',
  'file_hex',
  'file_original',
  'file_size',
  'file_size_formatted',
  'file_mime',
  'file_meta',
  'image_width',
  'image_height',
  'thumb',
  'thumb_width',
  'thumb_height',
  'audio_album',
  'embed',
  'country',
  'stickied',
  'locked',
];

function funcs_archive_json_post(array $post): array {
  $json = [];

  foreach (FUNCS_ARCHIVE_JSON_POST_FIELDS as $field) {
    if (!array_key_exists($field, $post)) {
      continue;
    }

    $json[$field] = $post[$field];
  }

  $json['message'] = $json['message_rendered'];
  unset($json['message_rendered']);
  $json['file'] = $json['file_rendered'];
  unset($json['file_rendered']);

  if (isset($json['file_meta']) && strlen($json['file_meta']) > 0) {
    $json['file_meta'] = json_decode($json['file_meta'], true);
  }

  return $json;
}

function funcs_archive_json(array $board, array $thread, string $base_url): string {
  $json_thread = funcs_archive_json_post($thread);
  $json_thread['replies'] = array_map('funcs_archive_json_post', $thread['replies'] ?? []);

  $json = json_encode([
    'site' => MB_SITE_NAME,
    'url' => $base_url . '/' . $board['id'] . '/' . $thread['post_id'],
    'archived' => time(),
    'board' => [
      'id' => $board['id'],
      'name' => $board['name'],
      'desc' => $board['desc'],
    ],
    'thread' => $json_thread,
  ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);

  if ($json === false) {
    throw new AppException('archive', 'json', 'failed to encode thread as JSON', SC_INTERNAL_ERROR);
  }

  return $json;
}
