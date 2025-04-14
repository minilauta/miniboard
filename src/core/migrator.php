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

		$files = scandir(DB_MIGRATION_DIR, SCANDIR_SORT_ASCENDING);
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
				`filename` text NOT NULL,
				`version` int NOT NULL,
				`script` text NOT NULL,
				PRIMARY KEY(`filename`),
				UNIQUE KEY (`version`)
			) ENGINE=InnoDB
			CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
		');
	}

	public function migrate(): void
	{
		$this->connection->transaction(function(\PDO $pdo) {
			foreach ($this->migrations as $migration) {
				$result = $pdo->exec($migration->get_script());
				echo "applied migration {$migration->get_filename()} successfully";
			}
		});
	}
}
