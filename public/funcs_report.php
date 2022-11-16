<?php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/exception.php';

function funcs_report_validate_fields(array $input, array $types) {
  if (!isset($input['type'])) {
    throw new FuncException('funcs_report', 'validate_fields', 'required field type is NULL', SC_BAD_REQUEST);
  }

  if (!array_key_exists($input['type'], $types)) {
    throw new FuncException('funcs_report', 'validate_fields', "field type value {$input['type']} is invalid", SC_BAD_REQUEST);
  }
}

function funcs_report_create(string $ip, string $board_id, int $post_id, int $type, array $types): array {
  return [
    'ip'        => $ip,
    'timestamp' => time(),
    'board_id'  => $board_id,
    'post_id'   => $post_id,
    'type'      => $types[$type]
  ];
}
