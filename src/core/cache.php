<?php

namespace minichan\core;

define('CACHE_FILE_FLUSH_INTERVAL', 3);

interface Cache
{
	public function set(string $key, mixed $val, int $ttl): void;
	public function get(string $key): mixed;
	public function flush(): void;
}

class FileCache implements Cache
{
	private string $filename;
	private array $data;

	public function __construct(string $id)
	{
		$this->filename = sys_get_temp_dir() . '/minichan.FileCache[' . $id . '].tmp';

		if (file_exists($this->filename)) {
			$this->load_from_disk();

			if (time() - $this->data['time'] > CACHE_FILE_FLUSH_INTERVAL) {
				$this->flush();
			}
		} else {
			$this->flush();
		}
	}

	public function __destruct()
	{
		$this->save_to_disk();
	}

	public function set(string $key, mixed $val, int $ttl): void
	{
		$this->data['cache'][$key] = [
			'time' => time(),
			'ttl' => $ttl,
			'val' => $val,
		];
	}

	public function get(string $key): mixed
	{
		if (!isset($this->data['cache'][$key])) {
			return null;
		}

		$entry = &$this->data['cache'][$key];
		if (time() - $entry['time'] <= $entry['ttl']) {
			return $entry['val'];
		} else {
			unset($this->data['cache'][$key]);
		}

		return null;
	}

	public function flush(): void
	{
		$this->data = [
			'time' => time(),
			'cache' => [],
		];
	}

	private function load_from_disk(): void
	{
		$this->data = json_decode(file_get_contents($this->filename), true) ?? [
			'time' => time(),
			'cache' => [],
		];
	}

	private function save_to_disk(): bool
	{
		return file_put_contents($this->filename, json_encode($this->data), LOCK_EX) !== false;
	}
}

class ApcuCache implements Cache
{
	private string $prefix;

	public function __construct(string $id)
	{
		if (!self::is_available()) {
			throw new \RuntimeException('ApcuCache: APCu extension is not loaded or not enabled');
		}

		$this->prefix = 'minichan.ApcuCache[' . $id . '].';
	}

	public static function is_available(): bool
	{
		return function_exists('apcu_enabled') && apcu_enabled();
	}

	public function set(string $key, mixed $val, int $ttl): void
	{
		// APCu treats a TTL of 0 as "never expires", clamp it so a TTL is always an upper bound
		apcu_store($this->prefix . $key, $val, max(1, $ttl));
	}

	public function get(string $key): mixed
	{
		$val = apcu_fetch($this->prefix . $key, $success);

		return $success ? $val : null;
	}

	public function flush(): void
	{
		apcu_delete(new \APCUIterator('/^' . preg_quote($this->prefix, '/') . '/', APC_ITER_KEY));
	}
}
