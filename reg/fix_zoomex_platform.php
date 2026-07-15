<?php
// fix_zoomex_platform.php — Ejecutar UNA VEZ para reparar registros de Zoomex
// que quedaron con platform='' porque llegaron antes de que el ENUM aceptara
// 'zoomex' (MySQL en modo no estricto guarda '' cuando el valor de un ENUM
// no es válido, en vez de dar error). Eliminar del servidor después.
require_once __DIR__ . '/config.php';

$pdo = new PDO(
    "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
    DB_USER, DB_PASS,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

// Asegurar que el ENUM ya acepta 'zoomex' (por si setup.php no se corrió antes)
try {
    $pdo->exec("ALTER TABLE registrations MODIFY COLUMN platform ENUM('pepperstone','bingx','bitunix','zoomex') NOT NULL;");
    echo "✅ ENUM platform verificado/actualizado.<br>";
} catch (PDOException $e) {
    echo "⚠️ No se pudo alterar el ENUM: " . htmlspecialchars($e->getMessage()) . "<br>";
}

// Ubicar registros con platform inválido/vacío (el único origen posible de esto
// es Zoomex, ya que pepperstone/bingx/bitunix siempre fueron valores válidos)
$stmt = $pdo->query("SELECT id, telegram_name, telegram_username, created_at FROM registrations WHERE platform NOT IN ('pepperstone','bingx','bitunix','zoomex')");
$broken = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$broken) {
    echo "✅ No se encontraron registros con plataforma inválida. Nada que reparar.";
} else {
    echo "🔧 Encontrados " . count($broken) . " registro(s) con plataforma vacía/inválida:<br><ul>";
    foreach ($broken as $r) {
        echo "<li>#{$r['id']} — " . htmlspecialchars($r['telegram_name'] ?: '(sin nombre)') .
             " (@" . htmlspecialchars($r['telegram_username'] ?: 'sin username') . ") — {$r['created_at']}</li>";
    }
    echo "</ul>";

    $ids = array_column($broken, 'id');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $pdo->prepare("UPDATE registrations SET platform = 'zoomex' WHERE id IN ($placeholders)")->execute($ids);

    echo "✅ Reparados como 'zoomex': " . implode(', ', array_map(fn($id) => "#$id", $ids)) . "<br>";
}

echo "<br>Elimina este archivo del servidor cuando confirmes que todo quedó bien.";
