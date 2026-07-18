<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/ads_pricing.php';
$pdo = getPDO();

// ── AJAX: agregar tipo de servicio nuevo ────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_service_type') {
    header('Content-Type: application/json');
    $name = trim($_POST['name'] ?? '');
    if ($name === '') { echo json_encode(['ok' => false, 'error' => 'Escribe un nombre.']); exit; }
    $slug = trim(strtolower(preg_replace('/[^a-z0-9]+/i', '_', $name)), '_');
    if ($slug === '') { echo json_encode(['ok' => false, 'error' => 'Nombre inválido.']); exit; }
    try {
        $maxOrder = (int)$pdo->query("SELECT COALESCE(MAX(sort_order),0) FROM service_types")->fetchColumn();
        $pdo->prepare("INSERT INTO service_types (slug, name, sort_order) VALUES (?, ?, ?)")
            ->execute([$slug, $name, $maxOrder + 1]);
        echo json_encode(['ok' => true, 'id' => $pdo->lastInsertId(), 'name' => $name]);
    } catch (PDOException $e) {
        echo json_encode(['ok' => false, 'error' => 'Ya existe un tipo de servicio con ese nombre.']);
    }
    exit;
}

// ── AJAX: agregar producto/servicio nuevo al catálogo ───────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_catalog_item') {
    header('Content-Type: application/json');
    $name          = trim($_POST['name'] ?? '');
    $price         = (float)($_POST['price'] ?? 0);
    $serviceTypeId = (int)($_POST['service_type_id'] ?? 0);
    if ($name === '' || $serviceTypeId <= 0) { echo json_encode(['ok' => false, 'error' => 'Completa nombre y tipo de servicio.']); exit; }
    $pdo->prepare("INSERT INTO catalog_items (service_type_id, name, logic_type, default_unit_price) VALUES (?, ?, 'generic', ?)")
        ->execute([$serviceTypeId, $name, $price]);
    echo json_encode([
        'ok' => true, 'id' => $pdo->lastInsertId(), 'name' => $name,
        'price' => $price, 'service_type_id' => $serviceTypeId, 'logic_type' => 'generic',
    ]);
    exit;
}

// ── Recalcula server-side el precio de un ítem del catálogo con lógica especial.
//    Devuelve unit_price=null cuando es 'generic' (se respeta lo que venga del formulario). ──
function calcCatalogPrice(array $pricing, string $logicType, ?int $freq): array {
    $freq = $freq ?: 4;
    switch ($logicType) {
        case 'content_deluxe':
            return ['unit_price' => (float)($pricing['contenido']['deluxe']['base'] ?? 0), 'line_note' => null];
        case 'content_premium':
            return ['unit_price' => (float)($pricing['contenido']['premium']['base'] ?? 0), 'line_note' => null];
        case 'addon_publicidad_deluxe':
            return ['unit_price' => (float)($pricing['contenido']['deluxe']['ads'] ?? 0), 'line_note' => null];
        case 'addon_publicidad_premium':
            return ['unit_price' => (float)($pricing['contenido']['premium']['ads'] ?? 0), 'line_note' => null];
        case 'spot_inicio':
        case 'spot_pico':
        case 'spot_cierre':
            $spot  = substr($logicType, 5);
            $price = (float)($pricing['cintillos'][$spot][(string)$freq] ?? 0);
            $base4 = (float)($pricing['cintillos'][$spot]['4'] ?? 0);
            $note  = null;
            if ($freq > 4 && $base4 > 0) {
                $full   = $base4 * ($freq / 4);
                $saving = $full - $price;
                if ($saving > 0.01) {
                    $pct  = round($saving / $full * 100);
                    $note = "−{$pct}% · ahorras $" . number_format($saving, 2);
                }
            }
            return ['unit_price' => $price, 'line_note' => $note];
        default:
            return ['unit_price' => null, 'line_note' => null];
    }
}

$pricing    = getAdsPricing();
$freqLabels = getFreqLabels();

$serviceTypes = $pdo->query("SELECT * FROM service_types ORDER BY sort_order, id")->fetchAll(PDO::FETCH_ASSOC);
$catalogItems = $pdo->query("SELECT * FROM catalog_items WHERE active = 1 ORDER BY service_type_id, name")->fetchAll(PDO::FETCH_ASSOC);
$catalogById  = [];
foreach ($catalogItems as $ci) { $catalogById[(int)$ci['id']] = $ci; }

