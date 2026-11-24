<?php

namespace minichan\core;

require_once __DIR__ . '/../common/constants.php';

/**
 * Type of a config value, mirrors the `config.type` ENUM column. Values are stored as text and decoded by their type.
 */
enum ConfigType: string
{
	case Bool = 'bool';
	case Int = 'int';
	case Float = 'float';
	case String = 'string';
	case Json = 'json'; // a PHP array, stored as a JSON array or object

	/**
	 * Returns true if a (non-null) PHP value is of this type.
	 */
	public function accepts(mixed $value): bool
	{
		return match ($this) {
			self::Bool => is_bool($value),
			self::Int => is_int($value),
			self::Float => is_float($value) || is_int($value),
			self::String => is_string($value),
			self::Json => is_array($value),
		};
	}

	/**
	 * Encodes a PHP value into the text stored in the `config.value` column, null is stored as NULL.
	 */
	public function encode(mixed $value): ?string
	{
		if ($value === null) {
			return null;
		}

		if (!$this->accepts($value)) {
			throw new \InvalidArgumentException("cannot encode a value of type " . get_debug_type($value) . " as {$this->value}");
		}

		return match ($this) {
			self::Bool => $value ? '1' : '0',
			self::Int => (string) $value,
			self::Float => json_encode((float) $value, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION),
			self::String => $value,
			self::Json => json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION),
		};
	}

	/**
	 * Decodes the text of the `config.value` column into a PHP value, NULL is decoded as null.
	 *
	 * NOTE: integer keys of JSON objects (e.g. {"404": [...]}) are decoded back to integer keys by PHP.
	 */
	public function decode(?string $raw): mixed
	{
		if ($raw === null) {
			return null;
		}

		switch ($this) {
			case self::Bool:
				if ($raw !== '0' && $raw !== '1') {
					throw new \UnexpectedValueException("invalid bool value '{$raw}', expected '0' or '1'");
				}
				return $raw === '1';
			case self::Int:
				$int = filter_var($raw, FILTER_VALIDATE_INT);
				if ($int === false) {
					throw new \UnexpectedValueException("invalid int value '{$raw}'");
				}
				return $int;
			case self::Float:
				if (!is_numeric($raw)) {
					throw new \UnexpectedValueException("invalid float value '{$raw}'");
				}
				return (float) $raw;
			case self::String:
				return $raw;
			case self::Json:
				try {
					$json = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
				} catch (\JsonException $e) {
					throw new \UnexpectedValueException("invalid json value: {$e->getMessage()}", 0, $e);
				}
				if (!is_array($json)) {
					throw new \UnexpectedValueException('invalid json value, expected an array or an object');
				}
				return $json;
		}
	}
}

/**
 * Where a config key can be set.
 *
 * - Global: only site-wide (board_id 'global'), the key is read where no board is known (or must be the same on every board)
 * - Inheritable: site-wide and per board, a board value overrides the site-wide value
 * - Board: only per board, required on every board
 */
enum ConfigScope: string
{
	case Global = 'global';
	case Inheritable = 'inheritable';
	case Board = 'board';
}

/**
 * Definition of a config key.
 */
final class ConfigKey
{
	/**
	 * @param string $key the key, as stored in `config.key`
	 * @param ConfigScope $scope where the key can be set
	 * @param ConfigType $type type of the value
	 * @param mixed $default value used when no row is set (and when the database is unavailable, for the error page),
	 *                       written site-wide by migrate on a new install, a placeholder for board keys
	 * @param bool $nullable true if null is a valid value
	 * @param bool $secret true if the value must not be shown or logged (e.g. salts)
	 * @param ?string $legacy where the value was in the legacy config.php: a constant name, or 'MB_BOARDS' for a key
	 *                        of each MB_BOARDS entry, null if the key is new
	 * @param ?\Closure $validator fn(mixed $value): ?string, returns an error message, called for non-null values only
	 * @param string $description short help text, shown in the manage UI
	 */
	public function __construct(
		public readonly string $key,
		public readonly ConfigScope $scope,
		public readonly ConfigType $type,
		public readonly mixed $default,
		public readonly bool $nullable = false,
		public readonly bool $secret = false,
		public readonly ?string $legacy = null,
		public readonly ?\Closure $validator = null,
		public readonly string $description = '',
	) {}

