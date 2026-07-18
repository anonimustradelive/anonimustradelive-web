<?php
// Tasa de cambio USD → DOP en vivo, misma fuente (gratuita, sin API key) que usa
// finance.anonimustradelive.com. Sin caché propia: se llama solo cuando el admin
// activa/actualiza la moneda Pesos en el formulario de facturas, no en cada carga.

function fetchLiveExchangeRate(): array {
    $sources = [
        'https://cdn.jsdelivr.net/npm/@fawazahmed0/currency-api@latest/v1/currencies/usd.json',
        'https://latest.currency-api.pages.dev/v1/currencies/usd.json',
    ];
    foreach ($sources as $url) {
        $json = fetchUrlWithTimeout($url);
        if ($json === null) continue;
        $data = json_decode($json, true);
        if (isset($data['usd']['dop']) && is_numeric($data['usd']['dop'])) {
            return ['rate' => round((float)$data['usd']['dop'], 4), 'source' => 'live'];
        }
    }
    return ['rate' => 60.5, 'source' => 'fallback'];
}

function fetchUrlWithTimeout(string $url): ?string {
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $res = curl_exec($ch);
        $ok = $res !== false && curl_getinfo($ch, CURLINFO_HTTP_CODE) === 200;
        curl_close($ch);
        return $ok ? $res : null;
    }
    $ctx = stream_context_create(['http' => ['timeout' => 5]]);
    $res = @file_get_contents($url, false, $ctx);
    return $res !== false ? $res : null;
}
