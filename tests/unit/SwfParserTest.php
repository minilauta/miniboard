<?php

use PHPUnit\Framework\TestCase;
use minichan\core\SwfParser;
use minichan\core\SwfParserException;

require_once __ROOT__ . '/core/swf_parser.php';

class SwfParserTest extends TestCase
{
	private string $dir;

	protected function setUp(): void
	{
		$this->dir = sys_get_temp_dir() . '/miniboard_swf_test';
		if (!is_dir($this->dir)) {
			mkdir($this->dir, 0755, true);
		}
	}

	protected function tearDown(): void
	{
		foreach (array_diff(scandir($this->dir), ['.', '..']) as $file) {
			unlink($this->dir . '/' . $file);
		}
		rmdir($this->dir);
	}

	// --- Minimal SWF builders ---

	private static function tag(int $code, string $body = '', bool $long = false): string
	{
		if (strlen($body) < 0x3F && !$long) {
			return pack('v', ($code << 6) | strlen($body)) . $body;
		}
		return pack('vV', ($code << 6) | 0x3F, strlen($body)) . $body;
	}

	private static function rect(int $xmin, int $xmax, int $ymin, int $ymax): string
	{
		$values = [$xmin, $xmax, $ymin, $ymax];
		$nbits = max(array_map(fn($v) => strlen(decbin(abs($v))), $values)) + 1;
		$bits = sprintf('%05b', $nbits);
		foreach ($values as $v) {
			$bits .= substr(sprintf('%032b', $v & 0xFFFFFFFF), -$nbits);
		}
		$bits .= str_repeat('0', (8 - strlen($bits) % 8) % 8);
		return implode('', array_map(fn($byte) => chr(bindec($byte)), str_split($bits, 8)));
	}

	/** Header fields after the 8 byte file header: 550x400 stage, 29.97 fps (8.8 fixed point), 300 frames */
	private static function movie_header(?string $rect = null, string $frame_rate = "\xF8\x1D", int $frame_count = 300): string
	{
		return ($rect ?? self::rect(0, 550 * 20, 0, 400 * 20)) . $frame_rate . pack('v', $frame_count);
	}

	private static function xmp(string $title, string $creator_tool): string
	{
		return '<x:xmpmeta xmlns:x="adobe:ns:meta/"><rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
			. '<rdf:Description rdf:about="" xmlns:xmp="http://ns.adobe.com/xap/1.0/" xmp:CreatorTool="' . $creator_tool . '">'
			. '<dc:title xmlns:dc="http://purl.org/dc/elements/1.1/"><rdf:Alt><rdf:li xml:lang="x-default">' . $title . '</rdf:li></rdf:Alt></dc:title>'
			. '</rdf:Description></rdf:RDF></x:xmpmeta>' . "\0";
	}

	/** Movie with every tag the parser looks at, the sound stream head sits on a sprite's timeline */
	private static function full_body(): string
	{
		$sprite_tags = self::tag(45, "\x0E\x2E\x40\x02") // SoundStreamHead2: mp3
			. self::tag(1)                               // ShowFrame
			. self::tag(0);                              // End
		return self::movie_header()
			. self::tag(69, pack('V', 0x18))                                 // FileAttributes: ActionScript3 + HasMetadata
			. self::tag(77, self::xmp('My &amp; Movie', 'Adobe Flash CS6'))  // Metadata
			. self::tag(9, "\x33\x66\x99")                                   // SetBackgroundColor
			. self::tag(14, pack('v', 1) . "\x2E" . pack('V', 0) . 'data')   // DefineSound
			. self::tag(39, pack('vv', 2, 1) . $sprite_tags)                 // DefineSprite
			. self::tag(60, pack('vvvv', 3, 10, 320, 240) . "\x00\x04")      // DefineVideoStream
			. self::tag(71, "lib.swf\0\x01\x00\x01\x00" . pack('v', 1) . pack('v', 4) . "asset\0") // ImportAssets2
			. self::tag(1)
			. self::tag(0);
	}