	/**
	 * Validates a value of this key, returns an error message or null if the value is valid.
	 */
	public function validate(mixed $value): ?string
	{
		if ($value === null) {
			return $this->nullable ? null : "{$this->key} cannot be null";
		}

		if (!$this->type->accepts($value)) {
			return "{$this->key} must be of type {$this->type->value}, got " . get_debug_type($value);
		}

		if ($this->validator !== null) {
			$error = ($this->validator)($value);
			if ($error !== null) {
				return "{$this->key} {$error}";
			}
		}

		return null;
	}

	/**
	 * Returns true if the key can be set on the given board ID ('global' = site-wide).
	 */
	public function allows_board_id(string $board_id): bool
	{
		return match ($this->scope) {
			ConfigScope::Global => $board_id === ConfigSchema::GLOBAL,
			ConfigScope::Inheritable => true,
			ConfigScope::Board => $board_id !== ConfigSchema::GLOBAL,
		};
	}
}

/**
 * The config keys, their scopes, types and defaults.
 *
 * The defaults are the values of the legacy src/common/config.php, the board defaults are its /a/ board.
 */
final class ConfigSchema
{
	/** board_id of the site-wide values */
	public const GLOBAL = 'global';

	/** valid board IDs, same width as posts.board_id */
	public const BOARD_ID_PATTERN = '/^[a-z0-9]{1,8}$/';

	/** board IDs that cannot be used: 'global' and the paths of the other modules and the public directories */
	public const RESERVED_BOARD_IDS = [
		self::GLOBAL, 'home', 'manage', 'logs', 'bans', 'board',
		'css', 'dist', 'icons', 'js', 'plugins', 'src', 'static', 'vendor',
	];

	/** valid style, plugin and file names */
	private const NAME_PATTERN = '/^[a-z0-9_]{1,64}$/';
	private const FILE_NAME_PATTERN = '/^[A-Za-z0-9_-]{1,128}\.[A-Za-z0-9]{1,8}$/';

	/** @var array<string, ConfigKey>|null */
	private static ?array $keys = null;

	/**
	 * Returns every config key, keyed by the key.
	 *
	 * @return array<string, ConfigKey>
	 */
	public static function keys(): array
	{
		if (self::$keys === null) {
			self::$keys = [];
			foreach (self::definitions() as $definition) {
				self::$keys[$definition->key] = $definition;
			}
		}

		return self::$keys;
	}

	/**
	 * Returns the definition of a key, null if the key does not exist.
	 */
	public static function get(string $key): ?ConfigKey
	{
		return self::keys()[$key] ?? null;
	}

	/**
	 * Returns the keys of the given scopes.
	 *
	 * @return array<string, ConfigKey>
	 */
	public static function keys_of(ConfigScope ...$scopes): array
	{
		return array_filter(self::keys(), fn(ConfigKey $k) => in_array($k->scope, $scopes, true));
	}

	/**
	 * Validates a board ID, returns an error message or null if the board ID can be used.
	 */
	public static function validate_board_id(string $board_id): ?string
	{
		if (!preg_match(self::BOARD_ID_PATTERN, $board_id)) {
			return "invalid board ID '{$board_id}', expected 1-8 characters of a-z and 0-9";
		}

		if (in_array($board_id, self::RESERVED_BOARD_IDS, true)) {
			return "board ID '{$board_id}' is reserved";
		}

		return null;
	}

	/**
	 * Validates a value about to be written, returns an error message or null if it can be written.
	 *
	 * @param string $board_id 'global' or a board ID
	 */
	public static function validate_write(string $board_id, string $key, mixed $value): ?string
	{
		$definition = self::get($key);
		if ($definition === null) {
			return "unknown config key '{$key}'";
		}

		if ($board_id !== self::GLOBAL && ($error = self::validate_board_id($board_id)) !== null) {
			return $error;
		}

		if (!$definition->allows_board_id($board_id)) {
			return match ($definition->scope) {
				ConfigScope::Global => "{$key} can only be set site-wide (board 'global')",
				ConfigScope::Board => "{$key} can only be set on a board",
				default => "{$key} cannot be set on '{$board_id}'",
			};
		}

		return $definition->validate($value);
	}

