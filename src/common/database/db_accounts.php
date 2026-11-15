<?php
require_once __DIR__ . '/db_handle.php';

function select_account(string $username): array|bool {
  $dbh = get_db_handle();
  $sth = $dbh->prepare('SELECT * FROM accounts WHERE username = :username');
  $sth->execute([
    'username' => $username
  ]);
  return $sth->fetch();
}

function select_account_by_id(int $id): array|bool {
  $dbh = get_db_handle();
  $sth = $dbh->prepare('SELECT * FROM accounts WHERE id = :id');
  $sth->execute([
    'id' => $id
  ]);
  return $sth->fetch();
}

function select_all_accounts(bool $desc = true, int $offset = 0, int $limit = 10): array|bool {
  $dbh = get_db_handle();
  $sth = $dbh->prepare('
    SELECT
      *
    FROM accounts
    ORDER BY id ' . ($desc === true ? 'DESC' : 'ASC') . '
    LIMIT :limit OFFSET :offset
  ');
  $sth->execute([
    'limit' => $limit,
    'offset' => $offset
  ]);
  return $sth->fetchAll();
}

function count_all_accounts(): int|bool {
  $dbh = get_db_handle();
  $sth = $dbh->prepare('SELECT COUNT(*) FROM accounts');
  $sth->execute();
  return $sth->fetchColumn();
}

function update_account(array $account): bool {
  $dbh = get_db_handle();
  $sth = $dbh->prepare('
    UPDATE accounts
    SET
      password = :password,
      role = :role,
      lastactive = :lastactive
    WHERE id = :id
  ');
  return $sth->execute([
    'password' => $account['password'],
    'role' => $account['role'],
    'lastactive' => $account['lastactive'],
    'id' => $account['id']
  ]);
}

function insert_account(string $username, string $password, int $role): int {
  $dbh = get_db_handle();
  $sth = $dbh->prepare('
    INSERT INTO accounts (username, password, role, lastactive)
    VALUES (:username, :password, :role, :lastactive)
  ');
  $sth->execute([
    'username' => $username,
    'password' => $password,
    'role' => $role,
    'lastactive' => time()
  ]);
  return intval($dbh->lastInsertId());
}

function insert_import_accounts_tinyib(array $db_creds, string $table_name): int {
  if (!preg_match('/^[a-zA-Z0-9_]+$/', $table_name)) {
    throw new DbException('insert_import_accounts_tinyib: invalid table name');
  }
  $dbh = get_db_handle_import($db_creds);
  $sth = $dbh->prepare('
    INSERT INTO ' . MB_DB_NAME . '.accounts (
      username,
      password,
      role,
      lastactive,
      imported
    )
    SELECT
      username,
      password,
      role,
      lastactive,
      1 AS imported
    FROM ' . $table_name);
  $sth->execute();
  return $sth->rowCount();
}
