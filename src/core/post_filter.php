<?php

namespace minichan\core;

/**
 * How a post filter pattern is matched, mirrors the `post_filters.match_type` ENUM column.
 */
enum PostFilterMatchType: string
{
	case Text = 'text';   // literal, case-insensitive substring
	case Regex = 'regex'; // full PCRE pattern including the delimiters and flags, e.g. /spam+/iu
}

/**
 * What a post filter does when its pattern matches, mirrors the `post_filters.action` ENUM column.
 */
enum PostFilterAction: string
{
	case Replace = 'replace';
	case Reject = 'reject';
	case Ban = 'ban';
}

/**
 * Result of running the post filters on the fields of a post.
 */
final class PostFilterResult
{
	/**
	 * @param array $fields the fields with the replace filters applied (unchanged if the post is blocked)
	 * @param array|null $blocked_by the reject/ban filter row that matched, null if the post is allowed
	 * @param string[] $errors errors of the filters that failed, failed filters are skipped
	 */
	public function __construct(
		public readonly array $fields,
		public readonly ?array $blocked_by,
		public readonly array $errors,
	) {}
}

/**
 * Runs post filters (rows of the `post_filters` table) on the text fields of a post.
 *
 * Blocking filters (ban, then reject) are checked first against the original fields, so a replace filter cannot mask
 * them. If no blocking filter matches, the replace filters are applied in the given order, each on the result of the
 * previous one. Filters that fail at runtime (e.g. PCRE backtrack limit) are skipped, they must not prevent posting.
 */
class PostFilter
{
	public const PATTERN_MAX_LEN = 256;

	/**
	 * Returns the PCRE pattern of a filter pattern.
	 */
	public static function compile(PostFilterMatchType $match_type, string $pattern): string
	{
		return match ($match_type) {
			PostFilterMatchType::Text => '/' . preg_quote($pattern, '/') . '/iu',
			PostFilterMatchType::Regex => $pattern,
		};
	}

	/**
	 * Validates a filter pattern, returns an error message or null if valid.
	 */
	public static function validate(PostFilterMatchType $match_type, string $pattern): ?string
	{
		if ($pattern === '') {
			return 'Pattern cannot be empty';
		}

		if (mb_strlen($pattern) > self::PATTERN_MAX_LEN) {
			return 'Pattern exceeds maximum length of ' . self::PATTERN_MAX_LEN . ' characters';
		}

		$error = null;
		$result = self::preg(fn() => preg_match(self::compile($match_type, $pattern), ''), $error);
		if ($result === false) {
			return "Invalid regex: {$error}";
		}

		// a pattern that matches empty text matches every post (e.g. /.*/)
		if ($result === 1) {
			return 'Pattern matches empty text, it would match every post';
		}

		return null;
	}

	/**
	 * Runs the filters on the given fields of a post.
	 *
	 * @param array $filters filter rows, at least id, match_type, pattern, action and replacement
	 * @param array $fields post fields, only the string fields named in $keys are filtered
	 * @param string[] $keys names of the fields to filter
	 */
	public static function apply(array $filters, array $fields, array $keys = ['subject', 'message']): PostFilterResult
	{
		$errors = [];

		// blocking filters first, against the original fields (a ban takes precedence over a reject)
		foreach ([PostFilterAction::Ban, PostFilterAction::Reject] as $action) {
			foreach ($filters as $filter) {
				if ($filter['action'] !== $action->value) {
					continue;
				}

				if (self::matches($filter, $fields, $keys, $errors)) {
					return new PostFilterResult($fields, $filter, $errors);
				}
			}
		}

		// replace filters in order, a filter that fails on any field is not applied to any field
		foreach ($filters as $filter) {
			if ($filter['action'] !== PostFilterAction::Replace->value) {
				continue;
			}

			$replaced = self::replace($filter, $fields, $keys, $errors);
			if ($replaced !== null) {
				$fields = $replaced;
			}
		}

		return new PostFilterResult($fields, null, $errors);
	}

	/**
	 * Returns true if the filter pattern matches any of the fields.
	 */
	private static function matches(array $filter, array $fields, array $keys, array &$errors): bool
	{
		$pattern = self::compile_filter($filter, $errors);
		if ($pattern === null) {
			return false;
		}

		foreach ($keys as $key) {
			if (!isset($fields[$key]) || !is_string($fields[$key]) || $fields[$key] === '') {
				continue;
			}

			$error = null;
			$result = self::preg(fn() => preg_match($pattern, $fields[$key]), $error);
			if ($result === false) {
				$errors[] = "filter #{$filter['id']}: {$error}";
				return false;
			}

			if ($result === 1) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Returns the fields with the filter matches replaced, null if the filter failed.
	 */
	private static function replace(array $filter, array $fields, array $keys, array &$errors): ?array
	{
		$pattern = self::compile_filter($filter, $errors);
		if ($pattern === null) {
			return null;
		}

		// text filters replace literally, regex filters may use backreferences ($1, ${1}, \1)
		$replacement = (string) ($filter['replacement'] ?? '');
		$literal = $filter['match_type'] === PostFilterMatchType::Text->value;

		foreach ($keys as $key) {
			if (!isset($fields[$key]) || !is_string($fields[$key]) || $fields[$key] === '') {
				continue;
			}

			$error = null;
			$result = self::preg(fn() => $literal
				? preg_replace_callback($pattern, fn() => $replacement, $fields[$key])
				: preg_replace($pattern, $replacement, $fields[$key]), $error);
			if ($result === null) {
				$errors[] = "filter #{$filter['id']}: {$error}";
				return null;
			}

			$fields[$key] = $result;
		}

		return $fields;
	}

	/**
	 * Returns the PCRE pattern of a filter row, null if the row is invalid.
	 */
	private static function compile_filter(array $filter, array &$errors): ?string
	{
		$match_type = PostFilterMatchType::tryFrom($filter['match_type'] ?? '');
		if ($match_type === null) {
			$errors[] = "filter #{$filter['id']}: invalid match type";
			return null;
		}

		return self::compile($match_type, (string) $filter['pattern']);
	}

	/**
	 * Runs a preg_* call, capturing its warnings (e.g. regex compilation errors) as the error message.
	 * On failure (false or null result) $error is set to the warning or the last PCRE error message.
	 */
	private static function preg(callable $fn, ?string &$error): mixed
	{
		$warning = null;
		set_error_handler(function (int $errno, string $errstr) use (&$warning): bool {
			// strip the 'preg_match(): ' prefix
			$pos = strpos($errstr, '(): ');
			$warning = $pos !== false ? substr($errstr, $pos + 4) : $errstr;
			return true;
		});

		try {
			$result = $fn();
		} finally {
			restore_error_handler();
		}

		if ($result === false || $result === null) {
			$error = $warning ?? preg_last_error_msg();
		}

		return $result;
	}
}
