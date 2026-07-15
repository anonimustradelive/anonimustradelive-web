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

    // Soporte activo — interceptar antes del flujo de registro
    $suppStmt = $pdo->prepare("SELECT id FROM registrations WHERE telegram_user_id = ? AND support_active = 1 ORDER BY created_at DESC LIMIT 1");
    $suppStmt->execute([(int)$user['id']]);
    $suppReg = $suppStmt->fetch(PDO::FETCH_ASSOC);
    if ($suppReg) {
        handleSupportMessage($pdo, $user, $chat_id, $message, $suppReg['id']);
    } else {
        handleMessage($pdo, $user, $chat_id, $text);
    }
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

    // ── /start ───────────────────────────────────────────────────────────────
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
                tgSend($chat_id, "✅ Tu registro ya fue *aprobado*\\. Si no recibiste el link, [contáctanos directamente](https://t.me/+18495683020)\\.");
                return;
            }
            // rechazado: cae al sendWelcome para permitir nuevo intento
        }
        sendWelcome($chat_id);
        setState($pdo, $uid, 'awaiting_type');
        return;
    }

    $session = getSession($pdo, $uid);

    // ── Estados que esperan botones — nunca caer al fallback ────────────────
    if (in_array($session['state'], ['awaiting_type', 'awaiting_asset', 'awaiting_crypto_platform'])) {
        tgSend($chat_id,
            "👆 Por favor usa los botones de arriba para continuar\\.\n\n" .
            "Si no los ves, escribe /start para reiniciar\\."
        );
        return;
    }

    if ($session['state'] === 'awaiting_migration_confirm') {
        tgSend($chat_id,
            "📧 Cuando hayas enviado el correo a Pepperstone, presiona el botón de arriba para continuar 👆\n\n" .
            "Si no lo ves, escribe /start para reiniciar\\."
        );
        return;
    }

    if ($session['state'] === 'awaiting_confirm') {
        tgSend($chat_id,
            "👆 Por favor usa los botones para *confirmar* o *corregir* tus datos\\.\n\n" .
            "Si no los ves, escribe /start para reiniciar\\."
        );
        return;
    }

    // ── awaiting_email ───────────────────────────────────────────────────────
    if ($session['state'] === 'awaiting_email') {
        if (!filter_var($text, FILTER_VALIDATE_EMAIL)) {
            tgSend($chat_id,
                "❌ *Ese no parece un correo válido\\.*\n\n" .
                "Escribe tu dirección completa incluyendo el @\\.\n" .
                "Ejemplo: `tunombre@gmail\\.com`"
            );
            return;
        }
        $d = $session['data'];
        $d['email'] = $text;
        $plabels = ['pepperstone' => 'Pepperstone', 'bingx' => 'BingX', 'bitunix' => 'Bitunix', 'zoomex' => 'Zoomex'];
        $pname = $plabels[$d['platform']] ?? $d['platform'];
        setState($pdo, $uid, 'awaiting_platform_id', $d);
        tgSend($chat_id,
            "✅ *Correo registrado\\.*\n\n" .
            "Ahora escribe tu *ID de usuario* de *$pname*\\.\n" .
            "Es el número de cuenta que te asigna la plataforma \\(solo números, ejemplo: `12345678`\\)\\.\n\n" .
            "_Si no lo recuerdas ahora, ábrela y búscalo en tu perfil\\. El bot te estará esperando aquí\\. 🕐_"
        );
        return;
    }

    // ── awaiting_platform_id — valida y muestra confirmación ────────────────
    if ($session['state'] === 'awaiting_platform_id') {
        $d = $session['data'];
        $plabels = ['pepperstone' => 'Pepperstone', 'bingx' => 'BingX', 'bitunix' => 'Bitunix', 'zoomex' => 'Zoomex'];
        $pname = $plabels[$d['platform'] ?? 'pepperstone'] ?? 'la plataforma';

        if (!preg_match('/^\d{4,20}$/', $text)) {
            tgSend($chat_id,
                "❌ *Eso no es un ID de usuario válido\\.*\n\n" .
                "El ID de usuario es el *número de cuenta* que *$pname* te asigna al crear tu cuenta \\(solo números\\)\\.\n\n" .
                "Si todavía no has creado tu cuenta, créala primero con el botón que te enviamos y luego regresa aquí\\."
            );
            return;
        }

        // Guardar en sesión y pedir confirmación — NO insertar aún
        $d['platform_id'] = $text;
        setState($pdo, $uid, 'awaiting_confirm', $d);

        $migr_line = !empty($d['is_migration']) ? "\n🔄 _Migración de cuenta existente_" : "";

        tgSend($chat_id,
            "📋 *Revisa tus datos antes de confirmar:*\n\n" .
            "🏦 *Plataforma:* " . mdEscape($pname) . $migr_line . "\n" .
            "📧 *Correo:* " . mdEscape($d['email'] ?? '') . "\n" .
            "🔑 *ID de cuenta:* `" . $text . "`\n\n" .
            "¿Todo está correcto?",
            [
                [['text' => '✅ Confirmar y registrarme', 'callback_data' => 'reg_confirmar']],
                [['text' => '✏️ Corregir datos',          'callback_data' => 'reg_corregir']],
            ]
        );
        return;
    }

    // ── completed — verificar estado real en DB ──────────────────────────────
    if ($session['state'] === 'completed') {
        $stmt = $pdo->prepare("SELECT status FROM registrations WHERE telegram_user_id = ? ORDER BY created_at DESC LIMIT 1");
        $stmt->execute([$uid]);
        $reg = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($reg && $reg['status'] === 'rejected') {
            tgSend($chat_id,
                "❌ Tu solicitud anterior no fue aprobada\\.\n\n" .
                "Si deseas intentarlo nuevamente o crees que hubo un error, escribe /start\\.\n\n" .
                "También puedes [contactarnos directamente](https://t.me/+18495683020)\\."
            );
        } elseif ($reg && $reg['status'] === 'accepted') {
            tgSend($chat_id,
                "✅ Tu registro ya fue *aprobado*\\. Si tienes algún inconveniente, [contáctanos directamente](https://t.me/+18495683020)\\."
            );
        } else {
            tgSend($chat_id,
                "⏳ Tu registro está siendo revisado\\. Te notificaremos en cuanto sea aprobado\\. ✅"
            );
        }
        return;
    }

    // ── fallback (estado desconocido) ────────────────────────────────────────
    sendWelcome($chat_id);
    setState($pdo, $uid, 'awaiting_type');
}

