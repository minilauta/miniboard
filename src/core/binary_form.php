<?php

namespace minichan\core;

use JsonException;
use RuntimeException;
use Throwable;
use UnexpectedValueException;

/**
 * Form submission sent as a single application/octet-stream body instead of multipart/form-data,
 * so PHP's multipart parser never runs. See encodeBinaryForm() in public/js/utils.js.
 *
 * Body layout: a 4-byte big-endian header length, a JSON header
 * {"fields": "<urlencoded fields>", "files": [{"name": ..., "type": ..., "size": ...}, ...]}
 * and the raw bytes of each file in header order.
 *
 * Fields are parsed like $_POST and files are exposed in the same shape as $_FILES entries.
 * Tmp files are owned by this object and deleted when it is destroyed, unless moved elsewhere before that.
 */
class BinaryForm
{
	public const CONTENT_TYPE = 'application/octet-stream';
	public const NO_FILE = ['name' => '', 'type' => '', 'tmp_name' => '', 'error' => UPLOAD_ERR_NO_FILE, 'size' => 0];
	private const MAX_HEADER_SIZE = 1024 * 1024;

	public readonly array $fields;
	public readonly array $files;
	private string $tmp_dir;

	/**
	 * @param resource $stream
	 */
	public function __construct($stream, int $max_files, int $max_file_size)
	{
		$this->tmp_dir = sys_get_temp_dir() . '/minichan.BinaryForm.' . bin2hex(random_bytes(8));

		try {
			$header = $this->read_header($stream);
			if (count($header['files']) > $max_files) {
				throw new UnexpectedValueException('too many files: ' . count($header['files']) . " > {$max_files}");
			}

			parse_str($header['fields'], $fields);
			$files = [];
			foreach ($header['files'] as $idx => $meta) {
				$files[] = $this->read_file($stream, $idx, $meta, $max_file_size);
			}

			if (fread($stream, 1) !== '') {
				throw new UnexpectedValueException('form body has trailing data');
			}
		} catch (Throwable $ex) {
			$this->cleanup();
			throw $ex;
		}

		$this->fields = $fields;
		$this->files = $files;
	}

	public function __destruct()
	{
		$this->cleanup();
	}

	private function read_header($stream): array
	{
		$header_size = unpack('N', $this->read($stream, 4))[1];
		if ($header_size > self::MAX_HEADER_SIZE) {
			throw new UnexpectedValueException("form header too large: {$header_size} bytes");
		}

		try {
			$header = json_decode($this->read($stream, $header_size), true, 4, JSON_THROW_ON_ERROR);
		} catch (JsonException $ex) {
			throw new UnexpectedValueException('form header is not valid JSON', 0, $ex);
		}

		if (!is_string($header['fields'] ?? null) || !is_array($header['files'] ?? null) || !array_is_list($header['files'])) {
			throw new UnexpectedValueException('form header is malformed');
		}

		return $header;
	}

	private function read_file($stream, int $idx, mixed $meta, int $max_file_size): array
	{
		$name = $meta['name'] ?? null;
		$type = $meta['type'] ?? null;
		$size = $meta['size'] ?? null;
		if (!is_string($name) || !is_string($type) || !is_int($size) || $size < 0) {
			throw new UnexpectedValueException("form file #{$idx} is malformed");
		}

		// NOTE: an empty file input has no name, PHP reports it as UPLOAD_ERR_NO_FILE
		if ($name === '') {
			if ($size !== 0) {
				throw new UnexpectedValueException("form file #{$idx} has no name");
			}
			return self::NO_FILE;
		}

		if ($size > $max_file_size) {
			throw new UnexpectedValueException("form file #{$idx} size exceeds limit: {$size} bytes > {$max_file_size} bytes");
		}

		if (!is_dir($this->tmp_dir) && !mkdir($this->tmp_dir, 0700)) {
			throw new RuntimeException("failed to create tmp dir {$this->tmp_dir}");
		}

		$tmp_name = "{$this->tmp_dir}/{$idx}";
		$tmp_handle = fopen($tmp_name, 'xb');
		if ($tmp_handle === false) {
			throw new RuntimeException("failed to create tmp file {$tmp_name}");
		}
		$copied = stream_copy_to_stream($stream, $tmp_handle, $size);
		fclose($tmp_handle);
		if ($copied !== $size) {
			throw new UnexpectedValueException('form body ended unexpectedly');
		}

		return [
			'name' => preg_replace('~^.*[/\\\\]~s', '', $name), // NOTE: strip client paths like PHP does
			'type' => $type,
			'tmp_name' => $tmp_name,
			'error' => UPLOAD_ERR_OK,
			'size' => $size,
		];
	}

	private function read($stream, int $length): string
	{
		$data = stream_get_contents($stream, $length);
		if ($data === false || strlen($data) !== $length) {
			throw new UnexpectedValueException('form body ended unexpectedly');
		}

		return $data;
	}

	private function cleanup(): void
	{
		if (!is_dir($this->tmp_dir)) {
			return;
		}

		foreach (array_diff(scandir($this->tmp_dir), ['.', '..']) as $file) {
			unlink("{$this->tmp_dir}/{$file}");
		}
		rmdir($this->tmp_dir);
	}
}
