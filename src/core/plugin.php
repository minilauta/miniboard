<?php

namespace minichan\core;

interface Plugin
{
	public function register_hooks(): void;
	public function get_name(): string;
	public function get_dependencies(): array;
}
