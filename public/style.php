<?php

header("Content-Type: text/css; charset=UTF-8");

$mb_style = 'miniboard.css';
if (isset($_COOKIE["miniboard/style"])) {
  $mb_style_cookie = $_COOKIE["miniboard/style"];

  if (in_array($mb_style_cookie, array('miniboard', 'futaba', 'burichan', 'tomorrow'))) {
    $mb_style = $mb_style_cookie . '.css';
  }
}

include 'css/' . $mb_style;
