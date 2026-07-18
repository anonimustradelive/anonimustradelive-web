<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
$pdo = getPDO();

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM invoices WHERE id = ?");
$stmt->execute([$id]);
$inv = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$inv) { http_response_code(404); exit('Factura no encontrada.'); }

$istmt = $pdo->prepare("SELECT * FROM invoice_items WHERE invoice_id = ? ORDER BY sort_order, id");
$istmt->execute([$id]);
$items = $istmt->fetchAll(PDO::FETCH_ASSOC);

$status_labels = ['draft' => 'Borrador', 'sent' => 'Enviada', 'paid' => 'Pagada', 'overdue' => 'Vencida', 'cancelled' => 'Anulada'];
$is_receipt = ($inv['doc_type'] ?? 'invoice') === 'receipt';
$doc_label  = $is_receipt ? 'Comprobante' : 'Factura';
function money($n) { return '$' . number_format((float)$n, 2); }
function trimNum($n) { return rtrim(rtrim(number_format((float)$n, 2), '0'), '.'); }
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title><?= $doc_label ?> <?= htmlspecialchars($inv['invoice_number']) ?> — AnonimusTrade Live</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
  :root { --blue:#2563EB; --ink:#0F172A; --muted:#64748B; --bdr:#E2E8F0; --bg3:#F1F5F9; --green:#059669; }
  * { margin:0; padding:0; box-sizing:border-box; }
  body { font-family:'Inter',sans-serif; color:var(--ink); background:#F8FAFC; padding:2.5rem 1rem; }
  .sheet { max-width:760px; margin:0 auto; background:#fff; border:1px solid var(--bdr); border-radius:12px; padding:2.5rem; }
  .toolbar { max-width:760px; margin:0 auto 1rem; display:flex; justify-content:flex-end; gap:0.6rem; }
  .toolbar button, .toolbar a { font-family:inherit; font-size:0.82rem; font-weight:700; padding:0.6rem 1.1rem; border-radius:6px; border:1px solid var(--bdr); background:#fff; color:var(--ink); cursor:pointer; text-decoration:none; }
  .toolbar .btn-print { background:var(--blue); color:#fff; border-color:var(--blue); }
  .inv-head { display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:2rem; padding-bottom:1.5rem; border-bottom:2px solid var(--ink) }
  .brand { font-size:1.3rem; font-weight:800 }
  .brand span { color:#C0112B }
  .brand-sub { font-size:0.78rem; color:var(--muted); margin-top:2px }
  .inv-meta { text-align:right }
  .inv-meta .num { font-size:1.1rem; font-weight:800; color:var(--blue) }
  .inv-meta .status { display:inline-block; margin-top:6px; font-size:0.68rem; font-weight:700; letter-spacing:0.04em; text-transform:uppercase; padding:3px 10px; border-radius:20px; background:var(--bg3); color:var(--muted) }
  .status-paid { background:#DCFCE7; color:#15803D }
  .status-sent { background:#DBEAFE; color:#1E40AF }
  .status-overdue { background:#FEE2E2; color:#B91C1C }
  .status-draft { background:#F1F5F9; color:#64748B }
  .status-cancelled { background:#F1F5F9; color:#94A3B8; text-decoration:line-through }
  .grid-2 { display:grid; grid-template-columns:1fr 1fr; gap:1.5rem; margin-bottom:2rem }
  .block-label { font-size:0.65rem; font-weight:700; letter-spacing:0.08em; text-transform:uppercase; color:var(--muted); margin-bottom:6px }
  .block-val { font-size:0.92rem; font-weight:600 }
  .block-sub { font-size:0.8rem; color:var(--muted); margin-top:2px }
  table { width:100%; border-collapse:collapse; margin-bottom:1.5rem }
  th { text-align:left; font-size:0.68rem; font-weight:700; letter-spacing:0.05em; text-transform:uppercase; color:var(--muted); padding:8px 6px; border-bottom:2px solid var(--ink) }
  th.num, td.num { text-align:right }
  td { padding:10px 6px; font-size:0.86rem; border-bottom:1px solid var(--bdr) }
  .line-note { font-size:0.72rem; color:var(--green); font-weight:600; margin-top:2px }
  .totals { margin-left:auto; width:280px }
  .totals div { display:flex; justify-content:space-between; padding:6px 0; font-size:0.86rem }
  .totals .discount { color:var(--green) }
  .totals .final { border-top:2px solid var(--ink); margin-top:6px; padding-top:10px; font-size:1.1rem; font-weight:800 }
  .notes { margin-top:2rem; padding-top:1.5rem; border-top:1px solid var(--bdr); font-size:0.82rem; color:var(--muted); white-space:pre-wrap }
  .footer-note { margin-top:2rem; font-size:0.72rem; color:var(--muted); text-align:center }
  @media print {
    body { background:#fff; padding:0 }
    .toolbar { display:none }
    .sheet { border:none; border-radius:0; max-width:100%; padding:0 }
    @page { margin:1.5cm }
  }
</style>
</head>
<body>

<div class="toolbar">
  <a href="/facturas/">← Volver al listado</a>
  <button class="btn-print" onclick="window.print()">🖨️ Imprimir / Guardar PDF</button>
</div>

<div class="sheet">
  <div class="inv-head">
    <div>
      <div class="brand">Anonimus<span>Trade</span> Live</div>
      <div class="brand-sub">anonimustradelive@outlook.com</div>
    </div>
    <div class="inv-meta">
      <div class="block-label" style="margin-bottom:2px"><?= $doc_label ?></div>
      <div class="num"><?= htmlspecialchars($inv['invoice_number']) ?></div>
      <span class="status status-<?= $inv['status'] ?>"><?= $status_labels[$inv['status']] ?></span>
    </div>
  </div>

  <div class="grid-2">
    <div>
      <div class="block-label">Facturado a</div>
      <div class="block-val"><?= htmlspecialchars($inv['client_name']) ?></div>
      <?php if ($inv['client_email']): ?><div class="block-sub"><?= htmlspecialchars($inv['client_email']) ?></div><?php endif; ?>
    </div>
    <div>
      <div class="block-label">Fecha de emisión</div>
      <div class="block-val"><?= date('d/m/Y', strtotime($inv['issue_date'])) ?></div>
      <?php if ($inv['due_date']): ?>
      <div class="block-label" style="margin-top:10px">Vencimiento</div>
      <div class="block-val"><?= date('d/m/Y', strtotime($inv['due_date'])) ?></div>
      <?php endif; ?>
    </div>
  </div>

  <table>
    <thead><tr><th>Descripción</th><th class="num">Cant.</th><th class="num">Precio</th><th class="num">Total</th></tr></thead>
    <tbody>
      <?php foreach ($items as $it): ?>
      <tr>
        <td>
          <?= htmlspecialchars($it['description']) ?>
          <?php if (!empty($it['line_note'])): ?><div class="line-note"><?= htmlspecialchars($it['line_note']) ?></div><?php endif; ?>
        </td>
        <td class="num"><?= trimNum($it['quantity']) ?></td>
        <td class="num"><?= money($it['unit_price']) ?></td>
        <td class="num"><?= money($it['line_total']) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <div class="totals">
    <div><span>Subtotal</span><strong><?= money($inv['subtotal']) ?></strong></div>
    <?php if ($inv['discount_amount'] > 0): ?>
    <div class="discount"><span>Descuento (<?= trimNum($inv['discount_pct']) ?>%)</span><strong>−<?= money($inv['discount_amount']) ?></strong></div>
    <?php endif; ?>
    <?php if ($inv['tax_enabled']): ?>
    <div><span>ITBIS (<?= trimNum($inv['tax_pct']) ?>%)</span><strong><?= money($inv['tax_amount']) ?></strong></div>
    <?php endif; ?>
    <div class="final"><span>Total</span><strong><?= money($inv['total']) ?> USD</strong></div>
  </div>

  <?php if ($inv['notes']): ?>
  <div class="notes"><strong>Notas:</strong><br><?= nl2br(htmlspecialchars($inv['notes'])) ?></div>
  <?php endif; ?>

  <div class="footer-note">AnonimusTrade Live · República Dominicana</div>
</div>

</body>
</html>
