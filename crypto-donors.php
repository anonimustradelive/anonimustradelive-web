<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/config.php';

$WALLET    = '0x20332BD20d55cc85282AFFe05BcC473bb8D18D91';
$USDT_BSC  = '0x55d398326f99059fF775485246999027B3197955';
$USDC_BASE = '0x833589fCD6eDb6E08f4c7C32D4f71b54bdA02913';
$FROM_DATE = '2026-05-23T00:00:00Z';

$CACHE_FILE = __DIR__ . '/crypto_donors_cache.json';
$CACHE_TTL  = 300; // 5 minutos

if (file_exists($CACHE_FILE) && (time() - filemtime($CACHE_FILE)) < $CACHE_TTL) {
    echo file_get_contents($CACHE_FILE);
    exit;
}

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
    $all    = [];
    $cursor = null;
    do {
        $url = "https://deep-index.moralis.io/api/v2.2/{$wallet}/erc20/transfers"
             . "?chain={$chain}"
             . "&contract_addresses%5B0%5D={$token}"
             . "&from_date=" . urlencode($fromDate)
             . "&limit=100"
             . ($cursor ? "&cursor=" . urlencode($cursor) : '');
        $data   = moralisGet($url, $key);
        if (!$data) break;
        $all    = array_merge($all, $data['result'] ?? []);
        $cursor = $data['cursor'] ?? null;
    } while ($cursor);
    return $all;
}

function parseAmount(array $tx, int $fallbackDecimals): float {
    if (isset($tx['value_decimal']) && $tx['value_decimal'] !== '') {
        return (float)$tx['value_decimal'];
    }
    $val      = ltrim($tx['value'] ?? '0', '0') ?: '0';
    $decimals = (int)($tx['token_decimals'] ?? $fallbackDecimals);
    if (strlen($val) <= $decimals) {
        return (float)('0.' . str_pad($val, $decimals, '0', STR_PAD_LEFT));
    }
    return (float)(substr($val, 0, strlen($val) - $decimals) . '.' . substr($val, -$decimals));
}

$donors = [];

$walletLower = strtolower($WALLET);

// BSC — USDT
foreach (fetchTransfers($WALLET, $USDT_BSC, 'bsc', $FROM_DATE, $MORALIS_KEY) as $tx) {
    if (strtolower($tx['to_address'])   !== $walletLower) continue; // debe ser entrante
    if (strtolower($tx['from_address']) === $walletLower) continue; // ignorar auto-transferencias
    $from = strtolower($tx['from_address']);
    $donors[$from] = ($donors[$from] ?? 0) + parseAmount($tx, 18);
}

// Base — USDC
foreach (fetchTransfers($WALLET, $USDC_BASE, 'base', $FROM_DATE, $MORALIS_KEY) as $tx) {
    if (strtolower($tx['to_address'])   !== $walletLower) continue;
    if (strtolower($tx['from_address']) === $walletLower) continue;
    $from = strtolower($tx['from_address']);
    $donors[$from] = ($donors[$from] ?? 0) + parseAmount($tx, 6);
}

$result = [];
foreach ($donors as $addr => $total) {
    if ($total < 0.01) continue;
    $result[] = ['address' => $addr, 'total' => round($total, 2)];
}
usort($result, fn($a, $b) => $b['total'] <=> $a['total']);
$result = array_slice($result, 0, 20);

$json = json_encode($result);
file_put_contents($CACHE_FILE, $json);
echo $json;
