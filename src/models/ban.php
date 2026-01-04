<?php

namespace minichan\models;

require_once __ROOT__ . '/common/config.php';

class Ban
{
	public int $id;
	public string $ip;
	public int $timestamp;
	public int $expire;
	public string $reason;
	public bool $imported;

	public function redact_ip(): string {
		if (str_contains($this->ip, '.')) {
			return implode('.', array_slice(explode('.', $this->ip), 0, 2)) . '.x.x';
		} else if (str_contains($this->ip, ':')) {
			return implode(':', array_slice(explode(':', $this->ip), 0, 2)) . ':x:x';
		}

		return 'NULL';
	}

	public function fmt_timestamp(): string {
		return date(MB_DATEFORMAT, $this->timestamp);
	}

	public function fmt_expire(): string {
		return date(MB_DATEFORMAT, $this->expire);
	}
}
