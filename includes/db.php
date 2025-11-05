<?php
// includes/db.php
require_once __DIR__ . '/../config.php';

function getPDO() {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ];
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    }
    return $pdo;
}

function db_query($sql, $params = []) {
    $pdo = getPDO();
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function db_execute($sql, $params = []) {
    $pdo = getPDO();
    $stmt = $pdo->prepare($sql);
    return $stmt->execute($params);
}
