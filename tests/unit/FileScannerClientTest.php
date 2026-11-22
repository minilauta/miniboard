<?php

use PHPUnit\Framework\TestCase;
use minichan\core\integrations\BannedContentType;
use minichan\core\integrations\FileScannerClient;
use minichan\core\integrations\FileScannerException;
use minichan\core\integrations\FileScannerMatch;

require_once __ROOT__ . '/core/integrations/file_scanner.php';

class FileScannerClientTest extends TestCase
{
	private const TEST_FILE = __DIR__ . '/../src/yotsuba.png';

	/**
	 * Creates a client that records requests and returns a canned response instead of calling the service.
	 */
	private function createClient(array $response): FileScannerClient
	{
		return new class('http://file-scanner:8000/', $response) extends FileScannerClient
		{
			public array $requests = [];

			public function __construct(string $base_url, private array $response)
			{
				parent::__construct($base_url);
			}

			protected function post(string $endpoint, array $fields): array
			{
				$this->requests[] = ['endpoint' => $endpoint, 'fields' => $fields];
				return $this->response;
			}
		};
	}

	public function testCheckNoMatch(): void
	{
		$client = $this->createClient(['match' => false]);

		$this->assertNull($client->check(self::TEST_FILE));
	}

	public function testCheckSendsFile(): void
	{
		$client = $this->createClient(['match' => false]);

		$client->check(self::TEST_FILE);

		$this->assertCount(1, $client->requests);
		$this->assertSame('/check', $client->requests[0]['endpoint']);
		$file = $client->requests[0]['fields']['input'];
		$this->assertInstanceOf(\CURLFile::class, $file);
		$this->assertSame(self::TEST_FILE, $file->getFilename());
		$this->assertSame('image/png', $file->getMimeType());
		$this->assertSame('yotsuba.png', $file->getPostFilename());
	}

	public function testCheckMatch(): void
	{
		$client = $this->createClient([
			'match' => true,
			'id' => 42,
			'content_type' => 'spam',
			'algorithm' => 'pdq',
			'sha256' => str_repeat('ab', 32),
			'similarity' => 0.97,
			'type' => 'image',
		]);

		$match = $client->check(self::TEST_FILE);

		$this->assertInstanceOf(FileScannerMatch::class, $match);
		$this->assertSame(42, $match->id);
		$this->assertSame(BannedContentType::Spam, $match->content_type);
		$this->assertSame('pdq', $match->algorithm);
		$this->assertSame(str_repeat('ab', 32), $match->sha256);
		$this->assertSame(0.97, $match->similarity);
	}

	public function testCheckMatchInvalidContentType(): void
	{
		$client = $this->createClient([
			'match' => true,
			'id' => 1,
			'content_type' => 'unknown',
			'algorithm' => 'sha256',
			'sha256' => str_repeat('ab', 32),
			'similarity' => 1.0,
		]);

		$this->expectException(FileScannerException::class);
		$client->check(self::TEST_FILE);
	}

	public function testCheckMatchMissingFields(): void
	{
		$client = $this->createClient(['match' => true, 'content_type' => 'csam']);

		$this->expectException(FileScannerException::class);
		$client->check(self::TEST_FILE);
	}

	public function testCheckMissingFile(): void
	{
		$client = $this->createClient(['match' => false]);

		try {
			$client->check(self::TEST_FILE . '.missing');
			$this->fail('expected FileScannerException');
		} catch (FileScannerException $e) {
			$this->assertCount(0, $client->requests);
		}
	}

	public function testBan(): void
	{
		$client = $this->createClient(['success' => true, 'sha256' => str_repeat('ab', 32), 'hashes' => 2, 'inserted' => 2]);

		$inserted = $client->ban(self::TEST_FILE, BannedContentType::Csam);

		$this->assertSame(2, $inserted);
		$this->assertCount(1, $client->requests);
		$this->assertSame('/ban', $client->requests[0]['endpoint']);
		$this->assertSame('csam', $client->requests[0]['fields']['content_type']);
		$this->assertInstanceOf(\CURLFile::class, $client->requests[0]['fields']['input']);
		$this->assertArrayNotHasKey('comment', $client->requests[0]['fields']);
	}

	public function testBanWithComment(): void
	{
		$client = $this->createClient(['success' => true, 'sha256' => str_repeat('ab', 32), 'hashes' => 1, 'inserted' => 1]);

		$client->ban(self::TEST_FILE, BannedContentType::Spam, 'reposted ad image');

		$this->assertSame('reposted ad image', $client->requests[0]['fields']['comment']);
	}

	public function testBanWithEmptyComment(): void
	{
		$client = $this->createClient(['success' => true, 'sha256' => str_repeat('ab', 32), 'hashes' => 1, 'inserted' => 1]);

		$client->ban(self::TEST_FILE, BannedContentType::Spam, '');

		$this->assertArrayNotHasKey('comment', $client->requests[0]['fields']);
	}

	public function testBanInvalidResponse(): void
	{
		$client = $this->createClient(['detail' => 'unsupported content type']);

		$this->expectException(FileScannerException::class);
		$client->ban(self::TEST_FILE, BannedContentType::Other);
	}

	public function testUnreachableService(): void
	{
		// nothing listens on port 1, the connection is refused immediately
		$client = new FileScannerClient('http://127.0.0.1:1', 2, 1);

		$this->expectException(FileScannerException::class);
		$client->check(self::TEST_FILE);
	}

	public function testContentTypesMatchMigration(): void
	{
		$migration = file_get_contents(__DIR__ . '/../../migrations/0026__file_scanner_hashes.yaml');
		$this->assertMatchesRegularExpression('/enum\(([^)]*)\)/', $migration);
		preg_match('/enum\(([^)]*)\)/', $migration, $matches);
		$enum_values = array_map(fn($v) => trim($v, " '"), explode(',', $matches[1]));

		$this->assertSame($enum_values, array_map(fn($c) => $c->value, BannedContentType::cases()));
	}
}
