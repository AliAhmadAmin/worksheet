<?php
/**
 * Clean Logout Handler
 * Destroys session cookies and redirects cleanly to login.php
 */
require_once __DIR__ . '/config/database.php';

if (session_status() === PHP_SESSION_ACTIVE) {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }
    session_destroy();
}

header('Location: login.php');
exit;
