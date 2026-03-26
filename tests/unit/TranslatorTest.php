<?php

use PHPUnit\Framework\TestCase;
use minichan\core\Translator;

require_once __ROOT__ . '/core/translator.php';

class TranslatorTest extends TestCase
{
	private string $langDir;

	protected function setUp(): void
	{
		$this->langDir = sys_get_temp_dir() . '/miniboard_test_lang';
		if (!is_dir($this->langDir)) {
			mkdir($this->langDir);
		}

		file_put_contents($this->langDir . '/en.php', '<?php return [
			"hello" => "Hello",
			"greeting" => "Hello, :name!",
			"multi" => ":a and :b",
		];');

		file_put_contents($this->langDir . '/fi.php', '<?php return [
			"hello" => "Moi",
			"greeting" => "Moi, :name!",
		];');

		// Clear any cookie state
		unset($_COOKIE['miniboard/lang']);
	}

	protected function tearDown(): void
	{
		foreach (glob($this->langDir . '/*.php') as $file) {
			unlink($file);
		}
		rmdir($this->langDir);
	}

	public function test_translate_known_key(): void
	{
		$t = new Translator($this->langDir, 'en');
		$this->assertSame('Hello', $t->t('hello'));
	}

	public function test_translate_unknown_key_returns_key(): void
	{
		$t = new Translator($this->langDir, 'en');
		$this->assertSame('nonexistent.key', $t->t('nonexistent.key'));
	}

	public function test_translate_with_single_param(): void
	{
		$t = new Translator($this->langDir, 'en');
		$this->assertSame('Hello, World!', $t->t('greeting', ['name' => 'World']));
	}

	public function test_translate_with_multiple_params(): void
	{
		$t = new Translator($this->langDir, 'en');
		$this->assertSame('foo and bar', $t->t('multi', ['a' => 'foo', 'b' => 'bar']));
	}

	public function test_default_language_used_when_no_cookie(): void
	{
		$t = new Translator($this->langDir, 'fi');
		$this->assertSame('Moi', $t->t('hello'));
	}

	public function test_cookie_selects_language(): void
	{
		$_COOKIE['miniboard/lang'] = 'fi';
		$t = new Translator($this->langDir, 'en');
		$this->assertSame('Moi', $t->t('hello'));
		unset($_COOKIE['miniboard/lang']);
	}

	public function test_invalid_cookie_falls_back_to_default(): void
	{
		$_COOKIE['miniboard/lang'] = 'nonexistent';
		$t = new Translator($this->langDir, 'en');
		$this->assertSame('Hello', $t->t('hello'));
		unset($_COOKIE['miniboard/lang']);
	}

	public function test_missing_language_file_returns_empty(): void
	{
		$t = new Translator($this->langDir, 'xx');
		$this->assertSame('hello', $t->t('hello'));
	}
}
