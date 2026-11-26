<?php

namespace minichan\core;

/**
 * Thrown when a file is not a valid or acceptable SWF file.
 */
class SwfParserException extends \Exception {}

/**
 * Extracts metadata from SWF (Adobe Flash) files without executing them.
 *
 * Supports uncompressed (FWS), zlib compressed (CWS) and LZMA compressed (ZWS) files. LZMA data is
 * decompressed with the `xz` command line tool, because PHP has no built-in LZMA decoder.
 *
 * Decompression is streamed and never goes past the uncompressed length declared in the header (like
 * Flash Player and Ruffle), and files declaring more than MAX_UNCOMPRESSED_SIZE are rejected, so
 * decompression bombs can't exhaust memory.
 */
class SwfParser
{
	/** Largest accepted uncompressed length (as declared in the header), same as Ruffle's preallocation cap */
	public const MAX_UNCOMPRESSED_SIZE = 128 * 1024 * 1024;

	/** Maximum number of tags to scan, bounds the CPU time spent on files packed with tiny tags */
	public const MAX_TAGS = 2000000;

	/** Bytes read from the input (or xz output) per step */
	private const READ_CHUNK = 8192;

	/** Compressed bytes inflated per step, kept small because deflate can expand data ~1000x */
	private const INFLATE_CHUNK = 1024;

	/** Maximum length of a Metadata (XMP) tag body to inspect */
	private const MAX_XMP_LEN = 65536;

	/** Maximum length of strings taken from XMP metadata */
	private const MAX_STRING_LEN = 128;

	/** Decoder memory limit for xz, guards against huge dictionary sizes in the LZMA properties */
	private const XZ_MEMLIMIT = '128MiB';

	private const COMPRESSION = ['FWS' => 'none', 'CWS' => 'zlib', 'ZWS' => 'lzma'];

	/** SoundStreamHead StreamSoundCompression values */
	private const SOUND_FORMATS = [
		0 => 'pcm', 1 => 'adpcm', 2 => 'mp3', 3 => 'pcm', 4 => 'nellymoser', 5 => 'nellymoser', 6 => 'nellymoser', 11 => 'speex',
	];

	private const TAG_END = 0;
	private const TAG_SET_BACKGROUND_COLOR = 9;
	private const TAG_DEFINE_SOUND = 14;
	private const TAG_SOUND_STREAM_HEAD = 18;
	private const TAG_DEFINE_SPRITE = 39;
	private const TAG_SOUND_STREAM_HEAD2 = 45;
	private const TAG_IMPORT_ASSETS = 57;
	private const TAG_DEFINE_VIDEO_STREAM = 60;
	private const TAG_FILE_ATTRIBUTES = 69;
	private const TAG_IMPORT_ASSETS2 = 71;
	private const TAG_METADATA = 77;

	/** @var resource|null */
	private $file = null;

	/** @var resource|null */
	private $xz_process = null;

	/** @var resource|null */
	private $xz_stdout = null;

	private ?string $xz_input = null;
	private ?\InflateContext $inflate = null;
	private bool $inflate_done = false;
	private string $compression = 'none';

	/** Decompressed body bytes that may still be produced */
	private int $remaining = 0;

	/** Decompressed body bytes consumed so far */
	private int $consumed = 0;

	private string $buffer = '';
	private int $buffer_pos = 0;

	private function __construct()
	{
	}

	/**
	 * Checks if the given leading bytes (at least 4) look like a SWF file: FWS/CWS/ZWS signature and a non-zero version.
	 */
	public static function has_signature(string $head): bool
	{
		return strlen($head) >= 4 && isset(self::COMPRESSION[substr($head, 0, 3)]) && ord($head[3]) > 0;
	}

	/**
	 * Parses a SWF file and returns its metadata:
	 *
	 * - swf_version (int), compression ('none'|'zlib'|'lzma'), uncompressed_size (int, bytes, as declared in the header)
	 * - width, height (int, stage size in pixels)
	 * - frame_rate (float), frame_count (int, as declared in the header), duration (?float, seconds)
	 * - background (?string, '#rrggbb'), as3 (bool, ActionScript 3 movie)
	 * - stream_audio (?string, codec of the streaming soundtrack), sound_count (int, event sounds)
	 * - video_count (int, embedded video streams), imports (bool, imports assets from other files)
	 * - title, creator_tool (?string, from XMP metadata, untrusted text that must be escaped when displayed)
	 * - truncated (bool, the data ended or the tag limit was hit before the End tag)
	 *
	 * @throws SwfParserException if the file is not a valid SWF file or declares a too large uncompressed length
	 * @throws \RuntimeException on I/O errors or when xz is not available
	 */
	public static function parse(string $path): array
	{
		$parser = new self();
		try {
			return $parser->parse_file($path);
		} finally {
			$parser->close();
		}
	}

