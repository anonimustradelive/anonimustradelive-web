<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
$pdo = getPDO();

$edit_id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$invoice = null;
$items = [['description' => '', 'quantity' => 1, 'unit_price' => 0]];

if ($edit_id && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $stmt = $pdo->prepare("SELECT * FROM invoices WHERE id = ? AND status = 'draft'");
    $stmt->execute([$edit_id]);
    $invoice = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$invoice) {
        $edit_id = 0;
    } else {
        $istmt = $pdo->prepare("SELECT * FROM invoice_items WHERE invoice_id = ? ORDER BY sort_order, id");
        $istmt->execute([$edit_id]);
        $items = $istmt->fetchAll(PDO::FETCH_ASSOC) ?: $items;
    }
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $client_name  = trim($_POST['client_name'] ?? '');
    $client_email = trim($_POST['client_email'] ?? '');
    $service_type = in_array($_POST['service_type'] ?? '', ['ads', 'contenido', 'otro']) ? $_POST['service_type'] : 'otro';
    $issue_date   = $_POST['issue_date'] ?? date('Y-m-d');
    $due_date     = trim($_POST['due_date'] ?? '') ?: null;
    $discount_pct = max(0, min(100, (float)($_POST['discount_pct'] ?? 0)));
    $tax_enabled  = !empty($_POST['tax_enabled']) ? 1 : 0;
    $tax_pct      = max(0, (float)($_POST['tax_pct'] ?? 18));
    $notes        = trim($_POST['notes'] ?? '');

    $desc_arr  = $_POST['item_desc']  ?? [];
    $qty_arr   = $_POST['item_qty']   ?? [];
    $price_arr = $_POST['item_price'] ?? [];

    $clean_items = [];
    $subtotal = 0;
    foreach ($desc_arr as $i => $d) {
        $d = trim($d);
        $q = (float)($qty_arr[$i] ?? 0);
        $p = (float)($price_arr[$i] ?? 0);
        if ($d === '' || $q <= 0) continue;
        $line_total = round($q * $p, 2);
        $subtotal += $line_total;
        $clean_items[] = ['description' => $d, 'quantity' => $q, 'unit_price' => $p, 'line_total' => $line_total];
    }

    if ($client_name === '') {
        $error = 'El nombre del cliente es obligatorio.';
    } elseif (empty($clean_items)) {
        $error = 'Agrega al menos un servicio con descripción y cantidad válida.';
    } else {
        $discount_amount = round($subtotal * $discount_pct / 100, 2);
        $taxed_base = $subtotal - $discount_amount;
        $tax_amount = $tax_enabled ? round($taxed_base * $tax_pct / 100, 2) : 0;
        $total = $taxed_base + $tax_amount;

        if ($edit_id) {
            $chk = $pdo->prepare("SELECT id FROM invoices WHERE id = ? AND status = 'draft'");
            $chk->execute([$edit_id]);
            if (!$chk->fetch()) { $edit_id = 0; }
        }

        if ($edit_id) {
            $pdo->prepare("UPDATE invoices SET client_name=?, client_email=?, service_type=?, issue_date=?, due_date=?,
                subtotal=?, discount_pct=?, discount_amount=?, tax_enabled=?, tax_pct=?, tax_amount=?, total=?, notes=?, updated_at=NOW()
                WHERE id=?")
                ->execute([$client_name, $client_email ?: null, $service_type, $issue_date, $due_date,
                    $subtotal, $discount_pct, $discount_amount, $tax_enabled, $tax_pct, $tax_amount, $total, $notes ?: null, $edit_id]);
            $inv_id = $edit_id;
            $pdo->prepare("DELETE FROM invoice_items WHERE invoice_id=?")->execute([$inv_id]);
        } else {
            $year = (int)date('Y');
            $seq  = (int)$pdo->query("SELECT COUNT(*) FROM invoices WHERE YEAR(created_at) = $year")->fetchColumn() + 1;
            $invoice_number = "INV-$year-" . str_pad((string)$seq, 4, '0', STR_PAD_LEFT);

            $pdo->prepare("INSERT INTO invoices
                (invoice_number, client_name, client_email, service_type, issue_date, due_date,
                 subtotal, discount_pct, discount_amount, tax_enabled, tax_pct, tax_amount, total, notes)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
                ->execute([$invoice_number, $client_name, $client_email ?: null, $service_type, $issue_date, $due_date,
                    $subtotal, $discount_pct, $discount_amount, $tax_enabled, $tax_pct, $tax_amount, $total, $notes ?: null]);
            $inv_id = $pdo->lastInsertId();
        }

        $ins = $pdo->prepare("INSERT INTO invoice_items (invoice_id, description, quantity, unit_price, line_total, sort_order) VALUES (?,?,?,?,?,?)");
        foreach ($clean_items as $idx => $it) {
            $ins->execute([$inv_id, $it['description'], $it['quantity'], $it['unit_price'], $it['line_total'], $idx]);
        }

        header("Location: /facturas/ver.php?id=$inv_id&created=1");
        exit;
    }

    // Si hubo error, conservar lo que el usuario escribió para no perder el trabajo
    $invoice = [
        'client_name' => $client_name, 'client_email' => $client_email, 'service_type' => $service_type,
        'issue_date' => $issue_date, 'due_date' => $due_date, 'discount_pct' => $discount_pct,
        'tax_enabled' => $tax_enabled, 'tax_pct' => $tax_pct, 'notes' => $notes,
    ];
    $items = $clean_items ?: $items;
}

$active = 'facturas';
$panel_title = $edit_id ? 'Editar factura' : 'Nueva factura';
include __DIR__ . '/../includes/nav.php';
?>
<div class="page-head">
  <h1><?= $edit_id ? '✏️ Editar factura' : '🧾 Nueva factura' ?></h1>
  <a href="/facturas/" class="btn-secondary">← Volver</a>
</div>

<?php if ($error): ?><div class="flash flash-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<form method="POST" class="invoice-form" id="invoice-form">
  <?php if ($edit_id): ?><input type="hidden" name="id" value="<?= $edit_id ?>"><?php endif; ?>

  <div class="form-grid">
    <div class="field">
      <label>Cliente *</label>
      <input type="text" name="client_name" required value="<?= htmlspecialchars($invoice['client_name'] ?? '') ?>" placeholder="Nombre del cliente o empresa">
    </div>
    <div class="field">
      <label>Correo</label>
      <input type="email" name="client_email" value="<?= htmlspecialchars($invoice['client_email'] ?? '') ?>" placeholder="cliente@correo.com">
    </div>
    <div class="field">
      <label>Servicio</label>
      <select name="service_type">
        <?php foreach (['ads' => 'Ads (publicidad)', 'contenido' => 'Creación de contenido', 'otro' => 'Otro'] as $v => $l): ?>
        <option value="<?= $v ?>" <?= ($invoice['service_type'] ?? '') === $v ? 'selected' : '' ?>><?= $l ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field">
      <label>Fecha de emisión</label>
      <input type="date" name="issue_date" value="<?= $invoice['issue_date'] ?? date('Y-m-d') ?>">
    </div>
    <div class="field">
      <label>Fecha de vencimiento</label>
      <input type="date" name="due_date" value="<?= $invoice['due_date'] ?? '' ?>">
    </div>
  </div>

  <h3 class="section-title">Servicios facturados</h3>
  <div id="items-wrap">
    <?php foreach ($items as $it): ?>
    <div class="item-row">
      <input type="text" name="item_desc[]" placeholder="Descripción del servicio" value="<?= htmlspecialchars($it['description'] ?? '') ?>" class="item-desc">
      <input type="number" name="item_qty[]" min="0" step="0.01" value="<?= htmlspecialchars((string)($it['quantity'] ?? 1)) ?>" class="item-qty" placeholder="Cant.">
      <input type="number" name="item_price[]" min="0" step="0.01" value="<?= htmlspecialchars((string)($it['unit_price'] ?? 0)) ?>" class="item-price" placeholder="Precio">
      <span class="item-total">$0.00</span>
      <button type="button" class="btn-remove-item" onclick="removeItemRow(this)">✕</button>
    </div>
    <?php endforeach; ?>
  </div>
  <button type="button" class="btn-secondary" onclick="addItemRow()">+ Agregar línea</button>

  <div class="totals-panel">
    <div class="field">
      <label>Descuento (%)</label>
      <input type="number" name="discount_pct" min="0" max="100" step="0.01" value="<?= $invoice['discount_pct'] ?? 0 ?>" id="discount_pct">
    </div>
    <div class="field field-checkbox">
      <label><input type="checkbox" name="tax_enabled" id="tax_enabled" <?= !empty($invoice['tax_enabled']) ? 'checked' : '' ?>> Incluir ITBIS</label>
      <input type="number" name="tax_pct" min="0" step="0.01" value="<?= $invoice['tax_pct'] ?? 18 ?>" id="tax_pct" style="width:80px">%
    </div>
    <div class="totals-summary">
      <div><span>Subtotal</span><strong id="t-subtotal">$0.00</strong></div>
      <div><span>Descuento</span><strong id="t-discount">−$0.00</strong></div>
      <div><span>ITBIS</span><strong id="t-tax">$0.00</strong></div>
      <div class="total-final"><span>Total</span><strong id="t-total">$0.00</strong></div>
    </div>
  </div>

  <div class="field">
    <label>Notas (opcional)</label>
    <textarea name="notes" rows="3" placeholder="Condiciones de pago, referencia, etc."><?= htmlspecialchars($invoice['notes'] ?? '') ?></textarea>
  </div>

  <button type="submit" class="btn-primary btn-save">💾 Guardar factura</button>
</form>

<script>
function rowTemplate() {
  const row = document.createElement('div');
  row.className = 'item-row';
  row.innerHTML = `
    <input type="text" name="item_desc[]" placeholder="Descripción del servicio" class="item-desc">
    <input type="number" name="item_qty[]" min="0" step="0.01" value="1" class="item-qty" placeholder="Cant.">
    <input type="number" name="item_price[]" min="0" step="0.01" value="0" class="item-price" placeholder="Precio">
    <span class="item-total">$0.00</span>
    <button type="button" class="btn-remove-item" onclick="removeItemRow(this)">✕</button>`;
  return row;
}
function addItemRow() {
  document.getElementById('items-wrap').appendChild(rowTemplate());
  bindRecalc();
  recalc();
}
function removeItemRow(btn) {
  const wrap = document.getElementById('items-wrap');
  if (wrap.children.length > 1) btn.closest('.item-row').remove();
  recalc();
}
function fmt(n) { return '$' + n.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}); }
function recalc() {
  let subtotal = 0;
  document.querySelectorAll('.item-row').forEach(row => {
    const q = parseFloat(row.querySelector('.item-qty').value) || 0;
    const p = parseFloat(row.querySelector('.item-price').value) || 0;
    const lt = q * p;
    row.querySelector('.item-total').textContent = fmt(lt);
    subtotal += lt;
  });
  const discPct = parseFloat(document.getElementById('discount_pct').value) || 0;
  const discAmt = subtotal * discPct / 100;
  const taxedBase = subtotal - discAmt;
  const taxEnabled = document.getElementById('tax_enabled').checked;
  const taxPct = parseFloat(document.getElementById('tax_pct').value) || 0;
  const taxAmt = taxEnabled ? taxedBase * taxPct / 100 : 0;
  const total = taxedBase + taxAmt;

  document.getElementById('t-subtotal').textContent = fmt(subtotal);
  document.getElementById('t-discount').textContent = '−' + fmt(discAmt);
  document.getElementById('t-tax').textContent = fmt(taxAmt);
  document.getElementById('t-total').textContent = fmt(total);
}
function bindRecalc() {
  document.querySelectorAll('.item-qty, .item-price').forEach(el => el.oninput = recalc);
}
bindRecalc();
document.getElementById('discount_pct').oninput = recalc;
document.getElementById('tax_enabled').onchange = recalc;
document.getElementById('tax_pct').oninput = recalc;
recalc();
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
