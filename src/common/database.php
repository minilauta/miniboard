<?php
/**
 * Database access layer entry point.
 *
 * The DB functions are split into files by the DB table they operate on,
 * see the `database/` directory. Requiring this file loads all of them.
 */
require_once __DIR__ . '/database/db_handle.php';
require_once __DIR__ . '/database/db_accounts.php';
require_once __DIR__ . '/database/db_bans.php';
require_once __DIR__ . '/database/db_board_filters.php';
require_once __DIR__ . '/database/db_file_scanner_hashes.php';
require_once __DIR__ . '/database/db_hides.php';
require_once __DIR__ . '/database/db_logs.php';
require_once __DIR__ . '/database/db_pins.php';
require_once __DIR__ . '/database/db_post_filters.php';
require_once __DIR__ . '/database/db_post_history.php';
require_once __DIR__ . '/database/db_posts.php';
require_once __DIR__ . '/database/db_posts_serial.php';
require_once __DIR__ . '/database/db_reports.php';
