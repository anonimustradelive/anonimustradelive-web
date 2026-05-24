<?php
// ──────────────────────────────────────────────────────────────────
// telegram-auth-webhook.php
// Recibe mensajes del bot @AnonimusTradeLiveDonBot.
// Si el usuario envía /acceso, verifica membresía en el grupo
// privado y le manda un link mágico de un solo uso (15 min).
// ──────────────────────────────────────────────────────────────────

require_once __DIR__ . '/config.php';
// Necesita en config.php:
//   $TG_TOKEN       — token del bot
//   $TG_CHAT_ID     — ID del grupo privado (-1001517888411)
//   $SITE_URL       — URL base del sitio (https://anonimustradelive.com)

$TOKENS_FILE = __DIR__ . '/auth-tokens.json';
$TOKEN_TTL   = 900; // 15 minutos en segundos

// ── Leer el update de Telegram ──
$body   = file_get_contents('php://input');
$update = json_decode($body, true);

if (!$update) { http_response_code(200); exit; }

// Solo procesamos mensajes de texto
$message = $update['message'] ?? null;
if (!$message) { http_response_code(200); exit; }

$user_id  = $message['from']['id']         ?? null;
$username = $message['from']['username']   ?? '';
$text     = trim($message['text']          ?? '');
$chat_id  = $message['chat']['id']         ?? null;

// Solo respondemos en chats privados (DM con el bot)
if (($message['chat']['type'] ?? '') !== 'private') {
    http_response_code(200);
    exit;
}

if (!$user_id) { http_response_code(200); exit; }

// ── Responder a /start ──
if ($text === '/start' || $text === '/inicio') {
    sendMessage($TG_TOKEN, $user_id,
        "👋 <b>Hola!</b> Soy el bot de AnonimusTrade Live.\n\n" .
        "Para acceder a las herramientas premium, envíame:\n\n" .
        "<b>/acceso</b>\n\n" .
        "Verificaré si eres miembro de la comunidad y te enviaré un link de acceso."
    );
    http_response_code(200);
    exit;
}

// ── Responder a /acceso ──
if ($text === '/acceso') {

    // 1. Verificar membresía en el grupo privado
    $status = getMemberStatus($TG_TOKEN, $TG_CHAT_ID, $user_id);

    $allowed = in_array($status, ['creator', 'administrator', 'member', 'restricted']);

    if (!$allowed) {
        sendMessage($TG_TOKEN, $user_id,
            "❌ <b>Acceso denegado</b>\n\n" .
            "Tu cuenta de Telegram no está en la comunidad privada de AnonimusTrade Live.\n\n" .
            "Si crees que es un error, contáctanos por WhatsApp."
        );
        http_response_code(200);
        exit;
    }

    // 2. Generar token de un solo uso
    $token   = bin2hex(random_bytes(32)); // 64 chars hex, criptográficamente seguro
    $expires = time() + $TOKEN_TTL;

    // 3. Guardar token
    $tokens = [];
    if (file_exists($TOKENS_FILE)) {
        $tokens = json_decode(file_get_contents($TOKENS_FILE), true) ?? [];
    }
    // Limpiar tokens expirados antes de guardar
    $tokens = array_filter($tokens, fn($t) => $t['expires'] > time());
    $tokens[$token] = [
        'user_id'  => $user_id,
        'username' => $username,
        'expires'  => $expires,
        'used'     => false,
    ];
    file_put_contents($TOKENS_FILE, json_encode($tokens));

    // 4. Construir link mágico
    $link = rtrim($SITE_URL, '/') . '/verify.php?token=' . urlencode($token);

    // 5. Enviar link al usuario
    sendMessage($TG_TOKEN, $user_id,
        "✅ <b>¡Membresía verificada!</b>\n\n" .
        "Haz clic en el link para acceder a las herramientas:\n\n" .
        "🔗 <a href=\"{$link}\">Abrir herramientas</a>\n\n" .
        "⏱ Este link expira en <b>15 minutos</b> y solo funciona <b>una vez</b>.\n" .
        "Si necesitas otro, vuelve a enviar /acceso."
    );

    http_response_code(200);
    exit;
}

// Cualquier otro mensaje
sendMessage($TG_TOKEN, $user_id,
    "Envía <b>/acceso</b> para obtener tu link de acceso a las herramientas premium."
);
http_response_code(200);

// ── Funciones auxiliares ──

function getMemberStatus(string $token, string $chatId, int $userId): string {
    $url = "https://api.telegram.org/bot{$token}/getChatMember"
         . "?chat_id=" . urlencode($chatId)
         . "&user_id={$userId}";
    $ctx = stream_context_create([
        'http' => ['timeout' => 8, 'ignore_errors' => true]
    ]);
    $raw = @file_get_contents($url, false, $ctx);
    if (!$raw) return 'unknown';
    $data = json_decode($raw, true);
    return $data['result']['status'] ?? 'unknown';
}

function sendMessage(string $token, int $chatId, string $text): void {
    $url     = "https://api.telegram.org/bot{$token}/sendMessage";
    $payload = json_encode([
        'chat_id'                  => $chatId,
        'text'                     => $text,
        'parse_mode'               => 'HTML',
        'disable_web_page_preview' => true,
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
