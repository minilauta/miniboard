<?php

use PHPUnit\Framework\TestCase;
use minichan\core\Analyzer;
use minichan\core\DbConnection;

require_once __ROOT__ . '/core/db_connection.php';
require_once __ROOT__ . '/core/analyzer.php';

class AnalyzerTest extends TestCase
{
	private string $srcDir;

	protected function setUp(): void
	{
		$this->srcDir = __PUBLIC__ . '/src';
		if (!is_dir($this->srcDir)) {
			mkdir($this->srcDir, 0755, true);
		}
	}

	protected function tearDown(): void
	{
		if (is_dir($this->srcDir)) {
			$this->removeDir($this->srcDir);
		}
	}

	private function removeDir(string $dir): void
	{
		foreach (array_diff(scandir($dir), ['.', '..']) as $file) {
			$path = $dir . '/' . $file;
			is_dir($path) ? $this->removeDir($path) : unlink($path);
		}
		rmdir($dir);
	}

	private function createAnalyzer(): Analyzer
	{
		return new Analyzer($this->createMock(DbConnection::class));
	}

	// --- Minimal image data builders ---

	private function buildJpeg(): string
	{
		// SOI + APP0 (minimal 2-byte length) + EOI
		return "\xFF\xD8\xFF\xE0\x00\x02\x00\x00\xFF\xD9";
	}

	private function buildPng(array $extraChunks = []): string
	{
		$data = "\x89\x50\x4E\x47\x0D\x0A\x1A\x0A";
		// IHDR: 1x1 RGB 8-bit
		$ihdr = "\x00\x00\x00\x01\x00\x00\x00\x01\x08\x02\x00\x00\x00";
		$data .= pack('N', strlen($ihdr)) . 'IHDR' . $ihdr . "\x00\x00\x00\x00";
		foreach ($extraChunks as $chunk) {
			$data .= pack('N', strlen($chunk['data'])) . $chunk['type'] . $chunk['data'] . "\x00\x00\x00\x00";
		}
		$data .= "\x00\x00\x00\x00IEND\xAE\x42\x60\x82";
		return $data;
	}

	private function buildGif(): string
	{
		// GIF89a + LSD (1x1, no GCT) + trailer
		return "GIF89a\x01\x00\x01\x00\x00\x00\x00\x3B";
	}

	private function buildBmp(): string
	{
		// BMP header: signature + declared file size (14) + reserved + data offset
		return "\x42\x4D" . pack('V', 14) . "\x00\x00\x00\x00" . pack('V', 14);
	}

	private function buildWebp(): string
	{
		// RIFF header + payload size (4 = just the WEBP FourCC) + WEBP
		return "RIFF" . pack('V', 4) . "WEBP";
	}

	// --- Empty / missing directory ---

	public function test_no_files(): void
	{
		$analyzer = $this->createAnalyzer();
		$this->expectOutputRegex('/no files to analyze/');
		$analyzer->analyze_files();
	}

	public function test_directory_not_found(): void
	{
		$this->removeDir($this->srcDir);
		$analyzer = $this->createAnalyzer();
		$this->expectOutputRegex('/does not exist/');
		$analyzer->analyze_files();
	}

	// --- Skipping non-image files ---

	public function test_skips_non_image_files(): void
	{
		file_put_contents($this->srcDir . '/readme.txt', 'hello world, this is text');
		$analyzer = $this->createAnalyzer();
		$this->expectOutputRegex('/skipped 1 non-image/');
		$analyzer->analyze_files();
	}

	public function test_skips_empty_files(): void
	{
		file_put_contents($this->srcDir . '/empty.jpg', '');
		$analyzer = $this->createAnalyzer();
		$this->expectOutputRegex('/skipped 1 non-image/');
		$analyzer->analyze_files();
	}

	public function test_excludes_trashbin_and_bans(): void
	{
		mkdir($this->srcDir . '/.trashbin', 0755);
		mkdir($this->srcDir . '/bans', 0755);
		file_put_contents($this->srcDir . '/.trashbin/image.jpg', $this->buildJpeg());
		file_put_contents($this->srcDir . '/bans/image.jpg', $this->buildJpeg());

		$analyzer = $this->createAnalyzer();
		$this->expectOutputRegex('/no files to analyze/');
		$analyzer->analyze_files();
	}

	// --- Clean images (no findings) ---

	public function test_clean_jpeg(): void
	{
		file_put_contents($this->srcDir . '/clean.jpg', $this->buildJpeg());
		$analyzer = $this->createAnalyzer();
		$this->expectOutputRegex('/analyzed 1 images.*flagged 0/');
		$analyzer->analyze_files();
	}

	public function test_clean_png(): void
	{
		file_put_contents($this->srcDir . '/clean.png', $this->buildPng());
		$analyzer = $this->createAnalyzer();
		$this->expectOutputRegex('/analyzed 1 images.*flagged 0/');
		$analyzer->analyze_files();
	}

