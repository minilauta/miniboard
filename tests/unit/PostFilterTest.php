<?php

use PHPUnit\Framework\TestCase;
use minichan\core\PostFilter;
use minichan\core\PostFilterAction;
use minichan\core\PostFilterMatchType;

require_once __ROOT__ . '/core/post_filter.php';

class PostFilterTest extends TestCase
{
	private array $ini = [];

	protected function tearDown(): void
	{
		foreach ($this->ini as $key => $value) {
			ini_set($key, $value);
		}
		$this->ini = [];
	}

	private function setIni(string $key, string $value): void
	{
		$this->ini[$key] ??= ini_get($key);
		ini_set($key, $value);
	}

	private function filter(int $id, PostFilterMatchType $match_type, string $pattern, PostFilterAction $action, ?string $replacement = null): array
	{
		return [
			'id' => $id,
			'match_type' => $match_type->value,
			'pattern' => $pattern,
			'action' => $action->value,
			'replacement' => $replacement,
			'duration' => $action === PostFilterAction::Ban ? 3600 : null,
			'reason' => null,
		];
	}

	private function fields(string $message, string $subject = ''): array
	{
		return ['name' => 'Anonymous', 'subject' => $subject, 'message' => $message];
	}

	// --- compile ---

	public function testCompileTextIsLiteralAndCaseInsensitive(): void
	{
		$this->assertSame('/a\.b\/c/iu', PostFilter::compile(PostFilterMatchType::Text, 'a.b/c'));
	}

	public function testCompileRegexIsUnchanged(): void
	{
		$this->assertSame('/spa+m/i', PostFilter::compile(PostFilterMatchType::Regex, '/spa+m/i'));
	}

	// --- validate ---

	public function testValidateAcceptsValidPatterns(): void
	{
		$this->assertNull(PostFilter::validate(PostFilterMatchType::Text, 'spam'));
		$this->assertNull(PostFilter::validate(PostFilterMatchType::Text, '.*'));
		$this->assertNull(PostFilter::validate(PostFilterMatchType::Regex, '/fr[e3]{2}\s*m[o0]ney/iu'));
		$this->assertNull(PostFilter::validate(PostFilterMatchType::Regex, '#https?://spam\.example#i'));
	}

	public function testValidateRejectsEmptyPattern(): void
	{
		$this->assertSame('Pattern cannot be empty', PostFilter::validate(PostFilterMatchType::Text, ''));
	}

	public function testValidateRejectsTooLongPattern(): void
	{
		$this->assertNull(PostFilter::validate(PostFilterMatchType::Text, str_repeat('ä', PostFilter::PATTERN_MAX_LEN)));
		$this->assertStringContainsString('maximum length', PostFilter::validate(PostFilterMatchType::Text, str_repeat('a', PostFilter::PATTERN_MAX_LEN + 1)));
	}

	public function testValidateRejectsInvalidRegex(): void
	{
		$this->assertSame("Invalid regex: No ending delimiter '/' found", PostFilter::validate(PostFilterMatchType::Regex, '/spam'));
		$this->assertStringStartsWith('Invalid regex: Compilation failed: missing closing parenthesis', PostFilter::validate(PostFilterMatchType::Regex, '/sp(am/'));
		$this->assertStringStartsWith('Invalid regex: Delimiter must not be alphanumeric', PostFilter::validate(PostFilterMatchType::Regex, 'spam'));
		$this->assertStringStartsWith('Invalid regex:', PostFilter::validate(PostFilterMatchType::Regex, '/spam/e'));
	}

	public function testValidateRejectsPatternMatchingEmptyText(): void
	{
		$expected = 'Pattern matches empty text, it would match every post';
		$this->assertSame($expected, PostFilter::validate(PostFilterMatchType::Regex, '/.*/'));
		$this->assertSame($expected, PostFilter::validate(PostFilterMatchType::Regex, '/x?/'));
		$this->assertSame($expected, PostFilter::validate(PostFilterMatchType::Regex, '/^/'));
	}

	public function testValidateDoesNotLeakWarnings(): void
	{
		$warnings = [];
		set_error_handler(function (int $errno, string $errstr) use (&$warnings): bool {
			$warnings[] = $errstr;
			return true;
		});

		try {
			PostFilter::validate(PostFilterMatchType::Regex, '/spam');
		} finally {
			restore_error_handler();
		}

		$this->assertSame([], $warnings);
	}

