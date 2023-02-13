<?php

header("Content-Type: text/css; charset=UTF-8");

$mb_style = 'miniboard.css';
$mb_styles = [
  'miniboard',
  'futaba',
  'yotsuba',
  'yotsuba_blue'
];
if (isset($_COOKIE["miniboard/style"])) {
  $mb_style_cookie = $_COOKIE["miniboard/style"];

  if (in_array($mb_style_cookie, $mb_styles)) {
    $mb_style = $mb_style_cookie . '.css';
  }
}

include 'css/' . $mb_style;
