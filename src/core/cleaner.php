<?php

namespace minichan\core;

use Exception;

class Cleaner
{
	/**
	 * Summary of connection
	 * @var DbConnection
	 */
	private DbConnection $connection;

	/**
	 * Summary of files
	 * @var array
	 */
	private array $files;

	public function __construct(DbConnection $connection)
	{
		$this->connection = $connection;
		$this->files = array_slice(scandir(__PUBLIC__ . '/src', SCANDIR_SORT_ASCENDING), 2);
	}

	public function clean_posts(): void
	{
		$this->connection->transaction(function(\PDO $pdo) {
			$cleaned_n = $pdo
				->query('DELETE FROM posts WHERE board_id IS NULL')
				->rowCount();
			printf("cleaner: cleaned posts: %d\n", $cleaned_n);
		});
	}

	public function clean_files(): void
	{
		printf("cleaner: total files: %d\n", count($this->files));
		$cleaned_n = 0;
		foreach ($this->files as $file) {
			$sth = $this->connection
				->get_pdo()
				->prepare('SELECT id FROM posts WHERE file = :file OR thumb = :thumb LIMIT 1');
			$sth->execute(['file' => "/src/$file", 'thumb' => "/src/$file",]);
			$result = $sth->fetch();
			if ($result !== FALSE && empty($result)) {
				printf("cleaner: file '%s' not referenced in db, cleaning...\n", $file);
				if (!unlink(__PUBLIC__ . '/src/' . $file)) {
					printf("cleaner: warning, failed to clean file '%s'!\n", $file);
				}
				$cleaned_n++;
			}
		}
		printf("cleaner: cleaned files: %d\n", $cleaned_n);
	}
}
