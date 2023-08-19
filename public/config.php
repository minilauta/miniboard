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
define('MB_SITE_DISCLAIMER', 'All trademarks, copyrights, comments, and images on this page are owned by and are the responsibility of their respective parties.');
define('MB_SITE_CONTACT', 'info@miniboard.dev');
define('MB_SITE_RULES', '
  <ol>
    <li>something...</li>
    <li>something else...</li>
  </ol>
');
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
define('MB_STYLES', [
  'miniboard',
  'futaba',
  'yotsuba',
  'yotsuba_blue',
  'zenburn'
]);
define('MB_DELAY', 15);
define('MB_ERROR_IMAGES', [
  404 => [
    'nazrin1.png',
    'mini1.jpg',
    'mini2.png',
    'mini3.png',
  ]
]);
define('MB_BANNER_IMAGES', [
  'b2.png' => 'b',
  'b3.png' => 'b',
  'b4.png' => 'b',
  'b6.png' => 'b',
  'b7.gif' => 'b',
]);

// board settings
define('MB_BOARDS', [
  'main' => [
    'id'                => 'main',
    'name'              => 'Main',
    'desc'              => 'Board of all boards',
    'type'              => 'main',
    'req_role'          => null,
    'alwaysnoko'        => true,
    'threads_per_page'  => 10,
    'threads_per_catalog_page' => 50,
    'posts_per_preview' => 4,
    'truncate'          => 15,
    'anonymous'         => null,
    'hashid_salt'       => null,
    'max_threads'       => 0,
    'max_replies'       => 0,
    'fields_post'       => [
      'board'     => ['required' => true,   'type' => 'string', 'max_len' => 8    ],
      'name'      => ['required' => false,  'type' => 'string', 'max_len' => 75   ],
      'email'     => ['required' => false,  'type' => 'string', 'max_len' => 320  ],
      'subject'   => ['required' => false,  'type' => 'string', 'max_len' => 75   ],
      'message'   => ['required' => true,   'type' => 'string', 'max_len' => 8192 ],
      'password'  => ['required' => false,  'type' => 'string', 'max_len' => 64,  'min_len' => 8],
      'embed'     => ['required' => false,  'type' => 'string', 'max_len' => 1024 ],
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
    'req_role'          => null,
    'alwaysnoko'        => true,
    'threads_per_page'  => 10,
    'threads_per_catalog_page' => 50,
    'posts_per_preview' => 4,
    'truncate'          => 15,
    'anonymous'         => 'Anonymous',
    'hashid_salt'       => '_J9..salt', // CRYPT_EXT_DES, _.... = iteration count, followed by 4 char salt
    'max_threads'       => 100,
    'max_replies'       => 100,
    'fields_post'       => [
      'name'      => ['required' => false,  'type' => 'string', 'max_len' => 75   ],
      'email'     => ['required' => false,  'type' => 'string', 'max_len' => 320  ],
      'subject'   => ['required' => false,  'type' => 'string', 'max_len' => 75   ],
      'message'   => ['required' => true,   'type' => 'string', 'max_len' => 8192 ],
      'password'  => ['required' => false,  'type' => 'string', 'max_len' => 64,  'min_len' => 8],
      'embed'     => ['required' => false,  'type' => 'string', 'max_len' => 1024 ],
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
      'youtube.com'     => 'https://youtube.com/oembed?format=json&url=',
      'www.youtube.com' => 'https://youtube.com/oembed?format=json&url=',
      'soundcloud.com'  => 'https://soundcloud.com/oembed?format=json&url='
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
    'req_role'          => null,
    'alwaysnoko'        => true,
    'threads_per_page'  => 10,
    'threads_per_catalog_page' => 50,
    'posts_per_preview' => 4,
    'truncate'          => 15,
    'anonymous'         => 'Anonymous',
    'hashid_salt'       => null,
    'max_threads'       => 100,
    'max_replies'       => 100,
    'fields_post'       => [
      'name'      => ['required' => false,  'type' => 'string', 'max_len' => 75   ],
      'email'     => ['required' => false,  'type' => 'string', 'max_len' => 320  ],
      'subject'   => ['required' => false,  'type' => 'string', 'max_len' => 75   ],
      'message'   => ['required' => true,   'type' => 'string', 'max_len' => 8192 ],
      'password'  => ['required' => false,  'type' => 'string', 'max_len' => 64,  'min_len' => 8],
      'embed'     => ['required' => false,  'type' => 'string', 'max_len' => 1024 ],
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
      'youtube.com'     => 'https://youtube.com/oembed?format=json&url=',
      'www.youtube.com' => 'https://youtube.com/oembed?format=json&url='
    ],
    'maxkb'             => 24000,
    'nofileok'          => false,
    'max_width'         => 250,
    'max_height'        => 250
  ],
  'mod' => [
    'id'                => 'mod',
    'name'              => 'Moderator',
    'desc'              => 'Moderator-only discussion board',
    'type'              => 'normal',
    'req_role'          => MB_ROLE_MODERATOR,
    'alwaysnoko'        => true,
    'threads_per_page'  => 10,
    'threads_per_catalog_page' => 50,
    'posts_per_preview' => 4,
    'truncate'          => 15,
    'anonymous'         => 'Anonymous',
    'hashid_salt'       => null,
    'max_threads'       => 0,
    'max_replies'       => 1000,
    'fields_post'       => [
      'name'      => ['required' => false,  'type' => 'string', 'max_len' => 75   ],
      'email'     => ['required' => false,  'type' => 'string', 'max_len' => 320  ],
      'subject'   => ['required' => false,  'type' => 'string', 'max_len' => 75   ],
      'message'   => ['required' => true,   'type' => 'string', 'max_len' => 8192 ],
      'password'  => ['required' => false,  'type' => 'string', 'max_len' => 64,  'min_len' => 8],
      'embed'     => ['required' => false,  'type' => 'string', 'max_len' => 1024 ],
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
      'youtube.com'     => 'https://youtube.com/oembed?format=json&url=',
      'www.youtube.com' => 'https://youtube.com/oembed?format=json&url=',
      'soundcloud.com'  => 'https://soundcloud.com/oembed?format=json&url='
    ],
    'maxkb'             => 24000,
    'nofileok'          => false,
    'max_width'         => 250,
    'max_height'        => 250
  ]
]);
