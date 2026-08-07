<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/concurso_data.php';

$entries = array_reverse(getConcursoSubmissions(), true); // más recientes primero

$active = 'concurso';
$panel_title = 'Concurso';
include __DIR__ . '/../includes/nav.php';
?>
<div class="page-head">
  <h1>🏆 Concurso — Participaciones</h1>
  <div class="items-actions">
    <a href="/concurso/export_csv.php" class="btn-secondary">⬇️ CSV</a>
    <a href="/concurso/export_zip.php" class="btn-primary">⬇️ Imágenes (ZIP)</a>
  </div>
</div>

<?php if (($dir = getConcursoDir()) === null): ?>
<div class="flash flash-error">⚠️ No se pudo leer la carpeta del concurso (<code>concurso/</code> en el docroot principal). Revisa <code>panel/includes/concurso_data.php</code>.</div>
<?php endif; ?>

<p class="muted-sub" style="margin-bottom:1.25rem"><?= count($entries) ?> participación<?= count($entries) === 1 ? '' : 'es' ?></p>

<?php if (empty($entries)): ?>
<div class="table-wrap"><div class="empty">Todavía no hay participaciones.</div></div>
<?php else: foreach ($entries as $i => $e):
    $note = trim($e['note'] ?? '');
?>
<div class="concurso-entry">
  <div class="concurso-entry-head">
    <div class="concurso-entry-name"><?= htmlspecialchars(trim(($e['first_name'] ?? '') . ' ' . ($e['last_name'] ?? ''))) ?></div>
    <div class="muted-sub"><?= htmlspecialchars($e['submitted_at'] ?? '') ?></div>
  </div>
  <div class="concurso-entry-note<?= $note === '' ? ' is-empty' : '' ?>"><?= $note !== '' ? nl2br(htmlspecialchars($note)) : 'Sin nota.' ?></div>
  <div class="concurso-thumbs">
    <?php foreach (($e['files'] ?? []) as $f):
        $filename = $f['filename'] ?? '';
        if ($filename === '') continue;
        $url = 'https://anonimustradelive.com/concurso/uploads/' . rawurlencode($filename);
    ?>
    <a class="concurso-thumb" href="<?= htmlspecialchars($url) ?>" target="_blank" rel="noopener">
      <img src="<?= htmlspecialchars($url) ?>" alt="Captura" loading="lazy">
    </a>
    <?php endforeach; ?>
  </div>
</div>
<?php endforeach; endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
