<?php

namespace minichan\core;

class Sql
{
	private string $raw;
	private bool $literal;

	private function __construct(string $raw, bool $literal)
	{
		$this->raw = $raw;
		$this->literal = $literal;
	}

	public static function new(string $raw = ''): Sql
	{
		return new Sql($raw, false);
	}

	public static function lit(string $raw = ''): Sql
	{
		return new Sql($raw, true);
	}

	public function is_literal(): bool
	{
		return $this->literal;
	}

	public function str(): string
	{
		return $this->raw;
	}

	public function select(array $cols): Sql
	{
		$cols_n = count($cols);

		$this->raw .= "SELECT\n";
		foreach ($cols as $idx => $col) {
			$this->raw .= $col;
			if ($idx !== $cols_n - 1) {
				$this->raw .= ',';
			}
			$this->raw .= "\n";
		}

		return $this;
	}

	public function insert(string $table, array $cols): Sql
	{
		$cols_n = count($cols);

		$this->raw .= "INSERT INTO $table (";
		foreach ($cols as $idx => $col) {
			$this->raw .= $col;
			if ($idx !== $cols_n - 1) {
				$this->raw .= ',';
			}
		}
		$this->raw .= ")\n";
		$this->raw .= "VALUES (";
		foreach ($cols as $idx => $col) {
			$this->raw .= ":$col";
			if ($idx !== $cols_n - 1) {
				$this->raw .= ',';
			}
		}
		$this->raw .= ")\n";

		return $this;
	}

	public function from(Sql $sql, ?string $as = null): Sql
	{
		if ($sql->is_literal()) {
			$this->raw .= "FROM {$sql->str()}";
		} else {
			$this->raw .= "FROM ({$sql->str()})";
		}

		if ($as != null) {
			$this->raw .= " AS $as";
		}

		$this->raw .= "\n";

		return $this;
	}

	public function where(Sql $sql): Sql
	{
		if ($sql->is_literal()) {
			$this->raw .= "WHERE {$sql->str()}\n";
		} else {
			$this->raw .= "WHERE ({$sql->str()})\n";
		}

		return $this;
	}

	private function cnd(string $lhs, string $op, int|string $rhs): Sql
	{
		if (gettype($rhs) === 'string') {
			$this->raw .= "$lhs $op \'$rhs\'\n";
		} else {
			$this->raw .= "$lhs $op $rhs\n";
		}

		return $this;
	}

	public function eq(string $lhs, int|string $rhs): Sql
	{
		return $this->cnd($lhs, '=', $rhs);
	}

	public function neq(string $lhs, int|string $rhs): Sql
	{
		return $this->cnd($lhs, '!=', $rhs);
	}

	public function gt(string $lhs, int|string $rhs): Sql
	{
		return $this->cnd($lhs, '>', $rhs);
	}

	public function gte(string $lhs, int|string $rhs): Sql
	{
		return $this->cnd($lhs, '>=', $rhs);
	}

	public function lt(string $lhs, int|string $rhs): Sql
	{
		return $this->cnd($lhs, '<', $rhs);
	}

	public function lte(string $lhs, int|string $rhs): Sql
	{
		return $this->cnd($lhs, '<=', $rhs);
	}

	public function and(): Sql
	{
		$this->raw .= "AND\n";

		return $this;
	}

	public function or(): Sql
	{
		$this->raw .= "OR\n";

		return $this;
	}

	public function op(string $op): Sql
	{
		$this->raw .= "$op\n";

		return $this;
	}
}
