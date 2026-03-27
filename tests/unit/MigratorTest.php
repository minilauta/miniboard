<?php

use PHPUnit\Framework\TestCase;
use minichan\core\Migrator;
use minichan\core\DbConnection;
use minichan\core\DbMigration;

if (!defined('DB_MIGRATION_DIR')) {
	define('DB_MIGRATION_DIR', sys_get_temp_dir() . '/miniboard_test_migrations');
}

require_once __ROOT__ . '/core/migrator.php';

class MigratorTest extends TestCase
{
	private string $migrationDir;

	protected function setUp(): void
	{
		$this->migrationDir = DB_MIGRATION_DIR;

		if (is_dir($this->migrationDir)) {
			$this->removeDir($this->migrationDir);
		}
		mkdir($this->migrationDir, 0755, true);
	}

	protected function tearDown(): void
	{
		if (is_dir($this->migrationDir)) {
			$this->removeDir($this->migrationDir);
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

	private function writeMigration(string $filename, int $version, string $script): void
	{
		file_put_contents(
			$this->migrationDir . '/' . $filename,
			"version: $version\nscript: |\n  $script\n"
		);
	}

	private function createMockConnection(): DbConnection
	{
		return $this->createMock(DbConnection::class);
	}

	private function createMockPdo(array $currentMigration = []): PDO
	{
		$fetchStmt = $this->createMock(PDOStatement::class);
		$fetchStmt->method('fetch')->willReturn($currentMigration ?: false);

		$insertStmt = $this->createMock(PDOStatement::class);
		$insertStmt->method('execute')->willReturn(true);

		$pdo = $this->createMock(PDO::class);
		$pdo->method('query')->willReturn($fetchStmt);
		$pdo->method('exec')->willReturn(0);
		$pdo->method('prepare')->willReturn($insertStmt);

		return $pdo;
	}

	// --- Constructor ---

	public function test_constructor_throws_on_unreadable_dir(): void
	{
		$this->removeDir($this->migrationDir);

		try {
			new Migrator($this->createMockConnection());
			$this->fail('Expected an error when migration dir is missing');
		} catch (\Throwable $e) {
			$this->assertStringContainsString('scandir', $e->getMessage());
		}
	}

	public function test_constructor_with_empty_dir(): void
	{
		$pdo = $this->createMockPdo();
		$connection = $this->createMockConnection();
		$connection->method('get_pdo')->willReturn($pdo);

		$migrator = new Migrator($connection);

		$this->expectOutputString("migrator: current db version: v-1:NULL\n");
		$migrator->migrate();
	}

	public function test_constructor_filters_non_yaml_files(): void
	{
		$this->writeMigration('0001__test.yaml', 1, 'SELECT 1;');
		file_put_contents($this->migrationDir . '/notes.txt', 'not yaml');
		file_put_contents($this->migrationDir . '/script.sql', 'SELECT 1;');

		$pdo = $this->createMockPdo();
		$connection = $this->createMockConnection();
		$connection->method('get_pdo')->willReturn($pdo);
		$connection->method('transaction')->willReturnCallback(fn($cb) => $cb($pdo));

		$migrator = new Migrator($connection);

		ob_start();
		$migrator->migrate();
		$output = ob_get_clean();

		$this->assertSame(1, substr_count($output, 'migrated db to:'));
	}

	public function test_constructor_accepts_yml_extension(): void
	{
		$this->writeMigration('0001__test.yml', 1, 'SELECT 1;');

		$pdo = $this->createMockPdo();
		$connection = $this->createMockConnection();
		$connection->method('get_pdo')->willReturn($pdo);
		$connection->method('transaction')->willReturnCallback(fn($cb) => $cb($pdo));

		$migrator = new Migrator($connection);

		$this->expectOutputRegex('/migrated db to: v1:0001__test\.yml/');
		$migrator->migrate();
	}

	public function test_constructor_sorts_files_ascending(): void
	{
		$this->writeMigration('0002__second.yaml', 2, 'SELECT 2;');
		$this->writeMigration('0001__first.yaml', 1, 'SELECT 1;');

		$pdo = $this->createMockPdo();
		$connection = $this->createMockConnection();
		$connection->method('get_pdo')->willReturn($pdo);
		$connection->method('transaction')->willReturnCallback(fn($cb) => $cb($pdo));

		$migrator = new Migrator($connection);

		ob_start();
		$migrator->migrate();
		$output = ob_get_clean();

		$pos1 = strpos($output, 'v1:0001__first.yaml');
		$pos2 = strpos($output, 'v2:0002__second.yaml');
		$this->assertNotFalse($pos1);
		$this->assertNotFalse($pos2);
		$this->assertLessThan($pos2, $pos1);
	}

	// --- init() ---

	public function test_init_creates_migration_table(): void
	{
		$pdo = $this->createMock(PDO::class);
		$pdo->expects($this->once())
			->method('exec')
			->with($this->stringContains('CREATE TABLE IF NOT EXISTS migration'))
			->willReturn(0);

		$connection = $this->createMockConnection();
		$connection->method('get_pdo')->willReturn($pdo);

		$migrator = new Migrator($connection);
		$this->assertSame(0, $migrator->init());
	}

	public function test_init_returns_exec_result(): void
	{
		$pdo = $this->createMock(PDO::class);
		$pdo->method('exec')->willReturn(false);

		$connection = $this->createMockConnection();
		$connection->method('get_pdo')->willReturn($pdo);

		$migrator = new Migrator($connection);
		$this->assertFalse($migrator->init());
	}

	// --- insert() ---

	public function test_insert_prepares_insert_statement(): void
	{
		$this->writeMigration('0001__test.yaml', 1, 'CREATE TABLE t (id int);');
		$migration = new DbMigration('0001__test.yaml');

		$stmt = $this->createMock(PDOStatement::class);
		$stmt->expects($this->once())
			->method('execute')
			->with([
				'filename' => '0001__test.yaml',
				'version' => 1,
				'script' => "CREATE TABLE t (id int);\n",
			])
			->willReturn(true);

		$pdo = $this->createMock(PDO::class);
		$pdo->expects($this->once())
			->method('prepare')
			->with($this->stringContains('INSERT INTO migration'))
			->willReturn($stmt);

		$connection = $this->createMockConnection();
		$migrator = new Migrator($connection);

		$this->assertTrue($migrator->insert($pdo, $migration));
	}

	public function test_insert_returns_false_on_failure(): void
	{
		$this->writeMigration('0001__test.yaml', 1, 'SELECT 1;');
		$migration = new DbMigration('0001__test.yaml');

		$stmt = $this->createMock(PDOStatement::class);
		$stmt->method('execute')->willReturn(false);

		$pdo = $this->createMock(PDO::class);
		$pdo->method('prepare')->willReturn($stmt);

		$connection = $this->createMockConnection();
		$migrator = new Migrator($connection);

		$this->assertFalse($migrator->insert($pdo, $migration));
	}

	// --- migrate() ---

	public function test_migrate_applies_all_from_empty_db(): void
	{
		$this->writeMigration('0001__first.yaml', 1, 'SELECT 1;');
		$this->writeMigration('0002__second.yaml', 2, 'SELECT 2;');

		$pdo = $this->createMockPdo();
		$connection = $this->createMockConnection();
		$connection->method('get_pdo')->willReturn($pdo);
		$connection->method('transaction')->willReturnCallback(fn($cb) => $cb($pdo));

		$migrator = new Migrator($connection);

		ob_start();
		$migrator->migrate();
		$output = ob_get_clean();

		$this->assertStringContainsString('current db version: v-1:NULL', $output);
		$this->assertStringContainsString('migrated db to: v1:0001__first.yaml', $output);
		$this->assertStringContainsString('migrated db to: v2:0002__second.yaml', $output);
	}

	public function test_migrate_skips_already_applied(): void
	{
		$this->writeMigration('0001__first.yaml', 1, 'SELECT 1;');
		$this->writeMigration('0002__second.yaml', 2, 'SELECT 2;');

		$pdo = $this->createMockPdo(['version' => 1, 'filename' => '0001__first.yaml']);
		$connection = $this->createMockConnection();
		$connection->method('get_pdo')->willReturn($pdo);
		$connection->method('transaction')->willReturnCallback(fn($cb) => $cb($pdo));

		$migrator = new Migrator($connection);

		ob_start();
		$migrator->migrate();
		$output = ob_get_clean();

		$this->assertStringContainsString('skipping migration v1:0001__first.yaml', $output);
		$this->assertStringContainsString('migrated db to: v2:0002__second.yaml', $output);
	}

	public function test_migrate_skips_all_when_up_to_date(): void
	{
		$this->writeMigration('0001__first.yaml', 1, 'SELECT 1;');
		$this->writeMigration('0002__second.yaml', 2, 'SELECT 2;');

		$pdo = $this->createMockPdo(['version' => 2, 'filename' => '0002__second.yaml']);
		$connection = $this->createMockConnection();
		$connection->method('get_pdo')->willReturn($pdo);

		$migrator = new Migrator($connection);

		ob_start();
		$migrator->migrate();
		$output = ob_get_clean();

		$this->assertSame(2, substr_count($output, 'skipping migration'));
		$this->assertStringNotContainsString('migrated db to:', $output);
	}

	public function test_migrate_executes_script_via_pdo_exec(): void
	{
		$this->writeMigration('0001__test.yaml', 1, 'CREATE TABLE foo (id int);');

		$fetchStmt = $this->createMock(PDOStatement::class);
		$fetchStmt->method('fetch')->willReturn(false);

		$insertStmt = $this->createMock(PDOStatement::class);
		$insertStmt->method('execute')->willReturn(true);

		$pdo = $this->createMock(PDO::class);
		$pdo->method('query')->willReturn($fetchStmt);
		$pdo->expects($this->once())
			->method('exec')
			->with($this->stringContains('CREATE TABLE foo'))
			->willReturn(0);
		$pdo->method('prepare')->willReturn($insertStmt);

		$connection = $this->createMockConnection();
		$connection->method('get_pdo')->willReturn($pdo);
		$connection->method('transaction')->willReturnCallback(fn($cb) => $cb($pdo));

		$migrator = new Migrator($connection);

		ob_start();
		$migrator->migrate();
		ob_end_clean();
	}

	public function test_migrate_wraps_insert_in_transaction(): void
	{
		$this->writeMigration('0001__test.yaml', 1, 'SELECT 1;');

		$pdo = $this->createMockPdo();
		$connection = $this->createMockConnection();
		$connection->method('get_pdo')->willReturn($pdo);
		$connection->expects($this->once())
			->method('transaction')
			->willReturnCallback(fn($cb) => $cb($pdo));

		$migrator = new Migrator($connection);

		ob_start();
		$migrator->migrate();
		ob_end_clean();
	}

	public function test_migrate_inserts_record_per_applied_migration(): void
	{
		$this->writeMigration('0001__first.yaml', 1, 'SELECT 1;');
		$this->writeMigration('0002__second.yaml', 2, 'SELECT 2;');

		$fetchStmt = $this->createMock(PDOStatement::class);
		$fetchStmt->method('fetch')->willReturn(false);

		$insertStmt = $this->createMock(PDOStatement::class);
		$insertStmt->method('execute')->willReturn(true);

		$pdo = $this->createMock(PDO::class);
		$pdo->method('query')->willReturn($fetchStmt);
		$pdo->method('exec')->willReturn(0);
		$pdo->expects($this->exactly(2))
			->method('prepare')
			->with($this->stringContains('INSERT INTO migration'))
			->willReturn($insertStmt);

		$connection = $this->createMockConnection();
		$connection->method('get_pdo')->willReturn($pdo);
		$connection->method('transaction')->willReturnCallback(fn($cb) => $cb($pdo));

		$migrator = new Migrator($connection);

		ob_start();
		$migrator->migrate();
		ob_end_clean();
	}
}