	private static function expected_meta(string $compression, int $version, int $length): array
	{
		return [
			'swf_version'       => $version,
			'compression'       => $compression,
			'uncompressed_size' => $length,
			'width'             => 550,
			'height'            => 400,
			'frame_rate'        => 29.969,
			'frame_count'       => 300,
			'duration'          => 10.01,
			'background'        => '#336699',
			'as3'               => true,
			'stream_audio'      => 'mp3',
			'sound_count'       => 1,
			'video_count'       => 1,
			'imports'           => true,
			'title'             => 'My & Movie',
			'creator_tool'      => 'Adobe Flash CS6',
			'truncated'         => false,
		];
	}

	private static function lzma_compress(string $data): ?string
	{
		exec('xz --version 2>/dev/null', $output, $status);
		if ($status !== 0) {
			return null;
		}
		$process = proc_open(['xz', '--format=lzma', '--stdout'], [0 => ['pipe', 'r'], 1 => ['pipe', 'w']], $pipes);
		fwrite($pipes[0], $data); // NOTE: test data is small enough for the pipe buffers
		fclose($pipes[0]);
		$alone = stream_get_contents($pipes[1]);
		fclose($pipes[1]);
		proc_close($process);
		// .lzma = 5 bytes of properties + 8 byte uncompressed size + data, ZWS = compressed length + properties + data
		return pack('V', strlen($alone) - 13) . substr($alone, 0, 5) . substr($alone, 13);
	}

	private function write_swf(string $signature, int $version, string $body, ?int $declared_length = null): string
	{
		$data = $signature . chr($version) . pack('V', $declared_length ?? 8 + strlen($body));
		if ($signature === 'CWS') {
			$data .= gzcompress($body);
		} else if ($signature === 'ZWS') {
			$compressed = self::lzma_compress($body);
			if ($compressed === null) {
				$this->markTestSkipped('xz not installed');
			}
			$data .= $compressed;
		} else {
			$data .= $body;
		}
		return $this->write_file($data);
	}

	private function write_file(string $data): string
	{
		$path = tempnam($this->dir, 'swf');
		file_put_contents($path, $data);
		return $path;
	}

	/** Writes a CWS file with a huge zero-filled tag without holding it in memory */
	private function write_cws_with_zero_tag(int $zero_bytes, ?int $declared_length = null): string
	{
		$before = self::movie_header() . pack('vV', (255 << 6) | 0x3F, $zero_bytes);
		$after = self::tag(9, "\xAA\xBB\xCC") . self::tag(0);
		$length = $declared_length ?? 8 + strlen($before) + $zero_bytes + strlen($after);

		$path = tempnam($this->dir, 'swf');
		$file = fopen($path, 'wb');
		fwrite($file, 'CWS' . chr(10) . pack('V', $length));
		$deflate = deflate_init(ZLIB_ENCODING_DEFLATE, ['level' => 9]);
		fwrite($file, deflate_add($deflate, $before, ZLIB_NO_FLUSH));
		$zeros = str_repeat("\0", 1 << 20);
		for ($i = 0; $i < $zero_bytes >> 20; $i++) {
			fwrite($file, deflate_add($deflate, $zeros, ZLIB_NO_FLUSH));
		}
		fwrite($file, deflate_add($deflate, $after, ZLIB_FINISH));
		fclose($file);
		return $path;
	}

	// --- Signature ---

	public function test_has_signature(): void
	{
		$this->assertTrue(SwfParser::has_signature("FWS\x08"));
		$this->assertTrue(SwfParser::has_signature("CWS\x0A\x00\x00"));
		$this->assertTrue(SwfParser::has_signature("ZWS\x0D"));
		$this->assertFalse(SwfParser::has_signature("ZWS\x00"));
		$this->assertFalse(SwfParser::has_signature('ZWS'));
		$this->assertFalse(SwfParser::has_signature('GIF89a'));
		$this->assertFalse(SwfParser::has_signature("fws\x08"));
	}

