<?php

// database settings
define('MB_DB_HOST', '127.0.0.1');
define('MB_DB_NAME', 'miniboard');
define('MB_DB_USER', 'admin');
define('MB_DB_PASS', 'admin');

// global settings
define('MB_GLOBAL', [
  'timezone'         => 'UTC',
  'datefmt'          => '%d/%m/%g(%a)%H:%M:%S',
  'captcha_thread'   => false,
  'captcha_reply'    => false,
  'captcha_report'   => false,
]);

// board settings
define('MB_BOARDS', [
  'b' => [
    'id'                => 'b',
    'name'              => 'Random',
    'desc'              => '(┛ಠ_ಠ)┛彡┻━┻',
    'alwaysnoko'        => true,
    'threads_per_page'  => 10,
    'threads_per_catalog_page' => 50,
    'posts_per_preview' => 4,
    'truncate'          => 15,
    'wordbreak'         => 80,
    'anonymous'         => 'Anonymous',
    'max_threads'       => 100,
    'max_replies'       => 100,
    'max_name'          => 75,
    'max_email'         => 320,
    'max_subject'       => 75,
    'max_message'       => 8192,
    'mime_ext_types'    => [
      'image/jpeg'          => ['jpg'],
      'image/pjpeg'         => ['jpg'],
      'image/png'           => ['png'],
      'image/gif'           => ['gif'],
      'image/bmp'           => ['bmp'],
      'image/webp'          => ['webp'],
      'video/mp4'           => ['mp4'],
      'video/webm'          => ['webm']
    ],
    'embed_types'       => [
      'YouTube'             => 'https://www.youtube.com/oembed?url=TINYIBEMBED&format=json'
    ],
    'maxkb'             => 24000,
    'nofileok'          => false,
    'max_width'         => 250,
    'max_height'        => 250
  ],
  'a' => [
    'id'                => 'a',
    'name'              => 'Anime',
    'desc'              => '┏━┓┏━┓┏━┓ ︵ /(^.^/)',
    'alwaysnoko'        => true,
    'threads_per_page'  => 10,
    'threads_per_catalog_page' => 50,
    'posts_per_preview' => 4,
    'truncate'          => 15,
    'wordbreak'         => 80,
    'anonymous'         => 'Anonymous',
    'max_threads'       => 100,
    'max_replies'       => 100,
    'max_name'          => 75,
    'max_email'         => 320,
    'max_subject'       => 75,
    'max_message'       => 8192,
    'mime_ext_types'    => [
      'image/jpeg'          => ['jpg'],
      'image/pjpeg'         => ['jpg'],
      'image/png'           => ['png'],
      'image/gif'           => ['gif'],
      'image/bmp'           => ['bmp'],
      'video/mp4'           => ['mp4'],
      'video/webm'          => ['webm']
    ],
    'embed_types'       => [
      'YouTube'             => 'https://www.youtube.com/oembed?url=TINYIBEMBED&format=json'
    ],
    'maxkb'             => 24000,
    'nofileok'          => false,
    'max_width'         => 250,
    'max_height'        => 250
  ]
]);
