<?php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/exception.php';
require_once __DIR__ . '/shared.php';

function funcs_common_parse_query_str(array $server): ?array {
  $query = [];

  if (!isset($server['QUERY_STRING'])) {
    return $query;
  }

  parse_str($server['QUERY_STRING'], $query);
  return $query;
}

/**
 * Get board config array by board ID.
 */
function funcs_common_get_board_cfg(string $board_id): array {
  if (!isset(MB_BOARDS[$board_id])) {
    throw new AppException('funcs_common', 'get_board_cfg', "board with id /{$board_id}/ cannot be found", SC_NOT_FOUND);
  }

  return MB_BOARDS[$board_id];
}

/**
 * Returns true if user is logged in by checking session vars.
 */
function funcs_common_is_logged_in(): bool {
  return isset($_SESSION['mb_username']) && isset($_SESSION['mb_role']);
}

/**
 * Gets user role from session if set.
 */
function funcs_common_get_role(): int|null {
  if (isset($_SESSION['mb_role'])) {
    return $_SESSION['mb_role'];
  }

  return null;
}

/**
 * Validates form fields based on a set of simple validation rules, throws on errors.
 */
function funcs_common_validate_fields(array $input, array $fields) {
  foreach ($fields as $key => $val) {
    // check for required field
    $input_set = isset($input[$key]);
    if (!$input_set && $val['required'] === true) {
      throw new AppException('funcs_common', 'validate_fields', "required field {$key} is NULL", SC_BAD_REQUEST);
    } else if (!$input_set) {
      continue;
    }

    $ival = $input[$key];

    // check if input type matches required type
    $input_type = gettype($ival);
    if ($input_type != $val['type']) {
      throw new AppException('funcs_common', 'validate_fields', "field {$key} data type {$input_type} is invalid", SC_BAD_REQUEST);
    }

    // check for requirements based on type
    switch ($val['type']) {
      case 'string':
        $input_len = strlen($ival);

        if (isset($val['max_len'])) {
          $max_len = $val['max_len'];
          if ($input_len > $max_len) {
            throw new AppException('funcs_common', 'validate_fields', "field {$key} length {$input_len} is longer than max length {$max_len}", SC_BAD_REQUEST);
          }
        }

        if (isset($val['min_len']) && !empty($ival)) {
          $min_len = $val['min_len'];
          if ($input_len < $min_len) {
            throw new AppException('funcs_common', 'validate_fields', "field {$key} length {$input_len} is shorter than min length {$min_len}", SC_BAD_REQUEST);
          }
        }
        break;
      case 'array':
        if (!$ival) {
          throw new AppException('funcs_common', 'validate_fields', "field {$key} of type 'array' length is 0", SC_BAD_REQUEST);
        }
        break;
    }
  }
}

/**
 * Parses an input string as a string, throws on errors.
 */
function funcs_common_parse_input_str(array $input, string $key, string $default = null, int $min_len = null, int $max_len = null): string {
  if (!isset($input[$key])) {
    if ($default !== null) {
      return $default;
    }

    throw new AppException('funcs_common', 'parse_input_str', 'null error', SC_BAD_REQUEST);
  }

  $result = $input[$key];

  if ($min_len !== null && strlen($result) < $min_len) {
    throw new AppException('funcs_common', 'parse_input_str', 'min length error', SC_BAD_REQUEST);
  }

  if ($max_len !== null && strlen($result) > $max_len) {
    throw new AppException('funcs_common', 'parse_input_str', 'max length error', SC_BAD_REQUEST);
  }

  return $result;
}

/**
 * Parses an input string as an integer, throws on errors.
 */
