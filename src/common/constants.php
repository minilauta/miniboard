<?php
/**
 * Code constants, these are not configuration.
 */

// account roles, lower is more privileged, stored in the database (accounts.role, posts.role)
defined('MB_ROLE_SUPERADMIN') || define('MB_ROLE_SUPERADMIN', 1);
defined('MB_ROLE_ADMIN')      || define('MB_ROLE_ADMIN', 2);
defined('MB_ROLE_MODERATOR')  || define('MB_ROLE_MODERATOR', 3);
defined('MB_ROLE_DISABLED')   || define('MB_ROLE_DISABLED', 99);
defined('MB_ROLE_SYSTEM')     || define('MB_ROLE_SYSTEM', 100);

// TinyIB import table types
defined('MB_IMPORT_TINYIB_ACCOUNTS') || define('MB_IMPORT_TINYIB_ACCOUNTS', 1);
defined('MB_IMPORT_TINYIB_BANS')     || define('MB_IMPORT_TINYIB_BANS', 2);
defined('MB_IMPORT_TINYIB_LOGS')     || define('MB_IMPORT_TINYIB_LOGS', 3);
defined('MB_IMPORT_TINYIB_POSTS')    || define('MB_IMPORT_TINYIB_POSTS', 4);
defined('MB_IMPORT_TINYIB_REPORTS')  || define('MB_IMPORT_TINYIB_REPORTS', 5);
defined('MB_IMPORT_TABLE_TYPES') || define('MB_IMPORT_TABLE_TYPES', [
  MB_IMPORT_TINYIB_ACCOUNTS => 'TINYIB_ACCOUNTS',
  MB_IMPORT_TINYIB_BANS     => 'TINYIB_BANS',
  MB_IMPORT_TINYIB_LOGS     => 'TINYIB_LOGS',
  MB_IMPORT_TINYIB_POSTS    => 'TINYIB_POSTS',
  MB_IMPORT_TINYIB_REPORTS  => 'TINYIB_REPORTS',
]);

// enabled modules
defined('MB_MODULES') || define('MB_MODULES', ['home', 'manage', 'logs', 'bans', 'board']);
