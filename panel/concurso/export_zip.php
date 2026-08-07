<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/concurso_data.php';

if (!class_exists('ZipArchive')) {
    http_response_code(500);
    exit('La extensión ZipArchive no está disponible en este servidor.');
}

$dir     = getConcursoDir();
$entries = getConcursoSubmissions();

if (!$dir || empty($entries)) {
    exit('No hay participaciones todavía.');
}

$zipPath = tempnam(sys_get_temp_dir(), 'concurso_');
$zip = new ZipArchive();
$zip->open($zipPath, ZipArchive::OVERWRITE);

$summary = ["#\tNombre\tApellido\tNota\tFecha\tCarpeta"];

foreach ($entries as $i => $e) {
    $n = $i + 1;
    $safeFirst = preg_replace('/[^A-Za-z0-9]+/', '', $e['first_name'] ?? '') ?: 'sn';
    $safeLast  = preg_replace('/[^A-Za-z0-9]+/', '', $e['last_name'] ?? '') ?: 'sn';
    $folder    = sprintf('%02d_%s_%s', $n, $safeFirst, $safeLast);

    foreach (($e['files'] ?? []) as $f) {
        $filename = $f['filename'] ?? '';
        $src = $dir . '/uploads/' . $filename;
        if ($filename !== '' && is_file($src)) {
            $zip->addFile($src, $folder . '/' . $filename);
        }
    }

    $noteFlat = str_replace(["\t", "\r\n", "\n"], [' ', ' ', ' '], $e['note'] ?? '');
    $summary[] = implode("\t", [
        $n,
        $e['first_name'] ?? '',
        $e['last_name'] ?? '',
        $noteFlat,
        $e['submitted_at'] ?? '',
        $folder,
    ]);
}

$zip->addFromString('resumen.tsv', implode("\n", $summary));
$zip->close();

header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="concurso_participaciones_' . date('Ymd_His') . '.zip"');
header('Content-Length: ' . filesize($zipPath));
readfile($zipPath);
unlink($zipPath);
exit;
