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

        $migrations = [
            "ALTER TABLE photos ADD COLUMN recipe JSON DEFAULT NULL",
            "ALTER TABLE albums ADD COLUMN visibility ENUM('private','public','shared') NOT NULL DEFAULT 'private'",
            "CREATE TABLE IF NOT EXISTS album_shares (
                album_id INT UNSIGNED NOT NULL,
                user_id  INT UNSIGNED NOT NULL,
                PRIMARY KEY (album_id, user_id),
                FOREIGN KEY (album_id) REFERENCES albums(id) ON DELETE CASCADE,
                FOREIGN KEY (user_id)  REFERENCES users(id)  ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            "ALTER TABLE photos ADD COLUMN thumb_path VARCHAR(500) DEFAULT NULL",
        ];
        foreach ($migrations as $sql) {
            try { $pdo->exec($sql); } catch (PDOException) {}
        }
    }
    return $pdo;
}
