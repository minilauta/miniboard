<?php

namespace minichan\modules;

use Closure;
use minichan\core;

require_once __ROOT__ . '/core/module.php';
require_once __ROOT__ . '/core/renderer.php';
require_once __ROOT__ . '/core/cache.php';
require_once __ROOT__ . '/core/db_connection.php';
require_once __ROOT__ . '/common/config.php';

class HomeModule implements core\Module
{
	private const CACHE_ID = 'home';
	private const SITE_STATS_CACHE_TTL = 60;

	private core\HtmlRenderer $renderer;

	public function __construct()
	{
		$this->renderer = new core\HtmlRenderer();
	}

	public function __destruct() {}

	public function init(core\App &$app): void
	{
		$this->renderer->set_var('app', $app);
	}

	public function register_middleware(core\Router &$router): void {}

	public function register_routes(core\Router &$router): void
	{
		$router->add_route(HTTP_GET, '/', function ($vars) {
			echo $this->renderer->render(__DIR__ . '/templates/root.phtml', [
				'site_name' => MB_SITE_NAME,
				'site_desc' => MB_SITE_DESC,
				'site_stats' => $this->select_site_stats(),
			]);
		});
	}

	public function get_name(): string
	{
		return 'home';
	}

	public function get_index(): string
	{
		return '/';
	}

	private function select_site_stats(): array|bool
	{
		// NOTE: created here instead of the constructor, every module is constructed on every request
		$cache = core\ApcuCache::is_available()
			? new core\ApcuCache(self::CACHE_ID)
			: new core\FileCache(self::CACHE_ID);

		$site_stats = $cache->get('site_stats');
		if ($site_stats !== null) {
			return $site_stats;
		}

		// NOTE: DbConnection disables autocommit, the transaction ensures the read snapshot is closed
		$connection = new core\DbConnection(MB_DB_HOST, MB_DB_NAME, MB_DB_USER, MB_DB_PASS);
		$connection->transaction(function (\PDO $pdo) use (&$site_stats) {
			$sth = $pdo->prepare('
				SELECT
					MAX(sq1.total_posts) AS total_posts,
					MAX(sq1.current_posts) AS current_posts,
					MAX(sq1.unique_posters) AS unique_posters,
					MAX(sq1.imported_posts) AS imported_posts,
					MAX(sq1.current_files) AS current_files,
					MAX(sq1.active_content) AS active_content
				FROM (
					SELECT
						SUM(sq11.total_posts) AS total_posts,
						NULL AS current_posts,
						NULL AS unique_posters,
						NULL AS imported_posts,
						NULL AS current_files,
						NULL AS active_content
					FROM (
						SELECT
							board_id AS board_id,
							MAX(post_id) AS total_posts
						FROM posts
						GROUP BY board_id
					) AS sq11

					UNION ALL

					SELECT
						NULL AS total_posts,
						COUNT(*) AS current_posts,
						NULL AS unique_posters,
						NULL AS imported_posts,
						NULL AS current_files,
						NULL AS active_content
					FROM posts

					UNION ALL

					SELECT
						NULL AS total_posts,
						NULL AS current_posts,
						COUNT(DISTINCT(ip)) AS unique_posters,
						NULL AS imported_posts,
						NULL AS current_files,
						NULL AS active_content
					FROM posts

					UNION ALL

					SELECT
						NULL AS total_posts,
						NULL AS current_posts,
						NULL AS unique_posters,
						COUNT(*) AS imported_posts,
						NULL AS current_files,
						NULL AS active_content
					FROM posts
					WHERE imported = 1

					UNION ALL

					SELECT
						NULL AS total_posts,
						NULL AS current_posts,
						NULL AS unique_posters,
						NULL AS imported_posts,
						COUNT(DISTINCT(file_hex)) AS current_files,
						NULL AS active_content
					FROM posts

					UNION ALL

					SELECT
						NULL AS total_posts,
						NULL AS current_posts,
						NULL AS unique_posters,
						NULL AS imported_posts,
						NULL AS current_files,
						SUM(sq12.file_size) AS active_content
					FROM (
						SELECT
							file_hex,
							file_size
						FROM posts
						GROUP BY file_hex
					) AS sq12
				) AS sq1
			');
			$sth->execute();
			$site_stats = $sth->fetch(\PDO::FETCH_ASSOC);
		});

		if ($site_stats !== false) {
			$cache->set('site_stats', $site_stats, self::SITE_STATS_CACHE_TTL);
		}

		return $site_stats;
	}
}

return new HomeModule();
