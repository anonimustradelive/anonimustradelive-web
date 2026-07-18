<?php
// setup.php — Ejecutar para crear/actualizar las tablas de Facturación. Eliminar del servidor después.
require_once __DIR__ . '/includes/db.php';
$pdo = getPDO();

$pdo->exec("
CREATE TABLE IF NOT EXISTS invoices (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    invoice_number    VARCHAR(20)  NOT NULL UNIQUE,
    client_name       VARCHAR(255) NOT NULL,
    client_email      VARCHAR(255) NULL,
    service_type      VARCHAR(50)  NOT NULL DEFAULT 'otro',
    doc_type          ENUM('invoice','receipt') NOT NULL DEFAULT 'invoice',
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
    id              INT AUTO_INCREMENT PRIMARY KEY,
    invoice_id      INT NOT NULL,
    catalog_item_id INT NULL,
    description     VARCHAR(500) NOT NULL,
    quantity        DECIMAL(10,2) NOT NULL DEFAULT 1,
    unit_price      DECIMAL(10,2) NOT NULL DEFAULT 0,
    line_total      DECIMAL(10,2) NOT NULL DEFAULT 0,
    frequency       INT NULL,
    line_note       VARCHAR(120) NULL,
    sort_order      INT NOT NULL DEFAULT 0,
    INDEX idx_invoice (invoice_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

// ── Migraciones sobre tablas ya existentes (idempotentes) ──────────────────
try {
    $pdo->exec("ALTER TABLE invoices MODIFY COLUMN service_type VARCHAR(50) NOT NULL DEFAULT 'otro';");
} catch (PDOException $e) {}

try {
    $pdo->exec("ALTER TABLE invoices ADD COLUMN doc_type ENUM('invoice','receipt') NOT NULL DEFAULT 'invoice' AFTER service_type;");
} catch (PDOException $e) {}

try {
    $pdo->exec("ALTER TABLE invoices ADD COLUMN currency VARCHAR(3) NOT NULL DEFAULT 'USD' AFTER doc_type;");
} catch (PDOException $e) {}

try {
    $pdo->exec("ALTER TABLE invoices ADD COLUMN exchange_rate DECIMAL(10,4) NULL AFTER currency;");
} catch (PDOException $e) {}

try {
    $pdo->exec("ALTER TABLE invoice_items ADD COLUMN catalog_item_id INT NULL AFTER invoice_id;");
} catch (PDOException $e) {}

try {
    $pdo->exec("ALTER TABLE invoice_items ADD COLUMN frequency INT NULL AFTER unit_price;");
} catch (PDOException $e) {}

try {
    $pdo->exec("ALTER TABLE invoice_items ADD COLUMN line_note VARCHAR(120) NULL AFTER frequency;");
} catch (PDOException $e) {}

// ── Tipos de servicio (editables desde el panel, reemplazan el ENUM fijo) ──
$pdo->exec("
CREATE TABLE IF NOT EXISTS service_types (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    slug        VARCHAR(50)  NOT NULL UNIQUE,
    name        VARCHAR(100) NOT NULL,
    sort_order  INT NOT NULL DEFAULT 0,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

$seedTypes = [
    ['ads',       'Ads (publicidad)',        1],
    ['contenido', 'Creación de contenido',   2],
    ['otro',      'Otro',                    3],
];
$insType = $pdo->prepare("INSERT IGNORE INTO service_types (slug, name, sort_order) VALUES (?, ?, ?)");
foreach ($seedTypes as $t) { $insType->execute($t); }

// ── Catálogo de productos/servicios ─────────────────────────────────────────
// logic_type distingue los ítems que llevan la lógica automática de precios/
// descuentos de ads/index.html ('content_deluxe', 'content_premium',
// 'addon_publicidad_deluxe', 'addon_publicidad_premium', 'spot_inicio',
// 'spot_pico', 'spot_cierre') de los ítems genéricos que se agregan a mano
// desde el panel ('generic' — sin ninguna regla especial de descuento).
$pdo->exec("
CREATE TABLE IF NOT EXISTS catalog_items (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    service_type_id     INT NOT NULL,
    name                VARCHAR(255) NOT NULL,
    logic_type          VARCHAR(40) NOT NULL DEFAULT 'generic',
    default_unit_price  DECIMAL(10,2) NOT NULL DEFAULT 0,
    active              TINYINT(1) NOT NULL DEFAULT 1,
    created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_service_type (service_type_id),
    FOREIGN KEY (service_type_id) REFERENCES service_types(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

$adsTypeId = (int)$pdo->query("SELECT id FROM service_types WHERE slug = 'ads'")->fetchColumn();
$contenidoTypeId = (int)$pdo->query("SELECT id FROM service_types WHERE slug = 'contenido'")->fetchColumn();

$seedItems = [
    // service_type_id, name, logic_type, default_unit_price (0 = se calcula en vivo desde ads/index.html)
    [$contenidoTypeId, 'Deluxe — Alta producción (2 videos/mes)',      'content_deluxe',           0],
    [$contenidoTypeId, 'Premium — Máximo alcance (4 videos/mes)',      'content_premium',          0],
    [$contenidoTypeId, 'Derecho a publicidad pagada — Deluxe',         'addon_publicidad_deluxe',  0],
    [$contenidoTypeId, 'Derecho a publicidad pagada — Premium',        'addon_publicidad_premium', 0],
    [$adsTypeId,       'Spot Inicio (cintillo en vivo)',                'spot_inicio',              0],
    [$adsTypeId,       'Spot Pico (cintillo en vivo)',                  'spot_pico',                0],
    [$adsTypeId,       'Spot Cierre (cintillo en vivo)',                'spot_cierre',              0],
];
$insItem = $pdo->prepare("INSERT INTO catalog_items (service_type_id, name, logic_type, default_unit_price)
    SELECT ?, ?, ?, ? FROM DUAL
    WHERE NOT EXISTS (SELECT 1 FROM catalog_items WHERE logic_type = ? AND logic_type != 'generic')");
foreach ($seedItems as $it) {
    $insItem->execute([$it[0], $it[1], $it[2], $it[3], $it[2]]);
}

echo "✅ Tablas de facturación (incluyendo catálogo y tipos de servicio) verificadas correctamente. Elimina este archivo del servidor.";
