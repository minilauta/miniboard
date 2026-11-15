<?php

use PHPUnit\Framework\TestCase;
use minichan\core\ApcuCache;

require_once __ROOT__ . '/core/cache.php';

class ApcuCacheTest extends TestCase
{
	private string $cacheId;

	protected function setUp(): void
	{
		if (!ApcuCache::is_available()) {
			$this->markTestSkipped('APCu is not loaded or not enabled (apc.enable_cli=1 is required for the CLI)');
		}

		$this->cacheId = 'test_' . uniqid();
	}

	protected function tearDown(): void
	{
		if (ApcuCache::is_available()) {
			(new ApcuCache($this->cacheId))->flush();
			(new ApcuCache($this->cacheId . '_other'))->flush();
		}
	}

	public function test_set_and_get(): void
	{
		$cache = new ApcuCache($this->cacheId);
		$cache->set('key', 'value', 60);

		$this->assertSame('value', $cache->get('key'));
	}

	public function test_get_nonexistent_key_returns_null(): void
	{
		$cache = new ApcuCache($this->cacheId);

		$this->assertNull($cache->get('nonexistent'));
	}

	public function test_set_overwrites_existing(): void
	{
		$cache = new ApcuCache($this->cacheId);
		$cache->set('key', 'first', 60);
		$cache->set('key', 'second', 60);

		$this->assertSame('second', $cache->get('key'));
	}

	public function test_stores_complex_values(): void
	{
		$cache = new ApcuCache($this->cacheId);
		$data = ['foo' => 'bar', 'nested' => [1, 2, 3]];
		$cache->set('key', $data, 60);

		$this->assertSame($data, $cache->get('key'));
	}

	public function test_stores_false_distinct_from_miss(): void
	{
		$cache = new ApcuCache($this->cacheId);
		$cache->set('key', false, 60);

		$this->assertFalse($cache->get('key'));
	}

	public function test_flush_clears_all_entries(): void
	{
		$cache = new ApcuCache($this->cacheId);
		$cache->set('a', 1, 60);
		$cache->set('b', 2, 60);
		$cache->flush();

		$this->assertNull($cache->get('a'));
		$this->assertNull($cache->get('b'));
	}

	public function test_flush_does_not_clear_other_caches(): void
	{
		$cache = new ApcuCache($this->cacheId);
		$other = new ApcuCache($this->cacheId . '_other');
		$cache->set('key', 'mine', 60);
		$other->set('key', 'theirs', 60);
		$cache->flush();

		$this->assertNull($cache->get('key'));
		$this->assertSame('theirs', $other->get('key'));
	}

	public function test_caches_with_different_ids_are_isolated(): void
	{
		$cache = new ApcuCache($this->cacheId);
		$other = new ApcuCache($this->cacheId . '_other');
		$cache->set('key', 'mine', 60);

		$this->assertNull($other->get('key'));
	}

	public function test_shared_across_instances(): void
	{
		$cache = new ApcuCache($this->cacheId);
		$cache->set('key', 'shared', 60);

		$cache2 = new ApcuCache($this->cacheId);
		$this->assertSame('shared', $cache2->get('key'));
	}

	public function test_expired_entries_return_null(): void
	{
		if (ini_get('apc.use_request_time')) {
			$this->markTestSkipped('apc.use_request_time=1 freezes the TTL clock for the duration of the request');
		}

		$cache = new ApcuCache($this->cacheId);
		$cache->set('ttl_1', 'value', 1);
		$cache->set('ttl_0', 'value', 0); // clamped to 1, APCu would otherwise never expire it

		// APCu expires an entry once more than ttl seconds have passed since it was stored
		sleep(2);

		$this->assertNull($cache->get('ttl_1'));
		$this->assertNull($cache->get('ttl_0'));
	}
}
