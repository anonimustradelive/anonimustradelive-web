<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
$pdo = getPDO();

$flash = '';
$flash_type = 'ok';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id  = (int)($_POST['id'] ?? 0);
    $act = $_POST['action'] ?? '';

    $stmt = $pdo->prepare("SELECT * FROM invoices WHERE id = ?");
    $stmt->execute([$id]);
    $inv = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($inv) {
        if ($act === 'mark_sent' && $inv['status'] === 'draft') {
            $pdo->prepare("UPDATE invoices SET status='sent', updated_at=NOW() WHERE id=?")->execute([$id]);
            $flash = "Factura {$inv['invoice_number']} marcada como enviada.";
        } elseif ($act === 'mark_paid') {
            $pdo->prepare("UPDATE invoices SET status='paid', updated_at=NOW() WHERE id=?")->execute([$id]);
            $flash = "Factura {$inv['invoice_number']} marcada como pagada.";
        } elseif ($act === 'mark_overdue') {
            $pdo->prepare("UPDATE invoices SET status='overdue', updated_at=NOW() WHERE id=?")->execute([$id]);
            $flash = "Factura {$inv['invoice_number']} marcada como vencida.";
        } elseif ($act === 'cancel') {
            $pdo->prepare("UPDATE invoices SET status='cancelled', updated_at=NOW() WHERE id=?")->execute([$id]);
            $flash = "Factura {$inv['invoice_number']} anulada.";
        } elseif ($act === 'delete' && $inv['status'] === 'draft') {
            $pdo->prepare("DELETE FROM invoice_items WHERE invoice_id=?")->execute([$id]);
            $pdo->prepare("DELETE FROM invoices WHERE id=?")->execute([$id]);
            $flash = "Borrador {$inv['invoice_number']} eliminado.";
        }
    }
}

$status_filter = in_array($_GET['status'] ?? '', ['all', 'draft', 'sent', 'paid', 'overdue', 'cancelled'])
    ? ($_GET['status'] ?? 'all') : 'all';