	private function parse_file(string $path): array
	{
		$this->file = @fopen($path, 'rb');
		if ($this->file === false) {
			$this->file = null;
			throw new \RuntimeException("failed to open file '{$path}'");
		}

		$head = fread($this->file, 8);
		if ($head === false || strlen($head) < 8 || !self::has_signature($head)) {
			throw new SwfParserException('invalid signature');
		}

		$version = ord($head[3]);
		$length = unpack('V', $head, 4)[1];
		if ($length < 8) {
			throw new SwfParserException("malformed uncompressed length {$length}");
		}
		if ($length > self::MAX_UNCOMPRESSED_SIZE) {
			throw new SwfParserException("uncompressed length {$length} exceeds limit " . self::MAX_UNCOMPRESSED_SIZE);
		}

		$this->compression = self::COMPRESSION[substr($head, 0, 3)];
		$this->remaining = $length - 8;
		$this->open_stream();

		// frame size RECT: 5 bit field size followed by xmin, xmax, ymin, ymax in twips
		$rect = $this->read(1);
		if ($rect !== null) {
			$nbits = ord($rect) >> 3;
			$rest = $this->read((int) ceil((5 + 4 * $nbits) / 8) - 1);
			$rect = $rest !== null ? $rect . $rest : null;
		}
		$rate_count = $rect !== null ? $this->read(4) : null;
		if ($rect === null || $rate_count === null) {
			throw new SwfParserException('truncated header');
		}

		[$xmin, $xmax, $ymin, $ymax] = self::read_rect($rect);
		$frame_rate = ord($rate_count[1]) + ord($rate_count[0]) / 256; // UI8.8 fixed point
		$frame_count = unpack('v', $rate_count, 2)[1];

		$meta = [
			'swf_version'       => $version,
			'compression'       => $this->compression,
			'uncompressed_size' => $length,
			'width'             => max(0, (int) round(($xmax - $xmin) / 20)),
			'height'            => max(0, (int) round(($ymax - $ymin) / 20)),
			'frame_rate'        => round($frame_rate, 3),
			'frame_count'       => $frame_count,
			'duration'          => $frame_rate > 0 ? round($frame_count / $frame_rate, 2) : null,
			'background'        => null,
			'as3'               => false,
			'stream_audio'      => null,
			'sound_count'       => 0,
			'video_count'       => 0,
			'imports'           => false,
			'title'             => null,
			'creator_tool'      => null,
			'truncated'         => true,
		];

		$this->scan_tags($meta);

		return $meta;
	}

	/**
	 * Walks the tag list, descending into sprites (their timelines can hold sound stream heads).
	 */
	private function scan_tags(array &$meta): void
	{
		$sprite_end = null; // body offset where the current sprite's tags end, sprites can't nest

		for ($i = 0; $i < self::MAX_TAGS; $i++) {
			if ($sprite_end !== null && $this->consumed >= $sprite_end) {
				$sprite_end = null;
			}

			$header = $this->read(2);
			if ($header === null) return;
			$code_len = unpack('v', $header)[1];
			$code = $code_len >> 6;
			$len = $code_len & 0x3F;
			if ($len === 0x3F) {
				$long = $this->read(4);
				if ($long === null) return;
				$len = unpack('V', $long)[1];
			}
			$tag_end = $this->consumed + $len;

			if ($code === self::TAG_END) {
				if ($sprite_end === null) {
					$meta['truncated'] = false;
					return;
				}
				// end of the sprite's tags, continue after the sprite
				if (!$this->skip(max(0, $sprite_end - $this->consumed))) return;
				$sprite_end = null;
				continue;
			}

			$want = match ($code) {
				self::TAG_DEFINE_SPRITE => 4,
				self::TAG_FILE_ATTRIBUTES => 1,
				self::TAG_SET_BACKGROUND_COLOR => 3,
				self::TAG_SOUND_STREAM_HEAD, self::TAG_SOUND_STREAM_HEAD2 => 2,
				self::TAG_METADATA => self::MAX_XMP_LEN,
				default => 0,
			};
			$body = $want > 0 ? $this->read(min($len, $want)) : '';
			if ($body === null) return;

			if ($code === self::TAG_DEFINE_SPRITE && $sprite_end === null && $len >= 4) {
				// sprite id + frame count, the sprite's own tags follow inline
				$sprite_end = $tag_end;
				continue;
			}

			$this->apply_tag($meta, $code, $body);

			if (!$this->skip($len - strlen($body))) return;
		}
	}

