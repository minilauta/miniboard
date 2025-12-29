<?php

namespace minichan\plugins;

use Closure;
use minichan\core;


require_once __ROOT__ . '/core/plugin.php';
require_once __ROOT__ . '/common/config.php';
require_once __ROOT__ . '/common/version.php';

class Winter2025Plugin implements core\Plugin
{
	public function register_hooks(): void
    {
        $mb_version = MB_VERSION;
        core\App::get_instance()->add_hook('common.styles', function () use ($mb_version) {
            echo "<link rel='stylesheet' type='text/css' href='/plugins/winter2025/index.css?mb_version=$mb_version'>";
        });
        core\App::get_instance()->add_hook('common.scripts', function () use ($mb_version) {
            echo "<script src='/plugins/winter2025/index.js?mb_version=$mb_version'></script>";
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
