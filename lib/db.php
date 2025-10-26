<?php
// lib/db.php
// Central PDO connection with strict SQL settings

if (!defined('DB_DSN')) {
    define('DB_DSN', 'mysql:host=sql100.infinityfree.com;dbname=if0_39750606_ludo;charset=utf8mb4');
    define('DB_USER', 'if0_39750606');
    define('DB_PASS', 'HSgaming18');
}

/**
 * Returns a shared PDO connection (singleton)
 *
 * @return PDO
 */
function db(): PDO {
    static $pdo = null;

    if ($pdo === null) {
        $pdo = new PDO(DB_DSN, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        // Force strict SQL mode
        $pdo->exec("SET SESSION sql_mode='STRICT_ALL_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");
    }

    return $pdo;
}