function funcs_common_parse_input_int(array $input, string $key, int $default = null, int $min = null, int $max = null): int {
  if (!isset($input[$key])) {
    if ($default !== null) {
      return $default;
    }

    throw new AppException('funcs_common', 'parse_input_int', 'null error', SC_BAD_REQUEST);
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
 */
function funcs_common_clean_field(string $field): string {
  return htmlspecialchars($field, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML401, 'UTF-8');
}

/**
 * Cleans an user submitted text field before it's stored in database.
 */
function funcs_common_clean_unicode(string $field): string {
  $result = preg_replace('/[\x00-\x08\x0B-\x1F\x7F]/um', '', $field); // non-printable (excluding LF, HT and SP)
  $result = preg_replace(REGEX_MATCH_UNICODE_ICONS, '', $result);     // any unicode symbols and pictographs

  return $result;
}

/**
 * Turns a size in bytes to a human readable string representation.
 */
function funcs_common_human_filesize(int $bytes, int $dec = 2): string {
  $size = array('B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB');
  $factor = floor((strlen($bytes) - 1) / 3);

  return sprintf("%.{$dec}f", $bytes / pow(1024, $factor)) . @$size[$factor];
}

/**
 * Returns client remote IPv4 or IPv6 address.
 */
function funcs_common_get_client_remote_address(bool $cloudflare, array $server): string {
  if ($cloudflare && isset($server['HTTP_CF_CONNECTING_IP'])) {
    return $server['HTTP_CF_CONNECTING_IP'];
  }

  return $server['REMOTE_ADDR'];
}

/**
 * Returns client remote ISO-3166-1 alpha-2 country code as a lowercase string.
 * NOTE: currently supports only cloudflare.
 */
function funcs_common_get_client_remote_country(bool $cloudflare, array $server): ?string {
  if ($cloudflare && isset($server['HTTP_CF_IPCOUNTRY'])) {
    return strtolower($server['HTTP_CF_IPCOUNTRY']);
  }

  return null;
}

/**
 * Truncates a string to N line breaks (\n or <br>). Terminates leftover HTML-elements.
 */
function funcs_common_truncate_string_linebreak(string &$input, int $br_count = 15, bool $handle_html = true): bool {
  // exit early if nothing to truncate
  if (substr_count($input, '<br>') + substr_count($input, "\n") <= $br_count)
      return false;

  // get number of line breaks and their offsets
  $br_offsets_func = function(string $haystack, string $needle, int $offset) {
    $result = array();
    for ($i = $offset; $i < strlen($haystack); $i++) {
      $pos = strpos($haystack, $needle, $i);
      if ($pos !== false) {
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

  return true;
}

/**
 * Breaks individual long words with line-breaks. Does not break sentences.
 */
function funcs_common_break_long_words(string $input, int $max_len): string {
  $fn_wordwrap = function(string $val) use ($max_len) {
    if (preg_match(REGEX_MATCH_URL, $val) && strlen($val) < 256) {
      return $val;
    }

    return wordwrap($val, $max_len, "\n", true);
  };

  return implode(' ', array_map($fn_wordwrap, explode(' ', $input)));
}

/**
 * Encrypts a password for database storage.
 */
function funcs_common_hash_password(string $input): string {
  $hashed = password_hash($input, PASSWORD_BCRYPT, ['cost' => 10]);
  if ($hashed == null || $hashed === FALSE) {
    throw new AppException('funcs_common', 'hash_password', 'password_hash returned NULL or FALSE', SC_INTERNAL_ERROR);
  }

  return $hashed;
}

/**
 * Verifies a password against an ecrypted password.
 */
function funcs_common_verify_password(string $input, ?string $hash): bool {
  return password_verify($input, $hash);
}

/**
 * Generates a tripcode and a secure tripcode based on username input.
 */
function funcs_common_generate_tripcode(string $input, string $secure_salt): array {
  // find name(!|#)tripcode separator(s)
  $separators = ['!', '#'];
  $separator_pos = [];
  foreach ($separators as $separator) {
    // normal tripcode
    $pos = strpos($input, $separator, 0);
    $pos_min = count($separator_pos) > 0 ? min($separator_pos) : false;
    if ($pos !== false && ($pos_min === false || $pos < $pos_min)) {
      $separator_pos = [$pos];

      // secure tripcode
      $pos = strrpos($input, $separator, $pos + 1);
      if ($pos !== false) {
        $separator_pos[] = $pos;
      }
    }
  }
  
  // return name if no tripcode given
  if (count($separator_pos) === 0) {
    return [$input, null];
  }

  // get name, separator and pass
  $name = substr($input, 0, $separator_pos[0]);
  $normal_pass = null;
  $secure_pass = null;
  if (count($separator_pos) === 1) {
    $normal_pass = substr($input, $separator_pos[0] + 1);
    $secure_pass = "";
  } else {
    $normal_pass = substr($input, $separator_pos[0] + 1, $separator_pos[1] - $separator_pos[0] - 1);
    $secure_pass = substr($input, $separator_pos[1] + 1);
  }

  $tripcode = "";

  // generate normal tripcode (logic from Futabally)
  if (strlen($normal_pass) > 0) {
    $normal_pass = strtr($normal_pass, "&amp;", "&");   // just in case...
    $normal_pass = strtr($normal_pass, "&#44;", ", ");  // just in case...
    $normal_salt = substr($normal_pass . "H.", 1, 2);
    $normal_salt = preg_replace("/[^\.-z]/", ".", $normal_salt);
    $normal_salt = strtr($normal_salt, ":;<=>?@[\\]^_`", "ABCDEFGabcdef");
    $tripcode = substr(crypt($normal_pass, $normal_salt), -10);
  }

  // generate secure tripcode
  if (strlen($secure_pass) > 0) {
    $tripcode .= ($normal_pass != "" ? "!!" : "!") . substr(md5($secure_pass . $secure_salt), 2, 10);
  }

  // return name if empty passwords given
  if (strlen($tripcode) === 0) {
    return [$input, null];
  }

  return [$name, $tripcode];
}

/**
 * Generates a one-way hash ID to identify unique posters in a thread.
 */
function funcs_common_generate_hashid(?string $salt, string $ip, string $crypt_salt): ?string {
  if (!isset($salt) || strlen($salt) === 0) return null;
  else if (strlen($ip) === 0 || $ip == '0.0.0.0' || $ip == '127.0.0.1') return null;
  
  return substr(crypt(hash('sha256', "$salt::$ip"), $crypt_salt), strlen($crypt_salt));
}

/**
 * Validates form input h-captcha.
 */
function funcs_common_validate_captcha($input) {
  if (!isset($input['h-captcha-response'])) {
    throw new AppException('funcs_common', 'validate_captcha', 'h-captcha-response not found in input form data', SC_BAD_REQUEST);
  }

  $curl = curl_init();
  curl_setopt($curl, CURLOPT_URL, "https://hcaptcha.com/siteverify");
  curl_setopt($curl, CURLOPT_POST, true);
  curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query([
    'secret' => MB_CAPTCHA_HCAPTCHA_SECRET,
    'response' => $input['h-captcha-response']
  ]));
  curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
  $response = curl_exec($curl);
  curl_close($curl);
  $response = json_decode($response);

  if (!isset($response->success) || !$response->success) {
    throw new AppException('funcs_common', 'validate_captcha', 'h-captcha validation failed', SC_BAD_REQUEST);
  }
}

/**
 * Fetches file contents from target URL.
 */
function funcs_common_url_get_contents($url): ?string {
	if (!function_exists('curl_init')) {
		return file_get_contents($url);
	}

	$curl = curl_init();
	curl_setopt($curl, CURLOPT_URL, $url);
	curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
	$result = curl_exec($curl);
	$code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
	curl_close($curl);

	return intval($code) === 200 ? $result : null;
}

/**
 * Mutates target query array and returns it as string.
 */
function funcs_common_mutate_query(array $query, string $key, string $val): string {
  $query[$key] = $val;
  return http_build_query($query);
}

/**
 * Deletes target post and its replies if any. Deletes files if 0 references remaining.
 */
function funcs_common_delete_post(string $board_id, int $post_id): array {
  $warnings = [];

  // get board config
  $board_cfg = funcs_common_get_board_cfg($board_id);

  // select post with replies
  $selected_posts = select_post_with_replies($board_id, $post_id);

  foreach ($selected_posts as &$post) {
    // is the file thumbnail static?
    $static = str_contains($post['thumb'], '/static/');

    // count identical files, only unlink if this is the last one
    $file_collisions = select_files_by_md5($post['file_hex']);
    $file_collisions_n = count($file_collisions);

    // unlink file and thumb from filesystem
    if ($file_collisions_n === 1) {
      if ($post['embed'] === 0 && strlen($post['file']) > 0) {
        if (!unlink(__PUBLIC__ . $post['file'])) {
          $warnings[] = "Failed to delete file for post /{$post['board_id']}/{$post['post_id']}/ (maybe it didn't exist?)";
        }
      }
      
      if (!$static && strlen($post['thumb']) > 0) {
        if (!unlink(__PUBLIC__ . $post['thumb'])) {
          $warnings[] = "Failed to delete thumbnail for post /{$post['board_id']}/{$post['post_id']}/ (maybe it didn't exist?)";
        }
      }

      if (isset($post['audio_album']) && strlen($post['audio_album']) > 0) {
        if (!unlink(__PUBLIC__ . $post['audio_album'])) {
          $warnings[] = "Failed to delete album art for post /{$post['board_id']}/{$post['post_id']}/ (maybe it didn't exist?)";
        }
      }
    }

    // delete post from db
    if (!delete_post($post['board_id'], $post['post_id'])) {
      $warnings[] = "Failed to delete post /{$post['board_id']}/{$post['post_id']}/ from db";
    }

    // debump if deleted post was a reply
    $thread_bumped = false;
    if ($post['parent_id'] != null) {
      $thread_replies_n = count_posts('NULL', $post['board_id'], $post['parent_id'], false);
      if ($thread_replies_n <= $board_cfg['max_replies']) {
        $thread_bumped = bump_thread($post['board_id'], $post['parent_id']);
      }
    }
  }

  return $warnings;
}
