<?php
require_once __DIR__ . '/config.php';
$WALLET    = '0x20332BD20d55cc85282AFFe05BcC473bb8D18D91';
$USDT_BSC  = '0x55d398326f99059fF775485246999027B3197955';
$USDC_BASE = '0x833589fCD6eDb6E08f4c7C32D4f71b54bdA02913';
$FROM_DATE = '2026-05-23T00:00:00Z';
$SEEN_FILE = __DIR__ . '/crypto_seen_txs.json';

function moralisGet(string $url, string $key): ?array {
    $ctx = stream_context_create([
        'http' => [
            'timeout' => 10,
            'ignore_errors' => true,
            'header' => "X-API-Key: {$key}\r\nAccept: application/json\r\n",
        ]
    ]);
    $raw = @file_get_contents($url, false, $ctx);
    if (!$raw) return null;
    return json_decode($raw, true);
}

function fetchTransfers(string $wallet, string $token, string $chain, string $fromDate, string $key): array {
    $all = []; $cursor = null;
    do {
        $url = "https://deep-index.moralis.io/api/v2.2/{$wallet}/erc20/transfers"
             . "?chain={$chain}&contract_addresses%5B0%5D={$token}"
             . "&from_date=" . urlencode($fromDate) . "&limit=100"
             . ($cursor ? "&cursor=" . urlencode($cursor) : '');
        $data = moralisGet($url, $key);
        if (!$data) break;
        $all = array_merge($all, $data['result'] ?? []);
        $cursor = $data['cursor'] ?? null;
    } while ($cursor);
    return $all;
}

function parseAmount(array $tx, int $fallbackDecimals): float {
    if (isset($tx['value_decimal']) && $tx['value_decimal'] !== '') return (float)$tx['value_decimal'];
    $val = ltrim($tx['value'] ?? '0', '0') ?: '0';
    $dec = (int)($tx['token_decimals'] ?? $fallbackDecimals);
    if (strlen($val) <= $dec) return (float)('0.' . str_pad($val, $dec, '0', STR_PAD_LEFT));
    return (float)(substr($val, 0, strlen($val) - $dec) . '.' . substr($val, -$dec));
}

function sendTelegram(string $token, string $chatId, string $threadId, string $text): void {
    $url = "https://api.telegram.org/bot{$token}/sendMessage";
    $payload = json_encode([
        'chat_id'           => $chatId,
        'message_thread_id' => (int)$threadId,
        'text'              => $text,
        'parse_mode'        => 'HTML',
    ]);
    $ctx = stream_context_create([
        'http' => [
            'method'        => 'POST',
            'header'        => "Content-Type: application/json\r\n",
            'content'       => $payload,
            'timeout'       => 8,
            'ignore_errors' => true,
        ]
    ]);
    @file_get_contents($url, false, $ctx);
}

function shortAddr(string $addr): string {
    return substr($addr, 0, 6) . '…' . substr($addr, -4);
}

// Cargar txs ya notificadas
$seen = [];
if (file_exists($SEEN_FILE)) {
    $seen = json_decode(file_get_contents($SEEN_FILE), true) ?? [];
}

$walletLower = strtolower($WALLET);
$newSeen = $seen;
$notified = 0;

$chains = [
    ['token' => $USDT_BSC,  'chain' => 'bsc',  'symbol' => 'USDT', 'decimals' => 18],
    ['token' => $USDC_BASE, 'chain' => 'base', 'symbol' => 'USDC', 'decimals' => 6],
];

foreach ($chains as $c) {
    $txs = fetchTransfers($WALLET, $c['token'], $c['chain'], $FROM_DATE, $MORALIS_KEY);
    foreach ($txs as $tx) {
        if (strtolower($tx['to_address'])   !== $walletLower) continue;
        if (strtolower($tx['from_address']) === $walletLower) continue;

        $txHash = $tx['transaction_hash'] ?? '';
        $key    = $c['chain'] . '_' . $txHash;
        if (isset($seen[$key])) continue; // ya notificada

        $amount  = parseAmount($tx, $c['decimals']);
        $from    = shortAddr($tx['from_address']);
        $symbol  = $c['symbol'];
        $network = strtoupper($c['chain']);

        $msg = "💜 <b>¡Nueva donación cripto!</b>\n\n"
             . "💰 <b>\${$amount} {$symbol}</b> ({$network})\n"
             . "👛 Desde: <code>{$from}</code>\n\n"
             . "¡Gracias por apoyar a AnonimusTrade Live! 🙏";

        sendTelegram($TG_TOKEN, $TG_CHAT_ID, $TG_THREAD_ID, $msg);
        $newSeen[$key] = time();
        $notified++;
    }
}

// Guardar txs notificadas
file_put_contents($SEEN_FILE, json_encode($newSeen));
echo "OK — {$notified} nuevas notificaciones enviadas.\n";