	public function test_clean_gif(): void
	{
		file_put_contents($this->srcDir . '/clean.gif', $this->buildGif());
		$analyzer = $this->createAnalyzer();
		$this->expectOutputRegex('/analyzed 1 images.*flagged 0/');
		$analyzer->analyze_files();
	}

	public function test_clean_bmp(): void
	{
		file_put_contents($this->srcDir . '/clean.bmp', $this->buildBmp());
		$analyzer = $this->createAnalyzer();
		$this->expectOutputRegex('/analyzed 1 images.*flagged 0/');
		$analyzer->analyze_files();
	}

	public function test_clean_webp(): void
	{
		file_put_contents($this->srcDir . '/clean.webp', $this->buildWebp());
		$analyzer = $this->createAnalyzer();
		$this->expectOutputRegex('/analyzed 1 images.*flagged 0/');
		$analyzer->analyze_files();
	}

	// --- Trailing data detection ---

	public function test_jpeg_trailing_data(): void
	{
		file_put_contents($this->srcDir . '/suspect.jpg', $this->buildJpeg() . 'HIDDEN_DATA');
		$analyzer = $this->createAnalyzer();
		$this->expectOutputRegex('/trailing data after JPEG end marker: 11 bytes/');
		$analyzer->analyze_files();
	}

	public function test_png_trailing_data(): void
	{
		file_put_contents($this->srcDir . '/suspect.png', $this->buildPng() . 'HIDDEN_DATA');
		$analyzer = $this->createAnalyzer();
		$this->expectOutputRegex('/trailing data after PNG end marker: 11 bytes/');
		$analyzer->analyze_files();
	}

	public function test_gif_trailing_data(): void
	{
		file_put_contents($this->srcDir . '/suspect.gif', $this->buildGif() . 'HIDDEN_DATA');
		$analyzer = $this->createAnalyzer();
		$this->expectOutputRegex('/trailing data after GIF end marker/');
		$analyzer->analyze_files();
	}

	public function test_bmp_trailing_data(): void
	{
		file_put_contents($this->srcDir . '/suspect.bmp', $this->buildBmp() . 'HIDDEN_DATA');
		$analyzer = $this->createAnalyzer();
		$this->expectOutputRegex('/trailing data after BMP end marker/');
		$analyzer->analyze_files();
	}

	public function test_webp_trailing_data(): void
	{
		file_put_contents($this->srcDir . '/suspect.webp', $this->buildWebp() . 'HIDDEN_DATA');
		$analyzer = $this->createAnalyzer();
		$this->expectOutputRegex('/trailing data after WEBP end marker/');
		$analyzer->analyze_files();
	}

	// --- Embedded file signature detection ---

	public function test_trailing_zip_signature(): void
	{
		file_put_contents($this->srcDir . '/suspect.jpg', $this->buildJpeg() . "\x50\x4B\x03\x04" . 'zipdata');
		$analyzer = $this->createAnalyzer();
		$this->expectOutputRegex('/embedded ZIP signature in trailing data/');
		$analyzer->analyze_files();
	}

	public function test_trailing_rar_signature(): void
	{
		file_put_contents($this->srcDir . '/suspect.jpg', $this->buildJpeg() . "\x52\x61\x72\x21\x1A\x07" . 'rardata');
		$analyzer = $this->createAnalyzer();
		$this->expectOutputRegex('/embedded RAR signature in trailing data/');
		$analyzer->analyze_files();
	}

	public function test_trailing_pdf_signature(): void
	{
		file_put_contents($this->srcDir . '/suspect.png', $this->buildPng() . "\x25\x50\x44\x46" . '-1.4');
		$analyzer = $this->createAnalyzer();
		$this->expectOutputRegex('/embedded PDF signature in trailing data/');
		$analyzer->analyze_files();
	}

	// --- PNG chunk analysis ---

	public function test_png_nonstandard_chunk(): void
	{
		$png = $this->buildPng([
			['type' => 'xYzW', 'data' => 'hidden'],
		]);
		file_put_contents($this->srcDir . '/suspect.png', $png);
		$analyzer = $this->createAnalyzer();
		$this->expectOutputRegex('/non-standard PNG chunk "xYzW"/');
		$analyzer->analyze_files();
	}

	public function test_png_large_text_chunk(): void
	{
		$png = $this->buildPng([
			['type' => 'tEXt', 'data' => str_repeat('A', 1025)],
		]);
		file_put_contents($this->srcDir . '/suspect.png', $png);
		$analyzer = $this->createAnalyzer();
		$this->expectOutputRegex('/large PNG text chunk "tEXt" \(1025 bytes\)/');
		$analyzer->analyze_files();
	}

