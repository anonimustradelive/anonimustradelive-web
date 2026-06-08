<?php
// Ko-fi Webhook Receiver
// Receives donation notifications from Ko-fi and appends to donors.json

header('Content-Type: application/json');

require_once __DIR__ . '/config.php';
$DONORS_FILE  = __DIR__ . '/donors.json';

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

// Procesar donaciones y suscripciones
$type = $data['type'] ?? '';
if (!in_array($type, ['Donation', 'Subscription'])) {
    http_response_code(200);
    echo json_encode(['status' => 'ignored', 'type' => $type]);
    exit;
}

$is_subscription = ($type === 'Subscription');
$is_first_sub    = (bool)($data['is_first_subscription_payment'] ?? false);
$tier_name       = trim($data['tier_name'] ?? '');

$name      = trim($data['from_name'] ?? 'Anónimo');
$amount    = floatval($data['amount'] ?? 0);
$currency  = strtoupper($data['currency'] ?? 'USD');
$message   = trim($data['message'] ?? '');
$timestamp = date('Y-m-d H:i:s');
$email_hash = !empty($data['email']) ? md5(strtolower(trim($data['email']))) : '';

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
        $donor['total']  += $amount;
        $donor['count']  += 1;
        $donor['last']    = $timestamp;
        if (!empty($message)) $donor['last_message'] = $message;
        if ($is_subscription) $donor['is_subscriber'] = true;
        $found = true;
        break;
    }
}
unset($donor);

if (!$found) {
    $donors[] = [
        'name'          => $name,
        'total'         => $amount,
        'count'         => 1,
        'currency'      => $currency,
        'last'          => $timestamp,
        'last_message'  => $message,
        'email_hash'    => $email_hash,
        'is_subscriber' => $is_subscription,
    ];
}

// Ordenar por total descendente
usort($donors, fn($a, $b) => $b['total'] <=> $a['total']);

file_put_contents($DONORS_FILE, json_encode($donors, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

// Notificar en Telegram
if ($is_subscription) {
    $sub_label = $is_first_sub ? '¡Nuevo suscriptor!' : 'Renovación de suscripción';
    $msg = "⭐ <b>{$sub_label}</b>\n\n"
         . "💰 <b>\${$amount} {$currency}</b>/mes\n"
         . "👤 De: <b>{$name}</b>"
         . (!empty($tier_name) ? "\n🏅 Tier: <b>{$tier_name}</b>" : '')
         . (!empty($message) ? "\n💬 \"" . htmlspecialchars($message) . "\"" : '')
         . "\n\n¡Gracias por el apoyo continuo a AnonimusTrade Live! 🙏";
} else {
    $msg = "☕ <b>¡Nueva donación Ko-fi!</b>\n\n"
         . "💰 <b>\${$amount} {$currency}</b>\n"
         . "👤 De: <b>{$name}</b>"
         . (!empty($message) ? "\n💬 \"" . htmlspecialchars($message) . "\"" : '')
         . "\n\n¡Gracias por apoyar a AnonimusTrade Live! 🙏";
}

$tgPayload = json_encode([
    'chat_id'           => $TG_CHAT_ID,
    'message_thread_id' => (int)$TG_THREAD_ID,
    'text'              => $msg,
    'parse_mode'        => 'HTML',
]);
$ctx = stream_context_create([
    'http' => [
        'method'        => 'POST',
        'header'        => "Content-Type: application/json\r\n",
        'content'       => $tgPayload,
        'timeout'       => 5,
        'ignore_errors' => true,
    ]
]);
@file_get_contents("https://api.telegram.org/bot{$TG_TOKEN}/sendMessage", false, $ctx);

http_response_code(200);
echo json_encode(['status' => 'ok', 'donor' => $name, 'amount' => $amount]);
