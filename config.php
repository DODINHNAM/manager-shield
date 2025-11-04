<?php
// config.php
session_start();

define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'php_shield');
define('DB_USER', 'root');
define('DB_PASS', ''); // change if needed

// Base URL if needed (example: '/project/')
define('BASE_URL', '/');

ini_set('session.cookie_lifetime', 0);