	public function test_png_large_ztxt_chunk(): void
	{
		$png = $this->buildPng([
			['type' => 'zTXt', 'data' => str_repeat('B', 2000)],
		]);
		file_put_contents($this->srcDir . '/suspect.png', $png);
		$analyzer = $this->createAnalyzer();
		$this->expectOutputRegex('/large PNG text chunk "zTXt" \(2000 bytes\)/');
		$analyzer->analyze_files();
	}

	public function test_png_text_chunk_at_threshold_not_flagged(): void
	{
		$png = $this->buildPng([
			['type' => 'tEXt', 'data' => str_repeat('A', 1024)],
		]);
		file_put_contents($this->srcDir . '/suspect.png', $png);
		$analyzer = $this->createAnalyzer();
		$this->expectOutputRegex('/analyzed 1 images.*flagged 0/');
		$analyzer->analyze_files();
	}

	public function test_png_standard_ancillary_chunk_not_flagged(): void
	{
		$png = $this->buildPng([
			['type' => 'pHYs', 'data' => "\x00\x00\x00\x01\x00\x00\x00\x01\x01"],
		]);
		file_put_contents($this->srcDir . '/suspect.png', $png);
		$analyzer = $this->createAnalyzer();
		$this->expectOutputRegex('/analyzed 1 images.*flagged 0/');
		$analyzer->analyze_files();
	}

	public function test_png_ornt_chunk_not_flagged(): void
	{
		$png = $this->buildPng([
			['type' => 'orNT', 'data' => "\x01"],
		]);
		file_put_contents($this->srcDir . '/suspect.png', $png);
		$analyzer = $this->createAnalyzer();
		$this->expectOutputRegex('/analyzed 1 images.*flagged 0/');
		$analyzer->analyze_files();
	}

	// --- Combined detections ---

	public function test_png_nonstandard_chunk_and_trailing_zip(): void
	{
		$png = $this->buildPng([
			['type' => 'sEcR', 'data' => 'secret'],
		]);
		file_put_contents($this->srcDir . '/suspect.png', $png . "\x50\x4B\x03\x04" . 'zipdata');
		$analyzer = $this->createAnalyzer();

		ob_start();
		$analyzer->analyze_files();
		$output = ob_get_clean();

		$this->assertStringContainsString('non-standard PNG chunk "sEcR"', $output);
		$this->assertStringContainsString('trailing data after PNG end marker', $output);
		$this->assertStringContainsString('embedded ZIP signature', $output);
	}

	// --- Multiple files ---

	public function test_multiple_files_mixed(): void
	{
		file_put_contents($this->srcDir . '/clean.jpg', $this->buildJpeg());
		file_put_contents($this->srcDir . '/suspect.png', $this->buildPng() . 'EXTRA');
		file_put_contents($this->srcDir . '/readme.txt', 'not an image');

		$analyzer = $this->createAnalyzer();
		$this->expectOutputRegex('/analyzed 2 images, skipped 1 non-image files, flagged 1/');
		$analyzer->analyze_files();
	}

	// --- GIF block structure parsing ---

	public function test_gif_with_extension_and_image_blocks_clean(): void
	{
		// GIF89a + LSD (no GCT)
		$data = "GIF89a\x01\x00\x01\x00\x00\x00\x00";
		// Graphics Control Extension
		$data .= "\x21\xF9\x04\x00\x00\x00\x00\x00";
		// Image Descriptor (1x1, no LCT)
		$data .= "\x2C\x00\x00\x00\x00\x01\x00\x01\x00\x00";
		// LZW min code size + sub-block + terminator
		$data .= "\x02\x01\x00\x00";
		// Trailer
		$data .= "\x3B";

		file_put_contents($this->srcDir . '/animated.gif', $data);
		$analyzer = $this->createAnalyzer();
		$this->expectOutputRegex('/analyzed 1 images.*flagged 0/');
		$analyzer->analyze_files();
	}

	public function test_gif_with_blocks_and_trailing_data(): void
	{
		// GIF89a + LSD (no GCT)
		$data = "GIF89a\x01\x00\x01\x00\x00\x00\x00";
		// Graphics Control Extension
		$data .= "\x21\xF9\x04\x00\x00\x00\x00\x00";
		// Image Descriptor (1x1, no LCT)
		$data .= "\x2C\x00\x00\x00\x00\x01\x00\x01\x00\x00";
		// LZW min code size + sub-block + terminator
		$data .= "\x02\x01\x00\x00";
		// Trailer
		$data .= "\x3B";
		// Appended data
		$data .= "HIDDEN";

		file_put_contents($this->srcDir . '/suspect.gif', $data);
		$analyzer = $this->createAnalyzer();
		$this->expectOutputRegex('/trailing data after GIF end marker: 6 bytes/');
		$analyzer->analyze_files();
	}
}
