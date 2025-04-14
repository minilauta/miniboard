<?php

namespace minichan\core;

define('DB_MIGRATION_DIR', __ROOT__ . '/../migrations');

class DbMigration
{
	private string $filename;
	private int $version;
	private string $script;

	public function __construct(string $filename)
	{
		$this->filename = $filename;
		$yaml_data = yaml_parse_file(DB_MIGRATION_DIR . '/' . $this->filename, 0);
		$this->version = $yaml_data['version'];
		$this->script = $yaml_data['script'];
	}

	public function get_filename(): string
	{
		return $this->filename;
	}

	public function get_version(): int
	{
		return $this->version;
	}

	public function get_script(): string
	{
		return $this->script;
	}
}
