<?php
// ──────────────────────────────────────────────────────────────────
// telegram-auth-webhook.php — Router principal
// Recibe todos los mensajes del bot y delega al comando correcto.
// Agregar funcionalidades: crear un archivo en commands/ y añadir
// la ruta aquí abajo. NO poner lógica de negocio en este archivo.
// ──────────────────────────────────────────────────────────────────

require_once __DIR__ . '/config.php';

$TOKENS_FILE = __DIR__ . '/auth-tokens.json';
$TOKEN_TTL   = 900; // 15 minutos

$body   = file_get_contents('php://input');
$update = json_decode($body, true);
if (!$update) { http_response_code(200); exit; }

$message = $update['message'] ?? null;
if (!$message) { http_response_code(200); exit; }

$user_id   = $message['from']['id']         ?? null;
$username  = $message['from']['username']   ?? '';
$chat_id   = $message['chat']['id']         ?? null;
$chat_type = $message['chat']['type']       ?? '';
$text      = trim($message['text']          ?? '');

if (!$user_id) { http_response_code(200); exit; }

// Normalizar comando (quitar @botname si viene del grupo)
$cmd = strtolower(explode('@', explode(' ', $text)[0])[0]);

// ── ROUTER ──────────────────────────────────────────────────────────
if ($cmd === '/start' || $cmd === '/inicio') {
    if ($chat_type !== 'private') { http_response_code(200); exit; }
    require __DIR__ . '/commands/start.php';

} elseif ($cmd === '/acceso') {
    if ($chat_type !== 'private') { http_response_code(200); exit; }
    require __DIR__ . '/commands/acceso.php';

} elseif (in_array($cmd, ['/libros', '/librosp', '/toplibros', '/toplibrosp'])) {
    require __DIR__ . '/commands/libros.php';

} elseif ($chat_type === 'private') {
    // Fallback solo en DM
    sendMessage($TG_TOKEN, $user_id,
        "Comandos disponibles:\n\n" .
        "<b>/acceso</b> — Link de acceso a herramientas premium\n" .
        "<b>/libros</b> — Biblioteca completa de trading\n" .
        "<b>/toplibros</b> — Top 5 libros esenciales"
    );
}

http_response_code(200);

// ── FUNCIONES COMPARTIDAS ────────────────────────────────────────────
// Disponibles para todos los archivos en commands/

function sendMessage(string $token, int $chatId, string $text): void {
    $ctx = stream_context_create(['http' => [
        'method'        => 'POST',
        'header'        => "Content-Type: application/json\r\n",
        'content'       => json_encode([
            'chat_id'                  => $chatId,
            'text'                     => $text,
            'parse_mode'               => 'HTML',
            'disable_web_page_preview' => true,
        ]),
        'timeout'       => 8,
        'ignore_errors' => true,
    ]]);
    @file_get_contents("https://api.telegram.org/bot{$token}/sendMessage", false, $ctx);
}

function sendToGroup(string $token, string $chatId, string $text, string $threadId = ''): void {
    $payload = [
        'chat_id'                  => $chatId,
        'text'                     => $text,
        'parse_mode'               => 'HTML',
        'disable_web_page_preview' => true,
    ];
    if ($threadId) $payload['message_thread_id'] = (int)$threadId;
    $ctx = stream_context_create(['http' => [
        'method'        => 'POST',
        'header'        => "Content-Type: application/json\r\n",
        'content'       => json_encode($payload),
        'timeout'       => 8,
        'ignore_errors' => true,
    ]]);
    @file_get_contents("https://api.telegram.org/bot{$token}/sendMessage", false, $ctx);
}

function getMemberStatus(string $token, string $chatId, int $userId): string {
    $ctx = stream_context_create(['http' => ['timeout' => 8, 'ignore_errors' => true]]);
    $raw = @file_get_contents(
        "https://api.telegram.org/bot{$token}/getChatMember?chat_id=" . urlencode($chatId) . "&user_id={$userId}",
        false, $ctx
    );
    if (!$raw) return 'unknown';
    return json_decode($raw, true)['result']['status'] ?? 'unknown';
}
