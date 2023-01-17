<?php

define('SC_OK', 200);
define('SC_BAD_REQUEST', 400);
define('SC_UNAUTHORIZED', 401);
define('SC_FORBIDDEN', 403);
define('SC_NOT_FOUND', 404);
define('SC_INTERNAL_ERROR', 500);

class AppException extends Exception {
  public function __construct(string $module, string $func, string $message, int $status_code, Throwable $previous = null) {
    parent::__construct("{$module}::{$func} {$message}", $status_code, $previous);
  }
}

class DbException extends Exception {
  public function __construct(string $message, Throwable $previous = null) {
    parent::__construct($message, SC_INTERNAL_ERROR, $previous);
  }
}
