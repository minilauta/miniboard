<?php

namespace minichan\middlewares;

use Closure;
use minichan\core;

require __ROOT__ . '/core/db_connection.php';

function session_mw_update_session_id_refs(string $old_session_id, string $new_session_id): bool
{
    $connection = new core\DbConnection(MB_DB_HOST, MB_DB_NAME, MB_DB_USER, MB_DB_PASS);
    $sth = $connection
        ->get_pdo()
        ->prepare('UPDATE hides SET session_id = :s_id_new WHERE session_id = :s_id_old');
    return $sth->execute([
        's_id_new' => $new_session_id,
        's_id_old' => $old_session_id,
    ]);
}

function session_mw(int $session_lifetime, int $recreate_after): Closure
{
    return function() use ($session_lifetime, $recreate_after) {
        session_set_cookie_params([
            'lifetime' => $session_lifetime,
            'path' => '/',
            'domain' => '',
            'secure' => false,
            'httponly' => false,
            'samesite' => 'Lax'
        ]);
        session_start();
        $timestamp = time();
        if (!isset($_SESSION['timestamp']))
        {
            $_SESSION['timestamp'] = $timestamp;
        }
        $s_duration = $timestamp - $_SESSION['timestamp'];
        if ($s_duration <= $session_lifetime && $s_duration > $recreate_after)
        {
            $s_id_old = session_id();
            $s_vars = $_SESSION;
            session_destroy();
            session_start();
            $_SESSION = $s_vars;
            $_SESSION['timestamp'] = $timestamp;
            session_mw_update_session_id_refs($s_id_old, session_id());
        }
        else if ($s_duration > $session_lifetime)
        {
            session_destroy();
            session_start();
            $_SESSION['timestamp'] = $timestamp;
        }
    };
}
