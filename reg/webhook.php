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
    if (($message['chat']['type'] ?? '') !== 'private') exit;
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
        if (isGroupMember($uid)) {
            tgSend($chat_id, "✅ Ya eres miembro de la comunidad AnonimusTrade Live\\. ¡Nos vemos adentro\\! 🎉");
            return;
        }
        $existing = $pdo->prepare("SELECT status FROM registrations WHERE telegram_user_id = ? ORDER BY created_at DESC LIMIT 1");
        $existing->execute([$uid]);
        $reg = $existing->fetch(PDO::FETCH_ASSOC);
        if ($reg) {
            if ($reg['status'] === 'pending') {
                tgSend($chat_id, "⏳ Tu registro está *pendiente de aprobación*\\. Te notificaremos en cuanto sea revisado\\. ¡Gracias por tu paciencia\\!");
                return;
            }
            if ($reg['status'] === 'accepted') {
                tgSend($chat_id, "✅ Tu registro ya fue *aprobado*\\. Si no recibiste el link de invitación, contáctanos directamente\\.");
                return;
            }
        }
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
        tgSend($chat_id, "✅ Correo registrado\\.\n\nAhora escribe tu *ID de usuario* de *$pname*\\. Es el número de cuenta que te asigna la plataforma \\(solo números, ejemplo: `12345678`\\):");
        return;
    }

    if ($session['state'] === 'awaiting_platform_id') {
        if (!preg_match('/^\d{4,20}$/', $text)) {
            $d2 = $session['data'];
            $platform_labels = ['pepperstone' => 'Pepperstone', 'bingx' => 'BingX', 'bitunix' => 'Bitunix'];
            $pname2 = $platform_labels[$d2['platform'] ?? 'pepperstone'] ?? ($d2['platform'] ?? 'la plataforma');
            tgSend($chat_id,
                "❌ Eso no es un ID de usuario válido\\.\n\n" .
                "El ID de usuario es el *número de cuenta* que *$pname2* te asigna automáticamente al crear tu cuenta \\(solo números\\)\\.\n\n" .
                "Si todavía no has creado tu cuenta, créala primero con el botón que te enviamos, y luego regresa aquí a escribir tu número de cuenta\\."
            );
            return;
        }

        $d            = $session['data'];
        $platform     = $d['platform']     ?? 'pepperstone';
        $profile      = $d['profile']      ?? 'principiante';
        $asset        = $d['asset']        ?? null;
        $email        = $d['email']        ?? null;
        $is_migration = !empty($d['is_migration']) ? 1 : 0;
        $name         = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));

        $pdo->prepare("INSERT INTO registrations
            (telegram_user_id, telegram_name, telegram_username, profile_type, asset_type, platform, email, platform_user_id, is_migration)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)")
            ->execute([$uid, $name, $user['username'] ?? null, $profile, $asset, $platform, $email, $text, $is_migration]);

        setState($pdo, $uid, 'completed');

        if ($is_migration) {
            tgSend($chat_id,
                "✅ *¡Solicitud de migración recibida\\!*\n\n" .
                "Hemos registrado tu solicitud\\. Esperaremos a que Pepperstone procese el cambio de referido a AnonimusTrade Live\\.\n\n" .
                "En cuanto la migración culmine, te agregaremos a la comunidad de inmediato\\. ⚡\n\n" .
                "_Este proceso depende de los tiempos de Pepperstone \\(generalmente 24 a 48 horas\\)\\._\n\n" .
                "¡Gracias por tu paciencia\\! 🙏"
            );
        } else {
            tgSend($chat_id,
                "✅ *¡Registro recibido\\!*\n\n" .
                "Hemos recibido tu solicitud de acceso a la comunidad privada de AnonimusTrade Live\\.\n\n" .
                "Este es un proceso de verificación *manual*, por lo que puede tomar algunas horas\\. " .
                "En cuanto revisemos tu registro te enviaremos el link de invitación directamente aquí\\.\n\n" .
                "_Por favor sé paciente y no envíes tu ID nuevamente\\._\n\n" .
                "¡Gracias por tu interés en AnonimusTrade Live\\! 🙏"
            );
        }

        $plabels = ['pepperstone' => 'Pepperstone', 'bingx' => 'BingX', 'bitunix' => 'Bitunix'];
        $notif = "🔔 *Nueva solicitud de registro*" . ($is_migration ? " \\— _migración_" : "") . "\n\n" .
            "👤 " . htmlspecialchars($name) . "\n" .
            "🆔 @" . ($user['username'] ?? 'sin username') . "\n" .
            "📋 " . ucfirst($profile) . ($asset ? " · " . ucfirst($asset) : '') . "\n" .
            "🏦 " . ($plabels[$platform] ?? $platform) . ($is_migration ? " \\(migración\\)" : "") . "\n" .
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
                [
                    [['text' => '📈 Abrir cuenta en Pepperstone', 'url' => 'https://trk.pepperstonepartners.com/aff_c?offer_id=367&aff_id=45363']],
                    [['text' => '🔄 Ya tengo cuenta en Pepperstone', 'callback_data' => 'ya_tengo_pepperstone']],
                ]
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
                [
                    [['text' => '📈 Abrir cuenta en Pepperstone', 'url' => 'https://trk.pepperstonepartners.com/aff_c?offer_id=367&aff_id=45363']],
                    [['text' => '🔄 Ya tengo cuenta en Pepperstone', 'callback_data' => 'ya_tengo_pepperstone']],
                ]
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

        case 'ya_tengo_pepperstone':
            $session = getSession($pdo, $uid);
            $d = $session['data'];
            $d['is_migration'] = true;
            setState($pdo, $uid, 'awaiting_migration_confirm', $d);
            tgEdit($chat_id, $msg_id,
                "🔄 *Migración de cuenta a nuestro referido*\n\n" .
                "Para vincular tu cuenta existente al referido de AnonimusTrade Live, envía un correo a:\n" .
                "📧 *support@pepperstonepartners\\.com*\n\n" .
                "━━━━━━━━━━━━━━━\n\n" .
                "📋 *Copia y envía este mensaje:*\n\n" .
                "```\n" .
                "Asunto: Solicitud de cambio de IB referido\n\n" .
                "Estimado equipo de Pepperstone,\n\n" .
                "Solicito que mi cuenta sea migrada al referido\n" .
                "de AnonimusTrade Live con efecto inmediato.\n\n" .
                "Referido: https://trk.pepperstonepartners.com/aff_c?offer_id=367&aff_id=45363\n\n" .
                "Atentamente,\n" .
                "[Tu nombre completo]\n" .
                "```\n\n" .
                "━━━━━━━━━━━━━━━\n\n" .
                "Una vez enviado el correo, presiona el botón para continuar con tu registro\\.",
                [[['text' => '✅ Ya envié el correo', 'callback_data' => 'migr_confirmado']]]
            );
            break;

        case 'migr_confirmado':
            $session = getSession($pdo, $uid);
            $d = $session['data'];
            setState($pdo, $uid, 'awaiting_email', $d);
            tgEdit($chat_id, $msg_id,
                "✅ *Perfecto\\.*\n\n" .
                "Continuemos con tu registro\\. Escribe el *correo electrónico* de tu cuenta de Pepperstone:"
            );
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

function isGroupMember(int $uid): bool {
    if (!COMMUNITY_CHAT_ID) return false;
    $res = tgAPI('getChatMember', ['chat_id' => COMMUNITY_CHAT_ID, 'user_id' => $uid]);
    if (!($res['ok'] ?? false)) return false;
    return in_array($res['result']['status'] ?? '', ['creator', 'administrator', 'member', 'restricted']);
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