	/**
	 * @return ConfigKey[]
	 */
	private static function definitions(): array
	{
		$g = ConfigScope::Global;
		$i = ConfigScope::Inheritable;
		$b = ConfigScope::Board;

		$t_bool = ConfigType::Bool;
		$t_int = ConfigType::Int;
		$t_string = ConfigType::String;
		$t_json = ConfigType::Json;

		$min = fn(int $min) => fn(int $v) => $v >= $min ? null : "must be at least {$min}";
		$not_empty = fn(string $v) => strlen($v) > 0 ? null : 'cannot be empty';

		return [
			// site
			new ConfigKey('site_name', $g, $t_string, 'Miniboard', legacy: 'MB_SITE_NAME',
				description: 'Site name, shown on the home page'),
			new ConfigKey('site_desc', $g, $t_string, 'Minimalistic oldschool imageboard software', legacy: 'MB_SITE_DESC',
				description: 'Site description, shown on the home page'),
			new ConfigKey('site_disclaimer', $g, $t_string, 'All trademarks, copyrights, comments, and images on this page are owned by and are the responsibility of their respective parties.', legacy: 'MB_SITE_DISCLAIMER',
				description: 'Footer disclaimer (raw HTML)'),
			new ConfigKey('site_contact', $g, $t_string, 'info@miniboard.dev', legacy: 'MB_SITE_CONTACT',
				description: 'Footer contact (raw HTML)'),
			new ConfigKey('site_rules', $g, $t_string, "\n  <ol>\n    <li>something...</li>\n    <li>something else...</li>\n  </ol>\n", legacy: 'MB_SITE_RULES',
				description: 'Rules, shown on the home page (raw HTML)'),
			new ConfigKey('dateformat', $g, $t_string, 'd/m/y(D)H:i:s', legacy: 'MB_DATEFORMAT', validator: $not_empty,
				description: 'PHP date() format of timestamps'),
			new ConfigKey('styles', $g, $t_json, ['futaba', 'futaba_blue', 'yotsuba', 'yotsuba_blue', 'zenburn', 'miniboard'], legacy: 'MB_STYLES',
				validator: fn(array $v) => self::validate_name_list($v, false),
				description: 'Selectable styles (files in public/css/), the first one is the default'),
			new ConfigKey('error_images', $g, $t_json, [404 => ['nazrin1.png', 'mini1.jpg', 'mini2.png', 'mini3.png']], legacy: 'MB_ERROR_IMAGES',
				validator: fn(array $v) => self::validate_error_images($v),
				description: 'Error page images per HTTP status code (files in public/static/err_<code>/)'),
			new ConfigKey('banner_images', $g, $t_json, ['b2.png' => 'b', 'b3.png' => 'b', 'b4.png' => 'b', 'b6.png' => 'b', 'b7.gif' => 'b'], legacy: 'MB_BANNER_IMAGES',
				validator: fn(array $v) => self::validate_banner_images($v),
				description: 'Banner images and the board they link to (files in public/static/banners/)'),
			new ConfigKey('report_types', $g, $t_json, [
					1 => 'Content that contains violence.',
					2 => 'Content that sexualizes minors.',
					3 => 'Spamming and/or flooding.',
					4 => 'Not work safe content on work safe board.',
				], legacy: 'MB_REPORT_TYPES',
				validator: fn(array $v) => self::validate_report_types($v),
				description: 'Report types, the IDs are stored in reports'),

			// plugins
			new ConfigKey('plugins', $g, $t_json, ['friends'], legacy: 'MB_PLUGINS',
				validator: fn(array $v) => self::validate_plugins($v),
				description: 'Enabled plugins (directories in src/plugins/)'),
			new ConfigKey('plugin_friends_content', $g, $t_string, "\n  <ul>\n    <li><a target=\"_blank\" href=\"https://minilauta.org\">Minilauta</a></li>\n    <li><a target=\"_blank\" href=\"https://lainchan.org\">Lainchan</a></li>\n  </ul>\n", legacy: 'MB_PLUGIN_FRIENDS_CONTENT',
				description: 'Friends plugin content (raw HTML)'),

			// sessions, users and posting
			new ConfigKey('session_age_min', $g, $t_int, 60, legacy: 'MB_SESSION_AGE_MIN', validator: $min(0),
				description: 'Seconds a new session must exist before it can post'),
			new ConfigKey('session_lifetime', $g, $t_int, 432000, legacy: 'MB_SESSION_LIFETIME', validator: $min(1),
				description: 'Session lifetime in seconds'),
			new ConfigKey('cloudflare', $g, $t_bool, false, legacy: 'MB_CLOUDFLARE',
				description: 'Read the client IP and country from the Cloudflare headers'),
			new ConfigKey('anonymize_after', $g, $t_int, 3600 * 24 * 15, legacy: 'MB_ANONYMIZE_AFTER', validator: $min(0),
				description: 'Seconds after which the IP addresses of posts are removed, 0 = never'),
			new ConfigKey('delay', $i, $t_int, 15, legacy: 'MB_DELAY', validator: $min(0),
				description: 'Seconds between posts of a user (since the last post on any board), 0 = no delay'),
			new ConfigKey('delay_thread', $i, $t_int, 60, legacy: 'MB_DELAY_THREAD', validator: $min(0),
				description: 'Seconds between new threads of a user (since the last thread on any board), 0 = no delay'),
			new ConfigKey('tripcode_salt', $i, $t_string, '#!12345_MAKE_THIS_SECURE_67890!#', secret: true, legacy: 'MB_TRIPCODE_SALT',
				description: 'Salt of secure tripcodes, a different salt on a board gives different tripcodes there'),

			// captcha
			new ConfigKey('captcha_hcaptcha_site', $g, $t_string, '10000000-ffff-ffff-ffff-000000000001', nullable: true, legacy: 'MB_CAPTCHA_HCAPTCHA_SITE',
				description: 'hCaptcha site key, empty = captcha disabled'),
			new ConfigKey('captcha_hcaptcha_secret', $g, $t_string, '0x0000000000000000000000000000000000000000', secret: true, legacy: 'MB_CAPTCHA_HCAPTCHA_SECRET',
				description: 'hCaptcha secret key'),
			new ConfigKey('captcha_login', $g, $t_bool, true, legacy: 'MB_CAPTCHA_LOGIN',
				description: 'Require a captcha to log in to /manage/'),
			new ConfigKey('captcha_thread', $i, $t_bool, true, legacy: 'MB_CAPTCHA_THREAD',
				description: 'Require a captcha to create a thread'),
			new ConfigKey('captcha_reply', $i, $t_bool, true, legacy: 'MB_CAPTCHA_REPLY',
				description: 'Require a captcha to reply'),
			new ConfigKey('captcha_report', $i, $t_bool, true, legacy: 'MB_CAPTCHA_REPORT',
				description: 'Require a captcha to report a post'),

			// board identity
			new ConfigKey('name', $b, $t_string, '', legacy: 'MB_BOARDS', validator: $not_empty,
				description: 'Board name'),
			new ConfigKey('desc', $b, $t_string, '', legacy: 'MB_BOARDS',
				description: 'Board description'),
			new ConfigKey('type', $b, $t_string, 'normal', legacy: 'MB_BOARDS',
				validator: fn(string $v) => in_array($v, ['main', 'normal'], true) ? null : "must be 'main' or 'normal'",
				description: "Board type, 'main' lists the threads of every board and posts to a selected board"),
			new ConfigKey('position', $b, $t_int, 0,
				description: 'Position of the board in the board lists, ascending'),

			// board settings
			new ConfigKey('req_role', $i, $t_int, null, nullable: true, legacy: 'MB_BOARDS',
				validator: fn(int $v) => in_array($v, [MB_ROLE_SUPERADMIN, MB_ROLE_ADMIN, MB_ROLE_MODERATOR], true) ? null : 'must be a staff role (1 = superadmin, 2 = admin, 3 = moderator)',
				description: 'Role required to access the board (1 = superadmin, 2 = admin, 3 = moderator), empty = public'),
			new ConfigKey('alwaysnoko', $i, $t_bool, true, legacy: 'MB_BOARDS',
				description: 'Always return to the thread after posting'),
			new ConfigKey('threads_per_page', $i, $t_int, 10, legacy: 'MB_BOARDS', validator: $min(1),
				description: 'Threads per board page'),
			new ConfigKey('threads_per_catalog_page', $i, $t_int, 50, legacy: 'MB_BOARDS', validator: $min(1),
				description: 'Threads per catalog page'),
			new ConfigKey('posts_per_preview', $i, $t_int, 4, legacy: 'MB_BOARDS', validator: $min(0),
				description: 'Replies shown per thread on board pages'),
			new ConfigKey('truncate', $i, $t_int, 15, legacy: 'MB_BOARDS', validator: $min(0),
				description: 'Line breaks after which long messages are truncated on board pages'),
			new ConfigKey('anonymous', $i, $t_string, 'Anonymous', legacy: 'MB_BOARDS',
				description: 'Name of posters without a name'),
			new ConfigKey('hashid_salt', $i, $t_string, null, nullable: true, secret: true, legacy: 'MB_BOARDS',
				description: 'crypt() salt of poster IDs (e.g. CRYPT_EXT_DES _J9..salt), shorter than 2 characters = no poster IDs'),
			new ConfigKey('max_threads', $i, $t_int, 100, legacy: 'MB_BOARDS', validator: $min(0),
				description: 'Threads kept on the board, older ones are deleted, 0 = unlimited'),
			new ConfigKey('max_replies', $i, $t_int, 100, legacy: 'MB_BOARDS', validator: $min(0),
				description: 'Replies after which a thread is no longer bumped'),
			new ConfigKey('fields_post', $i, $t_json, [
					'name'      => ['required' => false,  'type' => 'string', 'max_len' => 75   ],
					'email'     => ['required' => false,  'type' => 'string', 'max_len' => 320  ],
					'subject'   => ['required' => false,  'type' => 'string', 'max_len' => 75   ],
					'message'   => ['required' => true,   'type' => 'string', 'max_len' => 8192 ],
					'password'  => ['required' => false,  'type' => 'string', 'max_len' => 64,  'min_len' => 8],
					'embed'     => ['required' => false,  'type' => 'string', 'max_len' => 1024 ],
				], legacy: 'MB_BOARDS',
				validator: fn(array $v) => self::validate_fields_post($v),
				description: 'Validation rules of the post form fields'),
			new ConfigKey('mime_ext_types', $i, $t_json, [
					'image/jpeg'          => ['jpg'],
					'image/pjpeg'         => ['jpg'],
					'image/png'           => ['png'],
					'image/gif'           => ['gif'],
					'image/bmp'           => ['bmp'],
					'video/mp4'           => ['mp4'],
					'video/webm'          => ['webm'],
					'audio/mpeg'          => ['mp3'],
				], legacy: 'MB_BOARDS',
				validator: fn(array $v) => self::validate_mime_ext_types($v),
				description: 'Allowed file MIME types and their extensions'),
			new ConfigKey('embed_types', $i, $t_json, [
					'youtube.com'     => 'https://youtube.com/oembed?format=json&url=',
					'www.youtube.com' => 'https://youtube.com/oembed?format=json&url=',
				], legacy: 'MB_BOARDS',
				validator: fn(array $v) => self::validate_embed_types($v),
				description: 'Allowed embed hosts and their oEmbed URLs (fetched by the server)'),
			new ConfigKey('maxkb', $i, $t_int, 24000, legacy: 'MB_BOARDS', validator: $min(0),
				description: 'Maximum file size in kB'),
			new ConfigKey('nofileok', $i, $t_bool, false, legacy: 'MB_BOARDS',
				description: 'Allow threads without a file'),
			new ConfigKey('text', $i, $t_bool, false, legacy: 'MB_BOARDS',
				description: 'Text board, no files or embeds'),
			new ConfigKey('max_width', $i, $t_int, 250, legacy: 'MB_BOARDS', validator: $min(0),
				description: 'Maximum thumbnail width'),
			new ConfigKey('max_height', $i, $t_int, 250, legacy: 'MB_BOARDS', validator: $min(0),
				description: 'Maximum thumbnail height'),
			new ConfigKey('nsfw', $i, $t_bool, false, legacy: 'MB_BOARDS',
				description: 'Not work safe board'),
			new ConfigKey('hidden', $i, $t_bool, false, legacy: 'MB_BOARDS',
				description: 'Hide the board from the board lists'),
			new ConfigKey('flags', $i, $t_bool, false, legacy: 'MB_BOARDS',
				description: 'Show poster country flags'),
		];
	}

