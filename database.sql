CREATE DATABASE IF NOT EXISTS wasm_editor
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE wasm_editor;

CREATE TABLE IF NOT EXISTS users (
    id         INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    username   VARCHAR(60)      NOT NULL UNIQUE,
    email      VARCHAR(255)     NOT NULL UNIQUE,
    password   VARCHAR(255)     NOT NULL,
    created_at DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
);

CREATE TABLE IF NOT EXISTS albums (
    id         INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    user_id    INT UNSIGNED     NOT NULL,
    name       VARCHAR(120)     NOT NULL,
    created_at DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS photos (
    id         INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    album_id   INT UNSIGNED     NOT NULL,
    user_id    INT UNSIGNED     NOT NULL,
    filename   VARCHAR(255)     NOT NULL,
    filepath   VARCHAR(500)     NOT NULL,
    filesize   INT UNSIGNED     NOT NULL DEFAULT 0,
    width      SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    height     SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    FOREIGN KEY (album_id) REFERENCES albums(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id)  REFERENCES users(id)  ON DELETE CASCADE
);

INSERT INTO users (username, email, password) VALUES
('admin', 'admin@local.dev', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi')
ON DUPLICATE KEY UPDATE password = VALUES(password);