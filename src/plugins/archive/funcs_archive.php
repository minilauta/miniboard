<?php

require_once __ROOT__ . '/common/config.php';
require_once __ROOT__ . '/common/exception.php';
require_once __ROOT__ . '/common/database.php';

const FUNCS_ARCHIVE_DIR = '/src/archives';

/**
 * Returns the absolute path of the archives directory, creating it if needed.
 */
function funcs_archive_dir(): string {
  $dir = __PUBLIC__ . FUNCS_ARCHIVE_DIR;
  if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
    throw new AppException('archive', 'dir', 'failed to create the archives directory', SC_INTERNAL_ERROR);
  }

  return $dir;
}

/**
 * Deletes a single archive directory and its contents.
 */
function funcs_archive_delete(string $token): bool {
  $dir = funcs_archive_dir() . '/' . $token;
  if (!is_dir($dir)) {
    return false;
  }

  foreach (array_slice(scandir($dir, SCANDIR_SORT_ASCENDING), 2) as $file) {
    @unlink($dir . '/' . $file);
  }

  return @rmdir($dir);
}

/**
 * Deletes expired archives.
 */
function funcs_archive_cleanup(): void {
  $dir = funcs_archive_dir();
  $deleted_ids = [];

  // delete archives past their time to live
  $expired_before = time() - MB_PLUGIN_ARCHIVE_TTL;
  foreach (select_expired_archives($expired_before) as $archive) {
    funcs_archive_delete($archive['token']);
    $deleted_ids[] = intval($archive['id']);
  }

  // delete oldest archives until the directory fits its byte budget
  $archives = array_filter(select_archives_oldest_first(), function (array $archive) use ($deleted_ids) {
    return !in_array(intval($archive['id']), $deleted_ids, true);
  });
  $total_size = 0;
  foreach ($archives as $archive) {
    $total_size += intval($archive['size']);
  }
  foreach ($archives as $archive) {
    if ($total_size <= MB_PLUGIN_ARCHIVE_MAX_TOTAL_BYTES) {
      break;
    }

    funcs_archive_delete($archive['token']);
    $deleted_ids[] = intval($archive['id']);
    $total_size -= intval($archive['size']);
  }

  delete_archives($deleted_ids);

  // cleanup archives that were interrupted mid-build
  $tokens = select_archive_tokens();
  foreach (array_slice(scandir($dir, SCANDIR_SORT_ASCENDING), 2) as $entry) {
    $path = $dir . '/' . $entry;
    if (@filemtime($path) >= $expired_before) {
      continue;
    }

    if (is_dir($path)) {
      if (!in_array($entry, $tokens, true)) {
        funcs_archive_delete($entry);
      }
    } else if (str_ends_with($entry, '.part')) {
      @unlink($path);
    }
  }
}

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
    if (!$info->isFile()) {
      continue;
    }

    $local_path = substr($path, strlen(__PUBLIC__) + 1);

    if (strtolower($info->getExtension()) === 'css') {
      $zip->addFromString($local_path, funcs_archive_rewrite_css(file_get_contents($path), $local_path));
      continue;
    }

    $zip->addFile($path, $local_path);
  }
}

function funcs_archive_add_src(\ZipArchive $zip, array $posts): void {
  $files = array_unique(array_merge([], ...array_map(function (array $post) {
    if (!isset($post['file']) || empty($post['file'])) {
      return [];
    }

    $file_meta = null;
    if (isset($post['file_meta']) && is_string($post['file_meta']) && strlen($post['file_meta']) > 0) {
      $file_meta = json_decode($post['file_meta'], true);
    }

    return [
      $post['embed'] === 0 ? $post['file'] : null,
      $post['thumb'],
      $post['audio_album'],
      is_array($file_meta) ? ($file_meta['tgk_png_file'] ?? null) : null,
    ];
  }, $posts)));

  foreach ($files as $file) {
    if ($file == null || !str_starts_with($file, '/src/') || strpos($file, '..') !== false) {
      continue;
    }

    $file_src = __PUBLIC__ . $file;
    if (is_file($file_src)) {
      $zip->addFile($file_src, ltrim($file, '/'));
    }
  }
}

function funcs_archive_rewrite_css(string $css, string $local_path): string {
  $prefix = str_repeat('../', substr_count($local_path, '/'));

  return preg_replace('/url\(\s*([\'"]?)\//', "url(\$1{$prefix}", $css);
}

function funcs_archive_rewrite_html(string $html): string {
  return preg_replace('/\bhref=([\'"])\/[^\'"#]*(#[^\'"]*)\1/i', 'href=$1$2$1', $html);
}

function funcs_archive_rewrite_post(array $post): array {
  if (isset($post['message_rendered']) && is_string($post['message_rendered'])) {
    $post['message_rendered'] = funcs_archive_rewrite_html($post['message_rendered']);
  }

  return $post;
}

function funcs_archive_rewrite_thread(array $thread): array {
  $replies = $thread['replies'] ?? [];
  $thread = funcs_archive_rewrite_post($thread);
  $thread['replies'] = array_map('funcs_archive_rewrite_post', $replies);

  return $thread;
}

const FUNCS_ARCHIVE_JSON_POST_FIELDS = [
  'post_id',
  'parent_id',
  'board_id',
  'timestamp',
  'bumped',
  'nameblock',
  'email',
  'subject',
  'message',
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

  $json['message_html'] = $json['message_rendered'];
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