function handleCallback(PDO $pdo, array $user, int $chat_id, string $cb, int $msg_id): void {
    $uid = $user['id'];

    switch ($cb) {

        // ── PERFIL ────────────────────────────────────────────────────────────

        case 'tipo_aprender':
            tgEdit($chat_id, $msg_id,
                "📚 *¡Perfecto, comencemos\\!*\n\n" .
                "Para unirte solo necesitas *una cosa*: abrir tu cuenta en Pepperstone con nuestro enlace de referido\\. Es gratis y tarda unos minutos\\.\n\n" .
                "*¿Qué obtienes al unirte?*\n" .
                "✅ Chat directo con Richard y Ridolfi\n" .
                "✅ Llamadas y clases exclusivas\n" .
                "✅ Estrategia rentable comprobada\n\n" .
                "Todo *100% GRATIS* para ti 🎁\n\n" .
                "━━━━━━━━━━━━━━━\n" .
                "⚠️ *¿Estás en EE\\.UU\\. o la Unión Europea?*\n" .
                "Regístrate con credenciales de tu país de origen\\. También necesitarás un VPN — recomendamos [Surfshark](https://surfshark.club/friend/2EHfq785) \\(hasta 3 meses gratis\\)\\.\n" .
                "━━━━━━━━━━━━━━━\n\n" .
                "*Paso 1 →* Abre tu cuenta con el botón de abajo\n" .
                "*Paso 2 →* Regresa aquí y escribe tu correo de Pepperstone\n\n" .
                "_Tómate tu tiempo, el bot te estará esperando aquí\\. 🕐_",
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
                    [['text' => '₿ Crypto',       'callback_data' => 'asset_crypto']],
                    [['text' => '💹 Forex / CFDs', 'callback_data' => 'asset_tradicional']],
                ]
            );
            setState($pdo, $uid, 'awaiting_asset', ['profile' => 'trader']);
            break;

        // ── ACTIVOS ───────────────────────────────────────────────────────────

        case 'asset_crypto':
            tgEdit($chat_id, $msg_id,
                "₿ *¿Cómo prefieres tu cuenta de crypto?*\n\n" .
                "📋 *Con verificación \\(KYC\\):* subes una foto de tu ID\\. Mayores límites de retiro\\.\n" .
                "🔓 *Sin verificación:* empiezas de inmediato, sin documentos\\.\n\n" .
                "¿Cuál prefieres?",
                [
                    [['text' => '✅ Con verificación — BingX',   'callback_data' => 'platform_bingx']],
                    [['text' => '🔒 Sin verificación — Bitunix', 'callback_data' => 'platform_bitunix']],
                    [['text' => '🔒 Sin verificación — Zoomex',  'callback_data' => 'platform_zoomex']],
                ]
            );
            setState($pdo, $uid, 'awaiting_crypto_platform', ['profile' => 'trader', 'asset' => 'crypto']);
            break;

        case 'asset_tradicional':
            tgEdit($chat_id, $msg_id,
                "💹 *Forex y activos tradicionales*\n\n" .
                "Para operar Forex, índices y commodities te recomendamos *Pepperstone*, nuestro broker regulado oficial\\.\n\n" .
                "━━━━━━━━━━━━━━━\n" .
                "⚠️ *¿Estás en EE\\.UU\\. o la Unión Europea?*\n" .
                "Regístrate con credenciales de tu país de origen\\. También necesitarás un VPN — recomendamos [Surfshark](https://surfshark.club/friend/2EHfq785) \\(hasta 3 meses gratis\\)\\.\n" .
                "━━━━━━━━━━━━━━━\n\n" .
                "*Paso 1 →* Abre tu cuenta con el botón de abajo\n" .
                "*Paso 2 →* Regresa aquí y escribe tu correo de Pepperstone\n\n" .
                "_Tómate tu tiempo, el bot te estará esperando aquí\\. 🕐_",
                [
                    [['text' => '📈 Abrir cuenta en Pepperstone', 'url' => 'https://trk.pepperstonepartners.com/aff_c?offer_id=367&aff_id=45363']],
                    [['text' => '🔄 Ya tengo cuenta en Pepperstone', 'callback_data' => 'ya_tengo_pepperstone']],
                ]
            );
            setState($pdo, $uid, 'awaiting_email', ['profile' => 'trader', 'asset' => 'tradicional', 'platform' => 'pepperstone']);
            break;

        // ── PLATAFORMAS ───────────────────────────────────────────────────────

        case 'platform_bingx':
            tgEdit($chat_id, $msg_id,
                "🏦 *BingX — Con verificación KYC*\n\n" .
                "*Paso 1 →* Abre tu cuenta con el botón de abajo\n" .
                "*Paso 2 →* Regresa aquí y escribe tu correo de BingX\n\n" .
                "_Tómate tu tiempo, el bot te estará esperando aquí\\. 🕐_",
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
                "No necesitas subir documentos de identidad\\. Puedes empezar a operar de inmediato\\.\n\n" .
                "*Paso 1 →* Abre tu cuenta con el botón de abajo\n" .
                "*Paso 2 →* Regresa aquí y escribe tu correo de Bitunix\n\n" .
                "_Tómate tu tiempo, el bot te estará esperando aquí\\. 🕐_",
                [[[
                    'text' => '🔒 Registrarse en Bitunix',
                    'url'  => 'https://www.bitunix.com/register?vipCode=KMrN'
                ]]]
            );
            setState($pdo, $uid, 'awaiting_email', ['profile' => 'trader', 'asset' => 'crypto', 'platform' => 'bitunix']);
            break;

        case 'platform_zoomex':
            tgEdit($chat_id, $msg_id,
                "🔒 *Zoomex — Sin verificación KYC*\n\n" .
                "No necesitas subir documentos de identidad\\. Puedes empezar a operar de inmediato\\.\n\n" .
                "*Paso 1 →* Abre tu cuenta con el botón de abajo\n" .
                "*Paso 2 →* Regresa aquí y escribe tu correo de Zoomex\n\n" .
                "_Tómate tu tiempo, el bot te estará esperando aquí\\. 🕐_",
                [[[
                    'text' => '🔒 Registrarse en Zoomex',
                    'url'  => 'https://partner.zoomex.com/aff/ZX904826'
                ]]]
            );
            setState($pdo, $uid, 'awaiting_email', ['profile' => 'trader', 'asset' => 'crypto', 'platform' => 'zoomex']);
            break;

        // ── MIGRACIÓN ─────────────────────────────────────────────────────────

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
                "📋 *Copia y envía este mensaje \\(copia todo\\):*\n\n" .
                "```\n" .
                "Asunto: Solicitud de cambio de IB referido\n\n" .
                "Estimado equipo de Pepperstone,\n\n" .
                "Solicito que mi cuenta sea migrada al referido\n" .
                "de AnonimusTrade Live con efecto inmediato.\n\n" .
                "Referido: https://trk.pepperstonepartners.com/aff_c?offer_id=367&aff_id=45363\n\n" .
                "Atentamente,\n" .
                "[Tu nombre completo]\n" .
                "```\n\n" .
                "⚠️ *Antes de enviarlo:* reemplaza \\[Tu nombre completo\\] con tu nombre real\\.\n\n" .
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

        // ── CONFIRMACIÓN DE REGISTRO ─────────────────────────────────────────

        case 'reg_confirmar':
            $session = getSession($pdo, $uid);
            $d = $session['data'];

            if (empty($d['platform_id']) || empty($d['email'])) {
                tgEdit($chat_id, $msg_id, "❌ Ha ocurrido un error\\. Por favor escribe /start para reiniciar\\.");
                break;
            }

            $platform    = $d['platform']    ?? 'pepperstone';
            $profile     = $d['profile']     ?? 'principiante';
            $asset       = $d['asset']       ?? null;
            $email       = $d['email'];
            $platform_id = $d['platform_id'];
            $is_migration = !empty($d['is_migration']) ? 1 : 0;
            $name         = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));

            $pdo->prepare("INSERT INTO registrations
                (telegram_user_id, telegram_name, telegram_username, profile_type, asset_type, platform, email, platform_user_id, is_migration)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)")
                ->execute([$uid, $name, $user['username'] ?? null, $profile, $asset, $platform, $email, $platform_id, $is_migration]);
            $reg_id = $pdo->lastInsertId();

            setState($pdo, $uid, 'completed');

            // Editar el mensaje de confirmación para quitar los botones
            tgEdit($chat_id, $msg_id, "✅ *¡Datos confirmados\\!*");

            if ($is_migration) {
                tgSend($chat_id,
                    "✅ *¡Solicitud de migración recibida\\!*\n\n" .
                    "Hemos registrado tu solicitud\\. Esperaremos a que Pepperstone procese el cambio de referido a AnonimusTrade Live\\.\n\n" .
                    "En cuanto la migración culmine, te agregaremos a la comunidad de inmediato\\. ⚡\n\n" .
                    "_Este proceso depende de los tiempos de Pepperstone \\(generalmente 24 a 48 horas\\)\\._\n\n" .
                    "¡Gracias por tu paciencia\\! 🙏"
                );
                $pdo->prepare("UPDATE registrations SET migration_status='pending' WHERE id=?")->execute([$reg_id]);
                tgSend($chat_id,
                    "📧 *Un último paso*\n\n" .
                    "Cuando recibas el correo de Pepperstone confirmando que tu cuenta ya fue migrada a nuestro referido, avísanos presionando el botón de abajo\\.\n\n" .
                    "⚠️ *Solo podrás presionar este botón una vez*, así que úsalo cuando ya hayas recibido la confirmación real de Pepperstone\\.",
                    [[['text' => '✅ Ya recibí la confirmación de Pepperstone', 'callback_data' => 'migr_notify']]]
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

            $plabels = ['pepperstone' => 'Pepperstone', 'bingx' => 'BingX', 'bitunix' => 'Bitunix', 'zoomex' => 'Zoomex'];
            $notif = "🔔 *Nueva solicitud de registro*" . ($is_migration ? " — _migración_" : "") . "\n\n" .
                "👤 " . mdEscape($name) . "\n" .
                "🆔 @" . mdEscape($user['username'] ?? 'sin username') . "\n" .
                "📋 " . ucfirst($profile) . ($asset ? " · " . ucfirst($asset) : '') . "\n" .
                "🏦 " . ($plabels[$platform] ?? $platform) . ($is_migration ? " \\(migración\\)" : "") . "\n" .
                "🔑 `" . htmlspecialchars($platform_id) . "`\n\n" .
                "👉 https://reg\\.anonimustradelive\\.com";
            tgAPI('sendMessage', ['chat_id' => ADMIN_TG_ID, 'text' => $notif, 'parse_mode' => 'MarkdownV2']);
            break;

        case 'reg_corregir':
            $session = getSession($pdo, $uid);
            $d = $session['data'];
            unset($d['email'], $d['platform_id']);
            setState($pdo, $uid, 'awaiting_email', $d);

            $plabels = ['pepperstone' => 'Pepperstone', 'bingx' => 'BingX', 'bitunix' => 'Bitunix', 'zoomex' => 'Zoomex'];
            $pname = $plabels[$d['platform'] ?? 'pepperstone'] ?? 'la plataforma';

            tgEdit($chat_id, $msg_id, "✏️ *Vamos a corregirlos\\.*");
            tgSend($chat_id,
                "Escribe nuevamente el *correo electrónico* de tu cuenta en *" . mdEscape($pname) . "*:"
            );
            break;

        // ── KYC ──────────────────────────────────────────────────────────────

        case 'kyc_done':
            $stmt = $pdo->prepare("SELECT * FROM registrations WHERE telegram_user_id = ? AND platform = 'bingx' AND status = 'pending' ORDER BY created_at DESC LIMIT 1");
            $stmt->execute([$uid]);
            $reg = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($reg) {
                $pdo->prepare("UPDATE registrations SET kyc_status='completed', updated_at=NOW() WHERE id=?")->execute([$reg['id']]);
                tgEdit($chat_id, $msg_id,
                    "✅ *¡Gracias\\!*\n\n" .
                    "Hemos recibido tu confirmación de KYC completado\\. Nuestro equipo revisará tu cuenta y te notificaremos en cuanto se apruebe tu acceso\\."
                );
                $name = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
                tgAPI('sendMessage', [
                    'chat_id' => ADMIN_TG_ID,
                    'text' => "✅ *KYC completado*\n\n" .
                        "👤 " . mdEscape($name) . "\n" .
                        "🆔 @" . mdEscape($user['username'] ?? 'sin username') . "\n" .
                        "🏦 BingX · ID \\#" . $reg['id'] . "\n\n" .
                        "👉 https://reg\\.anonimustradelive\\.com",
                    'parse_mode' => 'MarkdownV2',
                ]);
            }
            break;

        // ── MIGRACIÓN NOTIFY ─────────────────────────────────────────────────

        case 'migr_notify':
            $stmt = $pdo->prepare("SELECT * FROM registrations WHERE telegram_user_id = ? AND platform = 'pepperstone' AND is_migration = 1 AND status = 'pending' ORDER BY created_at DESC LIMIT 1");
            $stmt->execute([$uid]);
            $reg = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($reg && $reg['migration_status'] !== 'notified') {
                $pdo->prepare("UPDATE registrations SET migration_status='notified', updated_at=NOW() WHERE id=?")->execute([$reg['id']]);
                tgEdit($chat_id, $msg_id,
                    "✅ *¡Gracias por avisarnos\\!*\n\n" .
                    "Vamos a verificar tu migración y te daremos acceso en cuanto confirmemos que ya estás bajo nuestro referido\\."
                );
                $name = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
                tgAPI('sendMessage', [
                    'chat_id' => ADMIN_TG_ID,
                    'text' => "🔄 *Confirmación de migración Pepperstone*\n\n" .
                        "👤 " . mdEscape($name) . "\n" .
                        "🆔 @" . mdEscape($user['username'] ?? 'sin username') . "\n" .
                        "🏦 Pepperstone · ID \\#" . $reg['id'] . "\n\n" .
                        "Verifica si ya aparece bajo nuestro referido\\.\n\n" .
                        "👉 https://reg\\.anonimustradelive\\.com",
                    'parse_mode' => 'MarkdownV2',
                ]);
            }
            break;
    }
}

