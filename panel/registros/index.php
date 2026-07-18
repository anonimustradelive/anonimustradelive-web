<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
$pdo = getPDO();

// ── CHAT AJAX ────────────────────────────────────────────────────────────────
$chat_ajax = ['chat_open','chat_close','chat_send','chat_get','chat_unread_counts'];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['action'] ?? '', $chat_ajax)) {
    header('Content-Type: application/json');
    $act = $_POST['action'];

    if ($act === 'chat_open') {
        $id = (int)($_POST['reg_id'] ?? 0);
        $pdo->prepare("UPDATE registrations SET support_active=1, updated_at=NOW() WHERE id=?")->execute([$id]);
        echo json_encode(['ok' => true]);
        exit;
    }

    if ($act === 'chat_close') {
        $id = (int)($_POST['reg_id'] ?? 0);
        $pdo->prepare("UPDATE registrations SET support_active=0, updated_at=NOW() WHERE id=?")->execute([$id]);
        echo json_encode(['ok' => true]);
        exit;
    }

    if ($act === 'chat_send') {
        $id  = (int)($_POST['reg_id'] ?? 0);
        $msg = trim($_POST['message'] ?? '');
        if (!$id || !$msg) { echo json_encode(['ok'=>false]); exit; }
        $r = $pdo->prepare("SELECT telegram_user_id FROM registrations WHERE id=?");
        $r->execute([$id]);
        $reg = $r->fetch(PDO::FETCH_ASSOC);
        if (!$reg) { echo json_encode(['ok'=>false]); exit; }
        $pdo->prepare("INSERT INTO support_messages (registration_id, telegram_user_id, direction, message, leido) VALUES (?, ?, 'out', ?, 1)")
            ->execute([$id, $reg['telegram_user_id'], $msg]);
        $new_id = $pdo->lastInsertId();
        tgSendPlain((int)$reg['telegram_user_id'], $msg);
        echo json_encode(['ok'=>true, 'id'=>$new_id, 'ts'=>date('d/m H:i')]);
        exit;
    }

    if ($act === 'chat_get') {
        $id = (int)($_POST['reg_id'] ?? 0);
        $stmt = $pdo->prepare("SELECT id, direction, message, leido, DATE_FORMAT(created_at,'%d/%m %H:%i') AS ts FROM support_messages WHERE registration_id=? ORDER BY created_at ASC");
        $stmt->execute([$id]);
        $msgs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $pdo->prepare("UPDATE support_messages SET leido=1 WHERE registration_id=? AND direction='in' AND leido=0")->execute([$id]);
        $r2 = $pdo->prepare("SELECT support_active FROM registrations WHERE id=?");
        $r2->execute([$id]);
        $reg2 = $r2->fetch(PDO::FETCH_ASSOC);
        $last = end($msgs);
        $last_direction = $last ? $last['direction'] : null;
        echo json_encode(['ok'=>true, 'messages'=>$msgs, 'support_active'=>(bool)($reg2['support_active'] ?? false), 'last_direction'=>$last_direction]);
        exit;
    }

    if ($act === 'chat_unread_counts') {
        $stmt = $pdo->query("SELECT registration_id, COUNT(*) AS cnt FROM support_messages WHERE direction='in' AND leido=0 GROUP BY registration_id");
        $counts = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $counts[(int)$row['registration_id']] = (int)$row['cnt'];
        }
        // Registros con soporte activo donde el último mensaje es del usuario (pendiente de respuesta)
        $pstmt = $pdo->query("
            SELECT r.id FROM registrations r
            WHERE r.support_active = 1
            AND (SELECT direction FROM support_messages sm WHERE sm.registration_id = r.id ORDER BY sm.created_at DESC LIMIT 1) = 'in'
        ");
        $pending = array_column($pstmt->fetchAll(PDO::FETCH_ASSOC), 'id');
        echo json_encode(['ok'=>true, 'counts'=>$counts, 'pending'=>$pending]);
        exit;
    }
}

