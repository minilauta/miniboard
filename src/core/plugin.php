<?php

namespace minichan\core;

interface Plugin
{
	public function register(): void;
	public function get_name(): string;
	public function get_dependencies(): array;
}
