<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/concurso_data.php';

$entries = getConcursoSubmissions();

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="concurso_participantes_' . date('Ymd_His') . '.csv"');

$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF"); // BOM: para que Excel abra bien los acentos
fputcsv($out, ['#', 'Nombre', 'Apellido', 'Nota', 'Fecha', 'Cantidad de imágenes', 'Archivos']);

foreach ($entries as $i => $e) {
    $files = array_map(fn($f) => $f['filename'] ?? '', $e['files'] ?? []);
    fputcsv($out, [
        $i + 1,
        $e['first_name'] ?? '',
        $e['last_name'] ?? '',
        $e['note'] ?? '',
        $e['submitted_at'] ?? '',
        count($files),
        implode('; ', $files),
    ]);
}
fclose($out);
exit;
