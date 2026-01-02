<?php

namespace minichan\core;

interface Plugin
{
	public function init(App &$app): void;
	public function register_hooks(App &$app): void;
	public function get_name(): string;
	public function get_dependencies(): array;
}