// ── SOPORTE ──────────────────────────────────────────────────────────────────

function handleSupportMessage(PDO $pdo, array $user, int $chat_id, array $message, int $reg_id): void {
    $uid  = (int)$user['id'];
    $text = trim($message['text'] ?? '');

    // Detectar contenido no textual
    $has_media = isset($message['photo'])      || isset($message['video'])     ||
                 isset($message['document'])   || isset($message['voice'])     ||
                 isset($message['audio'])      || isset($message['sticker'])   ||
                 isset($message['video_note']) || isset($message['animation']) ||
                 isset($message['contact'])    || isset($message['location']);

    if ($has_media || $text === '') {
        tgSend($chat_id,
            "⚠️ *Solo texto permitido*\n\n" .
            "Este canal de soporte no puede recibir imágenes, archivos ni otros tipos de contenido\\.\n\n" .
            "Por favor escribe tu mensaje en texto\\."
        );
        return;
    }

    // Guardar mensaje entrante
    $pdo->prepare("INSERT INTO support_messages (registration_id, telegram_user_id, direction, message) VALUES (?, ?, 'in', ?)")
        ->execute([$reg_id, $uid, $text]);

    // Notificar al admin por Telegram
    $name     = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
    $username = !empty($user['username']) ? '@' . $user['username'] : 'ID: ' . $uid;
    tgAPI('sendMessage', [
        'chat_id'    => ADMIN_TG_ID,
        'text'       => "💬 *Respuesta de soporte*\n\n" .
                        "👤 " . mdEscape($name) . " \\(" . mdEscape($username) . "\\)\n\n" .
                        mdEscape($text) . "\n\n" .
                        "👉 https://reg\\.anonimustradelive\\.com",
        'parse_mode' => 'MarkdownV2',
    ]);
}

