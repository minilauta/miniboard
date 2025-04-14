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
