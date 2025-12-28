<?php

namespace minichan\plugins;

use Closure;
use minichan\core;


require_once __ROOT__ . '/core/plugin.php';
require_once __ROOT__ . '/common/config.php';

class Winter2025Plugin implements core\Plugin
{
	public function register_hooks(): void
    {
        core\App::get_instance()->add_hook('common.styles', function () {
            echo "<link rel='stylesheet' type='text/css' href='/plugins/winter2025/index.css'>";
        });
        core\App::get_instance()->add_hook('common.scripts', function () {
            echo "<script src='/plugins/winter2025/index.js'></script>";
        });
    }

	public function get_name(): string
    {
        return 'winter2025';
    }

	public function get_dependencies(): array
    {
        return [];
    }
}

return new Winter2025Plugin();