$edit_id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$invoice = null;
$items = [['description' => '', 'quantity' => 1, 'unit_price' => 0, 'catalog_item_id' => null, 'frequency' => null, 'line_note' => null]];

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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['action'])) {
    $client_name  = trim($_POST['client_name'] ?? '');
    $client_email = trim($_POST['client_email'] ?? '');
    $service_type = trim($_POST['service_type'] ?? '') ?: 'otro';
    $doc_type     = ($_POST['doc_type'] ?? 'invoice') === 'receipt' ? 'receipt' : 'invoice';
    $issue_date   = $_POST['issue_date'] ?? date('Y-m-d');
    $due_date     = $doc_type === 'receipt' ? null : (trim($_POST['due_date'] ?? '') ?: null);
    $discount_pct = max(0, min(100, (float)($_POST['discount_pct'] ?? 0)));
    $tax_enabled  = !empty($_POST['tax_enabled']) ? 1 : 0;
    $tax_pct      = max(0, (float)($_POST['tax_pct'] ?? 18));
    $notes        = trim($_POST['notes'] ?? '');

    $desc_arr = $_POST['item_desc'] ?? [];
    $qty_arr  = $_POST['item_qty']  ?? [];
    $price_arr = $_POST['item_price'] ?? [];
    $cat_arr  = $_POST['item_catalog_id'] ?? [];
    $freq_arr = $_POST['item_freq'] ?? [];

    $clean_items = [];
    $subtotal = 0;
    foreach ($desc_arr as $i => $d) {
        $d = trim($d);
        $q = (float)($qty_arr[$i] ?? 0);
        $p = (float)($price_arr[$i] ?? 0);
        $catId = (int)($cat_arr[$i] ?? 0);
        $freq  = isset($freq_arr[$i]) && $freq_arr[$i] !== '' ? (int)$freq_arr[$i] : null;
        $note  = null;

        if ($catId > 0 && isset($catalogById[$catId])) {
            $calc = calcCatalogPrice($pricing, $catalogById[$catId]['logic_type'], $freq);
            if ($calc['unit_price'] !== null) {
                $p = $calc['unit_price']; // recalculado server-side — no confiar en lo que mandó el navegador
                $note = $calc['line_note'];
            }
        } else {
            $catId = null;
        }

        if ($d === '' || $q <= 0) continue;
        $line_total = round($q * $p, 2);
        $subtotal += $line_total;
        $clean_items[] = [
            'catalog_item_id' => $catId, 'description' => $d, 'quantity' => $q,
            'unit_price' => $p, 'line_total' => $line_total, 'frequency' => $freq, 'line_note' => $note,
        ];
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
        $status = $doc_type === 'receipt' ? 'paid' : 'draft';

        if ($edit_id) {
            $chk = $pdo->prepare("SELECT id FROM invoices WHERE id = ? AND status = 'draft'");
            $chk->execute([$edit_id]);
            if (!$chk->fetch()) { $edit_id = 0; }
        }

        if ($edit_id) {
            $pdo->prepare("UPDATE invoices SET client_name=?, client_email=?, service_type=?, doc_type=?, issue_date=?, due_date=?,
                status=?, subtotal=?, discount_pct=?, discount_amount=?, tax_enabled=?, tax_pct=?, tax_amount=?, total=?, notes=?, updated_at=NOW()
                WHERE id=?")
                ->execute([$client_name, $client_email ?: null, $service_type, $doc_type, $issue_date, $due_date,
                    $status, $subtotal, $discount_pct, $discount_amount, $tax_enabled, $tax_pct, $tax_amount, $total, $notes ?: null, $edit_id]);
            $inv_id = $edit_id;
            $pdo->prepare("DELETE FROM invoice_items WHERE invoice_id=?")->execute([$inv_id]);
        } else {
            $year = (int)date('Y');
            $seq  = (int)$pdo->query("SELECT COUNT(*) FROM invoices WHERE YEAR(created_at) = $year")->fetchColumn() + 1;
            $invoice_number = "INV-$year-" . str_pad((string)$seq, 4, '0', STR_PAD_LEFT);

            $pdo->prepare("INSERT INTO invoices
                (invoice_number, client_name, client_email, service_type, doc_type, issue_date, due_date, status,
                 subtotal, discount_pct, discount_amount, tax_enabled, tax_pct, tax_amount, total, notes)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
                ->execute([$invoice_number, $client_name, $client_email ?: null, $service_type, $doc_type, $issue_date, $due_date, $status,
                    $subtotal, $discount_pct, $discount_amount, $tax_enabled, $tax_pct, $tax_amount, $total, $notes ?: null]);
            $inv_id = $pdo->lastInsertId();
        }

        $ins = $pdo->prepare("INSERT INTO invoice_items
            (invoice_id, catalog_item_id, description, quantity, unit_price, frequency, line_note, line_total, sort_order)
            VALUES (?,?,?,?,?,?,?,?,?)");
        foreach ($clean_items as $idx => $it) {
            $ins->execute([$inv_id, $it['catalog_item_id'], $it['description'], $it['quantity'],
                $it['unit_price'], $it['frequency'], $it['line_note'], $it['line_total'], $idx]);
        }

        header("Location: /facturas/ver.php?id=$inv_id&created=1");
        exit;
    }

    $invoice = [
        'client_name' => $client_name, 'client_email' => $client_email, 'service_type' => $service_type,
        'doc_type' => $doc_type, 'issue_date' => $issue_date, 'due_date' => $due_date, 'discount_pct' => $discount_pct,
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
<?php if ($pricing['error'] ?? false): ?>
<div class="flash flash-error">⚠️ No se pudo leer el catálogo de precios desde ads/index.html (<?= htmlspecialchars($pricing['error']) ?>). Los ítems de Ads/Contenido no calcularán precio automático hasta resolver esto — revisa <code>panel/includes/ads_pricing.php</code>.</div>
<?php endif; ?>

<form method="POST" class="invoice-form" id="invoice-form">
  <?php if ($edit_id): ?><input type="hidden" name="id" value="<?= $edit_id ?>"><?php endif; ?>

  <div class="doc-type-toggle">
    <label class="doc-type-opt">
      <input type="radio" name="doc_type" value="invoice" <?= ($invoice['doc_type'] ?? 'invoice') === 'invoice' ? 'checked' : '' ?>>
      <span>🧾 Factura <em>(pendiente de pago, con fecha de vencimiento)</em></span>
    </label>
    <label class="doc-type-opt">
      <input type="radio" name="doc_type" value="receipt" <?= ($invoice['doc_type'] ?? '') === 'receipt' ? 'checked' : '' ?>>
      <span>✅ Comprobante <em>(el cliente ya pagó — se guarda como Pagada)</em></span>
    </label>
  </div>

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
      <div class="field-with-add">
        <select name="service_type" id="service_type_select">
          <?php foreach ($serviceTypes as $st): ?>
          <option value="<?= htmlspecialchars($st['slug']) ?>" <?= ($invoice['service_type'] ?? '') === $st['slug'] ? 'selected' : '' ?>><?= htmlspecialchars($st['name']) ?></option>
          <?php endforeach; ?>
        </select>
        <button type="button" class="btn-add-inline" onclick="toggleNewServiceType()">+ Nuevo</button>
      </div>
      <div class="new-inline-form" id="new-service-type-form" style="display:none">
        <input type="text" id="new-service-type-name" placeholder="Nombre del tipo de servicio">
        <button type="button" class="btn-secondary" onclick="saveNewServiceType()">Guardar</button>
      </div>
    </div>
    <div class="field">
      <label>Fecha de emisión</label>
      <input type="date" name="issue_date" value="<?= $invoice['issue_date'] ?? date('Y-m-d') ?>">
    </div>
    <div class="field" id="due-date-field">
      <label>Fecha de vencimiento</label>
      <input type="date" name="due_date" value="<?= $invoice['due_date'] ?? '' ?>">
    </div>
  </div>

  <h3 class="section-title">Servicios facturados</h3>
  <div id="items-wrap"></div>
  <div class="items-actions">
    <button type="button" class="btn-secondary" onclick="addItemRow()">+ Agregar línea</button>
  </div>

  <div id="bundle-hint" class="bundle-hint" style="display:none">
    💡 Detectamos contenido + cintillo en la misma factura — aplicamos el descuento de combo (15%) automáticamente. Puedes ajustarlo si negociaste algo distinto.
  </div>

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
const CATALOG        = <?= json_encode(array_values($catalogItems), JSON_UNESCAPED_UNICODE) ?>;
const SERVICE_TYPES   = <?= json_encode($serviceTypes, JSON_UNESCAPED_UNICODE) ?>;
const ADS_PRICING     = <?= json_encode($pricing, JSON_UNESCAPED_UNICODE) ?>;
const EXISTING_ITEMS  = <?= json_encode(array_values($items), JSON_UNESCAPED_UNICODE) ?>;

function escHtml(str) {
  return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function fmt(n) { return '$' + n.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}); }

function buildCatalogOptionsHTML() {
  let html = '<option value="manual">✏️ Escribir manualmente…</option>';
  SERVICE_TYPES.forEach(st => {
    const its = CATALOG.filter(ci => String(ci.service_type_id) === String(st.id));
    if (!its.length) return;
    html += `<optgroup label="${escHtml(st.name)}">`;
    its.forEach(ci => {
      html += `<option value="${ci.id}" data-logic="${ci.logic_type}" data-price="${ci.default_unit_price}">${escHtml(ci.name)}</option>`;
    });
    html += '</optgroup>';
  });
  return html;
}

function rowTemplate() {
  const row = document.createElement('div');
  row.className = 'item-block';
  row.innerHTML = `
    <div class="item-row-top">
      <select class="item-catalog-select"></select>
      <select class="item-freq-select" style="display:none">
        <option value="4">4/mes · 1/sem</option>
        <option value="8">8/mes · 2/sem</option>
        <option value="12">12/mes · 3/sem</option>
        <option value="16">16/mes · 4/sem</option>
        <option value="20">20/mes · 5/sem</option>
      </select>
      <button type="button" class="btn-add-inline" onclick="toggleNewCatalogItem(this)">+ Nuevo producto</button>
    </div>
    <div class="new-inline-form new-catalog-form" style="display:none">
      <input type="text" class="new-cat-name" placeholder="Nombre del producto/servicio">
      <input type="number" class="new-cat-price" placeholder="Precio" step="0.01" min="0">
      <select class="new-cat-type"></select>
      <button type="button" class="btn-secondary" onclick="saveNewCatalogItem(this)">Guardar</button>
    </div>
    <div class="item-row-bottom">
      <input type="text" name="item_desc[]" placeholder="Descripción del servicio" class="item-desc">
      <input type="hidden" name="item_catalog_id[]" class="item-catalog-id" value="">
      <input type="hidden" name="item_freq[]" class="item-freq-hidden" value="">
      <input type="number" name="item_qty[]" min="0" step="0.01" value="1" class="item-qty" placeholder="Cant.">
      <input type="number" name="item_price[]" min="0" step="0.01" value="0" class="item-price" placeholder="Precio">
      <span class="item-total">$0.00</span>
      <button type="button" class="btn-remove-item" onclick="removeItemRow(this)">✕</button>
    </div>
    <div class="item-note"></div>`;
  return row;
}

function initRow(row, data) {
  const catSelect  = row.querySelector('.item-catalog-select');
  const typeSelect = row.querySelector('.new-cat-type');
  catSelect.innerHTML = buildCatalogOptionsHTML();
  typeSelect.innerHTML = SERVICE_TYPES.map(st => `<option value="${st.id}">${escHtml(st.name)}</option>`).join('');
  catSelect.onchange = () => onCatalogSelectChange(row);
  row.querySelector('.item-freq-select').onchange = () => { applyLiveCalc(row); recalc(); detectBundle(); };
  row.querySelector('.item-qty').oninput = recalc;
  row.querySelector('.item-price').oninput = recalc;

  if (data) {
    row.querySelector('.item-desc').value = data.description || '';
    row.querySelector('.item-qty').value = data.quantity || 1;
    row.querySelector('.item-price').value = data.unit_price || 0;
    row.querySelector('.item-note').textContent = data.line_note || '';
    if (data.catalog_item_id) {
      catSelect.value = data.catalog_item_id;
      const opt = catSelect.selectedOptions[0];
      if (opt) {
        row.dataset.logicType = opt.dataset.logic;
        row.querySelector('.item-catalog-id').value = data.catalog_item_id;
        row.querySelector('.item-desc').readOnly = true;
        if (opt.dataset.logic.startsWith('spot_')) {
          const freqSelect = row.querySelector('.item-freq-select');
          freqSelect.style.display = '';
          freqSelect.value = data.frequency || 4;
          row.querySelector('.item-freq-hidden').value = data.frequency || 4;
        }
      }
    }
  }
}

function addItemRow(data) {
  const row = rowTemplate();
  document.getElementById('items-wrap').appendChild(row);
  initRow(row, data);
  recalc();
}

function removeItemRow(btn) {
  const wrap = document.getElementById('items-wrap');
  if (wrap.children.length > 1) btn.closest('.item-block').remove();
  recalc();
  detectBundle();
}

function onCatalogSelectChange(row) {
  const select    = row.querySelector('.item-catalog-select');
  const descInput = row.querySelector('.item-desc');
  const catIdInput = row.querySelector('.item-catalog-id');
  const freqSelect = row.querySelector('.item-freq-select');
  const freqHidden = row.querySelector('.item-freq-hidden');
  const priceInput = row.querySelector('.item-price');
  const noteEl     = row.querySelector('.item-note');

  if (select.value === 'manual') {
    catIdInput.value = '';
    descInput.value = '';
    descInput.readOnly = false;
    descInput.focus();
    freqSelect.style.display = 'none';
    freqHidden.value = '';
    noteEl.textContent = '';
    row.dataset.logicType = '';
  } else {
    const opt = select.selectedOptions[0];
    catIdInput.value = select.value;
    descInput.value = opt.textContent;
    descInput.readOnly = true;
    const logicType = opt.dataset.logic;
    row.dataset.logicType = logicType;
    const isSpot = logicType.startsWith('spot_');
    freqSelect.style.display = isSpot ? '' : 'none';
    if (isSpot && !freqSelect.value) freqSelect.value = '4';
    if (logicType !== 'generic') {
      // Deluxe, Premium, addons y spots calculan su precio en vivo desde ADS_PRICING
      applyLiveCalc(row);
    } else {
      freqHidden.value = '';
      priceInput.value = (parseFloat(opt.dataset.price) || 0).toFixed(2);
      noteEl.textContent = '';
    }
  }
  recalc();
  detectBundle();
}

// Réplica en JS de calcCatalogPrice() (PHP) — el servidor vuelve a calcular esto
// de forma autoritativa al guardar, esto es solo para la vista previa en vivo.
function applyLiveCalc(row) {
  const logicType  = row.dataset.logicType || '';
  const freqSelect = row.querySelector('.item-freq-select');
  const freqHidden = row.querySelector('.item-freq-hidden');
  const priceInput = row.querySelector('.item-price');
  const noteEl     = row.querySelector('.item-note');
  const contenido  = ADS_PRICING.contenido || {};

  if (logicType === 'content_deluxe')  { priceInput.value = (contenido.deluxe  && contenido.deluxe.base  || 0).toFixed(2); noteEl.textContent = ''; return; }
  if (logicType === 'content_premium') { priceInput.value = (contenido.premium && contenido.premium.base || 0).toFixed(2); noteEl.textContent = ''; return; }
  if (logicType === 'addon_publicidad_deluxe')  { priceInput.value = (contenido.deluxe  && contenido.deluxe.ads  || 0).toFixed(2); noteEl.textContent = ''; return; }
  if (logicType === 'addon_publicidad_premium') { priceInput.value = (contenido.premium && contenido.premium.ads || 0).toFixed(2); noteEl.textContent = ''; return; }
  if (!logicType.startsWith('spot_')) return;

  const freq = parseInt(freqSelect.value, 10);
  freqHidden.value = freq;

  const spot  = logicType.replace('spot_', '');
  const table = (ADS_PRICING.cintillos && ADS_PRICING.cintillos[spot]) || {};
  const price = table[String(freq)] || 0;
  priceInput.value = price.toFixed(2);

  const base4 = table['4'] || 0;
  let note = '';
  if (freq > 4 && base4 > 0) {
    const full = base4 * (freq / 4);
    const saving = full - price;
    if (saving > 0.01) {
      const pct = Math.round(saving / full * 100);
      note = `−${pct}% · ahorras ${fmt(saving)}`;
    }
  }
  noteEl.textContent = note;
}

let discountTouchedManually = false;

function detectBundle() {
  const logicTypes = [...document.querySelectorAll('.item-block')].map(r => r.dataset.logicType).filter(Boolean);
  const hasContent = logicTypes.some(lt => lt === 'content_deluxe' || lt === 'content_premium');
  const hasSpot = logicTypes.some(lt => lt.startsWith('spot_'));
  const hint = document.getElementById('bundle-hint');
  if (hasContent && hasSpot) {
    hint.style.display = '';
    if (!discountTouchedManually) {
      document.getElementById('discount_pct').value = 15;
      recalc();
    }
  } else {
    hint.style.display = 'none';
  }
}

function recalc() {
  let subtotal = 0;
  document.querySelectorAll('.item-block').forEach(row => {
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

// ── Tipo de documento: Factura vs Comprobante ───────────────────────────────
function updateDocTypeUI() {
  const isReceipt = document.querySelector('input[name="doc_type"]:checked').value === 'receipt';
  document.getElementById('due-date-field').style.display = isReceipt ? 'none' : '';
}
document.querySelectorAll('input[name="doc_type"]').forEach(r => r.onchange = updateDocTypeUI);

// ── Agregar tipo de servicio nuevo ───────────────────────────────────────────
function toggleNewServiceType() {
  const f = document.getElementById('new-service-type-form');
  f.style.display = f.style.display === 'none' ? 'flex' : 'none';
}
async function saveNewServiceType() {
  const nameInput = document.getElementById('new-service-type-name');
  const name = nameInput.value.trim();
  if (!name) return;
  const fd = new FormData();
  fd.append('action', 'add_service_type');
  fd.append('name', name);
  const res = await fetch('nueva.php', { method: 'POST', body: fd });
  const data = await res.json();
  if (data.ok) {
    SERVICE_TYPES.push({ id: data.id, slug: name.toLowerCase(), name: data.name, sort_order: SERVICE_TYPES.length + 1 });
    const sel = document.getElementById('service_type_select');
    const opt = document.createElement('option');
    opt.value = name.toLowerCase(); opt.textContent = data.name;
    sel.appendChild(opt);
    sel.value = opt.value;
    document.querySelectorAll('.new-cat-type').forEach(ts => {
      ts.innerHTML = SERVICE_TYPES.map(st => `<option value="${st.id}">${escHtml(st.name)}</option>`).join('');
    });
    nameInput.value = '';
    toggleNewServiceType();
  } else {
    alert(data.error || 'No se pudo guardar.');
  }
}

// ── Agregar producto/servicio nuevo al catálogo (por línea) ─────────────────
function toggleNewCatalogItem(btn) {
  const row = btn.closest('.item-block');
  const f = row.querySelector('.new-catalog-form');
  f.style.display = f.style.display === 'none' ? 'flex' : 'none';
}
async function saveNewCatalogItem(btn) {
  const row = btn.closest('.item-block');
  const nameInput  = row.querySelector('.new-cat-name');
  const priceInput = row.querySelector('.new-cat-price');
  const typeSelect = row.querySelector('.new-cat-type');
  const name = nameInput.value.trim();
  const price = parseFloat(priceInput.value) || 0;
  const serviceTypeId = typeSelect.value;
  if (!name || !serviceTypeId) { alert('Completa nombre y tipo de servicio.'); return; }

  const fd = new FormData();
  fd.append('action', 'add_catalog_item');
  fd.append('name', name);
  fd.append('price', price);
  fd.append('service_type_id', serviceTypeId);
  const res = await fetch('nueva.php', { method: 'POST', body: fd });
  const data = await res.json();
  if (data.ok) {
    CATALOG.push({ id: data.id, service_type_id: data.service_type_id, name: data.name, logic_type: 'generic', default_unit_price: data.price });
    document.querySelectorAll('.item-catalog-select').forEach(sel => {
      const current = sel.value;
      sel.innerHTML = buildCatalogOptionsHTML();
      sel.value = current;
    });
    const catSelect = row.querySelector('.item-catalog-select');
    catSelect.value = data.id;
    onCatalogSelectChange(row);
    nameInput.value = ''; priceInput.value = '';
    toggleNewCatalogItem(btn);
  } else {
    alert(data.error || 'No se pudo guardar.');
  }
}

// ── Inicialización ───────────────────────────────────────────────────────────
if (EXISTING_ITEMS.length) {
  EXISTING_ITEMS.forEach(it => addItemRow(it));
} else {
  addItemRow();
}
document.getElementById('discount_pct').addEventListener('input', () => { discountTouchedManually = true; recalc(); });
document.getElementById('tax_enabled').addEventListener('change', recalc);
document.getElementById('tax_pct').addEventListener('input', recalc);
updateDocTypeUI();
detectBundle();
recalc();
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
