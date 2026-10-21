<?php

use PHPUnit\Framework\TestCase;
use minichan\core\BinaryForm;

require_once __ROOT__ . '/core/binary_form.php';

class BinaryFormTest extends TestCase
{
	private const MAX_FILES = 2;
	private const MAX_FILE_SIZE = 16;

	/**
	 * @param array $files list of [name, type, contents]
	 * @return resource
	 */
	private static function body(string $fields, array $files, ?string $header = null, string $trailing = '')
	{
		$header ??= json_encode([
			'fields' => $fields,
			'files' => array_map(fn(array $file) => ['name' => $file[0], 'type' => $file[1], 'size' => strlen($file[2])], $files),
		]);

		return self::raw(pack('N', strlen($header)) . $header . implode('', array_column($files, 2)) . $trailing);
	}

	/**
	 * @return resource
	 */
	private static function raw(string $bytes)
	{
		$stream = fopen('php://memory', 'w+b');
		fwrite($stream, $bytes);
		rewind($stream);
		return $stream;
	}

	private static function tmp_dirs(): array
	{
		return glob(sys_get_temp_dir() . '/minichan.BinaryForm.*');
	}

	public function test_parses_fields_like_post(): void
	{
		$form = new BinaryForm(self::body('csrf_token=abc&message=hello+world%21&spoiler=1&boards%5B%5D=a&boards%5B%5D=b', []), self::MAX_FILES, self::MAX_FILE_SIZE);

		$this->assertSame(['csrf_token' => 'abc', 'message' => 'hello world!', 'spoiler' => '1', 'boards' => ['a', 'b']], $form->fields);
		$this->assertSame([], $form->files);
	}

	public function test_parses_files_like_files_superglobal(): void
	{
		$form = new BinaryForm(self::body('', [['drawing.tgkr', '', 'TGK replay'], ['drawing.png', 'image/png', 'PNG image']]), self::MAX_FILES, self::MAX_FILE_SIZE);

		$this->assertCount(2, $form->files);
		$this->assertSame('drawing.tgkr', $form->files[0]['name']);
		$this->assertSame('', $form->files[0]['type']);
		$this->assertSame(UPLOAD_ERR_OK, $form->files[0]['error']);
		$this->assertSame(10, $form->files[0]['size']);
		$this->assertStringEqualsFile($form->files[0]['tmp_name'], 'TGK replay');
		$this->assertSame('drawing.png', $form->files[1]['name']);
		$this->assertSame('image/png', $form->files[1]['type']);
		$this->assertStringEqualsFile($form->files[1]['tmp_name'], 'PNG image');
	}

	public function test_empty_file_input_is_no_file(): void
	{
		$form = new BinaryForm(self::body('', [['', 'application/octet-stream', '']]), self::MAX_FILES, self::MAX_FILE_SIZE);

		$this->assertSame([BinaryForm::NO_FILE], $form->files);
	}

	public function test_strips_client_paths_from_file_names(): void
	{
		$form = new BinaryForm(self::body('', [['C:\\fakepath\\a.png', 'image/png', 'a'], ['../../b.png', 'image/png', 'b']]), self::MAX_FILES, self::MAX_FILE_SIZE);

		$this->assertSame('a.png', $form->files[0]['name']);
		$this->assertSame('b.png', $form->files[1]['name']);
	}

	public function test_destruct_deletes_tmp_files_that_were_not_moved(): void
	{
		$form = new BinaryForm(self::body('', [['a.png', 'image/png', 'a'], ['b.png', 'image/png', 'b']]), self::MAX_FILES, self::MAX_FILE_SIZE);
		$tmp_a = $form->files[0]['tmp_name'];
		$tmp_b = $form->files[1]['tmp_name'];
		$moved_b = tempnam(sys_get_temp_dir(), 'BinaryFormTest');
		rename($tmp_b, $moved_b);

		unset($form);

		$this->assertFileDoesNotExist($tmp_a);
		$this->assertDirectoryDoesNotExist(dirname($tmp_a));
		$this->assertStringEqualsFile($moved_b, 'b');
		unlink($moved_b);
	}

	public static function invalid_body_provider(): array
	{
		$valid_header = json_encode(['fields' => '', 'files' => [['name' => 'a.png', 'type' => 'image/png', 'size' => 4]]]);

		return [
			'empty body' => [fn() => self::raw(''), 'form body ended unexpectedly'],
			'truncated header' => [fn() => self::raw(pack('N', strlen($valid_header)) . substr($valid_header, 0, -1)), 'form body ended unexpectedly'],
			'truncated file' => [fn() => self::body('', [['a.png', 'image/png', 'aaa']], $valid_header), 'form body ended unexpectedly'],
			'trailing data' => [fn() => self::body('', [['a.png', 'image/png', 'a']], null, 'x'), 'form body has trailing data'],
			'invalid json' => [fn() => self::body('', [], '{'), 'form header is not valid JSON'],
			'header not an object' => [fn() => self::body('', [], '5'), 'form header is malformed'],
			'missing fields' => [fn() => self::body('', [], '{"files":[]}'), 'form header is malformed'],
			'files not a list' => [fn() => self::body('', [], '{"fields":"","files":{"a":1}}'), 'form header is malformed'],
			'file without size' => [fn() => self::body('', [], '{"fields":"","files":[{"name":"a.png","type":"image/png"}]}'), 'form file #0 is malformed'],
			'nameless file with data' => [fn() => self::body('', [['', 'image/png', 'a']]), 'form file #0 has no name'],
			'too many files' => [fn() => self::body('', [['a', '', 'a'], ['b', '', 'b'], ['c', '', 'c']]), 'too many files: 3 > 2'],
			'file too large' => [fn() => self::body('', [['a.png', 'image/png', str_repeat('a', 17)]]), 'form file #0 size exceeds limit: 17 bytes > 16 bytes'],
			'header too large' => [fn() => self::raw(pack('N', 1024 * 1024 + 1)), 'form header too large: 1048577 bytes'],
		];
	}

	/**
	 * @dataProvider invalid_body_provider
	 */
	public function test_invalid_body_throws_and_leaves_no_tmp_files(Closure $body, string $message): void
	{
		$tmp_dirs = self::tmp_dirs();

		try {
			new BinaryForm($body(), self::MAX_FILES, self::MAX_FILE_SIZE);
			$this->fail('expected UnexpectedValueException');
		} catch (UnexpectedValueException $ex) {
			$this->assertSame($message, $ex->getMessage());
		}

		$this->assertSame($tmp_dirs, self::tmp_dirs());
	}
}