	private function apply_tag(array &$meta, int $code, string $body): void
	{
		switch ($code) {
			case self::TAG_FILE_ATTRIBUTES:
				if (strlen($body) >= 1) {
					$meta['as3'] = (ord($body[0]) & 0x08) !== 0;
				}
				break;
			case self::TAG_SET_BACKGROUND_COLOR:
				if (strlen($body) === 3 && $meta['background'] === null) {
					$meta['background'] = sprintf('#%02x%02x%02x', ord($body[0]), ord($body[1]), ord($body[2]));
				}
				break;
			case self::TAG_SOUND_STREAM_HEAD:
			case self::TAG_SOUND_STREAM_HEAD2:
				if (strlen($body) === 2 && $meta['stream_audio'] === null) {
					$meta['stream_audio'] = self::SOUND_FORMATS[ord($body[1]) >> 4] ?? 'unknown';
				}
				break;
			case self::TAG_DEFINE_SOUND:
				$meta['sound_count']++;
				break;
			case self::TAG_DEFINE_VIDEO_STREAM:
				$meta['video_count']++;
				break;
			case self::TAG_IMPORT_ASSETS:
			case self::TAG_IMPORT_ASSETS2:
				$meta['imports'] = true;
				break;
			case self::TAG_METADATA:
				$meta['title'] ??= self::xmp_value($body, 'dc:title');
				$meta['creator_tool'] ??= self::xmp_value($body, 'xmp:CreatorTool');
				break;
		}
	}

	/**
	 * Reads a simple XMP property, either as an element (plain or the first rdf:Alt/Seq/Bag item) or as an attribute.
	 */
	private static function xmp_value(string $xmp, string $name): ?string
	{
		$name = preg_quote($name, '~');
		if (preg_match('~<' . $name . '(?:\s[^>]*)?>\s*(?:<rdf:(?:Alt|Seq|Bag)>\s*<rdf:li(?:\s[^>]*)?>)?([^<]*)<~', $xmp, $m) !== 1
			&& preg_match('~\s' . $name . '\s*=\s*"([^"]*)"~', $xmp, $m) !== 1
		) {
			return null;
		}

		$value = html_entity_decode($m[1], ENT_QUOTES | ENT_XML1, 'UTF-8');
		$value = mb_scrub($value, 'UTF-8');
		$value = trim(preg_replace('/[\x00-\x1F\x7F]+/', ' ', $value));

		return $value !== '' ? mb_substr($value, 0, self::MAX_STRING_LEN, 'UTF-8') : null;
	}

	/**
	 * Decodes a RECT record into [xmin, xmax, ymin, ymax].
	 */
	private static function read_rect(string $bytes): array
	{
		$nbits = ord($bytes[0]) >> 3;
		$bit = 5;
		$values = [];
		for ($i = 0; $i < 4; $i++) {
			$value = 0;
			for ($j = 0; $j < $nbits; $j++, $bit++) {
				$value = ($value << 1) | ((ord($bytes[$bit >> 3]) >> (7 - ($bit & 7))) & 1);
			}
			if ($nbits > 0 && ($value & (1 << ($nbits - 1)))) {
				$value -= 1 << $nbits; // sign extend
			}
			$values[] = $value;
		}
		return $values;
	}

	private function open_stream(): void
	{
		if ($this->compression === 'zlib') {
			$this->inflate = inflate_init(ZLIB_ENCODING_DEFLATE);
		} else if ($this->compression === 'lzma') {
			$this->open_xz();
		}
	}

