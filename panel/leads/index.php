<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/leads_data.php';

$flash = '';
$flash_type = 'ok';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $result = deleteLead((int)($_POST['lead_index'] ?? -1), $_POST['lead_check'] ?? '');
    $flash = $result['message'];
    $flash_type = $result['ok'] ? 'ok' : 'error';
}

$leads = getLeads();
$total = count($leads);
$entries = array_reverse($leads, true); // más recientes primero, conservando el índice real

$active = 'leads';
$panel_title = 'Leads Punto Zerø';
include __DIR__ . '/../includes/nav.php';
?>
<div class="page-head">
  <h1>🎓 Punto Zerø — Lista de espera</h1>
  <div class="items-actions">
    <a href="/leads/export_csv.php" class="btn-primary">⬇️ CSV</a>
  </div>
</div>

<?php if ($flash): ?><div class="flash flash-<?= $flash_type ?>"><?= htmlspecialchars($flash) ?></div><?php endif; ?>

<?php if (getLeadsDir() === null): ?>
<div class="flash flash-error">⚠️ No se pudo leer la carpeta <code>puntozero/</code> del docroot principal. Revisa <code>panel/includes/leads_data.php</code>.</div>
<?php endif; ?>

<p class="muted-sub" style="margin-bottom:1.25rem">
  <?= $total ?> correo<?= $total === 1 ? '' : 's' ?> en la lista de espera de la próxima convocatoria.
</p>

<?php if ($total === 0): ?>
<div class="table-wrap"><div class="empty">Todavía nadie se ha anotado.</div></div>
<?php else: ?>
<div class="table-wrap">
  <table>
    <thead>
      <tr><th>#</th><th>Correo</th><th>Fecha</th><th>Origen</th><th></th></tr>
    </thead>
    <tbody>
      <?php $n = $total; foreach ($entries as $i => $l): ?>
      <tr>
        <td class="muted-sub"><?= $n-- ?></td>
        <td><a href="mailto:<?= htmlspecialchars($l['email'] ?? '') ?>"><?= htmlspecialchars($l['email'] ?? '') ?></a></td>
        <td class="muted-sub">
          <?php
            $f = $l['fecha'] ?? '';
            $ts = $f ? strtotime($f) : false;
            echo htmlspecialchars($ts ? date('d/m/Y H:i', $ts) : $f);
          ?>
        </td>
        <td class="muted-sub"><?= htmlspecialchars($l['origen'] ?? '—') ?></td>
        <td>
          <form method="POST" onsubmit="return confirm('¿Eliminar <?= htmlspecialchars($l['email'] ?? '', ENT_QUOTES) ?> de la lista? No se puede deshacer.')">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="lead_index" value="<?= $i ?>">
            <input type="hidden" name="lead_check" value="<?= htmlspecialchars($l['fecha'] ?? '') ?>">
            <button type="submit" class="btn-reject">🗑️</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