$flash = '';
$flash_type = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['id'])) {
    $id  = (int)$_POST['id'];
    $act = $_POST['action'];

    // ── AGREGAR NOTA (AJAX-capable) ──────────────────────────────────────────
    if ($act === 'add_note') {
        $note_text = trim($_POST['note'] ?? '');
        if ($note_text && $id) {
            $pdo->prepare("INSERT INTO registration_notes (registration_id, note) VALUES (?, ?)")
                ->execute([$id, $note_text]);
            $new_id = $pdo->lastInsertId();

            if (!empty($_POST['ajax'])) {
                header('Content-Type: application/json');
                echo json_encode([
                    'ok'         => true,
                    'id'         => $new_id,
                    'note'       => $note_text,
                    'created_at' => date('d/m/Y H:i'),
                ]);
                exit;
            }
            $flash = "📝 Nota guardada para el registro #$id.";
        }
    } else {
        // ── ACCIONES SOBRE EL REGISTRO ───────────────────────────────────────
        $stmt = $pdo->prepare("SELECT * FROM registrations WHERE id = ?");
        $stmt->execute([$id]);
        $reg = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($reg && $reg['status'] === 'pending') {
            if ($act === 'accept') {
                $link = createInviteLink();
                if ($link) {
                    $pdo->prepare("UPDATE registrations SET status='accepted', invite_link=?, updated_at=NOW() WHERE id=?")
                        ->execute([$link, $id]);
                    $plabels = ['pepperstone' => 'Pepperstone', 'bingx' => 'BingX', 'bitunix' => 'Bitunix', 'zoomex' => 'Zoomex'];
                    $pl = $plabels[$reg['platform']] ?? $reg['platform'];
                    tgSend((int)$reg['telegram_user_id'],
                        "🎉 *¡Felicidades\\! Tu registro fue aprobado\\.*\n\n" .
                        "El equipo de AnonimusTrade Live ha verificado tu cuenta en *$pl*\\.\n\n" .
                        "Aquí está tu invitación de un solo uso a la comunidad privada:\n\n" .
                        "👉 [Unirse al grupo]($link)\n\n" .
                        "⚠️ Este link es de uso único y expira en 24 horas\\. No lo compartas\\."
                    );
                    $flash = "✅ Registro #$id aceptado. Link enviado al usuario.";
                } else {
                    $flash = "❌ Error al crear el link. Verifica que el bot sea admin del grupo y que COMMUNITY_CHAT_ID esté configurado.";
                    $flash_type = 'error';
                }
            } elseif ($act === 'reject') {
                $pdo->prepare("UPDATE registrations SET status='rejected', updated_at=NOW() WHERE id=?")
                    ->execute([$id]);
                tgSend((int)$reg['telegram_user_id'],
                    "❌ *Tu solicitud no fue aprobada\\.*\n\n" .
                    "Si crees que esto es un error, [contáctanos directamente](https://t.me/+18495683020)\\."
                );
                $flash = "Registro #$id rechazado.";
            } elseif ($act === 'reject_uid') {
                $pdo->prepare("UPDATE registrations SET status='rejected', updated_at=NOW() WHERE id=?")
                    ->execute([$id]);
                $ref_urls = [
                    'pepperstone' => 'https://trk.pepperstonepartners.com/aff_c?offer_id=367&aff_id=45363',
                    'bingx'       => 'https://bingxdao.com/partner/AnonimusTrade/',
                    'bitunix'     => 'https://www.bitunix.com/register?vipCode=KMrN',
                    'zoomex'      => 'https://partner.zoomex.com/aff/ZX904826',
                ];
                $plabels2 = ['pepperstone' => 'Pepperstone', 'bingx' => 'BingX', 'bitunix' => 'Bitunix', 'zoomex' => 'Zoomex'];
                $pname    = $plabels2[$reg['platform']] ?? $reg['platform'];
                $ref_url  = $ref_urls[$reg['platform']] ?? '#';
                tgSend((int)$reg['telegram_user_id'],
                    "⛔ *Tu solicitud no pudo ser aprobada\\.*\n\n" .
                    "El ID de cuenta que nos proporcionaste \\(`" . $reg['platform_user_id'] . "`\\) no está registrado bajo el referido de *AnonimusTrade Live* en *$pname*\\.\n\n" .
                    "Esto ocurre cuando la cuenta fue creada antes de usar nuestro enlace, o con un enlace diferente al nuestro\\.\n\n" .
                    "━━━━━━━━━━━━━━━\n" .
                    "Para acceder a la comunidad debes abrir una *cuenta nueva* usando nuestro enlace oficial:\n\n" .
                    "👉 [Crear cuenta en $pname]($ref_url)\n\n" .
                    "Una vez creada, escribe /start y regístrala con nosotros\\."
                );
                $flash = "⛔ Registro #$id rechazado por UID no referido. Mensaje con instrucciones enviado al usuario.";
            } elseif ($act === 'kyc' && $reg['platform'] === 'bingx') {
                $attempts = (int)$reg['kyc_attempts'] + 1;
                if ($attempts >= 3) {
                    $pdo->prepare("UPDATE registrations SET status='rejected', kyc_attempts=?, updated_at=NOW() WHERE id=?")
                        ->execute([$attempts, $id]);
                    tgSend((int)$reg['telegram_user_id'],
                        "❌ *Tu solicitud ha sido rechazada\\.*\n\n" .
                        "Confirmaste tu verificación de KYC en varias ocasiones, pero al revisar tu cuenta de BingX notamos que aún no estaba completada\\.\n\n" .
                        "Si en algún momento la completas y deseas intentarlo de nuevo, puedes volver a escribirnos con /start\\.\n\n" .
                        "Si crees que esto es un error, [contáctanos directamente](https://t.me/+18495683020)\\."
                    );
                    $flash = "❌ Registro #$id rechazado automáticamente tras 3 intentos de KYC sin completar.";
                } else {
                    $pdo->prepare("UPDATE registrations SET kyc_status='pending', kyc_attempts=?, updated_at=NOW() WHERE id=?")
                        ->execute([$attempts, $id]);
                    if ($attempts === 1) {
                        tgSend((int)$reg['telegram_user_id'],
                            "⚠️ *Falta un paso para completar tu registro*\n\n" .
                            "Notamos que tu cuenta de BingX todavía *no tiene el KYC completado*\\. Es un requisito obligatorio del exchange para poder operar y para que podamos darte acceso a la comunidad\\.\n\n" .
                            "👉 Completa tu verificación de identidad \\(KYC\\) directamente en la app o web de BingX\\.\n\n" .
                            "Cuando termines, presiona el botón de abajo para avisarnos:",
                            [[['text' => '✅ Ya completé mi KYC', 'callback_data' => 'kyc_done']]]
                        );
                        $flash = "📋 Aviso de KYC enviado al usuario #$id (intento 1/3).";
                    } else {
                        tgSend((int)$reg['telegram_user_id'],
                            "⚠️ *Advertencia: verificación de KYC pendiente*\n\n" .
                            "Confirmaste que completaste tu KYC, pero al revisar tu cuenta de BingX notamos que *aún no está completado*\\.\n\n" .
                            "Esta es tu *segunda* confirmación sin completarlo realmente\\. Por favor finaliza tu verificación antes de volver a presionar el botón\\.\n\n" .
                            "⚠️ *Si confirmas una tercera vez sin haberlo completado, perderás la oportunidad de entrar a la comunidad\\.*\n\n" .
                            "Cuando termines, presiona el botón de abajo:",
                            [[['text' => '✅ Ya completé mi KYC', 'callback_data' => 'kyc_done']]]
                        );
                        $flash = "⚠️ Advertencia de KYC enviada al usuario #$id (intento 2/3).";
                    }
                }
            } elseif ($act === 'migration' && $reg['platform'] === 'pepperstone' && $reg['is_migration']) {
                if (!empty($reg['patience_sent'])) {
                    $flash = "ℹ️ El mensaje de paciencia ya fue enviado al usuario #$id. El botón está desactivado para evitar duplicados.";
                    $flash_type = 'error';
                } elseif ($reg['migration_status'] === 'notified') {
                    $pdo->prepare("UPDATE registrations SET patience_sent=1, updated_at=NOW() WHERE id=?")->execute([$id]);
                    tgSend((int)$reg['telegram_user_id'],
                        "🔄 *Tu migración sigue en proceso*\n\n" .
                        "Verificamos y todavía no apareces bajo nuestro referido en el sistema de Pepperstone\\. A veces este proceso tarda un poco más de lo esperado\\.\n\n" .
                        "No necesitas hacer nada más — en cuanto confirmemos tu migración, recibirás tu acceso a la comunidad de forma automática\\. Gracias por tu paciencia\\. 🙏"
                    );
                    $flash = "🔄 Mensaje de paciencia enviado al usuario #$id.";
                } else {
                    $flash = "ℹ️ El usuario #$id todavía no ha confirmado haber recibido el correo de Pepperstone.";
                }
            }
        }
    }
}

// ── FILTROS ──────────────────────────────────────────────────────────────────
$filter          = in_array($_GET['filter']   ?? '', ['all','pending','accepted','rejected'])  ? $_GET['filter']            : 'pending';
$platform_filter = in_array($_GET['platform'] ?? '', ['all','pepperstone','bingx','bitunix','zoomex'])  ? ($_GET['platform'] ?? 'all') : 'all';

$where_parts = [];
if ($filter !== 'all')          $where_parts[] = "status = "   . $pdo->quote($filter);
if ($platform_filter !== 'all') $where_parts[] = "platform = " . $pdo->quote($platform_filter);
$where = $where_parts ? "WHERE " . implode(" AND ", $where_parts) : "";

// Consulta principal con conteo de notas y última nota
$regs = $pdo->query("
    SELECT r.*,
        (SELECT COUNT(*) FROM registration_notes n WHERE n.registration_id = r.id) AS notes_count,
        (SELECT n.note FROM registration_notes n WHERE n.registration_id = r.id ORDER BY n.created_at DESC LIMIT 1) AS latest_note,
        (SELECT COUNT(*) FROM support_messages sm WHERE sm.registration_id = r.id AND sm.direction = 'in' AND sm.leido = 0) AS unread_count,
        (SELECT direction FROM support_messages sm WHERE sm.registration_id = r.id ORDER BY sm.created_at DESC LIMIT 1) AS last_msg_direction
    FROM registrations r
    $where
    ORDER BY r.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Cargar todo el historial de notas para los registros visibles
$all_notes = [];
if ($regs) {
    $reg_ids      = array_column($regs, 'id');
    $placeholders = implode(',', array_fill(0, count($reg_ids), '?'));
    $stmt = $pdo->prepare("SELECT id, registration_id, note, DATE_FORMAT(created_at, '%d/%m/%Y %H:%i') AS created_at FROM registration_notes WHERE registration_id IN ($placeholders) ORDER BY created_at ASC");
    $stmt->execute($reg_ids);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $note) {
        $all_notes[(int)$note['registration_id']][] = $note;
    }
}