	private static function validate_name_list(array $value, bool $allow_empty): ?string
	{
		if (!array_is_list($value)) {
			return 'must be a list';
		}

		if (!$allow_empty && count($value) === 0) {
			return 'cannot be empty';
		}

		foreach ($value as $name) {
			if (!is_string($name) || !preg_match(self::NAME_PATTERN, $name)) {
				return 'must be a list of names of a-z, 0-9 and _';
			}
		}

		return null;
	}

	private static function validate_plugins(array $value): ?string
	{
		if (($error = self::validate_name_list($value, true)) !== null) {
			return $error;
		}

		// NOTE: plugins are loaded with require, never allow a name that is not a plugin directory
		foreach ($value as $name) {
			if (!is_file(__DIR__ . "/../plugins/{$name}/plugin.php")) {
				return "plugin '{$name}' does not exist";
			}
		}

		return null;
	}

	private static function validate_error_images(array $value): ?string
	{
		foreach ($value as $code => $images) {
			if (!is_int($code) || $code < 100 || $code > 599) {
				return 'must be keyed by HTTP status code';
			}

			if (!is_array($images) || !array_is_list($images) || count($images) === 0) {
				return 'must have a non-empty list of images per status code';
			}

			foreach ($images as $image) {
				if (!is_string($image) || !preg_match(self::FILE_NAME_PATTERN, $image)) {
					return 'must have file names (e.g. image.png) as images';
				}
			}
		}

		return null;
	}