$where = $status_filter !== 'all' ? "WHERE status = " . $pdo->quote($status_filter) : "";
$invoices = $pdo->query("SELECT * FROM invoices $where ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

$counts_raw = $pdo->query("SELECT status, COUNT(*) n FROM invoices GROUP BY status")->fetchAll(PDO::FETCH_ASSOC);
$counts = ['draft' => 0, 'sent' => 0, 'paid' => 0, 'overdue' => 0, 'cancelled' => 0, 'all' => 0];
foreach ($counts_raw as $r) { $counts[$r['status']] = (int)$r['n']; $counts['all'] += (int)$r['n']; }

$status_labels  = ['draft' => 'Borrador', 'sent' => 'Enviada', 'paid' => 'Pagada', 'overdue' => 'Vencida', 'cancelled' => 'Anulada'];

$service_types_raw = $pdo->query("SELECT slug, name FROM service_types ORDER BY sort_order, id")->fetchAll(PDO::FETCH_ASSOC);
$service_labels = [];
foreach ($service_types_raw as $st) { $service_labels[$st['slug']] = $st['name']; }

$active = 'facturas';
$panel_title = 'Facturación';
include __DIR__ . '/../includes/nav.php';
?>
<div class="page-head">
  <h1>🧾 Facturación</h1>
  <a href="/facturas/nueva.php" class="btn-primary">+ Nueva factura</a>
</div>

<?php if ($flash): ?><div class="flash flash-<?= $flash_type ?>"><?= htmlspecialchars($flash) ?></div><?php endif; ?>

<div class="filters-wrap">
  <span class="filter-label">Estado:</span>
  <?php foreach (['all' => 'Todas', 'draft' => 'Borradores', 'sent' => 'Enviadas', 'paid' => 'Pagadas', 'overdue' => 'Vencidas', 'cancelled' => 'Anuladas'] as $sf => $sl): ?>
  <a class="filter-btn <?= $status_filter === $sf ? 'active' : '' ?>" href="?status=<?= $sf ?>"><?= $sl ?> (<?= $counts[$sf] ?>)</a>
  <?php endforeach; ?>
</div>

<div class="table-wrap">
<?php if (empty($invoices)): ?>
  <div class="empty">No hay facturas <?= $status_filter !== 'all' ? 'en este estado' : '' ?> todavía.</div>
<?php else: ?>
<table>
  <thead>
    <tr><th>#</th><th>Cliente</th><th>Servicio</th><th>Emisión</th><th>Vencimiento</th><th>Total</th><th>Estado</th><th>Acciones</th></tr>
  </thead>
  <tbody>
    <?php foreach ($invoices as $inv): ?>
    <tr>
      <td>
        <strong><?= htmlspecialchars($inv['invoice_number']) ?></strong>
        <?php if (($inv['doc_type'] ?? 'invoice') === 'receipt'): ?><div class="muted-sub">✅ Comprobante</div><?php endif; ?>
      </td>
      <td>
        <?= htmlspecialchars($inv['client_name']) ?>
        <?php if ($inv['client_email']): ?><div class="muted-sub"><?= htmlspecialchars($inv['client_email']) ?></div><?php endif; ?>
      </td>
      <td><?= $service_labels[$inv['service_type']] ?? $inv['service_type'] ?></td>
      <td><?= date('d/m/Y', strtotime($inv['issue_date'])) ?></td>
      <td><?= $inv['due_date'] ? date('d/m/Y', strtotime($inv['due_date'])) : '—' ?></td>
      <td>
        <strong><?= ($inv['currency'] ?? 'USD') === 'DOP' ? 'RD$' : '$' ?><?= number_format($inv['total'], 2) ?></strong>
        <?php if (($inv['currency'] ?? 'USD') === 'DOP'): ?><div class="muted-sub">DOP</div><?php endif; ?>
      </td>
      <td><span class="badge badge-<?= $inv['status'] ?>"><?= $status_labels[$inv['status']] ?></span></td>
      <td>
        <div class="action-btns">
          <a href="/facturas/ver.php?id=<?= $inv['id'] ?>" class="btn-view" target="_blank">Ver / Imprimir</a>
          <?php if ($inv['status'] === 'draft'): ?>
          <a href="/facturas/nueva.php?id=<?= $inv['id'] ?>" class="btn-edit">Editar</a>
          <form method="POST" onsubmit="return confirm('¿Marcar como enviada?')">
            <input type="hidden" name="id" value="<?= $inv['id'] ?>">
            <input type="hidden" name="action" value="mark_sent">
            <button type="submit" class="btn-sent">Marcar enviada</button>
          </form>
          <form method="POST" onsubmit="return confirm('¿Eliminar este borrador?')">
            <input type="hidden" name="id" value="<?= $inv['id'] ?>">
            <input type="hidden" name="action" value="delete">
            <button type="submit" class="btn-reject">Eliminar</button>
          </form>
          <?php elseif (in_array($inv['status'], ['sent', 'overdue'])): ?>
          <form method="POST" onsubmit="return confirm('¿Marcar como pagada?')">
            <input type="hidden" name="id" value="<?= $inv['id'] ?>">
            <input type="hidden" name="action" value="mark_paid">
            <button type="submit" class="btn-accept">Marcar pagada</button>
          </form>
          <?php if ($inv['status'] === 'sent'): ?>
          <form method="POST" onsubmit="return confirm('¿Marcar como vencida?')">
            <input type="hidden" name="id" value="<?= $inv['id'] ?>">
            <input type="hidden" name="action" value="mark_overdue">
            <button type="submit" class="btn-kyc">Marcar vencida</button>
          </form>
          <?php endif; ?>
          <form method="POST" onsubmit="return confirm('¿Anular esta factura?')">
            <input type="hidden" name="id" value="<?= $inv['id'] ?>">
            <input type="hidden" name="action" value="cancel">
            <button type="submit" class="btn-reject">Anular</button>
          </form>
          <?php endif; ?>
        </div>
      </td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