$raw_counts = $pdo->query("SELECT status, COUNT(*) n FROM registrations GROUP BY status")->fetchAll(PDO::FETCH_ASSOC);
$counts = ['pending' => 0, 'accepted' => 0, 'rejected' => 0, 'all' => 0];
foreach ($raw_counts as $r) { $counts[$r['status']] = (int)$r['n']; $counts['all'] += (int)$r['n']; }

$plabels  = ['pepperstone' => 'Pepperstone', 'bingx' => 'BingX', 'bitunix' => 'Bitunix', 'zoomex' => 'Zoomex'];
$prlabels = ['principiante' => 'Principiante', 'trader' => 'Trader'];

function relTime(string $ts): string {
    $diff = time() - strtotime($ts);
    if ($diff < 120)   return 'ahora';
    if ($diff < 3600)  return 'hace ' . round($diff / 60)    . 'm';
    if ($diff < 86400) return 'hace ' . round($diff / 3600)  . 'h';
    return 'hace '                    . round($diff / 86400) . 'd';
}

function createInviteLink(): ?string {
    if (!COMMUNITY_CHAT_ID) return null;
    $ctx = stream_context_create(['http' => [
        'method'        => 'POST',
        'header'        => "Content-Type: application/json\r\n",
        'content'       => json_encode([
            'chat_id'      => COMMUNITY_CHAT_ID,
            'expire_date'  => time() + 86400,
            'member_limit' => 1,
            'name'         => 'ATL-' . time(),
        ]),
        'timeout'       => 8,
        'ignore_errors' => true,
    ]]);
    $res = @file_get_contents("https://api.telegram.org/bot".BOT_TOKEN."/createChatInviteLink", false, $ctx);
    $d = json_decode($res ?: '{}', true);
    return ($d['ok'] ?? false) ? $d['result']['invite_link'] : null;
}

function tgSend(int $chat_id, string $text, array $keyboard = []): void {
    $payload = ['chat_id' => $chat_id, 'text' => $text, 'parse_mode' => 'MarkdownV2', 'link_preview_options' => ['is_disabled' => true]];
    if ($keyboard) $payload['reply_markup'] = ['inline_keyboard' => $keyboard];
    $ctx = stream_context_create(['http' => [
        'method'        => 'POST',
        'header'        => "Content-Type: application/json\r\n",
        'content'       => json_encode($payload),
        'timeout'       => 5,
        'ignore_errors' => true,
    ]]);
    @file_get_contents("https://api.telegram.org/bot".BOT_TOKEN."/sendMessage", false, $ctx);
}

function tgSendPlain(int $chat_id, string $text): void {
    $ctx = stream_context_create(['http' => [
        'method'        => 'POST',
        'header'        => "Content-Type: application/json\r\n",
        'content'       => json_encode(['chat_id' => $chat_id, 'text' => $text]),
        'timeout'       => 5,
        'ignore_errors' => true,
    ]]);
    @file_get_contents("https://api.telegram.org/bot".BOT_TOKEN."/sendMessage", false, $ctx);
}