	// --- Metadata ---

	public static function compression_provider(): array
	{
		return [
			'uncompressed'    => ['FWS', 'none', 10],
			'zlib compressed' => ['CWS', 'zlib', 10],
			'lzma compressed' => ['ZWS', 'lzma', 13],
		];
	}

	/**
	 * @dataProvider compression_provider
	 */
	public function test_parses_metadata(string $signature, string $compression, int $version): void
	{
		$body = self::full_body();
		$path = $this->write_swf($signature, $version, $body);

		$this->assertSame(self::expected_meta($compression, $version, 8 + strlen($body)), SwfParser::parse($path));
	}

	public function test_minimal_movie_defaults(): void
	{
		$path = $this->write_swf('FWS', 8, self::movie_header(null, "\x00\x0C", 1) . self::tag(1) . self::tag(0));

		$meta = SwfParser::parse($path);

		$this->assertSame(12.0, $meta['frame_rate']);
		$this->assertSame(0.08, $meta['duration']);
		$this->assertNull($meta['background']);
		$this->assertFalse($meta['as3']);
		$this->assertNull($meta['stream_audio']);
		$this->assertSame(0, $meta['sound_count']);
		$this->assertSame(0, $meta['video_count']);
		$this->assertFalse($meta['imports']);
		$this->assertNull($meta['title']);
		$this->assertNull($meta['creator_tool']);
		$this->assertFalse($meta['truncated']);
	}

	public function test_stage_with_offset_origin(): void
	{
		$path = $this->write_swf('FWS', 8, self::movie_header(self::rect(-2000, 8000, 100, 4100)) . self::tag(0));

		$meta = SwfParser::parse($path);

		$this->assertSame(500, $meta['width']);
		$this->assertSame(200, $meta['height']);
	}

	public function test_zero_frame_rate_has_no_duration(): void
	{
		$path = $this->write_swf('FWS', 8, self::movie_header(null, "\x00\x00") . self::tag(0));

		$this->assertNull(SwfParser::parse($path)['duration']);
	}

	public function test_xmp_strings_are_sanitized(): void
	{
		$title = "Bad \xFF\xFE utf8\x01\x1F" . str_repeat('ä', 200);
		$xmp = self::xmp($title, '&lt;b&gt;Tool&lt;/b&gt;');
		$path = $this->write_swf('FWS', 10, self::movie_header() . self::tag(77, $xmp) . self::tag(0));

		$meta = SwfParser::parse($path);

		$this->assertTrue(mb_check_encoding($meta['title'], 'UTF-8'));
		$this->assertStringStartsWith('Bad ?? utf8 ä', $meta['title']);
		$this->assertSame(128, mb_strlen($meta['title'], 'UTF-8'));
		$this->assertSame('<b>Tool</b>', $meta['creator_tool']);
		$this->assertNotFalse(json_encode($meta));
	}

	public function test_empty_xmp_values_are_null(): void
	{
		$path = $this->write_swf('FWS', 10, self::movie_header() . self::tag(77, self::xmp('  ', '')) . self::tag(0));

		$meta = SwfParser::parse($path);

		$this->assertNull($meta['title']);
		$this->assertNull($meta['creator_tool']);
	}

	// --- Malformed and hostile files ---

	public function test_rejects_non_swf(): void
	{
		$path = $this->write_file("GIF89a\x01\x00\x01\x00\x00\x00\x00;");

		$this->expectException(SwfParserException::class);
		SwfParser::parse($path);
	}

	public function test_rejects_version_zero(): void
	{
		$path = $this->write_swf('FWS', 0, self::movie_header() . self::tag(0));

		$this->expectException(SwfParserException::class);
		SwfParser::parse($path);
	}

	public function test_rejects_malformed_length(): void
	{
		$path = $this->write_swf('FWS', 8, self::movie_header() . self::tag(0), 0);

		$this->expectException(SwfParserException::class);
		$this->expectExceptionMessage('malformed uncompressed length 0');
		SwfParser::parse($path);
	}

