<?php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/exception.php';

function funcs_hide_create(string $session_id, string $board_id, int $post_id): array {
  return [
    'session_id' => $session_id,
    'board_id'   => $board_id,
    'post_id'    => $post_id
  ];
}