// ── HELPERS ──────────────────────────────────────────────────────────────────

function sendWelcome(int $chat_id): void {
    tgAPI('sendMessage', [
        'chat_id'      => $chat_id,
        'text'         =>
            "👋 *¡Bienvenido a AnonimusTrade Live\\!*\n\n" .
            "Este es el sistema de registro para nuestra *comunidad privada de trading*\\.\n\n" .
            "Análisis real · Entradas en vivo · Sin humo 🇩🇴\n\n" .
            "¿Cuál es tu perfil?\n\n" .
            "_Si en algún momento necesitas reiniciar, escribe /start\\._",
        'parse_mode'   => 'MarkdownV2',
        'reply_markup' => ['inline_keyboard' => [
            [['text' => '📚 Quiero aprender a tradear', 'callback_data' => 'tipo_aprender']],
            [['text' => '📈 Ya soy trader',              'callback_data' => 'tipo_trader']],
        ]],
    ]);
}

function isGroupMember(int $uid): bool {
    if (!COMMUNITY_CHAT_ID) return false;
    $res = tgAPI('getChatMember', ['chat_id' => COMMUNITY_CHAT_ID, 'user_id' => $uid]);
    if (!($res['ok'] ?? false)) return false;
    return in_array($res['result']['status'] ?? '', ['creator', 'administrator', 'member', 'restricted']);
}

function mdEscape(string $text): string {
    return preg_replace('/([_*\[\]()~`>#+\-=|{}.!\\\\])/', '\\\\$1', $text);
}

function tgSend(int $chat_id, string $text, array $keyboard = []): void {
    $p = ['chat_id' => $chat_id, 'text' => $text, 'parse_mode' => 'MarkdownV2', 'link_preview_options' => ['is_disabled' => true]];
    if ($keyboard) $p['reply_markup'] = ['inline_keyboard' => $keyboard];
    tgAPI('sendMessage', $p);
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
