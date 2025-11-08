<?php
// config.php
ini_set('session.cookie_lifetime', 0);
session_start();

define('DB_HOST', 'localhost');
define('DB_NAME', 'mana_manager');
define('DB_USER', 'mana_manager');
define('DB_PASS', 'manager'); // change if needed

define('BASE_URL', '/');

// Encryption Keys (DO NOT SHARE THESE)
define('ENCRYPTION_KEY', hex2bin('b51ed68a81fd0b3e26fce4f2302f8d4b1573c20ff4e247433c946bb4478b5c12'));
define('ENCRYPTION_IV', hex2bin('184b05491fa1fd4d6413ea6ac07ad429'));
