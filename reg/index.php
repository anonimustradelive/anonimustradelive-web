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

// Acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['id'])) {
    $id  = (int)$_POST['id'];
    $act = $_POST['action'];

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
                    "👉 $link\n\n" .
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
                "Si crees que esto es un error, contáctanos directamente\\."
            );
            $flash = "Registro #$id rechazado.";
        }
    }
}

// Filtro
$filter = in_array($_GET['filter'] ?? '', ['all','pending','accepted','rejected']) ? $_GET['filter'] : 'pending';
$where  = $filter === 'all' ? '' : "WHERE status = " . $pdo->quote($filter);
$regs   = $pdo->query("SELECT * FROM registrations $where ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

$raw_counts = $pdo->query("SELECT status, COUNT(*) n FROM registrations GROUP BY status")->fetchAll(PDO::FETCH_ASSOC);
$counts = ['pending' => 0, 'accepted' => 0, 'rejected' => 0, 'all' => 0];
foreach ($raw_counts as $r) { $counts[$r['status']] = (int)$r['n']; $counts['all'] += (int)$r['n']; }

$plabels = ['pepperstone' => 'Pepperstone', 'bingx' => 'BingX', 'bitunix' => 'Bitunix'];
$prlabels = ['principiante' => 'Principiante', 'trader' => 'Trader'];

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

function tgSend(int $chat_id, string $text): void {
    $ctx = stream_context_create(['http' => [
        'method'        => 'POST',
        'header'        => "Content-Type: application/json\r\n",
        'content'       => json_encode(['chat_id' => $chat_id, 'text' => $text, 'parse_mode' => 'MarkdownV2']),
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
  nav { background:var(--black2); border-bottom:1px solid var(--gray); padding:0 2rem; height:60px; display:flex; align-items:center; justify-content:space-between; }
  .nav-brand { font-size:0.75rem; font-weight:800; letter-spacing:0.12em; text-transform:uppercase; }
  .nav-brand span { color:var(--red); }
  .nav-right { display:flex; align-items:center; gap:1rem; }
  .nav-badge { font-size:0.62rem; font-weight:700; letter-spacing:0.1em; text-transform:uppercase; background:var(--yellow-light); color:var(--yellow); border:1px solid rgba(245,158,11,0.3); border-radius:20px; padding:3px 10px; }
  .nav-logout { font-size:0.68rem; font-weight:600; color:var(--text-muted); text-decoration:none; letter-spacing:0.08em; text-transform:uppercase; transition:color 0.2s; }
  .nav-logout:hover { color:var(--white); }

  /* MAIN */
  main { max-width:1100px; margin:0 auto; padding:2rem 1.5rem; }

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

  /* FILTERS */
  .filters { display:flex; gap:0.5rem; margin-bottom:1.5rem; }
  .filter-btn { font-family:inherit; font-size:0.68rem; font-weight:700; letter-spacing:0.08em; text-transform:uppercase; padding:6px 16px; border-radius:4px; border:1px solid var(--gray); background:transparent; color:var(--text-muted); cursor:pointer; text-decoration:none; transition:all 0.2s; }
  .filter-btn:hover { border-color:var(--purple-light); color:var(--purple-light); }
  .filter-btn.active { background:var(--purple); border-color:var(--purple); color:var(--white); }

  /* TABLE */
  .table-wrap { background:var(--black2); border:1px solid var(--gray); border-radius:6px; overflow:hidden; overflow-x:auto; }
  table { width:100%; border-collapse:collapse; }
  th { font-size:0.62rem; font-weight:700; letter-spacing:0.1em; text-transform:uppercase; color:var(--text-muted); padding:0.85rem 1.25rem; text-align:left; border-bottom:1px solid var(--gray); white-space:nowrap; }
  td { padding:0.9rem 1.25rem; border-bottom:1px solid rgba(255,255,255,0.04); font-size:0.78rem; vertical-align:middle; }
  tr:last-child td { border-bottom:none; }
  tr:hover td { background:rgba(255,255,255,0.02); }

  .badge { display:inline-block; font-size:0.6rem; font-weight:700; letter-spacing:0.08em; text-transform:uppercase; padding:3px 8px; border-radius:3px; }
  .badge-pending  { background:var(--yellow-light); color:var(--yellow); border:1px solid rgba(245,158,11,0.3); }
  .badge-accepted { background:var(--green-light);  color:var(--green);  border:1px solid rgba(34,197,94,0.3); }
  .badge-rejected { background:var(--red-light);    color:#FF6B6B;       border:1px solid rgba(192,17,43,0.3); }
  .badge-trader   { background:var(--purple-glow);  color:var(--purple-light); border:1px solid rgba(124,58,237,0.3); }
  .badge-prin     { background:rgba(255,255,255,0.06); color:var(--text-muted); border:1px solid var(--gray); }

  .uid { font-family:monospace; font-size:0.75rem; color:var(--purple-light); background:var(--purple-glow); padding:2px 6px; border-radius:3px; }

  .btn-accept { background:var(--green-light); border:1px solid rgba(34,197,94,0.4); color:var(--green); font-family:inherit; font-size:0.65rem; font-weight:700; letter-spacing:0.06em; text-transform:uppercase; padding:5px 12px; border-radius:3px; cursor:pointer; transition:all 0.2s; }
  .btn-accept:hover { background:rgba(34,197,94,0.25); }
  .btn-reject { background:var(--red-light); border:1px solid rgba(192,17,43,0.4); color:#FF6B6B; font-family:inherit; font-size:0.65rem; font-weight:700; letter-spacing:0.06em; text-transform:uppercase; padding:5px 12px; border-radius:3px; cursor:pointer; transition:all 0.2s; margin-left:6px; }
  .btn-reject:hover { background:rgba(192,17,43,0.22); }

  .empty { padding:3rem; text-align:center; color:var(--text-muted); font-size:0.82rem; }

  @media(max-width:768px) { .stats { grid-template-columns:1fr 1fr; } }
</style>
</head>
<body>

<nav>
  <div class="nav-brand">AnonimusTrade <span>Live</span> · Registros</div>
  <div class="nav-right">
    <?php if ($counts['pending'] > 0): ?>
    <span class="nav-badge"><?= $counts['pending'] ?> pendiente<?= $counts['pending'] > 1 ? 's' : '' ?></span>
    <?php endif; ?>
    <a class="nav-logout" href="logout.php">Cerrar sesión →</a>
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

  <!-- FILTERS -->
  <div class="filters">
    <?php foreach (['pending' => 'Pendientes', 'accepted' => 'Aceptados', 'rejected' => 'Rechazados', 'all' => 'Todos'] as $f => $l): ?>
    <a class="filter-btn <?= $filter === $f ? 'active' : '' ?>" href="?filter=<?= $f ?>"><?= $l ?></a>
    <?php endforeach; ?>
  </div>

  <!-- TABLE -->
  <div class="table-wrap">
    <?php if (empty($regs)): ?>
    <div class="empty">No hay registros <?= $filter === 'pending' ? 'pendientes' : '' ?> por el momento.</div>
    <?php else: ?>
    <table>
      <thead>
        <tr>
          <th>#</th>
          <th>Nombre</th>
          <th>Telegram</th>
          <th>Perfil</th>
          <th>Plataforma</th>
          <th>ID Usuario</th>
          <th>Estado</th>
          <th>Fecha</th>
          <th>Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($regs as $r): ?>
        <tr>
          <td style="color:var(--text-muted)"><?= $r['id'] ?></td>
          <td><strong><?= htmlspecialchars($r['telegram_name'] ?: '—') ?></strong></td>
          <td><?= $r['telegram_username'] ? '@' . htmlspecialchars($r['telegram_username']) : '<span style="color:var(--text-muted)">—</span>' ?></td>
          <td>
            <span class="badge <?= $r['profile_type'] === 'trader' ? 'badge-trader' : 'badge-prin' ?>">
              <?= $prlabels[$r['profile_type']] ?? $r['profile_type'] ?>
              <?php if ($r['asset_type']): ?> · <?= ucfirst($r['asset_type']) ?><?php endif; ?>
            </span>
          </td>
          <td><?= $plabels[$r['platform']] ?? $r['platform'] ?></td>
          <td><span class="uid"><?= htmlspecialchars($r['platform_user_id']) ?></span></td>
          <td><span class="badge badge-<?= $r['status'] ?>"><?= ucfirst($r['status'] === 'pending' ? 'Pendiente' : ($r['status'] === 'accepted' ? 'Aceptado' : 'Rechazado')) ?></span></td>
          <td style="color:var(--text-muted); white-space:nowrap"><?= date('d/m/Y H:i', strtotime($r['created_at'])) ?></td>
          <td>
            <?php if ($r['status'] === 'pending'): ?>
            <form method="POST" style="display:inline" onsubmit="return confirm('¿Aceptar y enviar link de invitación?')">
              <input type="hidden" name="id" value="<?= $r['id'] ?>">
              <input type="hidden" name="action" value="accept">
              <button type="submit" class="btn-accept">✅ Aceptar</button>
            </form>
            <form method="POST" style="display:inline" onsubmit="return confirm('¿Rechazar este registro?')">
              <input type="hidden" name="id" value="<?= $r['id'] ?>">
              <input type="hidden" name="action" value="reject">
              <button type="submit" class="btn-reject">❌ Rechazar</button>
            </form>
            <?php elseif ($r['status'] === 'accepted' && $r['invite_link']): ?>
            <a href="<?= htmlspecialchars($r['invite_link']) ?>" target="_blank" style="font-size:0.68rem; color:var(--purple-light);">Ver link →</a>
            <?php else: ?>
            <span style="color:var(--text-muted); font-size:0.72rem;">—</span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>

</main>
</body>
</html>
