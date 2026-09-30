<?php
declare(strict_types=1);

define('ROOT', dirname(__DIR__));
date_default_timezone_set('Europe/Belgrade');

$GLOBALS['CFG'] = is_file(ROOT . '/config.php') ? require ROOT . '/config.php' : null;

function cfg(): array
{
    return $GLOBALS['CFG'] ?? [];
}

define('BASE', rtrim((string)(cfg()['base'] ?? ''), '/'));

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => !empty($_SERVER['HTTPS']),
    ]);
    session_start();
}

require ROOT . '/includes/helpers.php';
require ROOT . '/includes/db.php';

if (!$GLOBALS['CFG'] && !defined('INSTALLING')) {
    header('Location: ' . (defined('IN_ADMIN') ? '../install' : 'install'));
    exit;
}
