<?php

define('REGEX_MATCH_URL', "/(http|https|ftp):\/\/([A-Za-zäö0-9-.]{3,128})([\/]{0,1}["
  ."\p{Latin}"
  ."\p{Hiragana}"
  ."\p{Katakana}"
  ."\x{4E00}-\x{9FAF}"    // kanji + chinese
  ."äöå0-9-_.~!*'();:@&=+$,\/?%#"
  ."]{0,128})/uim"
);
define('REGEX_MATCH_UNICODE_ICONS', "/["
  ."\x{1F100}-\x{1F1FF}"  // enclosed alphanumeric supplement
  ."\x{1F300}-\x{1F5FF}"  // miscellaneous symbols and pictographs
  ."\x{1F600}-\x{1F64F}"  // emoticons
  ."\x{1F680}-\x{1F6FF}"  // transport and map symbols
  ."\x{1F900}-\x{1F9FF}"  // supplemental symbols and pictographs
  ."\x{2600}-\x{26FF}"    // miscellaneous symbols
  ."\x{2700}-\x{27BF}"    // dingbats
  ."]/um"
);
define('KAOMOJI', [
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
  'キタ━━━(゜∀゜)━━━!!',
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
]);
