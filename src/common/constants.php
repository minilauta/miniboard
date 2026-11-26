<?php
/**
 * Code constants, these are not configuration.
 */

// account roles, lower is more privileged, stored in the database (accounts.role, posts.role)
defined('MB_ROLE_SUPERADMIN') || define('MB_ROLE_SUPERADMIN', 1);
defined('MB_ROLE_ADMIN')      || define('MB_ROLE_ADMIN', 2);
defined('MB_ROLE_MODERATOR')  || define('MB_ROLE_MODERATOR', 3);
defined('MB_ROLE_DISABLED')   || define('MB_ROLE_DISABLED', 99);
defined('MB_ROLE_SYSTEM')     || define('MB_ROLE_SYSTEM', 100);

// TinyIB import table types
defined('MB_IMPORT_TINYIB_ACCOUNTS') || define('MB_IMPORT_TINYIB_ACCOUNTS', 1);
defined('MB_IMPORT_TINYIB_BANS')     || define('MB_IMPORT_TINYIB_BANS', 2);
defined('MB_IMPORT_TINYIB_LOGS')     || define('MB_IMPORT_TINYIB_LOGS', 3);
defined('MB_IMPORT_TINYIB_POSTS')    || define('MB_IMPORT_TINYIB_POSTS', 4);
defined('MB_IMPORT_TINYIB_REPORTS')  || define('MB_IMPORT_TINYIB_REPORTS', 5);
defined('MB_IMPORT_TABLE_TYPES') || define('MB_IMPORT_TABLE_TYPES', [
  MB_IMPORT_TINYIB_ACCOUNTS => 'TINYIB_ACCOUNTS',
  MB_IMPORT_TINYIB_BANS     => 'TINYIB_BANS',
  MB_IMPORT_TINYIB_LOGS     => 'TINYIB_LOGS',
  MB_IMPORT_TINYIB_POSTS    => 'TINYIB_POSTS',
  MB_IMPORT_TINYIB_REPORTS  => 'TINYIB_REPORTS',
]);

// enabled modules
defined('MB_MODULES') || define('MB_MODULES', ['home', 'manage', 'logs', 'bans', 'board']);

// url match pattern
defined('REGEX_MATCH_URL') || define('REGEX_MATCH_URL', "/(http|https|ftp):\/\/([A-Za-zäö0-9-.]{3,128})([\/]{0,1}["
  ."\p{Latin}"
  ."\p{Hiragana}"
  ."\p{Katakana}"
  ."\x{4E00}-\x{9FAF}"    // kanji + chinese
  ."äöå0-9-_.~!*'();:@&=+$,\/?%#"
  ."]{0,128})/uim"
);

// unicode icon matching patterns
defined('REGEX_MATCH_UNICODE_ICONS') || define('REGEX_MATCH_UNICODE_ICONS', "/["
  ."\x{1F100}-\x{1F1FF}"  // enclosed alphanumeric supplement
  ."\x{1F300}-\x{1F5FF}"  // miscellaneous symbols and pictographs
  ."\x{1F600}-\x{1F64F}"  // emoticons
  ."\x{1F680}-\x{1F6FF}"  // transport and map symbols
  ."\x{1F900}-\x{1F9FF}"  // supplemental symbols and pictographs
  ."\x{2600}-\x{26FF}"    // miscellaneous symbols
  ."\x{2700}-\x{27BF}"    // dingbats
  ."]/um"
);

// kaomoji list for the post form
defined('KAOMOJI') || define('KAOMOJI', [
  // happy
  '(^_^)',
  '(´∀`)',
  '(・∀・)',
  '(*´▽`*)',
  'ヽ(´▽`)/',
  '＼(￣▽￣)／',
  '＼(^o^)／',
  '( ^ω^)',
  // excited
  '(つ≧▽≦)つ',
  'ｷﾀ━━━(ﾟ∀ﾟ)━━━!!',
  '(ﾟ∀ﾟ)',
  // neutral
  '(・ω・)',
  '(´・ω・`)',
  '( ´_ゝ`)',
  '(´ー`)',
  // affection
  '(づ￣ ³￣)づ',
  // sad
  '(´;ω;`)',
  '(T_T)',
  '(;_;)',
  '(´Д`)',
  // emotional
  '( ;∀;)',
  // angry
  'ヽ(`Д´)ノ',
  '(ノ゜Д゜)ノ ┻━┻',
  // frustrated
  '(>_<)',
  '(；一_一)',
  // nervous
  '(^_^;)',
  '(;´Д`)',
  // shocked
  '(ﾟДﾟ)',
  // apologetic
  'm(_ _)m',
  // lonely
  "('A`)",
  // sleepy
  '(-_-)zzZ',
  '_(:3 」∠)_',
  // animals
  '(=^・ω・^=)',
  '(ΦωΦ)',
  // 2ch characters
  '( ´∀`)ｵﾏｴﾓﾅｰ', // Mona's catchphrase
  '(,,ﾟДﾟ)ｺﾞﾙｧ!!', // Giko
  '(*ﾟーﾟ)', // Shii
  '(=ﾟωﾟ)ﾉぃょぅ', // Iyou
  '(´(ｪ)`)', // Kuma
  '(`・ω・´)ｼｬｷｰﾝ', // Shakin
  '⊂二二二( ^ω^)二⊃ﾌﾞｰﾝ', // Boon
  '(´<_` )', // Sasuga younger brother
  // happy
  '(・∀・)ｲｲ!!',
  '(ﾟдﾟ)ｳﾏｰ',
  '(*´ω`*)',
  '( ´∀`)人(´∀` )ﾅｶｰﾏ',
  // smug
  '(￣ー￣)ﾆﾔﾘ',
  '(・∀・)ﾆﾔﾆﾔ',
  '( ´,_ゝ`)ﾌﾟｯ',
  'm9(^Д^)ﾌﾟｷﾞｬｰ',
  '( ´∀`)σ)Д`)',
  // negative
  '(・A・)ｲｸﾅｲ!',
  '(・∀・)ｶｴﾚ!',
  '(ﾟ⊿ﾟ)ｲﾗﾈ',
  '(#^ω^)ﾋﾞｷﾋﾞｷ',
  // relaxed
  '( ´ー`)ﾏﾀｰﾘ',
  '( ´ー`)y-~~',
  'ε-(´ー`*)ﾎｯ',
  '(´・ω・)つ旦',
  '(´-`).｡oO(...)',
  // shocked
  'Σ(ﾟДﾟ)',
  '( ﾟдﾟ)ﾎﾟｶｰﾝ',
  '(ﾟдﾟ)ﾎｽｨ',
  '(つд⊂)ｺﾞｼｺﾞｼ',
  '((((;ﾟДﾟ))))ｶﾞｸｶﾞｸﾌﾞﾙﾌﾞﾙ',
  // laughing
  '( ﾟ∀ﾟ)ｱﾁｬｰ',
  '(ﾉ∀`)ｱﾁｬｰ',
  // despair
  'orz',
  '_| ̄|○',
  "('A`)ｳﾎﾞｧ",
  // fleeing
  'ε≡≡ヘ( ´Д`)ノ',
  // sleepy
  '( ˘ω˘)ｽﾔｧ',
]);
