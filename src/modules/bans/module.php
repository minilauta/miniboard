<?php

namespace minichan\modules;

use Closure;
use minichan\core;
use minichan\models;

require_once __ROOT__ . '/core/module.php';
require_once __ROOT__ . '/core/db_connection.php';
require_once __ROOT__ . '/core/renderer.php';
require_once __ROOT__ . '/models/ban.php';
require_once __ROOT__ . '/common/config.php';
require_once __ROOT__ . '/common/database.php';

class BansModule implements core\Module
{
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

	public function register_middleware(core\Router &$router, Closure $handler): void {}

	public function register_routes(core\Router &$router): void
	{
		$router->add_route(HTTP_GET, '/bans', function ($vars) {
			$connection = new core\DbConnection(MB_DB_HOST, MB_DB_NAME, MB_DB_USER, MB_DB_PASS);
			$sth = $connection
				->get_pdo()
				->prepare('
					SELECT
						id,
						INET6_NTOA(ip) AS ip,
						timestamp,
						expire,
						reason,
						imported
					FROM bans
					WHERE timestamp > :time
					ORDER BY id DESC
				');
			$bans = [];
			if ($sth->execute(['time' => time() - 15 * 86400]) == true) {
				$bans = $sth->fetchAll(\PDO::FETCH_CLASS, 'minichan\models\Ban');
			}

			echo $this->renderer->render(__DIR__ . '/templates/bans.phtml', ['bans' => $bans]);
		});
	}

	public function get_name(): string
	{
		return 'bans';
	}
}

return new BansModule();
