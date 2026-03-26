<?php

use PHPUnit\Framework\TestCase;
use minichan\core\FileCache;

require_once __ROOT__ . '/core/cache.php';

class FileCacheTest extends TestCase
{
	private string $cacheId;

	protected function setUp(): void
	{
		$this->cacheId = 'test_' . uniqid();
	}

	protected function tearDown(): void
	{
		$file = sys_get_temp_dir() . '/minichan.FileCache[' . $this->cacheId . '].tmp';
		if (file_exists($file)) {
			unlink($file);
		}
	}

	public function test_set_and_get(): void
	{
		$cache = new FileCache($this->cacheId);
		$cache->set('key', 'value', 60);

		$this->assertSame('value', $cache->get('key'));
	}

	public function test_get_nonexistent_key_returns_null(): void
	{
		$cache = new FileCache($this->cacheId);

		$this->assertNull($cache->get('nonexistent'));
	}

	public function test_set_overwrites_existing(): void
	{
		$cache = new FileCache($this->cacheId);
		$cache->set('key', 'first', 60);
		$cache->set('key', 'second', 60);

		$this->assertSame('second', $cache->get('key'));
	}

	public function test_stores_complex_values(): void
	{
		$cache = new FileCache($this->cacheId);
		$data = ['foo' => 'bar', 'nested' => [1, 2, 3]];
		$cache->set('key', $data, 60);

		$this->assertSame($data, $cache->get('key'));
	}

	public function test_flush_clears_all_entries(): void
	{
		$cache = new FileCache($this->cacheId);
		$cache->set('a', 1, 60);
		$cache->set('b', 2, 60);
		$cache->flush();

		$this->assertNull($cache->get('a'));
		$this->assertNull($cache->get('b'));
	}

	public function test_expired_entry_returns_null(): void
	{
		$cache = new FileCache($this->cacheId);
		$cache->set('key', 'value', 0);

		// TTL of 0 means it expires immediately (time() - time() = 0, 0 <= 0 is true)
		// so we need a negative-like scenario — actually 0 TTL means
		// time() - entry_time <= 0 which is only true at the exact same second
		// Let's just verify the get logic works
		sleep(1);

		$this->assertNull($cache->get('key'));
	}

	public function test_persistence_across_instances(): void
	{
		$cache = new FileCache($this->cacheId);
		$cache->set('key', 'persisted', 60);
		unset($cache); // triggers __destruct, saves to disk

		$cache2 = new FileCache($this->cacheId);
		$this->assertSame('persisted', $cache2->get('key'));
	}

	public function test_multiple_keys(): void
	{
		$cache = new FileCache($this->cacheId);
		$cache->set('a', 'alpha', 60);
		$cache->set('b', 'beta', 60);
		$cache->set('c', 'gamma', 60);

		$this->assertSame('alpha', $cache->get('a'));
		$this->assertSame('beta', $cache->get('b'));
		$this->assertSame('gamma', $cache->get('c'));
	}
}
