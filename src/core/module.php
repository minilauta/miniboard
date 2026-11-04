<?php

namespace minichan\core;

use Closure;

interface Module
{
	public function init(App &$app): void;
	public function register_middleware(Router &$router): void;
	public function register_routes(Router &$router): void;
	public function get_name(): string;
	public function get_index(): string;
}
