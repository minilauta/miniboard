<?php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/exception.php';

function funcs_common_get_board_cfg(string $board_id): array {
  if (!isset(MB_BOARDS[$board_id])) {
    throw new FuncException('funcs_common_get_board_cfg null err', SC_BAD_REQUEST);
  }

  return MB_BOARDS[$board_id];
}

/**
 * Parses an input string as a string, throws on errors.
 * 
 * @param array $input
 * @param string $key
 * @param string $default
 * @param int $min
 * @param int $max
 * @return string
 */
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

/**
 * Parses an input string as an integer, throws on errors.
 * 
 * @param array $input
 * @param string $key
 * @param int $default
 * @param int $min
 * @param int $max
 * @return string
 */
function funcs_common_parse_input_int(array $input, string $key, int $default = null, int $min = null, int $max = null): int {
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

/**
 * Truncates a string if it is too long for eg. catalog page.
 *
 * @param string $input String to truncate.
 * @param int $length Length the string should be truncated to.
 * @return string
 */
function funcs_common_truncate_string(string $input, int $length): string {
  if (strlen($input) > $length) {
    return trim(substr(string: $input, offset: 0, length: $length)) . '...';
  }

  return $input;
}

/**
 * Truncates a string to N line breaks (\n or <br>).
 * 
 * @param string &$input String to truncate.
 * @param int $br_count Line break count the string should be truncated to.
 * @param bool $handle_html Terminate HTML elements if they were cut on truncate?
 * @return bool
 */
function funcs_common_truncate_string_linebreak(string &$input, int $br_count = 15, bool $handle_html = TRUE): bool {
  // exit early if nothing to truncate
  if (substr_count($input, '<br>') + substr_count($input, "\n") <= $br_count)
      return FALSE;

  // get number of line breaks and their offsets
  $br_offsets_func = function(string $haystack, string $needle, int $offset) {
    $result = array();
    for ($i = $offset; $i < strlen($haystack); $i++) {
      $pos = strpos($haystack, $needle, $i);
      if ($pos !== False) {
        $offset = $pos;
        if ($offset >= $i) {
          $i = $offset;
          $result[] = $offset;
        }
      }
    }
    return $result;
  };
  $br_offsets = array_merge($br_offsets_func($input, '<br>', 0), $br_offsets_func($input, "\n", 0));
  sort($br_offsets);

  // truncate simply via line break threshold
  $input = substr($input, 0, $br_offsets[$br_count - 1]);

  // handle HTML elements in-case termination fails
  if ($handle_html) {
    $open_tags = [];

    preg_match_all('/(<\/?([\w+]+)[^>]*>)?([^<>]*)/', $input, $matches, PREG_SET_ORDER);
    foreach ($matches as $match) {
      if (preg_match('/br/i', $match[2]))
        continue;
      
      if (preg_match('/<[\w]+[^>]*>/', $match[0])) {
        array_unshift($open_tags, $match[2]);
      }
    }

    foreach ($open_tags as $open_tag) {
      $input .= '</' . $open_tag . '>';
    }
  }

  return TRUE;
}