	private static function validate_banner_images(array $value): ?string
	{
		foreach ($value as $image => $board_id) {
			if (!is_string($image) || !preg_match(self::FILE_NAME_PATTERN, $image)) {
				return 'must be keyed by file name (e.g. image.png)';
			}

			if (!is_string($board_id) || !preg_match(self::BOARD_ID_PATTERN, $board_id)) {
				return 'must have board IDs as values';
			}
		}

		return null;
	}

	private static function validate_report_types(array $value): ?string
	{
		foreach ($value as $id => $description) {
			if (!is_int($id) || !is_string($description) || strlen($description) === 0) {
				return 'must map integer IDs to descriptions';
			}
		}

		return null;
	}

	private static function validate_fields_post(array $value): ?string
	{
		foreach ($value as $field => $rules) {
			if (!is_string($field) || !preg_match('/^[a-z_]{1,32}$/', $field)) {
				return 'must be keyed by field name';
			}

			if (!is_array($rules) || !is_bool($rules['required'] ?? null) || !is_string($rules['type'] ?? null)) {
				return "field '{$field}' must have the rules 'required' (bool) and 'type' (string)";
			}

			foreach (['min_len', 'max_len'] as $len) {
				if (isset($rules[$len]) && !is_int($rules[$len])) {
					return "field '{$field}' rule '{$len}' must be an int";
				}
			}
		}

		return null;
	}

	private static function validate_mime_ext_types(array $value): ?string
	{
		foreach ($value as $mime => $exts) {
			if (!is_string($mime) || !preg_match('/^[a-z0-9.+-]+\/[a-z0-9.+-]+$/', $mime)) {
				return 'must be keyed by MIME type';
			}

			if (!is_array($exts) || !array_is_list($exts) || count($exts) === 0) {
				return "MIME type '{$mime}' must have a non-empty list of extensions";
			}

			foreach ($exts as $ext) {
				if (!is_string($ext) || !preg_match('/^[a-z0-9]{1,8}$/', $ext)) {
					return "MIME type '{$mime}' must have extensions of a-z and 0-9";
				}
			}
		}

		return null;
	}

	private static function validate_embed_types(array $value): ?string
	{
		foreach ($value as $host => $url) {
			if (!is_string($host) || !preg_match('/^[a-z0-9.-]{1,253}$/', $host)) {
				return 'must be keyed by host name';
			}

			if (!is_string($url) || !preg_match('/^https?:\/\//', $url)) {
				return "host '{$host}' must have an http(s) oEmbed URL";
			}
		}

		return null;
	}
}
