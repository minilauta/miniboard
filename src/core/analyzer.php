<?php

namespace minichan\core;

class Analyzer
{
	/**
	 * Summary of connection
	 * @var DbConnection
	 */
	private DbConnection $connection;

	/** Maximum file size to analyze (64 MB) */
	private const MAX_FILE_SIZE = 64 * 1024 * 1024;

	/** Known file signatures that may indicate hidden/embedded content */
	private const EMBEDDED_SIGNATURES = [
		'ZIP'  => "\x50\x4B\x03\x04",
		'RAR'  => "\x52\x61\x72\x21\x1A\x07",
		'7z'   => "\x37\x7A\xBC\xAF\x27\x1C",
		'PDF'  => "\x25\x50\x44\x46",
		'ELF'  => "\x7F\x45\x4C\x46",
		'GZIP' => "\x1F\x8B\x08",
	];

	/** Threshold for flagging large PNG text chunks (bytes) */
	private const PNG_TEXT_CHUNK_THRESHOLD = 1024;

	/** Standard PNG chunk types (critical + registered ancillary + APNG + known tool chunks) */
	private const STANDARD_PNG_CHUNKS = [
		'IHDR', 'PLTE', 'IDAT', 'IEND',
		'cHRM', 'gAMA', 'iCCP', 'sBIT', 'sRGB',
		'bKGD', 'hIST', 'tRNS', 'pHYs', 'sPLT',
		'tIME', 'iTXt', 'tEXt', 'zTXt', 'eXIf',
		'acTL', 'fcTL', 'fdAT',
		'orNT', // ImageMagick orientation
	];

	public function __construct(DbConnection $connection)
	{
		$this->connection = $connection;
	}

	/**
	 * Scan all user-submitted image files under public/src/ for hidden data.
	 * Detects trailing data appended after the image end marker and checks
	 * for known file signatures (ZIP, RAR, 7z, PDF, ELF, GZIP) in the trailing data.
	 */
	public function analyze_files(): void
	{
		$src_dir = __PUBLIC__ . '/src';
		if (!is_dir($src_dir)) {
			printf("analyzer: directory '%s' does not exist\n", $src_dir);
			return;
		}

		$entries = scandir($src_dir, SCANDIR_SORT_ASCENDING);
		if ($entries === false) {
			printf("analyzer: failed to scan directory '%s'\n", $src_dir);
			return;
		}

		$files = array_filter(array_slice($entries, 2), function (string $file) use ($src_dir) {
			return $file !== '.trashbin' && $file !== 'bans' && is_file($src_dir . '/' . $file);
		});

		$files_n = count($files);
		printf("analyzer: found %d files in public/src/\n", $files_n);

		if ($files_n === 0) {
			printf("analyzer: no files to analyze\n");
			return;
		}

		$analyzed = 0;
		$skipped = 0;
		$flagged = 0;

		foreach ($files as $file) {
			$path = $src_dir . '/' . $file;
			$size = filesize($path);

			if ($size === false || $size === 0) {
				$skipped++;
				continue;
			}

			if ($size > self::MAX_FILE_SIZE) {
				printf("analyzer: skipping '%s' (size %d exceeds limit)\n", $file, $size);
				$skipped++;
				continue;
			}

			$findings = $this->analyze_image($path);

			if ($findings === null) {
				$skipped++;
				continue;
			}

			$analyzed++;

			if (!empty($findings)) {
				$flagged++;
				printf("analyzer: FLAGGED '/src/%s':\n", $file);
				foreach ($findings as $finding) {
					printf("analyzer:   - %s\n", $finding);
				}
			}
		}

		printf("analyzer: analyzed %d images, skipped %d non-image files, flagged %d\n", $analyzed, $skipped, $flagged);
	}

	/**
	 * Analyze a single image file for hidden data.
	 * Returns null if the file is not a recognized image format.
	 * Returns an array of findings (empty array if clean).
	 */
	private function analyze_image(string $path): ?array
	{
		$data = file_get_contents($path);
		if ($data === false || strlen($data) < 8) return null;

		$format = $this->detect_format($data);
		if ($format === null) return null;

		$findings = [];
		$file_size = strlen($data);
		$image_end = null;

		if ($format === 'PNG') {
			$png_result = $this->analyze_png_chunks($data, $file_size);
			$findings = array_merge($findings, $png_result['findings']);
			$image_end = $png_result['end_pos'];
		} else {
			$image_end = $this->find_image_end($data, $format);
		}

		if ($image_end !== null && $image_end < $file_size) {
			$trailing_size = $file_size - $image_end;
			$findings[] = sprintf(
				'trailing data after %s end marker: %d bytes at offset 0x%X',
				$format, $trailing_size, $image_end
			);

			$trailing_data = substr($data, $image_end);
			foreach (self::EMBEDDED_SIGNATURES as $name => $sig) {
				$pos = strpos($trailing_data, $sig);
				if ($pos !== false) {
					$findings[] = sprintf(
						'embedded %s signature in trailing data at offset 0x%X',
						$name, $image_end + $pos
					);
				}
			}
		}

		return $findings;
	}

	/**
	 * Detect image format from magic bytes.
	 */
	private function detect_format(string $data): ?string
	{
		if (str_starts_with($data, "\x89\x50\x4E\x47\x0D\x0A\x1A\x0A")) return 'PNG';
		if (str_starts_with($data, "\xFF\xD8\xFF"))                       return 'JPEG';
		if (str_starts_with($data, "GIF87a") || str_starts_with($data, "GIF89a")) return 'GIF';
		if (str_starts_with($data, "\x42\x4D"))                           return 'BMP';
		if (str_starts_with($data, "RIFF") && substr($data, 8, 4) === 'WEBP') return 'WEBP';
		return null;
	}