	/**
	 * Starts xz to decode the LZMA body. ZWS stores a 4 byte compressed length and 5 bytes of LZMA properties
	 * after the header; together with an "unknown" uncompressed size they form a .lzma (LZMA-alone) stream.
	 */
	private function open_xz(): void
	{
		$zws_head = fread($this->file, 9);
		if ($zws_head === false || strlen($zws_head) < 9) {
			throw new SwfParserException('truncated header');
		}

		$xz_output = [];
		$xz_status = 1;
		exec('xz --version 2>/dev/null', $xz_output, $xz_status);
		if ($xz_status !== 0) {
			throw new \RuntimeException('xz not installed');
		}

		// NOTE: a temp file instead of a stdin pipe, writing all input before reading output could deadlock
		$tmp_path = tempnam(sys_get_temp_dir(), 'mb_swf_');
		if ($tmp_path === false) {
			throw new \RuntimeException('failed to create temp file for xz');
		}
		$this->xz_input = $tmp_path;
		$input = fopen($this->xz_input, 'wb');
		if ($input === false) {
			throw new \RuntimeException('failed to create temp file for xz');
		}
		$written = fwrite($input, substr($zws_head, 4, 5) . str_repeat("\xFF", 8));
		$copied = stream_copy_to_stream($this->file, $input);
		fclose($input);
		if ($written === false || $copied === false) {
			throw new \RuntimeException('failed to write temp file for xz');
		}

		$cmd = ['xz', '--format=lzma', '--decompress', '--stdout', '--memlimit-decompress=' . self::XZ_MEMLIMIT, '--', $this->xz_input];
		$descriptors = [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['file', '/dev/null', 'w']];
		$process = proc_open($cmd, $descriptors, $pipes);
		if ($process === false) {
			throw new \RuntimeException('failed to start xz');
		}
		$this->xz_process = $process;
		$this->xz_stdout = $pipes[1];
	}

	/**
	 * Returns the next piece of decompressed body data, or '' when the input is exhausted.
	 */
	private function next_chunk(): string
	{
		switch ($this->compression) {
			case 'zlib':
				while (!$this->inflate_done) {
					$input = fread($this->file, self::INFLATE_CHUNK);
					if ($input === false || $input === '') {
						$this->inflate_done = true;
						break;
					}
					$output = @inflate_add($this->inflate, $input, ZLIB_SYNC_FLUSH);
					if ($output === false) {
						$this->inflate_done = true; // corrupt stream, keep what was decoded so far
						break;
					}
					if (inflate_get_status($this->inflate) === ZLIB_STREAM_END) {
						$this->inflate_done = true;
					}
					if ($output !== '') {
						return $output;
					}
				}
				return '';
			case 'lzma':
				$output = fread($this->xz_stdout, self::READ_CHUNK);
				return $output === false ? '' : $output;
			default:
				$output = fread($this->file, self::READ_CHUNK);
				return $output === false ? '' : $output;
		}
	}

	/**
	 * Appends the next chunk to the buffer, never going past the declared uncompressed length.
	 */
	private function pull(): bool
	{
		if ($this->remaining <= 0) return false;

		$chunk = $this->next_chunk();
		if ($chunk === '') {
			$this->remaining = 0;
			return false;
		}
		if (strlen($chunk) > $this->remaining) {
			$chunk = substr($chunk, 0, $this->remaining);
		}
		$this->remaining -= strlen($chunk);

		$this->buffer = $this->buffer_pos < strlen($this->buffer) ? substr($this->buffer, $this->buffer_pos) . $chunk : $chunk;
		$this->buffer_pos = 0;
		return true;
	}

	/**
	 * Reads exactly $n bytes, or returns null if the data ends first.
	 */
	private function read(int $n): ?string
	{
		while (strlen($this->buffer) - $this->buffer_pos < $n) {
			if (!$this->pull()) return null;
		}
		$data = substr($this->buffer, $this->buffer_pos, $n);
		$this->buffer_pos += $n;
		$this->consumed += $n;
		return $data;
	}

	/**
	 * Skips exactly $n bytes, or returns false if the data ends first.
	 */
	private function skip(int $n): bool
	{
		while ($n > 0) {
			$available = strlen($this->buffer) - $this->buffer_pos;
			if ($available === 0) {
				if (!$this->pull()) return false;
				continue;
			}
			$step = min($n, $available);
			$this->buffer_pos += $step;
			$this->consumed += $step;
			$n -= $step;
		}
		return true;
	}

	private function close(): void
	{
		if ($this->xz_stdout !== null) {
			fclose($this->xz_stdout); // xz exits on its next write if it was not done yet
		}
		if ($this->xz_process !== null) {
			$status = proc_get_status($this->xz_process);
			if ($status['running']) {
				proc_terminate($this->xz_process);
			}
			proc_close($this->xz_process);
		}
		if ($this->xz_input !== null && is_file($this->xz_input)) {
			unlink($this->xz_input);
		}
		if ($this->file !== null) {
			fclose($this->file);
		}
	}
}
