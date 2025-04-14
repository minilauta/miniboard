<?php

namespace minichan\core;

use Closure;

class DbConnection
{
	private \PDO $pdo;

	public function __construct(string $host, string $dbname, string $username, string $password)
	{
		$this->pdo = new \PDO("mysql:host=$host;dbname=$dbname", $username, $password, [
			\PDO::ATTR_PERSISTENT => true,
			\PDO::ATTR_EMULATE_PREPARES => false,
			\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
		]);
	}

	public function get_pdo(): \PDO
	{
		return $this->pdo;
	}

	public function transaction(Closure $handler): void
	{
		try {
			$this->begin();
			call_user_func($handler, $this->pdo);
			$this->commit();
		} catch (\Exception $e) {
			$this->rollback();
			throw $e;
		}
	}

	private function begin(): bool
	{
		return $this->pdo->beginTransaction();
	}

	private function commit(): bool
	{
		return $this->pdo->commit();
	}

	private function rollback(): bool
	{
		return $this->pdo->rollBack();
	}
}