	/**
	 * Parse PNG chunk structure and check for suspicious ancillary chunks.
	 * Returns an array with 'findings' (array of strings) and 'end_pos' (int|null).
	 * Detects non-standard chunk types and oversized text chunks (tEXt, zTXt, iTXt).
	 */
	private function analyze_png_chunks(string $data, int $size): array
	{
		$findings = [];
		$end_pos = null;
		$pos = 8; // skip PNG signature

		while ($pos + 12 <= $size) {
			$chunk_length = unpack('Nlength', $data, $pos)['length'];
			$chunk_type = substr($data, $pos + 4, 4);
			$chunk_end = $pos + 12 + $chunk_length; // length field + type + data + CRC

			if ($chunk_end > $size) break;

			if (!in_array($chunk_type, self::STANDARD_PNG_CHUNKS, true)) {
				$safe_type = preg_replace('/[^\x20-\x7E]/', '?', $chunk_type);
				$findings[] = sprintf(
					'non-standard PNG chunk "%s" (%d bytes) at offset 0x%X',
					$safe_type, $chunk_length, $pos
				);
			}

			if (in_array($chunk_type, ['tEXt', 'zTXt', 'iTXt'], true)
				&& $chunk_length > self::PNG_TEXT_CHUNK_THRESHOLD
			) {
				$findings[] = sprintf(
					'large PNG text chunk "%s" (%d bytes) at offset 0x%X',
					$chunk_type, $chunk_length, $pos
				);
			}

			if ($chunk_type === 'IEND') {
				$end_pos = $chunk_end;
				break;
			}

			$pos = $chunk_end;
		}

		return ['findings' => $findings, 'end_pos' => $end_pos];
	}

	/**
	 * Find the logical end position of image data.
	 * Returns the byte offset immediately after the image end marker,
	 * or null if the end could not be determined.
	 */
	private function find_image_end(string $data, string $format): ?int
	{
		$size = strlen($data);

		switch ($format) {
			case 'JPEG':
				return $this->find_jpeg_end($data, $size);
			case 'GIF':
				return $this->find_gif_end($data, $size);
			case 'BMP':
				return $this->find_bmp_end($data, $size);
			case 'WEBP':
				return $this->find_webp_end($data, $size);
		}

		return null;
	}

	/**
	 * Find end of JPEG data.
	 * Uses strrpos to find the last EOI marker (FF D9), which skips
	 * any embedded JPEG thumbnails in EXIF data.
	 */
	private function find_jpeg_end(string $data, int $size): ?int
	{
		$pos = strrpos($data, "\xFF\xD9");
		return $pos !== false ? $pos + 2 : null;
	}

	/**
	 * Find end of GIF data by parsing the block structure.
	 * Walks through screen descriptor, color tables, extension blocks,
	 * and image descriptors until it finds the 0x3B trailer byte.
	 */
	private function find_gif_end(string $data, int $size): ?int
	{
		if ($size < 13) return null;

		// Logical Screen Descriptor at offset 6, packed field at offset 10
		$packed = ord($data[10]);
		$has_gct = ($packed >> 7) & 1;
		$gct_size = $packed & 0x07;
		$pos = 13;

		// Global Color Table
		if ($has_gct) {
			$pos += 3 * (1 << ($gct_size + 1));
		}

		while ($pos < $size) {
			$block_type = ord($data[$pos]);
			$pos++;

			if ($block_type === 0x3B) {
				return $pos;
			}

			if ($block_type === 0x2C) {
				// Image Descriptor (9 bytes after the introducer)
				if ($pos + 9 > $size) return null;
				$img_packed = ord($data[$pos + 8]);
				$has_lct = ($img_packed >> 7) & 1;
				$lct_size = $img_packed & 0x07;
				$pos += 9;

				if ($has_lct) {
					$pos += 3 * (1 << ($lct_size + 1));
				}

				// LZW minimum code size
				if ($pos >= $size) return null;
				$pos++;

				// Sub-blocks
				$pos = $this->skip_gif_sub_blocks($data, $pos, $size);
				if ($pos === null) return null;
			} else if ($block_type === 0x21) {
				// Extension block: skip label byte, then sub-blocks
				if ($pos >= $size) return null;
				$pos++; // extension label

				$pos = $this->skip_gif_sub_blocks($data, $pos, $size);
				if ($pos === null) return null;
			} else {
				// Unknown block type, cannot reliably parse further
				return null;
			}
		}

		return null;
	}

	/**
	 * Skip GIF sub-blocks (sequences of length-prefixed blocks terminated by a zero-length block).
	 * Returns the position after the terminating zero byte, or null on error.
	 */
	private function skip_gif_sub_blocks(string $data, int $pos, int $size): ?int
	{
		while ($pos < $size) {
			$block_size = ord($data[$pos]);
			$pos++;
			if ($block_size === 0) return $pos;
			$pos += $block_size;
		}
		return null;
	}

	/**
	 * Find end of BMP data.
	 * BMP declares its file size in the header at offset 2 (4 bytes, little-endian).
	 */
	private function find_bmp_end(string $data, int $size): ?int
	{
		if ($size < 14) return null;
		$declared = unpack('Vsize', $data, 2)['size'];
		return $declared > 0 ? $declared : null;
	}

	/**
	 * Find end of WebP data.
	 * RIFF container: 4-byte "RIFF" + 4-byte payload size.
	 * Total file size = payload size + 8 bytes for the RIFF header.
	 */
	private function find_webp_end(string $data, int $size): ?int
	{
		if ($size < 12) return null;
		$payload_size = unpack('Vsize', $data, 4)['size'];
		return $payload_size > 0 ? $payload_size + 8 : null;
	}
}
