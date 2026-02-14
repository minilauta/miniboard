<?php

namespace minichan\plugins;

use Closure;
use minichan\core;


require_once __ROOT__ . '/core/plugin.php';
require_once __ROOT__ . '/core/renderer.php';
require_once __ROOT__ . '/common/config.php';
require_once __ROOT__ . '/common/version.php';

class FriendsPlugin implements core\Plugin
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

	public function register_hooks(core\App &$app): void
    {
        $app->add_hook('home.root.body', function () {
		    echo $this->renderer->render(__DIR__ . '/templates/plugin.phtml', ['friends_content' => MB_PLUGIN_FRIENDS_CONTENT]);
        });
    }

	public function get_name(): string
    {
        return 'friends';
    }

	public function get_dependencies(): array
    {
        return [];
    }
}

return new FriendsPlugin();