	public function test_rejects_too_large_declared_length(): void
	{
		$path = $this->write_swf('CWS', 10, self::movie_header() . self::tag(0), SwfParser::MAX_UNCOMPRESSED_SIZE + 1);

		$this->expectException(SwfParserException::class);
		$this->expectExceptionMessage('exceeds limit');
		SwfParser::parse($path);
	}

	public function test_rejects_truncated_header(): void
	{
		$path = $this->write_file("FWS\x08" . pack('V', 100) . "\x78");

		$this->expectException(SwfParserException::class);
		$this->expectExceptionMessage('truncated header');
		SwfParser::parse($path);
	}

	public function test_rejects_truncated_lzma_header(): void
	{
		$path = $this->write_file("ZWS\x0D" . pack('V', 100) . "\x00\x00");

		$this->expectException(SwfParserException::class);
		$this->expectExceptionMessage('truncated header');
		SwfParser::parse($path);
	}

	public function test_truncated_tags(): void
	{
		$body = self::movie_header() . self::tag(9, "\x33\x66\x99") . self::tag(77, self::xmp('Title', 'Tool'));
		$path = $this->write_swf('FWS', 10, substr($body, 0, -20));

		$meta = SwfParser::parse($path);

		$this->assertSame('#336699', $meta['background']);
		$this->assertNull($meta['title']);
		$this->assertTrue($meta['truncated']);
	}

	public function test_corrupt_zlib_data(): void
	{
		$body = self::movie_header()
			. self::tag(9, "\x33\x66\x99")
			. self::tag(255, str_repeat("\0", 8200), true)
			. self::tag(77, self::xmp('Title', 'Tool'))
			. self::tag(0);
		// stored deflate blocks: a valid one with most of the body, then one with a bad NLEN in a later read
		$block = substr($body, 0, 8500);
		$zlib = "\x78\x01"
			. "\x00" . pack('vv', strlen($block), ~strlen($block) & 0xFFFF) . $block
			. "\x01" . pack('vv', 5, 5) . 'xxxxx';
		$path = $this->write_file('CWS' . chr(10) . pack('V', 8 + strlen($body)) . $zlib);

		$meta = SwfParser::parse($path);

		$this->assertSame(550, $meta['width']);
		$this->assertSame('#336699', $meta['background']);
		$this->assertNull($meta['title']);
		$this->assertTrue($meta['truncated']);
	}

	public function test_large_body_is_streamed(): void
	{
		$path = $this->write_cws_with_zero_tag(32 << 20);

		memory_reset_peak_usage();
		$base = memory_get_usage();
		$meta = SwfParser::parse($path);
		$peak = memory_get_peak_usage() - $base;

		$this->assertSame('#aabbcc', $meta['background']); // found after the 32 MiB tag
		$this->assertFalse($meta['truncated']);
		$this->assertLessThan(8 << 20, $peak);
	}

	public function test_stops_at_declared_length(): void
	{
		// header lies about the length: decompressing everything would produce 64 MiB
		$path = $this->write_cws_with_zero_tag(64 << 20, 1000);

		memory_reset_peak_usage();
		$base = memory_get_usage();
		$meta = SwfParser::parse($path);
		$peak = memory_get_peak_usage() - $base;

		$this->assertSame(1000, $meta['uncompressed_size']);
		$this->assertSame(550, $meta['width']);
		$this->assertNull($meta['background']);
		$this->assertTrue($meta['truncated']);
		$this->assertLessThan(8 << 20, $peak);
	}

	public function test_lzma_temp_file_is_removed(): void
	{
		$path = $this->write_swf('ZWS', 13, self::full_body());
		$before = glob(sys_get_temp_dir() . '/mb_swf_*');

		SwfParser::parse($path);

		$this->assertSame($before, glob(sys_get_temp_dir() . '/mb_swf_*'));
	}
}
