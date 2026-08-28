<?php

require_once __ROOT__ . '/common/config.php';
require_once __ROOT__ . '/common/exception.php';

function funcs_archive_add_public(\ZipArchive $zip): void {
  $dirs = new \RecursiveDirectoryIterator(__PUBLIC__, \FilesystemIterator::SKIP_DOTS);
  $filtered = new \RecursiveCallbackFilterIterator($dirs, function ($current) {
    return 
      !in_array($current->getFilename(), ['src', 'js', 'vendor', 'banners', 'err_404'], true) &&
      $current->getExtension() !== 'php'
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
