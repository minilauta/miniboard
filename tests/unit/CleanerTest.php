<?php

use PHPUnit\Framework\TestCase;
use minichan\core\Cleaner;
use minichan\core\DbConnection;

require_once __ROOT__ . '/core/db_connection.php';
require_once __ROOT__ . '/core/cleaner.php';

class CleanerTest extends TestCase
{
	private string $srcDir;
	private string $trashDir;

	protected function setUp(): void
	{
		$this->srcDir = __PUBLIC__ . '/src';
		$this->trashDir = $this->srcDir . '/.trashbin';

		if (!is_dir($this->srcDir)) {
			mkdir($this->srcDir, 0755, true);
		}
		if (is_dir($this->trashDir)) {
			$this->removeDir($this->trashDir);
		}
	}

	protected function tearDown(): void
	{
		if (is_dir($this->trashDir)) {
			$this->removeDir($this->trashDir);
		}
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

	private function createMockConnection(): DbConnection
	{
		return $this->createMock(DbConnection::class);
	}

	private function createMockPdo(array $dbFiles = []): PDO
	{
		$stmt = $this->createMock(PDOStatement::class);
		$stmt->method('fetchAll')->willReturn($dbFiles);

		$pdo = $this->createMock(PDO::class);
		$pdo->method('exec')->willReturn(0);
		$pdo->method('query')->willReturn($stmt);

		return $pdo;
	}

	// --- clean_posts tests ---

	public function test_clean_posts_executes_delete_query(): void
	{
		$stmt = $this->createMock(PDOStatement::class);
		$stmt->method('rowCount')->willReturn(5);

		$pdo = $this->createMock(PDO::class);
		$pdo->expects($this->once())
			->method('query')
			->with('DELETE FROM posts WHERE board_id IS NULL')
			->willReturn($stmt);

		$connection = $this->createMockConnection();
		$connection->expects($this->once())
			->method('transaction')
			->willReturnCallback(function ($callback) use ($pdo) {
				$callback($pdo);
			});

		$cleaner = new Cleaner($connection);

		$this->expectOutputString("cleaner: cleaned posts: 5\n");
		$cleaner->clean_posts();
	}

	public function test_clean_posts_zero_deleted(): void
	{
		$stmt = $this->createMock(PDOStatement::class);
		$stmt->method('rowCount')->willReturn(0);

		$pdo = $this->createMock(PDO::class);
		$pdo->method('query')->willReturn($stmt);

		$connection = $this->createMockConnection();
		$connection->method('transaction')
			->willReturnCallback(function ($callback) use ($pdo) {
				$callback($pdo);
			});

		$cleaner = new Cleaner($connection);

		$this->expectOutputString("cleaner: cleaned posts: 0\n");
		$cleaner->clean_posts();
	}

	// --- clean_files tests ---

	public function test_clean_files_creates_trashbin_dir(): void
	{
		$pdo = $this->createMockPdo();
		$connection = $this->createMockConnection();
		$connection->method('get_pdo')->willReturn($pdo);

		$cleaner = new Cleaner($connection);

		ob_start();
		$cleaner->clean_files();
		ob_end_clean();

		$this->assertDirectoryExists($this->trashDir);
	}

	public function test_clean_files_no_files_on_disk_exits_early(): void
	{
		$pdo = $this->createMockPdo();
		$connection = $this->createMockConnection();
		$connection->method('get_pdo')->willReturn($pdo);

		$cleaner = new Cleaner($connection);

		$this->expectOutputRegex('/no files to clean/');
		$cleaner->clean_files();
	}

	public function test_clean_files_db_returns_zero_aborts(): void
	{
		file_put_contents($this->srcDir . '/orphan.jpg', 'data');

		$pdo = $this->createMockPdo([]); // DB returns no files
		$connection = $this->createMockConnection();
		$connection->method('get_pdo')->willReturn($pdo);

		$cleaner = new Cleaner($connection);

		$this->expectOutputRegex('/WARNING.*aborting to prevent mass deletion/');
		$cleaner->clean_files();

		// File should NOT be moved
		$this->assertFileExists($this->srcDir . '/orphan.jpg');
	}

	public function test_clean_files_moves_orphan_to_trashbin(): void
	{
		file_put_contents($this->srcDir . '/orphan.jpg', 'orphan');
		file_put_contents($this->srcDir . '/keep.png', 'keep');

		// DB knows about keep.png but not orphan.jpg
		$pdo = $this->createMockPdo(['/src/keep.png']);
		$connection = $this->createMockConnection();
		$connection->method('get_pdo')->willReturn($pdo);

		$cleaner = new Cleaner($connection);

		ob_start();
		$cleaner->clean_files();
		ob_end_clean();

		$this->assertFileDoesNotExist($this->srcDir . '/orphan.jpg');
		$this->assertFileExists($this->trashDir . '/orphan.jpg');
		$this->assertFileExists($this->srcDir . '/keep.png');
	}

	public function test_clean_files_all_files_in_db_nothing_cleaned(): void
	{
		file_put_contents($this->srcDir . '/a.jpg', 'data');
		file_put_contents($this->srcDir . '/b.png', 'data');

		$pdo = $this->createMockPdo(['/src/a.jpg', '/src/b.png']);
		$connection = $this->createMockConnection();
		$connection->method('get_pdo')->willReturn($pdo);

		$cleaner = new Cleaner($connection);

		$this->expectOutputRegex('/cleaned files: 0/');
		$cleaner->clean_files();

		$this->assertFileExists($this->srcDir . '/a.jpg');
		$this->assertFileExists($this->srcDir . '/b.png');
	}

	public function test_clean_files_multiple_orphans(): void
	{
		file_put_contents($this->srcDir . '/keep.jpg', 'data');
		file_put_contents($this->srcDir . '/orphan1.png', 'data');
		file_put_contents($this->srcDir . '/orphan2.gif', 'data');

		$pdo = $this->createMockPdo(['/src/keep.jpg']);
		$connection = $this->createMockConnection();
		$connection->method('get_pdo')->willReturn($pdo);

		$cleaner = new Cleaner($connection);

		$this->expectOutputRegex('/cleaned files: 2/');
		$cleaner->clean_files();

		$this->assertFileExists($this->srcDir . '/keep.jpg');
		$this->assertFileExists($this->trashDir . '/orphan1.png');
		$this->assertFileExists($this->trashDir . '/orphan2.gif');
	}

	public function test_clean_files_locks_and_unlocks_tables(): void
	{
		$pdo = $this->createMockPdo();
		$pdo->expects($this->exactly(2))
			->method('exec')
			->withConsecutive(
				[$this->stringContains('LOCK TABLES')],
				[$this->stringContains('UNLOCK TABLES')]
			);

		$connection = $this->createMockConnection();
		$connection->method('get_pdo')->willReturn($pdo);

		$cleaner = new Cleaner($connection);

		ob_start();
		$cleaner->clean_files();
		ob_end_clean();
	}

	public function test_clean_files_trashbin_excluded_from_scan(): void
	{
		mkdir($this->trashDir, 0755);
		file_put_contents($this->srcDir . '/file.jpg', 'data');

		$pdo = $this->createMockPdo(['/src/file.jpg']);
		$connection = $this->createMockConnection();
		$connection->method('get_pdo')->willReturn($pdo);

		$cleaner = new Cleaner($connection);

		// .trashbin should not be counted as a disk file
		$this->expectOutputRegex('/files on disk: 1/');
		$cleaner->clean_files();
	}
}
