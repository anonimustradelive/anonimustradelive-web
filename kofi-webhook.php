<?php
// Ko-fi Webhook Receiver
// Receives donation notifications from Ko-fi and appends to donors.json

header('Content-Type: application/json');

$KOFI_TOKEN = 'REEMPLAZA_CON_TU_TOKEN_DE_KOFI';
$DONORS_FILE = __DIR__ . '/donors.json';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$raw = $_POST['data'] ?? '';
if (empty($raw)) {
    http_response_code(400);
    echo json_encode(['error' => 'No data received']);
    exit;
}

$data = json_decode($raw, true);
if (!$data) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON']);
    exit;
}

// Verificar token de Ko-fi
if (($data['verification_token'] ?? '') !== $KOFI_TOKEN) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid token']);
    exit;
}

// Solo procesar donaciones (no suscripciones ni shop orders)
if (($data['type'] ?? '') !== 'Donation') {
    http_response_code(200);
    echo json_encode(['status' => 'ignored', 'type' => $data['type'] ?? 'unknown']);
    exit;
}

$name   = trim($data['from_name'] ?? 'Anónimo');
$amount = floatval($data['amount'] ?? 0);
$currency = strtoupper($data['currency'] ?? 'USD');
$message  = trim($data['message'] ?? '');
$timestamp = date('Y-m-d H:i:s');

// Leer donors.json existente
$donors = [];
if (file_exists($DONORS_FILE)) {
    $content = file_get_contents($DONORS_FILE);
    $donors = json_decode($content, true) ?? [];
}

// Buscar si el donador ya existe (por nombre)
$found = false;
foreach ($donors as &$donor) {
    if (strtolower($donor['name']) === strtolower($name)) {
        $donor['total'] += $amount;
        $donor['count']  += 1;
        $donor['last']    = $timestamp;
        if (!empty($message)) $donor['last_message'] = $message;
        $found = true;
        break;
    }
}
unset($donor);

if (!$found) {
    $donors[] = [
        'name'         => $name,
        'total'        => $amount,
        'count'        => 1,
        'currency'     => $currency,
        'last'         => $timestamp,
        'last_message' => $message,
    ];
}

// Ordenar por total descendente
usort($donors, fn($a, $b) => $b['total'] <=> $a['total']);

file_put_contents($DONORS_FILE, json_encode($donors, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

http_response_code(200);
echo json_encode(['status' => 'ok', 'donor' => $name, 'amount' => $amount]);
