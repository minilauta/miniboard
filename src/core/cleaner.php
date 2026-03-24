<?php

namespace minichan\core;

class Cleaner
{
	/**
	 * Summary of connection
	 * @var DbConnection
	 */
	private DbConnection $connection;

	public function __construct(DbConnection $connection)
	{
		$this->connection = $connection;
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
		$pdo = $this->connection->get_pdo();
		$pdo->exec('LOCK TABLES posts AS p1 READ, posts AS p2 READ, posts AS p3 READ, posts AS p4 READ');
		try {
			$files_disk = array_slice(scandir(__PUBLIC__ . '/src', SCANDIR_SORT_ASCENDING), 2);
			array_walk($files_disk, function (string &$file, int $idx) { $file = '/src/' . $file; });
			$files_disk_n = count($files_disk);
			$files_db = $pdo
				->query('
					SELECT DISTINCT file AS file FROM posts AS p1
					WHERE LEFT(file, 5) = \'/src/\'
					UNION
					SELECT DISTINCT thumb AS file FROM posts AS p2
					WHERE LEFT(thumb, 5) = \'/src/\'
					UNION
					SELECT DISTINCT audio_album AS file FROM posts AS p3
					WHERE LEFT(audio_album, 5) = \'/src/\'
					UNION
					SELECT DISTINCT JSON_UNQUOTE(JSON_EXTRACT(file_meta, \'$.tgk_png_file\')) AS file FROM posts AS p4
					WHERE file_meta IS NOT NULL AND LEFT(JSON_UNQUOTE(JSON_EXTRACT(file_meta, \'$.tgk_png_file\')), 5) = \'/src/\'
				')
				->fetchAll(\PDO::FETCH_COLUMN);
			$files_db_n = count($files_db);
		} finally {
			$pdo->exec('UNLOCK TABLES');
		}
		printf("cleaner: files on disk: %d, files in database: %d\n", $files_disk_n, $files_db_n);
		if ($files_disk_n === 0) {
			printf("cleaner: no files to clean, exiting early ...\n");
			return;
		}
		if ($files_db_n === 0) {
			printf("cleaner: WARNING — database returned 0 files but %d exist on disk, aborting to prevent mass deletion\n", $files_disk_n);
			return;
		}
		$files_diff = array_diff($files_disk, $files_db);
		$files_diff_n = count($files_diff);
		printf("cleaner: files on disk that are not in database: %d\n", $files_diff_n);
		$cleaned_n = 0;
		foreach ($files_diff as $file) {
			$path = __PUBLIC__ . $file;
			if (!is_file($path)) continue;
			printf("cleaner: cleaning file '%s'...\n", $file);
			if (unlink($path)) $cleaned_n++;
			else printf("cleaner: warning, failed to clean file '%s'!\n", $file);
		}
		printf("cleaner: cleaned files: %d\n", $cleaned_n);
	}
}