	// --- apply: no match ---

	public function testApplyWithoutFiltersReturnsFieldsUnchanged(): void
	{
		$fields = $this->fields('hello', 'subject');
		$result = PostFilter::apply([], $fields);

		$this->assertSame($fields, $result->fields);
		$this->assertNull($result->blocked_by);
		$this->assertSame([], $result->errors);
	}

	public function testApplySkipsEmptyAndMissingFields(): void
	{
		$filters = [$this->filter(1, PostFilterMatchType::Text, 'spam', PostFilterAction::Reject)];
		$result = PostFilter::apply($filters, ['message' => 'hello']);

		$this->assertNull($result->blocked_by);
		$this->assertSame(['message' => 'hello'], $result->fields);
	}

	// --- apply: replace ---

	public function testApplyTextReplaceIsCaseInsensitive(): void
	{
		$filters = [$this->filter(1, PostFilterMatchType::Text, 'darn', PostFilterAction::Replace, 'heck')];
		$result = PostFilter::apply($filters, $this->fields('Darn it, DARN it'));

		$this->assertSame('heck it, heck it', $result->fields['message']);
		$this->assertNull($result->blocked_by);
	}

	public function testApplyTextReplaceIsCaseInsensitiveForUnicode(): void
	{
		$filters = [$this->filter(1, PostFilterMatchType::Text, 'äö', PostFilterAction::Replace, 'x')];
		$result = PostFilter::apply($filters, $this->fields('ÄÖ äö'));

		$this->assertSame('x x', $result->fields['message']);
	}

	public function testApplyTextPatternIsLiteral(): void
	{
		$filters = [$this->filter(1, PostFilterMatchType::Text, 'a.b', PostFilterAction::Replace, 'X')];
		$result = PostFilter::apply($filters, $this->fields('axb a.b'));

		$this->assertSame('axb X', $result->fields['message']);
	}

	public function testApplyTextReplacementIsLiteral(): void
	{
		$filters = [$this->filter(1, PostFilterMatchType::Text, 'spam', PostFilterAction::Replace, '$0 \\1 ${1}')];
		$result = PostFilter::apply($filters, $this->fields('spam'));

		$this->assertSame('$0 \\1 ${1}', $result->fields['message']);
	}

	public function testApplyRegexReplaceSupportsBackreferences(): void
	{
		$filters = [$this->filter(1, PostFilterMatchType::Regex, '/(\w+)@(\w+)\.com/', PostFilterAction::Replace, '$1 at $2')];
		$result = PostFilter::apply($filters, $this->fields('mail me: anon@example.com'));

		$this->assertSame('mail me: anon at example', $result->fields['message']);
	}

	public function testApplyReplaceWithEmptyReplacementRemovesMatches(): void
	{
		$filters = [$this->filter(1, PostFilterMatchType::Text, ' spam', PostFilterAction::Replace, '')];
		$result = PostFilter::apply($filters, $this->fields('hello spam spam'));

		$this->assertSame('hello', $result->fields['message']);
	}

	public function testApplyReplaceFiltersSubjectAndMessageOnly(): void
	{
		$filters = [$this->filter(1, PostFilterMatchType::Text, 'spam', PostFilterAction::Replace, 'ham')];
		$result = PostFilter::apply($filters, ['name' => 'spam', 'subject' => 'spam', 'message' => 'spam']);

		$this->assertSame(['name' => 'spam', 'subject' => 'ham', 'message' => 'ham'], $result->fields);
	}

	public function testApplyReplaceFiltersAreChainedInOrder(): void
	{
		$filters = [
			$this->filter(1, PostFilterMatchType::Text, 'a', PostFilterAction::Replace, 'b'),
			$this->filter(2, PostFilterMatchType::Text, 'b', PostFilterAction::Replace, 'c'),
		];
		$result = PostFilter::apply($filters, $this->fields('a'));

		$this->assertSame('c', $result->fields['message']);
	}

	// --- apply: reject / ban ---

	public function testApplyRejectMatchBlocksPost(): void
	{
		$filters = [$this->filter(7, PostFilterMatchType::Text, 'spam', PostFilterAction::Reject)];
		$fields = $this->fields('buy SPAM now');
		$result = PostFilter::apply($filters, $fields);

		$this->assertSame(7, $result->blocked_by['id']);
		$this->assertSame($fields, $result->fields);
	}

