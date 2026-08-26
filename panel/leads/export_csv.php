<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/leads_data.php';

$leads = getLeads();

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="puntozero_leads_' . date('Ymd_His') . '.csv"');

$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF"); // BOM: para que Excel abra bien los acentos
fputcsv($out, ['#', 'Correo', 'Fecha', 'Origen']);

foreach ($leads as $i => $l) {
    $f  = $l['fecha'] ?? '';
    $ts = $f ? strtotime($f) : false;
    fputcsv($out, [
        $i + 1,
        $l['email'] ?? '',
        $ts ? date('d/m/Y H:i', $ts) : $f,
        $l['origen'] ?? '',
    ]);
}

fclose($out);
