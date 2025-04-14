<?php

namespace minichan\core;

use Closure;

interface Module
{
	public function register_middleware(Closure $handler): void;
	public function register_routes(Router &$router): void;
	public function get_name(): string;
}
