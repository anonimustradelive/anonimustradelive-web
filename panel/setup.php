<?php
// setup.php — Ejecutar UNA VEZ para crear las tablas de Facturación. Eliminar del servidor después.
require_once __DIR__ . '/includes/db.php';
$pdo = getPDO();

$pdo->exec("
CREATE TABLE IF NOT EXISTS invoices (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    invoice_number    VARCHAR(20)  NOT NULL UNIQUE,
    client_name       VARCHAR(255) NOT NULL,
    client_email      VARCHAR(255) NULL,
    service_type      ENUM('ads','contenido','otro') NOT NULL DEFAULT 'otro',
    issue_date        DATE NOT NULL,
    due_date          DATE NULL,
    status            ENUM('draft','sent','paid','overdue','cancelled') NOT NULL DEFAULT 'draft',
    subtotal          DECIMAL(10,2) NOT NULL DEFAULT 0,
    discount_pct      DECIMAL(5,2)  NOT NULL DEFAULT 0,
    discount_amount   DECIMAL(10,2) NOT NULL DEFAULT 0,
    tax_enabled       TINYINT(1)    NOT NULL DEFAULT 0,
    tax_pct           DECIMAL(5,2)  NOT NULL DEFAULT 18.00,
    tax_amount        DECIMAL(10,2) NOT NULL DEFAULT 0,
    total             DECIMAL(10,2) NOT NULL DEFAULT 0,
    notes             TEXT NULL,
    created_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

$pdo->exec("
CREATE TABLE IF NOT EXISTS invoice_items (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    invoice_id    INT NOT NULL,
    description   VARCHAR(500) NOT NULL,
    quantity      DECIMAL(10,2) NOT NULL DEFAULT 1,
    unit_price    DECIMAL(10,2) NOT NULL DEFAULT 0,
    line_total    DECIMAL(10,2) NOT NULL DEFAULT 0,
    sort_order    INT NOT NULL DEFAULT 0,
    INDEX idx_invoice (invoice_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

echo "✅ Tablas de facturación verificadas correctamente. Elimina este archivo del servidor.";