$active = 'registros';
$panel_title = 'Registros';
include __DIR__ . '/../includes/nav.php';
?>
<style>
  :root {
    --red:#C0112B; --red-light:rgba(192,17,43,0.12);
    --black:#080810; --black2:#0E0E18; --black3:#14141F; --black4:#1A1A28;
    --white:#F0EFFF; --purple:#7C3AED; --purple-mid:#6D28D9; --purple-light:#A78BFA; --purple-glow:rgba(124,58,237,0.15);
    --gray:#252535; --gray2:#333348; --text-muted:#7070A0;
    --green:#22C55E; --green-light:rgba(34,197,94,0.12);
    --yellow:#F59E0B; --yellow-light:rgba(245,158,11,0.12);
  }

  /* FLASH */
  .flash { padding:0.85rem 1.25rem; border-radius:5px; font-size:0.78rem; font-weight:600; margin-bottom:1.5rem; }
  .flash.success { background:var(--green-light); border:1px solid rgba(34,197,94,0.3); color:var(--green); }
  .flash.error   { background:var(--red-light);   border:1px solid rgba(192,17,43,0.3);  color:#FF6B6B; }

  /* STATS */
  .stats { display:grid; grid-template-columns:repeat(4,1fr); gap:1rem; margin-bottom:2rem; }
  .stat { background:var(--black2); border:1px solid var(--gray); border-radius:6px; padding:1.25rem 1.5rem; }
  .stat-label { font-size:0.62rem; font-weight:700; letter-spacing:0.12em; text-transform:uppercase; color:var(--text-muted); margin-bottom:0.5rem; }
  .stat-value { font-size:2rem; font-weight:900; }
  .stat.pending  .stat-value { color:var(--yellow); }
  .stat.accepted .stat-value { color:var(--green); }
  .stat.rejected .stat-value { color:#EF4444; }
  .stat.all      .stat-value { color:var(--purple-light); }

  /* SEARCH */
  .search-wrap { margin-bottom:1rem; }
  .search-input { width:100%; padding:10px 16px; border-radius:5px; background:var(--black2); border:1px solid var(--gray); color:var(--white); font-family:inherit; font-size:0.82rem; transition:border-color 0.2s; outline:none; }
  .search-input::placeholder { color:var(--text-muted); }
  .search-input:focus { border-color:var(--purple); }

  /* FILTERS (namespaced con -reg para no chocar con panel/assets/style.css) */
  .filters-wrap { margin-bottom:1.5rem; }
  .filter-row { display:flex; align-items:center; gap:0.5rem; margin-bottom:0.6rem; flex-wrap:wrap; }
  .filter-label { font-size:0.62rem; font-weight:700; letter-spacing:0.1em; text-transform:uppercase; color:var(--text-muted); min-width:72px; }
  .filter-btn { font-family:inherit; font-size:0.68rem; font-weight:700; letter-spacing:0.08em; text-transform:uppercase; padding:6px 16px; border-radius:4px; border:1px solid var(--gray); background:transparent; color:var(--text-muted); cursor:pointer; text-decoration:none; transition:all 0.2s; }
  .filter-btn:hover { border-color:var(--purple-light); color:var(--purple-light); }
  .filter-btn.active { background:var(--purple); border-color:var(--purple); color:var(--white); }

  /* TABLE */
  .table-wrap { background:var(--black2); border:1px solid var(--gray); border-radius:6px; overflow:hidden; overflow-x:auto; }
  table { width:100%; border-collapse:collapse; }
  th { font-size:0.62rem; font-weight:700; letter-spacing:0.1em; text-transform:uppercase; color:var(--text-muted); padding:0.85rem 1.25rem; text-align:left; border-bottom:1px solid var(--gray); white-space:nowrap; }
  td { padding:0.9rem 1.25rem; border-bottom:1px solid rgba(255,255,255,0.04); font-size:0.78rem; vertical-align:top; }
  tr:last-child td { border-bottom:none; }
  tr:hover td { background:rgba(255,255,255,0.02); }
  tr.row-stale td { background:rgba(245,158,11,0.03) !important; }
  tr.row-stale td:first-child { box-shadow:inset 3px 0 0 var(--yellow); }

  /* BADGES */
  .badge { display:inline-block; font-size:0.6rem; font-weight:700; letter-spacing:0.08em; text-transform:uppercase; padding:3px 8px; border-radius:3px; }
  .badge-pending  { background:var(--yellow-light); color:var(--yellow); border:1px solid rgba(245,158,11,0.3); }
  .badge-accepted { background:var(--green-light);  color:var(--green);  border:1px solid rgba(34,197,94,0.3); }
  .badge-rejected { background:var(--red-light);    color:#FF6B6B;       border:1px solid rgba(192,17,43,0.3); }
  .badge-trader   { background:var(--purple-glow);  color:var(--purple-light); border:1px solid rgba(124,58,237,0.3); }
  .badge-prin     { background:rgba(255,255,255,0.06); color:var(--text-muted); border:1px solid var(--gray); }
  .badge-migration { background:rgba(14,165,233,0.12); color:#38BDF8; border:1px solid rgba(14,165,233,0.3); margin-left:4px; }
  .badge-kyc-pending   { background:rgba(245,158,11,0.12); color:var(--yellow); border:1px solid rgba(245,158,11,0.3); margin-left:4px; }
  .badge-kyc-completed { background:rgba(34,197,94,0.12); color:var(--green); border:1px solid rgba(34,197,94,0.3); margin-left:4px; }
  .badge-migr-pending  { background:rgba(245,158,11,0.12); color:var(--yellow); border:1px solid rgba(245,158,11,0.3); margin-left:4px; }
  .badge-migr-notified { background:rgba(34,197,94,0.12); color:var(--green); border:1px solid rgba(34,197,94,0.3); margin-left:4px; }

  .uid { font-family:monospace; font-size:0.75rem; color:var(--purple-light); background:var(--purple-glow); padding:2px 6px; border-radius:3px; }

  /* ACTION BUTTONS */
  .action-btns { display:flex; flex-direction:column; gap:5px; min-width:130px; }
  .action-btns form { display:flex; }
  .btn-accept, .btn-reject, .btn-kyc, .btn-migration {
    font-family:inherit; font-size:0.65rem; font-weight:700; letter-spacing:0.06em;
    text-transform:uppercase; padding:6px 10px; border-radius:3px; cursor:pointer;
    transition:all 0.2s; width:100%; text-align:center; border:1px solid;
  }
  .btn-accept    { background:var(--green-light);        border-color:rgba(34,197,94,0.4);   color:var(--green);        }
  .btn-accept:hover    { background:rgba(34,197,94,0.25); }
  .btn-reject    { background:var(--red-light);          border-color:rgba(192,17,43,0.4);   color:#FF6B6B;             }
  .btn-reject:hover    { background:rgba(192,17,43,0.22); }
  .btn-reject-uid { background:rgba(245,158,11,0.1); border-color:rgba(245,158,11,0.35); color:var(--yellow); }
  .btn-reject-uid:hover { background:rgba(245,158,11,0.2); }
  .btn-kyc       { background:var(--purple-glow);        border-color:rgba(124,58,237,0.4);  color:var(--purple-light); }
  .btn-kyc:hover:not(:disabled)       { background:rgba(124,58,237,0.25); }
  .btn-migration { background:rgba(56,189,248,0.12);     border-color:rgba(56,189,248,0.4);  color:#38BDF8;             }
  .btn-migration:hover:not(:disabled) { background:rgba(56,189,248,0.25); }
  button:disabled { background:rgba(255,255,255,0.04) !important; border-color:var(--gray2) !important; color:var(--text-muted) !important; cursor:not-allowed !important; opacity:0.55; }

  /* NOTE BUTTON (in table) */
  .note-section { margin-top:8px; padding-top:8px; border-top:1px solid rgba(255,255,255,0.05); }
  .note-preview { font-size:0.68rem; color:var(--text-muted); font-style:italic; margin-bottom:5px; line-height:1.4; }
  .btn-note {
    font-family:inherit; font-size:0.62rem; font-weight:600; letter-spacing:0.06em; text-transform:uppercase;
    background:transparent; border:1px solid var(--gray); color:var(--text-muted);
    padding:4px 10px; border-radius:3px; cursor:pointer; transition:all 0.2s; width:100%;
  }
  .btn-note:hover { border-color:var(--gray2); color:var(--white); }
  .btn-note.has-notes { border-color:rgba(124,58,237,0.3); color:var(--purple-light); }

  /* DATES */
  .date-stack { display:flex; flex-direction:column; gap:3px; white-space:nowrap; font-size:0.73rem; color:var(--text-muted); }
  .date-act { font-size:0.68rem; opacity:0.7; }
  .date-stale { font-size:0.6rem; color:var(--yellow); font-weight:700; margin-top:2px; }

  .empty { padding:3rem; text-align:center; color:var(--text-muted); font-size:0.82rem; }
  .no-results { padding:3rem; text-align:center; color:var(--text-muted); font-size:0.82rem; display:none; }

  /* ── MODAL ──────────────────────────────────────────────────────────────── */
  .modal-overlay {
    position:fixed; inset:0; z-index:9999;
    background:rgba(8,8,16,0.88); backdrop-filter:blur(5px);
    display:none; align-items:center; justify-content:center; padding:1.5rem;
  }
  .modal-overlay.open { display:flex; }
  .modal-box {
    background:var(--black2); border:1px solid var(--gray2); border-radius:10px;
    width:100%; max-width:560px; display:flex; flex-direction:column;
    max-height:80vh; box-shadow:0 20px 60px rgba(0,0,0,0.6);
  }
  .modal-header {
    display:flex; align-items:center; justify-content:space-between;
    padding:1.25rem 1.5rem; border-bottom:1px solid var(--gray); flex-shrink:0;
  }
  .modal-title { font-size:0.82rem; font-weight:700; letter-spacing:0.04em; }
  .modal-title span { color:var(--purple-light); }
  .modal-close {
    background:transparent; border:1px solid var(--gray); color:var(--text-muted);
    width:28px; height:28px; border-radius:4px; cursor:pointer; font-size:1rem;
    display:flex; align-items:center; justify-content:center; transition:all 0.2s;
  }
  .modal-close:hover { color:var(--white); border-color:var(--gray2); }

  /* Scrollable history */
  .modal-history {
    flex:1; overflow-y:auto; padding:1.25rem 1.5rem;
    display:flex; flex-direction:column; gap:0.85rem; min-height:80px;
  }
  .modal-history::-webkit-scrollbar { width:3px; }
  .modal-history::-webkit-scrollbar-track { background:transparent; }
  .modal-history::-webkit-scrollbar-thumb { background:var(--gray2); border-radius:2px; }
  .note-entry {
    background:var(--black3); border:1px solid var(--gray); border-radius:6px; padding:0.9rem 1rem;
  }
  .note-entry-meta {
    font-size:0.6rem; font-weight:700; letter-spacing:0.1em; text-transform:uppercase;
    color:var(--text-muted); margin-bottom:0.5rem; display:flex; align-items:center; gap:6px;
  }
  .note-entry-meta::before { content:''; display:block; width:6px; height:6px; border-radius:50%; background:var(--purple-light); flex-shrink:0; }
  .note-entry-text { font-size:0.78rem; line-height:1.65; color:var(--white); white-space:pre-wrap; word-break:break-word; }
  .no-notes-msg { font-size:0.78rem; color:var(--text-muted); text-align:center; padding:2.5rem 0; font-style:italic; }

  /* Add note form */
  .modal-add {
    padding:1.25rem 1.5rem; border-top:1px solid var(--gray); flex-shrink:0;
    display:flex; flex-direction:column; gap:0.65rem;
  }
  .modal-add-label { font-size:0.62rem; font-weight:700; letter-spacing:0.1em; text-transform:uppercase; color:var(--text-muted); }
  .modal-textarea {
    width:100%; background:var(--black3); border:1px solid var(--gray); border-radius:5px;
    color:var(--white); font-family:inherit; font-size:0.8rem; padding:10px 12px;
    resize:vertical; min-height:72px; outline:none; transition:border-color 0.2s; line-height:1.5;
  }
  .modal-textarea:focus { border-color:var(--purple); }
  .modal-footer { display:flex; align-items:center; justify-content:space-between; }
  .modal-note-count { font-size:0.65rem; color:var(--text-muted); }
  .modal-submit {
    font-family:inherit; font-size:0.68rem; font-weight:700; letter-spacing:0.08em;
    text-transform:uppercase; padding:8px 22px; border-radius:4px;
    background:var(--purple); border:1px solid rgba(124,58,237,0.6);
    color:var(--white); cursor:pointer; transition:all 0.2s;
  }
  .modal-submit:hover { background:var(--purple-mid); }
  .modal-submit:disabled { opacity:0.5; cursor:not-allowed; }

  @media(max-width:768px) { .stats { grid-template-columns:1fr 1fr; } }

  /* CHAT BUTTON */
  .btn-chat {
    font-family:inherit; font-size:0.62rem; font-weight:600; letter-spacing:0.06em; text-transform:uppercase;
    background:transparent; border:1px solid var(--gray); color:var(--text-muted);
    padding:4px 10px; border-radius:3px; cursor:pointer; transition:all 0.2s; width:100%; margin-top:5px;
    display:flex; align-items:center; justify-content:center; gap:4px;
  }
  .btn-chat:hover { border-color:var(--gray2); color:var(--white); }
  .btn-chat-active  { border-color:rgba(34,197,94,0.4);   color:var(--green); background:rgba(34,197,94,0.06); }
  .btn-chat-active:hover  { background:rgba(34,197,94,0.12); }
  .btn-chat-pending { border-color:rgba(249,115,22,0.5); color:#F97316;      background:rgba(249,115,22,0.08); }
  .btn-chat-pending:hover { background:rgba(249,115,22,0.15); }
  .chat-badge-inline {
    display:inline-flex; align-items:center; justify-content:center;
    background:#EF4444; color:#fff; border-radius:10px;
    font-size:0.55rem; padding:1px 5px; font-weight:800; min-width:16px;
  }

  /* CHAT MODAL */
  .chat-messages {
    flex:1; overflow-y:auto; padding:1rem 1.5rem;
    display:flex; flex-direction:column; gap:0.6rem;
    min-height:180px; max-height:320px;
  }
  .chat-messages::-webkit-scrollbar { width:3px; }
  .chat-messages::-webkit-scrollbar-track { background:transparent; }
  .chat-messages::-webkit-scrollbar-thumb { background:var(--gray2); border-radius:2px; }

  .msg-wrap { display:flex; flex-direction:column; }
  .msg-wrap.in  { align-items:flex-start; }
  .msg-wrap.out { align-items:flex-end; }
  .msg-bubble {
    max-width:80%; padding:8px 12px; font-size:0.78rem; line-height:1.5; word-break:break-word;
  }
  .msg-wrap.in  .msg-bubble { background:var(--black3); border:1px solid var(--gray); border-radius:2px 10px 10px 10px; color:var(--white); }
  .msg-wrap.out .msg-bubble { background:var(--purple-glow); border:1px solid rgba(124,58,237,0.3); border-radius:10px 2px 10px 10px; color:var(--purple-light); }
  .msg-label { font-size:0.58rem; color:var(--text-muted); margin-top:3px; padding:0 2px; }

  .chat-empty-msg { font-size:0.78rem; color:var(--text-muted); text-align:center; padding:3rem 0; font-style:italic; }
  .chat-input-area { padding:1rem 1.5rem; border-top:1px solid var(--gray); flex-shrink:0; display:flex; flex-direction:column; gap:0.6rem; }

  /* PLANTILLAS */
  .chat-templates { display:flex; align-items:center; gap:5px; flex-wrap:wrap; }
  .templates-label { font-size:0.58rem; font-weight:700; letter-spacing:0.1em; text-transform:uppercase; color:var(--text-muted); white-space:nowrap; margin-right:2px; }
  .tpl-btn {
    font-family:inherit; font-size:0.62rem; font-weight:600; padding:4px 9px;
    border-radius:3px; border:1px solid var(--gray2); background:var(--black3);
    color:var(--text-muted); cursor:pointer; transition:all 0.2s; white-space:nowrap;
  }
  .tpl-btn:hover { border-color:var(--purple-light); color:var(--purple-light); background:var(--purple-glow); }

  #chat-toggle-btn {
    font-family:inherit; font-size:0.65rem; font-weight:700; letter-spacing:0.06em;
    text-transform:uppercase; padding:5px 14px; border-radius:4px; cursor:pointer; transition:all 0.2s; border:1px solid;
  }
  .toggle-start { background:rgba(34,197,94,0.1); border-color:rgba(34,197,94,0.4); color:var(--green); }
  .toggle-start:hover { background:rgba(34,197,94,0.2); }
  .toggle-close { background:rgba(239,68,68,0.1); border-color:rgba(239,68,68,0.4); color:#EF4444; }
  .toggle-close:hover { background:rgba(239,68,68,0.2); }
</style>

<!-- ── MODAL DE NOTAS ────────────────────────────────────────────────────── -->
<div class="modal-overlay" id="note-modal" onclick="if(event.target===this)closeModal()">
  <div class="modal-box">
    <div class="modal-header">
      <div class="modal-title">📝 Notas — <span id="modal-name"></span></div>
      <button class="modal-close" onclick="closeModal()">✕</button>
    </div>
    <div class="modal-history" id="modal-history"></div>
    <div class="modal-add">
      <div class="modal-add-label">Nueva nota</div>
      <textarea class="modal-textarea" id="modal-input" placeholder="Escribe una nota interna para el equipo..." rows="3"></textarea>
      <div class="modal-footer">
        <span class="modal-note-count" id="modal-count"></span>
        <button class="modal-submit" id="modal-save-btn" onclick="submitNote()">💾 Guardar nota</button>
      </div>
    </div>
  </div>
</div>

<!-- ── MODAL DE CHAT ─────────────────────────────────────────────────────── -->
<div class="modal-overlay" id="chat-modal" onclick="if(event.target===this)closeChat()">
  <div class="modal-box" style="max-width:500px">
    <div class="modal-header">
      <div>
        <div class="modal-title">💬 Soporte — <span id="chat-modal-name"></span></div>
        <div id="chat-status-text" style="font-size:0.62rem;color:var(--text-muted);margin-top:3px;"></div>
      </div>
      <div style="display:flex;gap:8px;align-items:center;">
        <button id="chat-toggle-btn" onclick="toggleSupport()"></button>
        <button class="modal-close" onclick="closeChat()">✕</button>
      </div>
    </div>
    <div class="chat-messages" id="chat-messages">
      <div class="chat-empty-msg">Cargando...</div>
    </div>
    <div class="chat-input-area" id="chat-input-area" style="display:none">
      <div class="chat-templates">
        <span class="templates-label">Plantillas:</span>
        <button class="tpl-btn" onclick="useTemplate(0)" title="ID incorrecto o cambiado">🔢 ID</button>
        <button class="tpl-btn" onclick="useTemplate(1)" title="Migración Pepperstone sin respuesta">🔄 Migración</button>
        <button class="tpl-btn" onclick="useTemplate(2)" title="KYC BingX pendiente">📋 KYC</button>
        <button class="tpl-btn" onclick="useTemplate(3)" title="Recordatorio general">⏳ Recordatorio</button>
        <button class="tpl-btn" onclick="useTemplate(4)" title="Cierre de conversación">✅ Cierre</button>
      </div>
      <textarea class="modal-textarea" id="chat-input" placeholder="Escribe un mensaje al usuario..." rows="2"></textarea>
      <div class="modal-footer">
        <span style="font-size:0.6rem;color:var(--text-muted);">Enter para enviar · Shift+Enter = salto de línea</span>
        <button class="modal-submit" id="chat-send-btn" onclick="sendChatMessage()">Enviar →</button>
      </div>
    </div>
  </div>
</div>

<?php if ($flash): ?>
<div class="flash <?= $flash_type ?>"><?= htmlspecialchars($flash) ?></div>
<?php endif; ?>

<!-- STATS -->
<div class="stats">
  <div class="stat pending"><div class="stat-label">Pendientes</div><div class="stat-value"><?= $counts['pending'] ?></div></div>
  <div class="stat accepted"><div class="stat-label">Aceptados</div><div class="stat-value"><?= $counts['accepted'] ?></div></div>
  <div class="stat rejected"><div class="stat-label">Rechazados</div><div class="stat-value"><?= $counts['rejected'] ?></div></div>
  <div class="stat all"><div class="stat-label">Total</div><div class="stat-value"><?= $counts['all'] ?></div></div>
</div>

<!-- SEARCH -->
<div class="search-wrap">
  <input type="text" id="search" class="search-input" placeholder="🔍  Buscar por nombre, Telegram, email o ID de usuario...">
</div>

<!-- FILTERS -->
<div class="filters-wrap">
  <div class="filter-row">
    <span class="filter-label">Estado:</span>
    <?php foreach (['pending' => 'Pendientes', 'accepted' => 'Aceptados', 'rejected' => 'Rechazados', 'all' => 'Todos'] as $f => $l): ?>
    <a class="filter-btn <?= $filter === $f ? 'active' : '' ?>" href="?filter=<?= $f ?>&platform=<?= $platform_filter ?>"><?= $l ?></a>
    <?php endforeach; ?>
  </div>
  <div class="filter-row">
    <span class="filter-label">Plataforma:</span>
    <?php foreach (['all' => 'Todas', 'pepperstone' => 'Pepperstone', 'bingx' => 'BingX', 'bitunix' => 'Bitunix', 'zoomex' => 'Zoomex'] as $pf => $pl): ?>
    <a class="filter-btn <?= $platform_filter === $pf ? 'active' : '' ?>" href="?filter=<?= $filter ?>&platform=<?= $pf ?>"><?= $pl ?></a>
    <?php endforeach; ?>
  </div>
</div>

<!-- TABLE -->
<div class="table-wrap">
  <?php if (empty($regs)): ?>
  <div class="empty">No hay registros <?= $filter === 'pending' ? 'pendientes' : '' ?> por el momento.</div>
  <?php else: ?>
  <table id="reg-table">
    <thead>
      <tr>
        <th>#</th><th>Nombre</th><th>Telegram</th><th>Perfil</th>
        <th>Plataforma</th><th>Email</th><th>ID Usuario</th>
        <th>Estado</th><th>Fechas</th><th>Acciones</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($regs as $r):
        $is_stale     = $r['status'] === 'pending' && (time() - strtotime($r['updated_at'])) > 172800;
        $kyc_sent     = ($r['kyc_status'] ?? '') === 'pending';
        $migr_sent    = !empty($r['patience_sent']);
        $note_count   = (int)($r['notes_count'] ?? 0);
        $latest_note  = $r['latest_note'] ?? '';
        $reg_name     = htmlspecialchars($r['telegram_name'] ?: '#' . $r['id']);
        $unread_count  = (int)($r['unread_count'] ?? 0);
        $chat_pending  = $r['support_active'] && ($r['last_msg_direction'] ?? '') === 'in';
      ?>
      <tr id="reg-<?= $r['id'] ?>" <?= $is_stale ? 'class="row-stale"' : '' ?>>
        <td style="color:var(--text-muted)"><?= $r['id'] ?></td>
        <td><strong><?= htmlspecialchars($r['telegram_name'] ?: '—') ?></strong></td>
        <td><?= $r['telegram_username'] ? '@'.htmlspecialchars($r['telegram_username']) : '<span style="color:var(--text-muted)">—</span>' ?></td>
        <td>
          <span class="badge <?= $r['profile_type'] === 'trader' ? 'badge-trader' : 'badge-prin' ?>">
            <?= $prlabels[$r['profile_type']] ?? $r['profile_type'] ?>
            <?php if ($r['asset_type']): ?> · <?= ucfirst($r['asset_type']) ?><?php endif; ?>
          </span>
        </td>
        <td>
          <?= $plabels[$r['platform']] ?? $r['platform'] ?>
          <?php if (!empty($r['is_migration'])): ?><span class="badge badge-migration">Migración</span><?php endif; ?>
          <?php if (($r['kyc_status'] ?? '') === 'pending'):   ?><span class="badge badge-kyc-pending">Esperando KYC (<?= $r['kyc_attempts'] ?>/3)</span><?php endif; ?>
          <?php if (($r['kyc_status'] ?? '') === 'completed'): ?><span class="badge badge-kyc-completed">KYC completado (<?= $r['kyc_attempts'] ?>/3)</span><?php endif; ?>
          <?php if (($r['migration_status'] ?? '') === 'pending'):  ?><span class="badge badge-migr-pending">Esperando confirmación Pepperstone</span><?php endif; ?>
          <?php if (($r['migration_status'] ?? '') === 'notified'): ?><span class="badge badge-migr-notified">Usuario confirmó — verificar referido</span><?php endif; ?>
        </td>
        <td style="font-size:0.74rem"><?= htmlspecialchars($r['email'] ?? '—') ?></td>
        <td><span class="uid"><?= htmlspecialchars($r['platform_user_id']) ?></span></td>
        <td><span class="badge badge-<?= $r['status'] ?>"><?= $r['status'] === 'pending' ? 'Pendiente' : ($r['status'] === 'accepted' ? 'Aceptado' : 'Rechazado') ?></span></td>
        <td>
          <div class="date-stack">
            <span>📅 <?= date('d/m/y H:i', strtotime($r['created_at'])) ?></span>
            <span class="date-act">🔄 <?= relTime($r['updated_at']) ?></span>
            <?php if ($is_stale): ?><span class="date-stale">⚠️ Sin actividad 48h+</span><?php endif; ?>
          </div>
        </td>
        <td>
          <?php if ($r['status'] === 'pending'): ?>
          <div class="action-btns">
            <form method="POST" onsubmit="return confirm('¿Aceptar y enviar link de invitación?')">
              <input type="hidden" name="id" value="<?= $r['id'] ?>">
              <input type="hidden" name="action" value="accept">
              <button type="submit" class="btn-accept">✅ Aceptar</button>
            </form>
            <form method="POST" onsubmit="return confirm('¿Rechazar este registro?')">
              <input type="hidden" name="id" value="<?= $r['id'] ?>">
              <input type="hidden" name="action" value="reject">
              <button type="submit" class="btn-reject">❌ Rechazar</button>
            </form>
            <?php if ($r['platform'] !== 'pepperstone'): ?>
            <form method="POST" onsubmit="return confirm('¿Rechazar por UID no referido? Se le enviará al usuario un mensaje con el enlace para crear una cuenta nueva.')">
              <input type="hidden" name="id" value="<?= $r['id'] ?>">
              <input type="hidden" name="action" value="reject_uid">
              <button type="submit" class="btn-reject-uid">⛔ UID no referido</button>
            </form>
            <?php endif; ?>
            <?php if ($r['platform'] === 'bingx'): ?>
            <?php if ($kyc_sent): ?>
            <button type="button" class="btn-kyc" disabled>📋 KYC enviado</button>
            <?php else: ?>
            <form method="POST" onsubmit="return confirm('¿Avisar al usuario que falta completar su KYC?')">
              <input type="hidden" name="id" value="<?= $r['id'] ?>">
              <input type="hidden" name="action" value="kyc">
              <button type="submit" class="btn-kyc">📋 KYC</button>
            </form>
            <?php endif; ?>
            <?php endif; ?>
            <?php if ($r['platform'] === 'pepperstone' && $r['is_migration']): ?>
            <?php if ($migr_sent): ?>
            <button type="button" class="btn-migration" disabled>✓ Paciencia enviada</button>
            <?php else: ?>
            <form method="POST" onsubmit="return confirm('¿Enviar mensaje de seguimiento de migración al usuario?')">
              <input type="hidden" name="id" value="<?= $r['id'] ?>">
              <input type="hidden" name="action" value="migration">
              <button type="submit" class="btn-migration">🔄 Migración</button>
            </form>
            <?php endif; ?>
            <?php endif; ?>
          </div>
          <?php elseif ($r['status'] === 'accepted' && $r['invite_link']): ?>
          <a href="<?= htmlspecialchars($r['invite_link']) ?>" target="_blank" style="font-size:0.68rem; color:var(--purple-light);">Ver link →</a>
          <?php else: ?>
          <span style="color:var(--text-muted); font-size:0.72rem;">—</span>
          <?php endif; ?>

          <!-- NOTAS -->
          <div class="note-section">
            <?php if ($latest_note): ?>
            <div class="note-preview">📝 <?= htmlspecialchars(mb_substr($latest_note, 0, 55)) ?><?= mb_strlen($latest_note) > 55 ? '…' : '' ?></div>
            <?php endif; ?>
            <button type="button"
              class="btn-note <?= $note_count > 0 ? 'has-notes' : '' ?>"
              onclick="openModal(<?= $r['id'] ?>, '<?= addslashes($reg_name) ?>')">
              <?= $note_count > 0 ? "📝 Ver notas ($note_count)" : '📝 Agregar nota' ?>
            </button>
            <button type="button"
              class="btn-chat <?= $r['support_active'] ? ($chat_pending ? 'btn-chat-pending' : 'btn-chat-active') : '' ?>"
              onclick="openChat(<?= $r['id'] ?>, '<?= addslashes($reg_name) ?>', <?= (int)$r['support_active'] ?>)"
              data-reg-id="<?= $r['id'] ?>">
              <?php if (!$r['support_active']): ?>💬 Chat
              <?php elseif ($chat_pending): ?>🟠 Chat pendiente
              <?php else: ?>🟢 Chat activo<?php endif; ?>
              <?php if ($unread_count > 0): ?><span class="chat-badge-inline"><?= $unread_count ?></span><?php endif; ?>
            </button>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <div class="no-results" id="no-results">No se encontraron registros con esa búsqueda.</div>
  <?php endif; ?>
</div>

<script>
// ── DATOS DE NOTAS ──────────────────────────────────────────────────────────
const notesData = <?= json_encode($all_notes, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>;
let currentRegId = null;

// ── MODAL ───────────────────────────────────────────────────────────────────
function openModal(regId, regName) {
    currentRegId = regId;
    document.getElementById('modal-name').textContent = regName;
    renderHistory();
    document.getElementById('modal-input').value = '';
    document.getElementById('note-modal').classList.add('open');
    setTimeout(() => document.getElementById('modal-input').focus(), 50);
}

function closeModal() {
    document.getElementById('note-modal').classList.remove('open');
    currentRegId = null;
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') { closeModal(); closeChat(); }
});

function renderHistory() {
    const notes = notesData[currentRegId] || [];
    const container = document.getElementById('modal-history');
    const countEl   = document.getElementById('modal-count');

    countEl.textContent = notes.length === 0 ? '' : notes.length + ' nota' + (notes.length > 1 ? 's' : '');

    if (notes.length === 0) {
        container.innerHTML = '<div class="no-notes-msg">Sin notas aún. Sé el primero en dejar una.</div>';
        return;
    }

    container.innerHTML = notes.map(function(n) {
        return '<div class="note-entry">' +
            '<div class="note-entry-meta">' + escHtml(n.created_at) + '</div>' +
            '<div class="note-entry-text">' + escHtml(n.note) + '</div>' +
            '</div>';
    }).join('');

    // Scroll al final (nota más reciente)
    container.scrollTop = container.scrollHeight;
}

async function submitNote() {
    const input = document.getElementById('modal-input');
    const text  = input.value.trim();
    if (!text || !currentRegId) return;

    const btn = document.getElementById('modal-save-btn');
    btn.disabled = true;
    btn.textContent = 'Guardando…';

    const fd = new FormData();
    fd.append('action', 'add_note');
    fd.append('id', currentRegId);
    fd.append('note', text);
    fd.append('ajax', '1');

    try {
        const res  = await fetch('index.php', { method: 'POST', body: fd });
        const data = await res.json();

        if (data.ok) {
            if (!notesData[currentRegId]) notesData[currentRegId] = [];
            notesData[currentRegId].push({
                id: data.id, registration_id: currentRegId,
                note: data.note, created_at: data.created_at
            });
            input.value = '';
            renderHistory();
            updateNoteBtn(currentRegId);
        }
    } catch(e) {}

    btn.disabled = false;
    btn.textContent = '💾 Guardar nota';
}

function updateNoteBtn(regId) {
    const count = (notesData[regId] || []).length;
    const latest = count > 0 ? notesData[regId][count - 1].note : '';
    const row = document.getElementById('reg-' + regId);
    if (!row) return;
    const btn = row.querySelector('.btn-note');
    if (btn) {
        btn.textContent = count > 0 ? '📝 Ver notas (' + count + ')' : '📝 Agregar nota';
        btn.classList.toggle('has-notes', count > 0);
    }
    const preview = row.querySelector('.note-preview');
    if (preview && latest) {
        preview.textContent = '📝 ' + (latest.length > 55 ? latest.substring(0, 55) + '…' : latest);
        preview.style.display = '';
    }
}

// Enter para guardar (Shift+Enter = salto de línea)
document.getElementById('modal-input').addEventListener('keydown', function(e) {
    if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); submitNote(); }
});

function escHtml(str) {
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// ── BÚSQUEDA EN TIEMPO REAL ─────────────────────────────────────────────────
document.getElementById('search').addEventListener('input', function() {
    const q = this.value.toLowerCase().trim();
    let visible = 0;
    document.querySelectorAll('#reg-table tbody tr').forEach(function(tr) {
        const match = !q || tr.textContent.toLowerCase().includes(q);
        tr.style.display = match ? '' : 'none';
        if (match) visible++;
    });
    const noRes = document.getElementById('no-results');
    if (noRes) noRes.style.display = (q && visible === 0) ? 'block' : 'none';
});
</script>

<script>
// ── PLANTILLAS DE MENSAJES ────────────────────────────────────────────────────
const TEMPLATES = [
    // 0 — ID incorrecto o cambiado
    "Hola, ¿cómo estás? Hemos notado que llevas un tiempo esperando acceso a la comunidad y queremos ayudarte a resolverlo. El código de usuario que nos registraste no aparece en nuestra plataforma. ¿Podrías confirmarnos si sigue siendo el mismo o si ha cambiado? Puedes enviarnos tu ID de cuenta o código de usuario directamente desde tu perfil en la plataforma.",
    // 1 — Migración Pepperstone sin respuesta
    "Hola, vemos que enviaste la solicitud de migración hace un tiempo y aún no hemos recibido confirmación de Pepperstone. Te recomendamos contactarlos directamente para darle seguimiento a tu caso. Una vez recibas su confirmación, avísanos y procesamos tu acceso de inmediato.",
    // 2 — KYC BingX pendiente
    "Hola, revisamos tu cuenta de BingX y la verificación de identidad (KYC) aún aparece como pendiente. Sin ese paso no podemos procesar tu acceso. Cuando lo completes, avísanos con el botón que te enviamos anteriormente.",
    // 3 — Recordatorio general
    "Hola, ¿todo bien? Tu solicitud lleva un tiempo sin actualizarse. Si necesitas ayuda para completar el proceso, aquí estamos. Cuéntanos en qué punto quedaste.",
    // 4 — Cierre de conversación
    "Perfecto, quedamos atentos. Cuando tengas la información lista, escríbenos aquí y continuamos el proceso. ¡Éxitos!"
];

function useTemplate(index) {
    const input = document.getElementById('chat-input');
    input.value = TEMPLATES[index];
    input.focus();
    input.setSelectionRange(input.value.length, input.value.length);
}

// ── CHAT ──────────────────────────────────────────────────────────────────────
let chatRegId       = null;
let chatIsActive    = false;
let chatPollTimer   = null;
let chatLastCount   = 0;

function openChat(regId, regName, isActive) {
    chatRegId    = regId;
    chatIsActive = !!isActive;
    document.getElementById('chat-modal-name').textContent = regName;
    updateChatUI();
    loadMessages();
    document.getElementById('chat-modal').classList.add('open');
    chatPollTimer = setInterval(loadMessages, 4000);
    setTimeout(() => { if (chatIsActive) document.getElementById('chat-input').focus(); }, 80);
}

function closeChat() {
    document.getElementById('chat-modal').classList.remove('open');
    clearInterval(chatPollTimer);
    chatPollTimer = null;
    chatRegId     = null;
}

function updateChatUI() {
    const statusEl   = document.getElementById('chat-status-text');
    const toggleBtn  = document.getElementById('chat-toggle-btn');
    const inputArea  = document.getElementById('chat-input-area');

    if (chatIsActive) {
        statusEl.textContent         = '🟢 Soporte activo — el usuario puede responderte';
        toggleBtn.textContent        = '🔴 Cerrar chat';
        toggleBtn.className          = 'toggle-close';
        inputArea.style.display      = 'flex';
        inputArea.style.flexDirection = 'column';
    } else {
        statusEl.textContent    = '⚪ Soporte inactivo — el usuario no sabe que estás aquí';
        toggleBtn.textContent   = '💬 Iniciar soporte';
        toggleBtn.className     = 'toggle-start';
        inputArea.style.display = 'none';
    }
}

async function toggleSupport() {
    if (!chatRegId) return;
    const action = chatIsActive ? 'chat_close' : 'chat_open';
    const fd = new FormData();
    fd.append('action', action);
    fd.append('reg_id', chatRegId);
    const res  = await fetch('index.php', { method:'POST', body:fd });
    const data = await res.json();
    if (data.ok) {
        chatIsActive = !chatIsActive;
        updateChatUI();
        updateRowBtnState(chatRegId, chatIsActive, 'out');
        if (chatIsActive) setTimeout(() => document.getElementById('chat-input').focus(), 80);
    }
}

async function loadMessages() {
    if (!chatRegId) return;
    const fd = new FormData();
    fd.append('action', 'chat_get');
    fd.append('reg_id', chatRegId);
    const res  = await fetch('index.php', { method:'POST', body:fd });
    const data = await res.json();
    if (!data.ok) return;

    // Sync active state if changed externally
    if (data.support_active !== chatIsActive) {
        chatIsActive = data.support_active;
        updateChatUI();
    }

    const container = document.getElementById('chat-messages');
    const atBottom  = container.scrollTop + container.clientHeight >= container.scrollHeight - 20;

    if (data.messages.length === 0) {
        container.innerHTML = '<div class="chat-empty-msg">Sin mensajes aún.<br>Inicia el soporte y escribe el primer mensaje.</div>';
        return;
    }

    container.innerHTML = data.messages.map(function(m) {
        const dir   = m.direction === 'in' ? 'in' : 'out';
        const label = dir === 'in' ? 'Usuario · ' : 'Tú · ';
        return '<div class="msg-wrap ' + dir + '">' +
            '<div class="msg-bubble">' + escHtml(m.message) + '</div>' +
            '<div class="msg-label">' + label + escHtml(m.ts) + '</div>' +
            '</div>';
    }).join('');

    if (atBottom || data.messages.length !== chatLastCount) {
        container.scrollTop = container.scrollHeight;
    }
    chatLastCount = data.messages.length;

    updateChatBadge(chatRegId, 0);
    if (data.last_direction !== undefined) {
        updateRowBtnState(chatRegId, chatIsActive, data.last_direction);
    }
}

async function sendChatMessage() {
    const input = document.getElementById('chat-input');
    const msg   = input.value.trim();
    if (!msg || !chatRegId) return;

    const btn = document.getElementById('chat-send-btn');
    btn.disabled     = true;
    btn.textContent  = 'Enviando…';

    const fd = new FormData();
    fd.append('action',  'chat_send');
    fd.append('reg_id',  chatRegId);
    fd.append('message', msg);

    const res  = await fetch('index.php', { method:'POST', body:fd });
    const data = await res.json();

    if (data.ok) { input.value = ''; loadMessages(); }

    btn.disabled    = false;
    btn.textContent = 'Enviar →';
}

document.getElementById('chat-input').addEventListener('keydown', function(e) {
    if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendChatMessage(); }
});

// Refrescar badges y estados cada 20 segundos en segundo plano
setInterval(async function() {
    const fd = new FormData();
    fd.append('action', 'chat_unread_counts');
    const res  = await fetch('index.php', { method:'POST', body:fd });
    const data = await res.json();
    if (!data.ok) return;
    const pendingIds = data.pending || [];
    document.querySelectorAll('.btn-chat[data-reg-id]').forEach(function(btn) {
        const id      = parseInt(btn.dataset.regId);
        const count   = data.counts[id] || 0;
        const active  = btn.classList.contains('btn-chat-active') || btn.classList.contains('btn-chat-pending');
        const pending = pendingIds.includes(id);
        updateChatBadge(id, count);
        if (active) updateRowBtnState(id, true, pending ? 'in' : 'out');
    });
}, 20000);

function updateChatBadge(regId, count) {
    const btn = document.querySelector('.btn-chat[data-reg-id="' + regId + '"]');
    if (!btn) return;
    let badge = btn.querySelector('.chat-badge-inline');
    if (count > 0) {
        if (!badge) { badge = document.createElement('span'); badge.className = 'chat-badge-inline'; btn.appendChild(badge); }
        badge.textContent = count;
    } else if (badge) { badge.remove(); }
}

function updateRowBtnState(regId, isActive, lastDirection) {
    const btn = document.querySelector('.btn-chat[data-reg-id="' + regId + '"]');
    if (!btn) return;
    const badge   = btn.querySelector('.chat-badge-inline');
    const pending = isActive && lastDirection === 'in';
    btn.classList.remove('btn-chat-active', 'btn-chat-pending');
    if (!isActive) {
        btn.childNodes[0].textContent = '💬 Chat';
    } else if (pending) {
        btn.classList.add('btn-chat-pending');
        btn.childNodes[0].textContent = '🟠 Chat pendiente';
    } else {
        btn.classList.add('btn-chat-active');
        btn.childNodes[0].textContent = '🟢 Chat activo';
    }
    if (badge) btn.appendChild(badge);
}

function updateRowBtn(regId) {
    updateRowBtnState(regId, chatIsActive, chatIsActive ? null : null);
}
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
