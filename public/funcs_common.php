<?php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/exception.php';

function funcs_common_parse_input_str(array $input, string $key, string $default = null, int $min = null, int $max = null): string {
  if (!isset($input[$key])) {
    if ($default !== null) {
      return $default;
    }

    throw new FuncException('funcs_common_parse_input_str null err', SC_BAD_REQUEST);
  }

  $result = $input[$key];

  if ($min !== null && strlen($result) < $min) {
    throw new FuncException('funcs_common_parse_input_str min len err', SC_BAD_REQUEST);
  }

  if ($max !== null && strlen($result) > $max) {
    throw new FuncException('funcs_common_parse_input_str max len err', SC_BAD_REQUEST);
  }

  return $result;
}

function funcs_common_parse_input_int(array $input, string $key, string $default = null, int $min = null, int $max = null): int {
  if (!isset($input[$key])) {
    if ($default !== null) {
      return $default;
    }

    throw new FuncException('funcs_common_parse_input_int null err', SC_BAD_REQUEST);
  }

  $result = intval($input[$key]);

  if ($min !== null) {
    $result = max($min, $result);
  }

  if ($max !== null) {
    $result = min($max, $result);
  }

  return $result;
}

/**
 * Escapes an user submitted text field before it's stored in database.
 * 
 * @param string $field
 * @return string
 */
function funcs_common_clean_field(string $field) : string {
  return htmlspecialchars($field, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML401, 'UTF-8');
}

/**
 * Turns a size in bytes to a human readable string representation.
 * 
 * @param int $bytes
 * @param int $dec
 * @return string
 */
function funcs_common_human_filesize(int $bytes, int $dec = 2) : string {
  $size = array('B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB');
  $factor = floor((strlen($bytes) - 1) / 3);

  return sprintf("%.{$dec}f", $bytes / pow(1024, $factor)) . @$size[$factor];
}

/**
 * Returns client remote IPv4 or IPv6 address.
 * 
 * @param array $server
 * @return string
 */
function funcs_common_get_client_remote_address(array $server) {
  if (MB_GLOBAL['cloudflare'] && isset($server['HTTP_CF_CONNECTING_IP'])) {
    return $server['HTTP_CF_CONNECTING_IP'];
  }

  return $server['REMOTE_ADDR'];
}
