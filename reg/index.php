<?php
session_start();
if (!isset($_SESSION['admin'])) { header('Location: login.php'); exit; }
require_once __DIR__ . '/config.php';

$pdo = new PDO(
    "mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4",
    DB_USER, DB_PASS,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$flash = '';
$flash_type = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['id'])) {
    $id  = (int)$_POST['id'];
    $act = $_POST['action'];

    if ($act === 'save_note') {
        $notes = trim($_POST['notes'] ?? '') ?: null;
        $pdo->prepare("UPDATE registrations SET notes=? WHERE id=?")->execute([$notes, $id]);
        $flash = "📝 Nota guardada para el registro #$id.";
    } else {
        $stmt = $pdo->prepare("SELECT * FROM registrations WHERE id = ?");
        $stmt->execute([$id]);
        $reg = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($reg && $reg['status'] === 'pending') {
            if ($act === 'accept') {
                $link = createInviteLink();
                if ($link) {
                    $pdo->prepare("UPDATE registrations SET status='accepted', invite_link=?, updated_at=NOW() WHERE id=?")
                        ->execute([$link, $id]);
                    $plabels = ['pepperstone' => 'Pepperstone', 'bingx' => 'BingX', 'bitunix' => 'Bitunix'];
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

// Filtros
$filter          = in_array($_GET['filter']   ?? '', ['all','pending','accepted','rejected'])  ? $_GET['filter']            : 'pending';
$platform_filter = in_array($_GET['platform'] ?? '', ['all','pepperstone','bingx','bitunix'])  ? ($_GET['platform'] ?? 'all') : 'all';

$where_parts = [];
if ($filter !== 'all')          $where_parts[] = "status = "   . $pdo->quote($filter);
if ($platform_filter !== 'all') $where_parts[] = "platform = " . $pdo->quote($platform_filter);
$where = $where_parts ? "WHERE " . implode(" AND ", $where_parts) : "";

$regs = $pdo->query("SELECT * FROM registrations $where ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

$raw_counts = $pdo->query("SELECT status, COUNT(*) n FROM registrations GROUP BY status")->fetchAll(PDO::FETCH_ASSOC);
$counts = ['pending' => 0, 'accepted' => 0, 'rejected' => 0, 'all' => 0];
foreach ($raw_counts as $r) { $counts[$r['status']] = (int)$r['n']; $counts['all'] += (int)$r['n']; }

$plabels  = ['pepperstone' => 'Pepperstone', 'bingx' => 'BingX', 'bitunix' => 'Bitunix'];
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
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Registros — AnonimusTrade Live</title>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
  :root {
    --red:#C0112B; --red-light:rgba(192,17,43,0.12);
    --black:#080810; --black2:#0E0E18; --black3:#14141F; --black4:#1A1A28;
    --white:#F0EFFF; --purple:#7C3AED; --purple-light:#A78BFA; --purple-glow:rgba(124,58,237,0.15);
    --gray:#252535; --gray2:#333348; --text-muted:#7070A0;
    --green:#22C55E; --green-light:rgba(34,197,94,0.12);
    --yellow:#F59E0B; --yellow-light:rgba(245,158,11,0.12);
  }
  * { margin:0; padding:0; box-sizing:border-box; }
  body { font-family:'Montserrat',sans-serif; background:var(--black); color:var(--white); min-height:100vh; }

  /* NAV */
  nav { background:var(--black2); border-bottom:1px solid var(--gray); padding:0; height:60px; display:flex; align-items:center; justify-content:center; }
  .nav-inner { width:100%; max-width:1600px; padding:0 clamp(1.5rem,3vw,4rem); display:flex; align-items:center; justify-content:space-between; }
  .nav-brand { font-size:0.75rem; font-weight:800; letter-spacing:0.12em; text-transform:uppercase; }
  .nav-brand span { color:var(--red); }
  .nav-right { display:flex; align-items:center; gap:1rem; }
  .nav-badge { font-size:0.62rem; font-weight:700; letter-spacing:0.1em; text-transform:uppercase; background:var(--yellow-light); color:var(--yellow); border:1px solid rgba(245,158,11,0.3); border-radius:20px; padding:3px 10px; }
  .nav-logout { font-size:0.68rem; font-weight:600; color:var(--text-muted); text-decoration:none; letter-spacing:0.08em; text-transform:uppercase; transition:color 0.2s; }
  .nav-logout:hover { color:var(--white); }

  /* MAIN */
  main { max-width:1600px; margin:0 auto; padding:2rem clamp(1.5rem,3vw,4rem); }

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
  .search-input {
    width:100%; padding:10px 16px; border-radius:5px;
    background:var(--black2); border:1px solid var(--gray);
    color:var(--white); font-family:inherit; font-size:0.82rem;
    transition:border-color 0.2s; outline:none;
  }
  .search-input::placeholder { color:var(--text-muted); }
  .search-input:focus { border-color:var(--purple); }

  /* FILTERS */
  .filter-row { display:flex; align-items:center; gap:0.5rem; margin-bottom:0.75rem; flex-wrap:wrap; }
  .filter-label { font-size:0.62rem; font-weight:700; letter-spacing:0.1em; text-transform:uppercase; color:var(--text-muted); min-width:70px; }
  .filter-btn { font-family:inherit; font-size:0.68rem; font-weight:700; letter-spacing:0.08em; text-transform:uppercase; padding:6px 16px; border-radius:4px; border:1px solid var(--gray); background:transparent; color:var(--text-muted); cursor:pointer; text-decoration:none; transition:all 0.2s; }
  .filter-btn:hover { border-color:var(--purple-light); color:var(--purple-light); }
  .filter-btn.active { background:var(--purple); border-color:var(--purple); color:var(--white); }
  .filters-wrap { margin-bottom:1.5rem; }

  /* TABLE */
  .table-wrap { background:var(--black2); border:1px solid var(--gray); border-radius:6px; overflow:hidden; overflow-x:auto; }
  table { width:100%; border-collapse:collapse; }
  th { font-size:0.62rem; font-weight:700; letter-spacing:0.1em; text-transform:uppercase; color:var(--text-muted); padding:0.85rem 1.25rem; text-align:left; border-bottom:1px solid var(--gray); white-space:nowrap; }
  td { padding:0.9rem 1.25rem; border-bottom:1px solid rgba(255,255,255,0.04); font-size:0.78rem; vertical-align:top; }
  tr:last-child td { border-bottom:none; }
  tr:hover td { background:rgba(255,255,255,0.02); }

  /* STALE ROW */
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
  .btn-accept, .btn-reject, .btn-kyc, .btn-migration, .btn-sent {
    font-family:inherit; font-size:0.65rem; font-weight:700; letter-spacing:0.06em;
    text-transform:uppercase; padding:6px 10px; border-radius:3px; cursor:pointer;
    transition:all 0.2s; width:100%; text-align:center; border:1px solid;
  }
  .btn-accept    { background:var(--green-light);         border-color:rgba(34,197,94,0.4);   color:var(--green);        }
  .btn-accept:hover    { background:rgba(34,197,94,0.25); }
  .btn-reject    { background:var(--red-light);           border-color:rgba(192,17,43,0.4);   color:#FF6B6B;             }
  .btn-reject:hover    { background:rgba(192,17,43,0.22); }
  .btn-kyc       { background:var(--purple-glow);         border-color:rgba(124,58,237,0.4);  color:var(--purple-light); }
  .btn-kyc:hover:not(:disabled)       { background:rgba(124,58,237,0.25); }
  .btn-migration { background:rgba(56,189,248,0.12);      border-color:rgba(56,189,248,0.4);  color:#38BDF8;             }
  .btn-migration:hover:not(:disabled) { background:rgba(56,189,248,0.25); }
  .btn-sent, button:disabled {
    background:rgba(255,255,255,0.04) !important; border-color:var(--gray2) !important;
    color:var(--text-muted) !important; cursor:not-allowed !important; opacity:0.55;
  }

  /* NOTES */
  .note-section { margin-top:8px; padding-top:8px; border-top:1px solid rgba(255,255,255,0.05); }
  .note-preview { font-size:0.68rem; color:var(--text-muted); line-height:1.4; margin-bottom:5px; font-style:italic; }
  .note-toggle {
    font-family:inherit; font-size:0.62rem; font-weight:600; letter-spacing:0.06em; text-transform:uppercase;
    background:transparent; border:1px solid var(--gray); color:var(--text-muted);
    padding:4px 10px; border-radius:3px; cursor:pointer; transition:all 0.2s; width:100%;
  }
  .note-toggle:hover { border-color:var(--gray2); color:var(--white); }
  .note-box { display:none; margin-top:6px; }
  .note-textarea {
    width:100%; background:var(--black3); border:1px solid var(--gray); border-radius:3px;
    color:var(--white); font-family:inherit; font-size:0.72rem; padding:6px 8px;
    resize:vertical; min-height:60px; outline:none;
  }
  .note-textarea:focus { border-color:var(--purple); }
  .note-save {
    margin-top:5px; font-family:inherit; font-size:0.62rem; font-weight:700;
    letter-spacing:0.06em; text-transform:uppercase; padding:5px 12px; border-radius:3px;
    background:var(--purple-glow); border:1px solid rgba(124,58,237,0.4);
    color:var(--purple-light); cursor:pointer; transition:all 0.2s; width:100%;
  }
  .note-save:hover { background:rgba(124,58,237,0.25); }

  /* DATES */
  .date-stack { display:flex; flex-direction:column; gap:3px; white-space:nowrap; font-size:0.73rem; color:var(--text-muted); }
  .date-act { font-size:0.68rem; opacity:0.7; }
  .date-stale { font-size:0.6rem; color:var(--yellow); font-weight:700; margin-top:2px; }

  .empty { padding:3rem; text-align:center; color:var(--text-muted); font-size:0.82rem; }
  .no-results { padding:3rem; text-align:center; color:var(--text-muted); font-size:0.82rem; display:none; }

  @media(max-width:768px) { .stats { grid-template-columns:1fr 1fr; } }
</style>
</head>
<body>

<nav>
  <div class="nav-inner">
    <div class="nav-brand">AnonimusTrade <span>Live</span> · Registros</div>
    <div class="nav-right">
      <?php if ($counts['pending'] > 0): ?>
      <span class="nav-badge"><?= $counts['pending'] ?> pendiente<?= $counts['pending'] > 1 ? 's' : '' ?></span>
      <?php endif; ?>
      <a class="nav-logout" href="logout.php">Cerrar sesión →</a>
    </div>
  </div>
</nav>

<main>

  <?php if ($flash): ?>
  <div class="flash <?= $flash_type ?>"><?= htmlspecialchars($flash) ?></div>
  <?php endif; ?>

  <!-- STATS -->
  <div class="stats">
    <div class="stat pending">
      <div class="stat-label">Pendientes</div>
      <div class="stat-value"><?= $counts['pending'] ?></div>
    </div>
    <div class="stat accepted">
      <div class="stat-label">Aceptados</div>
      <div class="stat-value"><?= $counts['accepted'] ?></div>
    </div>
    <div class="stat rejected">
      <div class="stat-label">Rechazados</div>
      <div class="stat-value"><?= $counts['rejected'] ?></div>
    </div>
    <div class="stat all">
      <div class="stat-label">Total</div>
      <div class="stat-value"><?= $counts['all'] ?></div>
    </div>
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
      <?php foreach (['all' => 'Todas', 'pepperstone' => 'Pepperstone', 'bingx' => 'BingX', 'bitunix' => 'Bitunix'] as $pf => $pl): ?>
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
          <th>#</th>
          <th>Nombre</th>
          <th>Telegram</th>
          <th>Perfil</th>
          <th>Plataforma</th>
          <th>Email</th>
          <th>ID Usuario</th>
          <th>Estado</th>
          <th>Fechas</th>
          <th>Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($regs as $r):
          $is_stale   = $r['status'] === 'pending' && (time() - strtotime($r['updated_at'])) > 172800;
          $kyc_sent   = ($r['kyc_status']  ?? '') === 'pending';
          $migr_sent  = !empty($r['patience_sent']);
          $has_note   = !empty($r['notes']);
        ?>
        <tr id="reg-<?= $r['id'] ?>" <?= $is_stale ? 'class="row-stale"' : '' ?>>
          <td style="color:var(--text-muted)"><?= $r['id'] ?></td>
          <td><strong><?= htmlspecialchars($r['telegram_name'] ?: '—') ?></strong></td>
          <td><?= $r['telegram_username'] ? '@' . htmlspecialchars($r['telegram_username']) : '<span style="color:var(--text-muted)">—</span>' ?></td>
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
              <?php if ($r['platform'] === 'bingx'): ?>
              <?php if ($kyc_sent): ?>
              <form method="POST">
                <button type="button" class="btn-kyc btn-sent" disabled>📋 KYC enviado</button>
              </form>
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
              <form method="POST">
                <button type="button" class="btn-migration btn-sent" disabled>✓ Paciencia enviada</button>
              </form>
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

            <!-- NOTES (visible para todos los registros) -->
            <div class="note-section">
              <?php if ($has_note): ?>
              <div class="note-preview">📝 <?= htmlspecialchars(mb_substr($r['notes'], 0, 60)) ?><?= mb_strlen($r['notes']) > 60 ? '…' : '' ?></div>
              <?php endif; ?>
              <button type="button" class="note-toggle" onclick="toggleNote(<?= $r['id'] ?>)">
                <?= $has_note ? '✏️ Editar nota' : '📝 Agregar nota' ?>
              </button>
              <div id="note-<?= $r['id'] ?>" class="note-box">
                <form method="POST">
                  <input type="hidden" name="id" value="<?= $r['id'] ?>">
                  <input type="hidden" name="action" value="save_note">
                  <textarea name="notes" class="note-textarea" rows="3" placeholder="Nota interna del equipo..."><?= htmlspecialchars($r['notes'] ?? '') ?></textarea>
                  <button type="submit" class="note-save">💾 Guardar nota</button>
                </form>
              </div>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <div class="no-results" id="no-results">No se encontraron registros con esa búsqueda.</div>
    <?php endif; ?>
  </div>

</main>

<script>
// Búsqueda en tiempo real
const searchInput = document.getElementById('search');
if (searchInput) {
  searchInput.addEventListener('input', function () {
    const q = this.value.toLowerCase().trim();
    const rows = document.querySelectorAll('#reg-table tbody tr');
    let visible = 0;
    rows.forEach(function (tr) {
      const match = !q || tr.textContent.toLowerCase().includes(q);
      tr.style.display = match ? '' : 'none';
      if (match) visible++;
    });
    const noResults = document.getElementById('no-results');
    if (noResults) noResults.style.display = (q && visible === 0) ? 'block' : 'none';
  });
}

// Toggle de notas
function toggleNote(id) {
  const box = document.getElementById('note-' + id);
  if (box) box.style.display = box.style.display === 'block' ? 'none' : 'block';
}
</script>

</body>
</html>
