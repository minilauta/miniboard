<?php

namespace minichan\core;

use Exception;

require_once __DIR__ . '/db_migration.php';

class Migrator
{
	/**
	 * Summary of connection
	 * @var DbConnection
	 */
	private DbConnection $connection;

	/**
	 * Summary of migrations
	 * @var DbMigration[]
	 */
	private array $migrations;

	public function __construct(DbConnection $connection)
	{
		$this->connection = $connection;

		$files = array_slice(scandir(DB_MIGRATION_DIR, SCANDIR_SORT_ASCENDING), 2);
		if (!$files) {
			throw new Exception('DB_MIGRATION_DIR not found');
		}

		$this->migrations = [];
		foreach ($files as $file) {
			$this->migrations[] = new DbMigration(basename($file));
		}
	}

	public function init(): bool|int
	{
		return $this->connection->get_pdo()->exec('
			CREATE TABLE IF NOT EXISTS migration (
				`filename` varchar(256) NOT NULL,
				`version` int NOT NULL,
				`script` text NOT NULL,
				PRIMARY KEY(`filename`),
				UNIQUE KEY (`version`)
			) ENGINE=InnoDB
			CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
		');
	}

	public function insert(\PDO $pdo, DbMigration $migration): bool
	{
		$sth = $pdo->prepare('
			INSERT INTO migration (
				filename,
				version,
				script
			)
			VALUES (
				:filename,
				:version,
				:script
			)
		');
		return $sth->execute([
			'filename' => $migration->get_filename(),
			'version' => $migration->get_version(),
			'script' => $migration->get_script(),
		]);
	}

	public function migrate(): void
	{
		$sth = $this->connection->get_pdo()
			->query('SELECT filename, version, script FROM migration ORDER BY version DESC LIMIT 1');
		if (empty($current = $sth->fetch())) {
			$current = [
				'version' => -1,
				'filename' => 'NULL',
			];
		}

		printf("migrator: current db version: v%s:%s\n", $current['version'], $current['filename']);
		foreach ($this->migrations as $migration) {
			if ($migration->get_version() <= $current['version']) {
				printf("migrator: skipping migration v%s:%s\n", $migration->get_version(), $migration->get_filename());
				continue;
			}

			$this->connection->transaction(function(\PDO $pdo) use ($migration) {
				$pdo->exec($migration->get_script());
				$this->insert($pdo, $migration);
				printf("migrator: migrated db to: v%s:%s\n", $migration->get_version(), $migration->get_filename());
			});
		}
	}
}
