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
	public ?string $post_board_id;
	public ?int $post_id;
	public ?string $post_subject;
	public ?string $post_nameblock;
	public ?string $post_message_rendered;
	public ?string $post_thumb;
	public ?int $post_thumb_width;
	public ?int $post_thumb_height;

	public function has_preview(): bool {
		return $this->post_board_id !== null && $this->post_id !== null;
	}

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

	public function fmt_reason(int $len): string {
		if (strlen($this->reason) <= $len) return $this->reason;

		return substr($this->reason, 0, $len) . ' ...';
	}
}
