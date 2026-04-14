<?php
define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_PORT', '3306');
define('DB_NAME', 'wasm_editor');
define('DB_USER', 'root');
define('DB_PASS', '');

function getPDO(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $pdo = new PDO($dsn, DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE,            PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        // Migration : ajoute la colonne recipe si elle n'existe pas
        try {
            $pdo->exec("ALTER TABLE photos ADD COLUMN recipe JSON DEFAULT NULL");
        } catch (PDOException) {
            // Colonne déjà existante, on ignore
        }
    }
    return $pdo;
}
