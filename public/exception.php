<?php

define('SC_OK', 200);
define('SC_BAD_REQUEST', 400);
define('SC_INTERNAL_ERROR', 500);

class ApiException extends Exception {
  public function __construct(string $message, int $status_code, Throwable $previous = null) {
    parent::__construct($message, $status_code, $previous);
  }
}

class FuncException extends Exception {
  public function __construct(string $message, int $status_code, Throwable $previous = null) {
    parent::__construct($message, $status_code, $previous);
  }
}

class DbException extends Exception {
  public function __construct(string $message, Throwable $previous = null) {
    parent::__construct($message, 0, $previous);
  }
}
