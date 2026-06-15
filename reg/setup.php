<?php
// setup.php — Ejecutar UNA VEZ para crear las tablas. Eliminar del servidor después.
require_once __DIR__ . '/config.php';

$pdo = new PDO(
    "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
    DB_USER, DB_PASS,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$pdo->exec("
CREATE TABLE IF NOT EXISTS sessions (
    user_id     BIGINT PRIMARY KEY,
    state       VARCHAR(50)  NOT NULL DEFAULT 'start',
    data        JSON,
    updated_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS registrations (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    telegram_user_id    BIGINT       NOT NULL,
    telegram_name       VARCHAR(255),
    telegram_username   VARCHAR(255),
    profile_type        ENUM('principiante','trader') NOT NULL,
    asset_type          ENUM('crypto','tradicional')  NULL,
    platform            ENUM('pepperstone','bingx','bitunix') NOT NULL,
    platform_user_id    VARCHAR(255) NOT NULL,
    status              ENUM('pending','accepted','rejected') NOT NULL DEFAULT 'pending',
    invite_link         VARCHAR(500) NULL,
    created_at          TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

echo "✅ Tablas creadas correctamente. Elimina este archivo del servidor.";
