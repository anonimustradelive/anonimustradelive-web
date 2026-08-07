<?php
// Lee las participaciones del concurso DIRECTAMENTE de concurso/submissions.json y
// concurso/uploads/ en el docroot principal (anonimustradelive.com) — mismo patrón
// de docroots hermanos que ads_pricing.php, sin duplicar datos entre subdominios.

function getConcursoDir(): ?string {
    static $resolved = false; // false = todavía no se intentó resolver

    if ($resolved !== false) return $resolved;

    $candidates = [];
    if (defined('CONCURSO_DIR')) $candidates[] = CONCURSO_DIR;
    // Mismo árbol de archivos (repo local / hosting con un solo document root)
    $candidates[] = __DIR__ . '/../../concurso';
    // Producción: panel.anonimustradelive.com y anonimustradelive.com son
    // document roots distintos pero hermanos bajo el mismo home de cPanel
    $candidates[] = __DIR__ . '/../../anonimustradelive.com/concurso';

    foreach ($candidates as $c) {
        if ($c && is_dir($c)) { $resolved = realpath($c) ?: $c; return $resolved; }
    }

    error_log('concurso_data: no se encontró la carpeta concurso/ en ninguna ruta candidata. ' .
        'Define CONCURSO_DIR en config.php si la estructura del servidor cambió.');
    $resolved = null;
    return null;
}

function getConcursoSubmissions(): array {
    $dir = getConcursoDir();
    if (!$dir) return [];
    $raw = @file_get_contents($dir . '/submissions.json');
    if (!$raw) return [];
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}
