<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// ── API KEY ───────────────────────────────────────────────────────────────────
$ETHERSCAN_KEY = 'ZDC3DG6M2YYXF2K6G6RXP5JG2VGTRP7QZQ';
// ─────────────────────────────────────────────────────────────────────────────

$WALLET        = '0x20332BD20d55cc85282AFFe05BcC473bb8D18D91';
$START_TS      = 1779494400; // 2026-05-23 00:00:00 UTC
$USDT_BSC  = '0x55d398326f99059fF775485246999027B3197955';
$USDC_BASE = '0x833589fCD6eDb6E08f4c7C32D4f71b54bdA02913';

// Etherscan API V2 — una sola key para todas las cadenas
$BSC_CHAIN_ID  = '56';
$BASE_CHAIN_ID = '8453';
$API_BASE      = 'https://api.etherscan.io/v2/api';

$CACHE_FILE = __DIR__ . '/crypto_donors_cache.json';
$CACHE_TTL  = 300; // 5 minutos

if (file_exists($CACHE_FILE) && (time() - filemtime($CACHE_FILE)) < $CACHE_TTL) {
    echo file_get_contents($CACHE_FILE);
    exit;
}

function fetchTxs(string $url): array {
    $ctx = stream_context_create(['http' => ['timeout' => 8, 'ignore_errors' => true]]);
    $raw = @file_get_contents($url, false, $ctx);
    if (!$raw) return [];
    $data = json_decode($raw, true);
    if (($data['status'] ?? '') !== '1') return [];
    return $data['result'] ?? [];
}

function fromTokenUnits(string $val, int $decimals): float {
    $val = ltrim($val, '0') ?: '0';
    if (strlen($val) <= $decimals) {
        return (float)('0.' . str_pad($val, $decimals, '0', STR_PAD_LEFT));
    }
    $int  = substr($val, 0, strlen($val) - $decimals);
    $frac = substr($val, -$decimals);
    return (float)($int . '.' . $frac);
}

$donors = [];

// BSC — USDT (chainid=56, 18 decimals)
$url = "{$API_BASE}?chainid={$BSC_CHAIN_ID}&module=account&action=tokentx"
     . "&address={$WALLET}&contractaddress={$USDT_BSC}"
     . "&sort=desc&apikey={$ETHERSCAN_KEY}";
foreach (fetchTxs($url) as $tx) {
    if (strtolower($tx['to']) !== strtolower($WALLET)) continue;
    if ((int)($tx['timeStamp'] ?? 0) < $START_TS) continue;
    $from   = strtolower($tx['from']);
    $amount = fromTokenUnits($tx['value'], (int)($tx['tokenDecimal'] ?? 18));
    $donors[$from] = ($donors[$from] ?? 0) + $amount;
}

// Base — USDC (chainid=8453, 6 decimals)
$url = "{$API_BASE}?chainid={$BASE_CHAIN_ID}&module=account&action=tokentx"
     . "&address={$WALLET}&contractaddress={$USDC_BASE}"
     . "&sort=desc&apikey={$ETHERSCAN_KEY}";
foreach (fetchTxs($url) as $tx) {
    if (strtolower($tx['to']) !== strtolower($WALLET)) continue;
    if ((int)($tx['timeStamp'] ?? 0) < $START_TS) continue;
    $from   = strtolower($tx['from']);
    $amount = fromTokenUnits($tx['value'], (int)($tx['tokenDecimal'] ?? 6));
    $donors[$from] = ($donors[$from] ?? 0) + $amount;
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
