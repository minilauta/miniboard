<?php

// environment settings
define('MB_ENV', getenv('MB_ENV'));

// database settings
define('MB_DB_HOST', getenv('MB_DB_HOST'));
define('MB_DB_NAME', getenv('MB_DB_NAME'));
define('MB_DB_USER', getenv('MB_DB_USER'));
define('MB_DB_PASS', getenv('MB_DB_PASS'));

// global settings
define('MB_GLOBAL', [
  'timezone'         => 'UTC',
  'datefmt'          => '%d/%m/%g(%a)%H:%M:%S',
  'captcha_thread'   => false,
  'captcha_reply'    => false,
  'captcha_report'   => false,
  'report_types'     => [
    1                   => 'Content that contains violence.',
    2                   => 'Content that sexualizes minors.',
    3                   => 'Spamming and/or flooding.',
    4                   => 'Not work safe content on work safe board.'
  ],
  'cloudflare'       => false,
]);

// board settings
define('MB_BOARDS', [
  'main' => [
    'id'                => 'main',
    'name'              => 'Main',
    'desc'              => 'Board of all boards',
    'alwaysnoko'        => true,
    'threads_per_page'  => 10,
    'threads_per_catalog_page' => 50,
    'posts_per_preview' => 4,
    'truncate'          => 15,
    'wordbreak'         => 80,
    'anonymous'         => 'Anonymous',
    'max_threads'       => 100,
    'max_replies'       => 100,
    'fields_post'       => [
      'name'    => ['required' => false,  'max_len' => 75   ],
      'email'   => ['required' => false,  'max_len' => 320  ],
      'subject' => ['required' => false,  'max_len' => 75   ],
      'message' => ['required' => true,   'max_len' => 8192 ]
    ],
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
    'fields_post'       => [
      'name'    => ['required' => false,  'max_len' => 75   ],
      'email'   => ['required' => false,  'max_len' => 320  ],
      'subject' => ['required' => false,  'max_len' => 75   ],
      'message' => ['required' => true,   'max_len' => 8192 ]
    ],
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
    'fields_post'       => [
      'name'    => ['required' => false,  'max_len' => 75   ],
      'email'   => ['required' => false,  'max_len' => 320  ],
      'subject' => ['required' => false,  'max_len' => 75   ],
      'message' => ['required' => true,   'max_len' => 8192 ]
    ],
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
