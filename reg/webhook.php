<?php
// Bot de registro AnonimusTrade Live
require_once __DIR__ . '/config.php';

$input  = file_get_contents('php://input');
$update = json_decode($input, true);
if (!$update) exit;

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (Exception $e) { exit; }

$callback = $update['callback_query'] ?? null;
$message  = $update['message']        ?? null;

if ($callback) {
    $user    = $callback['from'];
    $chat_id = $callback['message']['chat']['id'];
    $data    = $callback['data'];
    $msg_id  = $callback['message']['message_id'];
    tgAPI('answerCallbackQuery', ['callback_query_id' => $callback['id']]);
    handleCallback($pdo, $user, $chat_id, $data, $msg_id);
} elseif ($message) {
    $user    = $message['from'];
    $chat_id = $message['chat']['id'];
    $text    = trim($message['text'] ?? '');
    handleMessage($pdo, $user, $chat_id, $text);
}

// ── STATE ────────────────────────────────────────────────────────────────────

function getSession(PDO $pdo, int $uid): array {
    $stmt = $pdo->prepare("SELECT state, data FROM sessions WHERE user_id = ?");
    $stmt->execute([$uid]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row
        ? ['state' => $row['state'], 'data' => json_decode($row['data'] ?? '{}', true)]
        : ['state' => 'start', 'data' => []];
}

function setState(PDO $pdo, int $uid, string $state, array $data = []): void {
    $pdo->prepare("INSERT INTO sessions (user_id, state, data) VALUES (?, ?, ?)
                   ON DUPLICATE KEY UPDATE state = VALUES(state), data = VALUES(data), updated_at = NOW()")
        ->execute([$uid, $state, json_encode($data, JSON_UNESCAPED_UNICODE)]);
}

// ── HANDLERS ─────────────────────────────────────────────────────────────────

function handleMessage(PDO $pdo, array $user, int $chat_id, string $text): void {
    $uid = $user['id'];

    if (str_starts_with($text, '/start')) {
        sendWelcome($chat_id);
        setState($pdo, $uid, 'awaiting_type');
        return;
    }

    $session = getSession($pdo, $uid);

    if ($session['state'] === 'awaiting_email') {
        if (!filter_var($text, FILTER_VALIDATE_EMAIL)) {
            tgSend($chat_id, "Por favor escribe un correo electrónico válido\\.");
            return;
        }
        $d = $session['data'];
        $d['email'] = $text;
        $platform_names = ['pepperstone' => 'Pepperstone', 'bingx' => 'BingX', 'bitunix' => 'Bitunix'];
        $pname = $platform_names[$d['platform']] ?? $d['platform'];
        setState($pdo, $uid, 'awaiting_platform_id', $d);
        tgSend($chat_id, "✅ Correo registrado\\.\n\nAhora escribe tu *ID de usuario* de *$pname* \\(solo texto, sin fotos\\):");
        return;
    }

    if ($session['state'] === 'awaiting_platform_id') {
        if (empty($text)) {
            tgSend($chat_id, "Por favor escribe tu ID de usuario de la plataforma como texto \\(sin fotos ni archivos\\)\\.");
            return;
        }

        $d        = $session['data'];
        $platform = $d['platform'] ?? 'pepperstone';
        $profile  = $d['profile']  ?? 'principiante';
        $asset    = $d['asset']    ?? null;
        $email    = $d['email']    ?? null;
        $name     = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));

        $pdo->prepare("INSERT INTO registrations
            (telegram_user_id, telegram_name, telegram_username, profile_type, asset_type, platform, email, platform_user_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)")
            ->execute([$uid, $name, $user['username'] ?? null, $profile, $asset, $platform, $email, $text]);

        setState($pdo, $uid, 'completed');

        tgSend($chat_id,
            "✅ *¡Registro recibido!*\n\n" .
            "Hemos recibido tu solicitud de acceso a la comunidad privada de AnonimusTrade Live.\n\n" .
            "Nuestro equipo verificará tu registro manualmente y te enviaremos el link de invitación en breve.\n\n" .
            "¡Gracias por tu paciencia\\! 🙏"
        );

        $plabels = ['pepperstone' => 'Pepperstone', 'bingx' => 'BingX', 'bitunix' => 'Bitunix'];
        $notif = "🔔 *Nueva solicitud de registro*\n\n" .
            "👤 " . htmlspecialchars($name) . "\n" .
            "🆔 @" . ($user['username'] ?? 'sin username') . "\n" .
            "📋 " . ucfirst($profile) . ($asset ? " · " . ucfirst($asset) : '') . "\n" .
            "🏦 " . ($plabels[$platform] ?? $platform) . "\n" .
            "🔑 `" . htmlspecialchars($text) . "`\n\n" .
            "👉 https://reg\\.anonimustradelive\\.com";
        tgAPI('sendMessage', ['chat_id' => ADMIN_TG_ID, 'text' => $notif, 'parse_mode' => 'MarkdownV2']);
        return;
    }

    if ($session['state'] === 'completed') {
        tgSend($chat_id, "Tu registro ya fue recibido\\. Te notificaremos cuando sea aprobado\\. ✅");
        return;
    }

    // fallback
    sendWelcome($chat_id);
    setState($pdo, $uid, 'awaiting_type');
}

function handleCallback(PDO $pdo, array $user, int $chat_id, string $cb, int $msg_id): void {
    $uid = $user['id'];

    switch ($cb) {

        case 'tipo_aprender':
            tgEdit($chat_id, $msg_id,
                "📚 *¡Bienvenido, futuro trader\\!*\n\n" .
                "Para acceder a nuestra comunidad privada solo necesitas *un requisito*: abrir tu cuenta en Pepperstone con nuestro enlace de referido\\.\n\n" .
                "Al registrarte obtendrás acceso *gratuito* a:\n" .
                "✅ Chat directo con Richard y Ridolfi\n" .
                "✅ Llamadas exclusivas internas\n" .
                "✅ Curso de trading desde cero\n" .
                "✅ Estrategia rentable comprobada\n\n" .
                "Todo valorado en *más de \\$1,500 USD* — completamente *GRATIS* para nuestra comunidad\\.\n\n" .
                "⚠️ *Si estás en EE\\.UU\\. o la Unión Europea:* regístrate con las credenciales de tu país de origen, ya que nuestro partnership no aplica para residentes de esas regiones\\.\n" .
                "🔒 Además, necesitarás un VPN seguro para operar sin inconvenientes\\. Te recomendamos [Surfshark](https://surfshark.club/friend/2EHfq785) — hasta 3 meses gratis con nuestro enlace\\.\n\n" .
                "👇 Abre tu cuenta y luego escribe aquí el *correo electrónico* con el que te registraste en Pepperstone:",
                [[[
                    'text' => '📈 Abrir cuenta en Pepperstone',
                    'url'  => 'https://trk.pepperstonepartners.com/aff_c?offer_id=367&aff_id=45363'
                ]]]
            );
            setState($pdo, $uid, 'awaiting_email', ['profile' => 'principiante', 'platform' => 'pepperstone']);
            break;

        case 'tipo_trader':
            tgEdit($chat_id, $msg_id,
                "📈 *¿En qué mercado operas?*",
                [
                    [['text' => '₿ Crypto',         'callback_data' => 'asset_crypto']],
                    [['text' => '💹 Forex / CFDs',   'callback_data' => 'asset_tradicional']],
                ]
            );
            setState($pdo, $uid, 'awaiting_asset', ['profile' => 'trader']);
            break;

        case 'asset_crypto':
            tgEdit($chat_id, $msg_id,
                "₿ *¿Prefieres cuenta con o sin verificación KYC?*",
                [
                    [['text' => '✅ Con KYC — BingX',   'callback_data' => 'platform_bingx']],
                    [['text' => '🔒 Sin KYC — Bitunix', 'callback_data' => 'platform_bitunix']],
                ]
            );
            setState($pdo, $uid, 'awaiting_crypto_platform', ['profile' => 'trader', 'asset' => 'crypto']);
            break;

        case 'asset_tradicional':
            tgEdit($chat_id, $msg_id,
                "💹 *Forex y activos tradicionales*\n\n" .
                "Para operar Forex, índices y commodities te recomendamos *Pepperstone*, nuestro broker regulado oficial\\.\n\n" .
                "⚠️ *Si estás en EE\\.UU\\. o la Unión Europea:* regístrate con las credenciales de tu país de origen, ya que nuestro partnership no aplica para residentes de esas regiones\\.\n" .
                "🔒 Además, necesitarás un VPN seguro para operar sin inconvenientes\\. Te recomendamos [Surfshark](https://surfshark.club/friend/2EHfq785) — hasta 3 meses gratis con nuestro enlace\\.\n\n" .
                "👇 Abre tu cuenta y luego escribe aquí el *correo electrónico* con el que te registraste en Pepperstone:",
                [[[
                    'text' => '📈 Abrir cuenta en Pepperstone',
                    'url'  => 'https://trk.pepperstonepartners.com/aff_c?offer_id=367&aff_id=45363'
                ]]]
            );
            setState($pdo, $uid, 'awaiting_email', ['profile' => 'trader', 'asset' => 'tradicional', 'platform' => 'pepperstone']);
            break;

        case 'platform_bingx':
            tgEdit($chat_id, $msg_id,
                "🏦 *BingX — Con verificación KYC*\n\n" .
                "Abre tu cuenta con nuestro enlace y luego escribe aquí el *correo electrónico* con el que te registraste en BingX:",
                [[[
                    'text' => '🏦 Registrarse en BingX',
                    'url'  => 'https://bingxdao.com/partner/AnonimusTrade/'
                ]]]
            );
            setState($pdo, $uid, 'awaiting_email', ['profile' => 'trader', 'asset' => 'crypto', 'platform' => 'bingx']);
            break;

        case 'platform_bitunix':
            tgEdit($chat_id, $msg_id,
                "🔒 *Bitunix — Sin verificación KYC*\n\n" .
                "Abre tu cuenta con nuestro enlace y luego escribe aquí el *correo electrónico* con el que te registraste en Bitunix:",
                [[[
                    'text' => '🔒 Registrarse en Bitunix',
                    'url'  => 'https://www.bitunix.com/register?vipCode=KMrN'
                ]]]
            );
            setState($pdo, $uid, 'awaiting_email', ['profile' => 'trader', 'asset' => 'crypto', 'platform' => 'bitunix']);
            break;
    }
}

// ── HELPERS ──────────────────────────────────────────────────────────────────

function sendWelcome(int $chat_id): void {
    tgAPI('sendMessage', [
        'chat_id'      => $chat_id,
        'text'         =>
            "👋 *¡Bienvenido a AnonimusTrade Live\\!*\n\n" .
            "Este es el sistema de registro para nuestra *comunidad privada de trading*\\.\n\n" .
            "Análisis real · Entradas en vivo · Sin humo 🇩🇴\n\n" .
            "¿Cuál es tu perfil?",
        'parse_mode'   => 'MarkdownV2',
        'reply_markup' => ['inline_keyboard' => [
            [['text' => '📚 Quiero aprender', 'callback_data' => 'tipo_aprender']],
            [['text' => '📈 Ya soy trader',   'callback_data' => 'tipo_trader']],
        ]],
    ]);
}

function tgSend(int $chat_id, string $text): void {
    tgAPI('sendMessage', ['chat_id' => $chat_id, 'text' => $text, 'parse_mode' => 'MarkdownV2']);
}

function tgEdit(int $chat_id, int $msg_id, string $text, array $keyboard = []): void {
    $p = ['chat_id' => $chat_id, 'message_id' => $msg_id, 'text' => $text, 'parse_mode' => 'MarkdownV2', 'link_preview_options' => ['is_disabled' => true]];
    if ($keyboard) $p['reply_markup'] = ['inline_keyboard' => $keyboard];
    tgAPI('editMessageText', $p);
}

function tgAPI(string $method, array $params): array {
    $ctx = stream_context_create(['http' => [
        'method'        => 'POST',
        'header'        => "Content-Type: application/json\r\n",
        'content'       => json_encode($params, JSON_UNESCAPED_UNICODE),
        'timeout'       => 5,
        'ignore_errors' => true,
    ]]);
    $res = @file_get_contents("https://api.telegram.org/bot" . BOT_TOKEN . "/$method", false, $ctx);
    return json_decode($res ?: '{}', true);
}
