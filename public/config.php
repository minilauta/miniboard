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
    'name'              => 'Satunnainen',
    'desc'              => 'Jotain satunnaista paskaa',
    'alwaysnoko'        => true,
    'threads_per_page'  => 10,
    'preview_replies'   => 4,
    'truncate'          => 16,
    'wordbreak'         => 80,
    'anonymous'         => 'Anonyymi',
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
      'image/bmp'           => ['bmp']
    ],
    'embed_types'       => [
      'YouTube'             => 'https://www.youtube.com/oembed?url=TINYIBEMBED&format=json'
    ],
    'maxkb'             => 4096,
    'nofileok'          => false,
    'max_width'         => 250,
    'max_height'        => 250
  ]
]);
