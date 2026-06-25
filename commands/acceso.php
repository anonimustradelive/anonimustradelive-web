<?php
// Comando: /acceso
// Verifica membresía en el grupo y envía link mágico de un solo uso (15 min)
// Vars disponibles: $TG_TOKEN, $TG_CHAT_ID, $SITE_URL, $TOKENS_FILE, $TOKEN_TTL, $user_id, $username

$status  = getMemberStatus($TG_TOKEN, $TG_CHAT_ID, $user_id);
$allowed = in_array($status, ['creator', 'administrator', 'member', 'restricted']);

if (!$allowed) {
    sendMessage($TG_TOKEN, $user_id,
        "❌ <b>Acceso denegado</b>\n\n" .
        "Tu cuenta de Telegram no está en la comunidad privada de AnonimusTrade Live.\n\n" .
        "Si crees que es un error, contáctanos por WhatsApp."
    );
    return;
}

// Generar token de un solo uso
$token   = bin2hex(random_bytes(32));
$expires = time() + $TOKEN_TTL;

// Guardar token (limpiar expirados antes)
$tokens = [];
if (file_exists($TOKENS_FILE)) {
    $tokens = json_decode(file_get_contents($TOKENS_FILE), true) ?? [];
}
$tokens = array_filter($tokens, fn($t) => $t['expires'] > time());
$tokens[$token] = [
    'user_id'  => $user_id,
    'username' => $username,
    'expires'  => $expires,
    'used'     => false,
];
file_put_contents($TOKENS_FILE, json_encode($tokens));

$link = rtrim($SITE_URL, '/') . '/verify.php?token=' . urlencode($token);

sendMessage($TG_TOKEN, $user_id,
    "✅ <b>¡Membresía verificada!</b>\n\n" .
    "Haz clic en el link para acceder a las herramientas:\n\n" .
    "🔗 <a href=\"{$link}\">Abrir herramientas</a>\n\n" .
    "⏱ Este link expira en <b>15 minutos</b> y solo funciona <b>una vez</b>.\n" .
    "Si necesitas otro, vuelve a enviar /acceso."
);
