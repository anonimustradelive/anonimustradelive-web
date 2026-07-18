<?php
// Lee el catálogo de precios DIRECTAMENTE de ads/index.html (fuente única de verdad),
// para que el módulo de Facturación siempre refleje los mismos precios que la
// calculadora pública. Si cambian los precios en ads/index.html, se reflejan aquí
// automáticamente — no hay copia duplicada que mantener sincronizada a mano.
//
// Requiere que ads/index.html defina `const PRECIOS = {...};` con claves entre
// comillas (JSON válido) — ver comentario en ese archivo.

function getAdsPricing(): array {
    static $cache = null;
    if ($cache !== null) return $cache;

    $candidates = [];
    if (defined('ADS_INDEX_PATH')) $candidates[] = ADS_INDEX_PATH;
    // Mismo árbol de archivos (repo local / hosting con un solo document root)
    $candidates[] = __DIR__ . '/../../ads/index.html';
    // Producción: panel.anonimustradelive.com y anonimustradelive.com son
    // document roots distintos pero hermanos bajo el mismo home de cPanel
    $candidates[] = __DIR__ . '/../../anonimustradelive.com/ads/index.html';

    $path = null;
    foreach ($candidates as $c) {
        if (is_readable($c)) { $path = $c; break; }
    }

    if ($path === null) {
        error_log('ads_pricing: no se encontró ads/index.html en ninguna ruta candidata. ' .
            'Define ADS_INDEX_PATH en config.php si la estructura del servidor cambió.');
        return $cache = ['error' => 'no_encontrado', 'contenido' => [], 'cintillos' => []];
    }

    $html = file_get_contents($path);
    if ($html === false || !preg_match('/const\s+PRECIOS\s*=\s*(\{.*?\});/s', $html, $m)) {
        error_log("ads_pricing: no se encontró el bloque 'const PRECIOS = {...};' en $path");
        return $cache = ['error' => 'bloque_no_encontrado', 'contenido' => [], 'cintillos' => []];
    }

    $data = json_decode($m[1], true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
        error_log('ads_pricing: el bloque PRECIOS no es JSON válido — ' . json_last_error_msg());
        return $cache = ['error' => 'json_invalido', 'contenido' => [], 'cintillos' => []];
    }

    return $cache = $data;
}

// Etiquetas de frecuencia semanal — solo texto de UI (no son precios), se mantienen aquí.
function getFreqLabels(): array {
    return ['4' => '1/sem', '8' => '2/sem', '12' => '3/sem', '16' => '4/sem', '20' => '5/sem'];
}
