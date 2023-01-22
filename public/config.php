<?php

date_default_timezone_set(getenv('MB_TIMEZONE'));

// defines
define('MB_ENV', getenv('MB_ENV'));
define('MB_DB_HOST', getenv('MB_DB_HOST'));
define('MB_DB_NAME', getenv('MB_DB_NAME'));
define('MB_DB_USER', getenv('MB_DB_USER'));
define('MB_DB_PASS', getenv('MB_DB_PASS'));
define('MB_ROLE_SUPERADMIN', 1);
define('MB_ROLE_ADMIN', 2);
define('MB_ROLE_MODERATOR', 3);
define('MB_ROLE_DISABLED', 99);
define('MB_IMPORT_TINYIB_ACCOUNTS', 1);
define('MB_IMPORT_TINYIB_BANS', 2);
define('MB_IMPORT_TINYIB_LOGS', 3);
define('MB_IMPORT_TINYIB_POSTS', 4);
define('MB_IMPORT_TINYIB_REPORTS', 5);
define('MB_IMPORT_TABLE_TYPES', [
  MB_IMPORT_TINYIB_ACCOUNTS => 'TINYIB_ACCOUNTS',
  MB_IMPORT_TINYIB_BANS     => 'TINYIB_BANS',
  MB_IMPORT_TINYIB_LOGS     => 'TINYIB_LOGS',
  MB_IMPORT_TINYIB_POSTS    => 'TINYIB_POSTS',
  MB_IMPORT_TINYIB_REPORTS  => 'TINYIB_REPORTS',
]);
define('MB_SITE_NAME', 'Miniboard');
define('MB_SITE_DESC', 'Minimalistic oldschool imageboard software');
define('MB_DATEFORMAT', '%d/%m/%g(%a)%H:%M:%S');
define('MB_CAPTCHA_HCAPTCHA_SITE', '10000000-ffff-ffff-ffff-000000000001');
define('MB_CAPTCHA_HCAPTCHA_SECRET', '0x0000000000000000000000000000000000000000');
define('MB_CAPTCHA_THREAD', true);
define('MB_CAPTCHA_REPLY', true);
define('MB_CAPTCHA_REPORT', true);
define('MB_CAPTCHA_LOGIN', true);
define('MB_REPORT_TYPES', [
  1 => 'Content that contains violence.',
  2 => 'Content that sexualizes minors.',
  3 => 'Spamming and/or flooding.',
  4 => 'Not work safe content on work safe board.'
]);
define('MB_CLOUDFLARE', false);
define('MB_TRIPCODE_SALT', '#!12345_MAKE_THIS_SECURE_67890!#');

// board settings
define('MB_BOARDS', [
  'main' => [
    'id'                => 'main',
    'name'              => 'Main',
    'desc'              => 'Board of all boards',
    'type'              => 'main',
    'alwaysnoko'        => true,
    'threads_per_page'  => 10,
    'threads_per_catalog_page' => 50,
    'posts_per_preview' => 4,
    'truncate'          => 15,
    'anonymous'         => null,
    'max_threads'       => 100,
    'max_replies'       => 100,
    'fields_post'       => [
      'board'     => ['required' => true,   'type' => 'string', 'max_len' => 8    ],
      'name'      => ['required' => false,  'type' => 'string', 'max_len' => 75   ],
      'email'     => ['required' => false,  'type' => 'string', 'max_len' => 320  ],
      'subject'   => ['required' => false,  'type' => 'string', 'max_len' => 75   ],
      'message'   => ['required' => true,   'type' => 'string', 'max_len' => 8192 ],
      'password'  => ['required' => false,  'type' => 'string', 'max_len' => 64,  'min_len' => 8],
    ],
    'mime_ext_types'    => [],
    'embed_types'       => [],
    'maxkb'             => 0,
    'nofileok'          => false,
    'max_width'         => 0,
    'max_height'        => 0
  ],
  'b' => [
    'id'                => 'b',
    'name'              => 'Random',
    'desc'              => '(┛ಠ_ಠ)┛彡┻━┻',
    'type'              => 'normal',
    'alwaysnoko'        => true,
    'threads_per_page'  => 10,
    'threads_per_catalog_page' => 50,
    'posts_per_preview' => 4,
    'truncate'          => 15,
    'anonymous'         => 'Anonymous',
    'max_threads'       => 100,
    'max_replies'       => 100,
    'fields_post'       => [
      'name'      => ['required' => false,  'type' => 'string', 'max_len' => 75   ],
      'email'     => ['required' => false,  'type' => 'string', 'max_len' => 320  ],
      'subject'   => ['required' => false,  'type' => 'string', 'max_len' => 75   ],
      'message'   => ['required' => true,   'type' => 'string', 'max_len' => 8192 ],
      'password'  => ['required' => false,  'type' => 'string', 'max_len' => 64,  'min_len' => 8],
    ],
    'mime_ext_types'    => [
      'image/jpeg'          => ['jpg'],
      'image/pjpeg'         => ['jpg'],
      'image/png'           => ['png'],
      'image/gif'           => ['gif'],
      'image/bmp'           => ['bmp'],
      'image/webp'          => ['webp'],
      'video/mp4'           => ['mp4'],
      'video/webm'          => ['webm'],
      'audio/mpeg'          => ['mp3'],
      'application/x-shockwave-flash' => ['swf']
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
    'type'              => 'normal',
    'alwaysnoko'        => true,
    'threads_per_page'  => 10,
    'threads_per_catalog_page' => 50,
    'posts_per_preview' => 4,
    'truncate'          => 15,
    'anonymous'         => 'Anonymous',
    'max_threads'       => 100,
    'max_replies'       => 100,
    'fields_post'       => [
      'name'      => ['required' => false,  'type' => 'string', 'max_len' => 75   ],
      'email'     => ['required' => false,  'type' => 'string', 'max_len' => 320  ],
      'subject'   => ['required' => false,  'type' => 'string', 'max_len' => 75   ],
      'message'   => ['required' => true,   'type' => 'string', 'max_len' => 8192 ],
      'password'  => ['required' => false,  'type' => 'string', 'max_len' => 64,  'min_len' => 8],
    ],
    'mime_ext_types'    => [
      'image/jpeg'          => ['jpg'],
      'image/pjpeg'         => ['jpg'],
      'image/png'           => ['png'],
      'image/gif'           => ['gif'],
      'image/bmp'           => ['bmp'],
      'video/mp4'           => ['mp4'],
      'video/webm'          => ['webm'],
      'audio/mpeg'          => ['mp3']
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
