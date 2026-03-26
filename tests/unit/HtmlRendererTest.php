<?php

use PHPUnit\Framework\TestCase;
use minichan\core\HtmlRenderer;

require_once __ROOT__ . '/core/renderer.php';

class HtmlRendererTest extends TestCase
{
	private string $templateDir;

	protected function setUp(): void
	{
		$this->templateDir = sys_get_temp_dir() . '/miniboard_test_templates';
		if (!is_dir($this->templateDir)) {
			mkdir($this->templateDir);
		}
	}

	protected function tearDown(): void
	{
		$files = glob($this->templateDir . '/*.php');
		foreach ($files as $file) {
			unlink($file);
		}
		rmdir($this->templateDir);
	}

	private function createTemplate(string $name, string $content): string
	{
		$path = $this->templateDir . '/' . $name;
		file_put_contents($path, $content);
		return $path;
	}

	public function test_render_plain_template(): void
	{
		$path = $this->createTemplate('plain.php', '<h1>Hello</h1>');
		$renderer = new HtmlRenderer();

		$this->assertSame('<h1>Hello</h1>', $renderer->render($path));
	}

	public function test_render_with_constructor_vars(): void
	{
		$path = $this->createTemplate('vars.php', '<p><?= $name ?></p>');
		$renderer = new HtmlRenderer(['name' => 'World']);

		$this->assertSame('<p>World</p>', $renderer->render($path));
	}

	public function test_render_with_inline_vars(): void
	{
		$path = $this->createTemplate('inline.php', '<p><?= $greeting ?></p>');
		$renderer = new HtmlRenderer();

		$this->assertSame('<p>Hi</p>', $renderer->render($path, ['greeting' => 'Hi']));
	}

	public function test_inline_vars_do_not_overwrite_constructor_vars(): void
	{
		$path = $this->createTemplate('both.php', '<p><?= $a ?>-<?= $b ?></p>');
		$renderer = new HtmlRenderer(['a' => 'original']);

		$result = $renderer->render($path, ['a' => 'overwritten', 'b' => 'new']);
		$this->assertSame('<p>original-new</p>', $result);
	}

	public function test_set_var(): void
	{
		$path = $this->createTemplate('setvar.php', '<?= $x ?>');
		$renderer = new HtmlRenderer();
		$renderer->set_var('x', 42);

		$this->assertSame('42', $renderer->render($path));
	}

	public function test_set_var_overwrites(): void
	{
		$path = $this->createTemplate('overwrite.php', '<?= $x ?>');
		$renderer = new HtmlRenderer(['x' => 'old']);
		$renderer->set_var('x', 'new');

		$this->assertSame('new', $renderer->render($path));
	}

	public function test_render_returns_string(): void
	{
		$path = $this->createTemplate('empty.php', '');
		$renderer = new HtmlRenderer();

		$result = $renderer->render($path);
		$this->assertIsString($result);
	}
}
