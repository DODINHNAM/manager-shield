<?php
// config.php
session_start();

define('DB_HOST', 'localhost');
define('DB_NAME', 'mana_manager');
define('DB_USER', 'mana_manager');
define('DB_PASS', 'manager'); // change if needed

define('BASE_URL', '/');

ini_set('session.cookie_lifetime', 0);