	public function testApplyRejectMatchesSubject(): void
	{
		$filters = [$this->filter(7, PostFilterMatchType::Text, 'spam', PostFilterAction::Reject)];
		$result = PostFilter::apply($filters, $this->fields('hello', 'spam'));

		$this->assertSame(7, $result->blocked_by['id']);
	}

	public function testApplyBanTakesPrecedenceOverReject(): void
	{
		$filters = [
			$this->filter(1, PostFilterMatchType::Text, 'spam', PostFilterAction::Reject),
			$this->filter(2, PostFilterMatchType::Text, 'spam', PostFilterAction::Ban),
		];
		$result = PostFilter::apply($filters, $this->fields('spam'));

		$this->assertSame(2, $result->blocked_by['id']);
	}

	public function testApplyBlockingFiltersSeeOriginalText(): void
	{
		$filters = [
			$this->filter(1, PostFilterMatchType::Text, 'spam', PostFilterAction::Replace, '****'),
			$this->filter(2, PostFilterMatchType::Text, 'spam', PostFilterAction::Ban),
		];
		$result = PostFilter::apply($filters, $this->fields('spam'));

		$this->assertSame(2, $result->blocked_by['id']);
	}

	public function testApplyBlockingFiltersDoNotSeeReplacements(): void
	{
		$filters = [
			$this->filter(1, PostFilterMatchType::Text, 'ham', PostFilterAction::Replace, 'spam'),
			$this->filter(2, PostFilterMatchType::Text, 'spam', PostFilterAction::Reject),
		];
		$result = PostFilter::apply($filters, $this->fields('ham'));

		$this->assertNull($result->blocked_by);
		$this->assertSame('spam', $result->fields['message']);
	}

	// --- apply: failures ---

	public function testApplySkipsFilterWithInvalidRegex(): void
	{
		$filters = [
			$this->filter(1, PostFilterMatchType::Regex, '/spam', PostFilterAction::Reject),
			$this->filter(2, PostFilterMatchType::Regex, '/sp(am/', PostFilterAction::Replace, 'x'),
			$this->filter(3, PostFilterMatchType::Text, 'spam', PostFilterAction::Replace, 'ham'),
		];
		$result = PostFilter::apply($filters, $this->fields('spam'));

		$this->assertNull($result->blocked_by);
		$this->assertSame('ham', $result->fields['message']);
		$this->assertCount(2, $result->errors);
		$this->assertStringStartsWith('filter #1: No ending delimiter', $result->errors[0]);
		$this->assertStringStartsWith('filter #2: Compilation failed', $result->errors[1]);
	}

	public function testApplySkipsFilterWithInvalidMatchType(): void
	{
		$filter = $this->filter(1, PostFilterMatchType::Text, 'spam', PostFilterAction::Reject);
		$filter['match_type'] = 'glob';
		$result = PostFilter::apply([$filter], $this->fields('spam'));

		$this->assertNull($result->blocked_by);
		$this->assertSame(['filter #1: invalid match type'], $result->errors);
	}

	public function testApplySkipsFilterThatExceedsBacktrackLimit(): void
	{
		$this->setIni('pcre.jit', '0');
		$this->setIni('pcre.backtrack_limit', '1000');

		$filters = [
			$this->filter(1, PostFilterMatchType::Regex, '/(a+)+$/', PostFilterAction::Ban),
			$this->filter(2, PostFilterMatchType::Regex, '/(a+)+$/', PostFilterAction::Replace, 'x'),
		];
		$fields = $this->fields(str_repeat('a', 32) . 'b');
		$result = PostFilter::apply($filters, $fields);

		$this->assertNull($result->blocked_by);
		$this->assertSame($fields, $result->fields);
		$this->assertSame(['filter #1: Backtrack limit exhausted', 'filter #2: Backtrack limit exhausted'], $result->errors);
	}

	public function testApplyFailedReplaceIsNotPartiallyApplied(): void
	{
		$this->setIni('pcre.jit', '0');
		$this->setIni('pcre.backtrack_limit', '1000');

		// matches the subject fine, exceeds the backtrack limit on the message
		$filters = [$this->filter(1, PostFilterMatchType::Regex, '/(a+)+$/', PostFilterAction::Replace, 'x')];
		$fields = $this->fields(str_repeat('a', 32) . 'b', 'aaa');
		$result = PostFilter::apply($filters, $fields);

		$this->assertSame($fields, $result->fields);
		$this->assertCount(1, $result->errors);
	}
}
