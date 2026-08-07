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

// Borra una participación por posición en el array, verificando 'submitted_at' para
// evitar borrar la entrada equivocada si la lista cambió entre que se cargó la
// página y se envió el formulario de borrado. También borra sus imágenes en uploads/.
function deleteConcursoSubmission(int $index, string $expectedSubmittedAt): array {
    $dir = getConcursoDir();
    if (!$dir) return ['ok' => false, 'message' => 'No se encontró la carpeta del concurso.'];

    $raw = @file_get_contents($dir . '/submissions.json');
    $all = $raw ? (json_decode($raw, true) ?: []) : [];

    if (!isset($all[$index]) || ($all[$index]['submitted_at'] ?? '') !== $expectedSubmittedAt) {
        return ['ok' => false, 'message' => 'La lista cambió — recarga la página e intenta de nuevo.'];
    }

    $removed = $all[$index];
    foreach (($removed['files'] ?? []) as $f) {
        $filename = $f['filename'] ?? '';
        if ($filename === '') continue;
        $path = $dir . '/uploads/' . $filename;
        if (is_file($path)) @unlink($path);
    }

    unset($all[$index]);
    $all = array_values($all);
    file_put_contents($dir . '/submissions.json', json_encode($all, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);

    $name = trim(($removed['first_name'] ?? '') . ' ' . ($removed['last_name'] ?? ''));
    return ['ok' => true, 'message' => "Participación de $name eliminada."];
}
