<?php

namespace minichan\models;

require_once __ROOT__ . '/common/config.php';

class Log
{
	public int $id;
	public int $timestamp;
	public string $username;
	public string $message;
	public bool $imported;

	public function fmt_timestamp(): string {
		return date(MB_DATEFORMAT, $this->timestamp);
	}
}
