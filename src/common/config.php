<?php
/**
 * Environment config, read from env variables.
 */

date_default_timezone_set(getenv('MB_TIMEZONE'));

define('MB_ENV', getenv('MB_ENV'));
define('MB_DB_HOST', getenv('MB_DB_HOST'));
define('MB_DB_NAME', getenv('MB_DB_NAME'));
define('MB_DB_USER', getenv('MB_DB_USER'));
define('MB_DB_PASS', getenv('MB_DB_PASS'));
define('FILE_SCANNER_HOST', getenv('FILE_SCANNER_HOST'));
