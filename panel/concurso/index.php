<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/concurso_data.php';

$flash = '';
$flash_type = 'ok';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $result = deleteConcursoSubmission((int)($_POST['entry_index'] ?? -1), $_POST['entry_check'] ?? '');
    $flash = $result['message'];
    $flash_type = $result['ok'] ? 'ok' : 'error';
}

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

<?php if ($flash): ?><div class="flash flash-<?= $flash_type ?>"><?= htmlspecialchars($flash) ?></div><?php endif; ?>

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
    <div class="concurso-entry-meta">
      <div class="muted-sub"><?= htmlspecialchars($e['submitted_at'] ?? '') ?></div>
      <form method="POST" class="concurso-delete-form" onsubmit="return confirm('¿Eliminar esta participación? También se borran sus imágenes. No se puede deshacer.')">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="entry_index" value="<?= $i ?>">
        <input type="hidden" name="entry_check" value="<?= htmlspecialchars($e['submitted_at'] ?? '') ?>">
        <button type="submit" class="btn-reject">🗑️ Eliminar</button>
      </form>
    </div>
  </div>
  <div class="concurso-entry-note<?= $note === '' ? ' is-empty' : '' ?>"><?= $note !== '' ? nl2br(htmlspecialchars($note)) : 'Sin nota.' ?></div>
  <?php
    $entryUrls = [];
    foreach (($e['files'] ?? []) as $f) {
        $filename = $f['filename'] ?? '';
        if ($filename === '') continue;
        $entryUrls[] = 'https://anonimustradelive.com/concurso/uploads/' . rawurlencode($filename);
    }
    $entryUrlsAttr = htmlspecialchars(json_encode($entryUrls, JSON_UNESCAPED_SLASHES), ENT_QUOTES);
  ?>
  <div class="concurso-thumbs">
    <?php foreach ($entryUrls as $idx => $url): ?>
    <a class="concurso-thumb" href="<?= htmlspecialchars($url) ?>" onclick="openLightbox(<?= $entryUrlsAttr ?>, <?= $idx ?>); return false;" target="_blank" rel="noopener">
      <img src="<?= htmlspecialchars($url) ?>" alt="Captura" loading="lazy">
    </a>
    <?php endforeach; ?>
  </div>
</div>
<?php endforeach; endif; ?>

<div class="lightbox" id="lightbox" onclick="closeLightbox()">
  <button type="button" class="lightbox-nav lightbox-prev" id="lightbox-prev" onclick="lightboxPrev(event)" aria-label="Imagen anterior">‹</button>
  <img id="lightbox-img" src="" alt="Captura ampliada">
  <button type="button" class="lightbox-nav lightbox-next" id="lightbox-next" onclick="lightboxNext(event)" aria-label="Imagen siguiente">›</button>
  <div class="lightbox-counter" id="lightbox-counter"></div>
</div>
<script>
  let lightboxImages = [];
  let lightboxIndex = 0;

  function openLightbox(images, index) {
    lightboxImages = images;
    lightboxIndex = index;
    renderLightbox();
    document.getElementById('lightbox').classList.add('open');
  }
  function closeLightbox() {
    document.getElementById('lightbox').classList.remove('open');
    document.getElementById('lightbox-img').src = '';
  }
  function renderLightbox() {
    document.getElementById('lightbox-img').src = lightboxImages[lightboxIndex];
    const multi = lightboxImages.length > 1;
    const prev = document.getElementById('lightbox-prev');
    const next = document.getElementById('lightbox-next');
    const counter = document.getElementById('lightbox-counter');
    prev.style.display = multi ? 'flex' : 'none';
    next.style.display = multi ? 'flex' : 'none';
    counter.style.display = multi ? 'block' : 'none';
    prev.classList.toggle('is-disabled', lightboxIndex === 0);
    next.classList.toggle('is-disabled', lightboxIndex === lightboxImages.length - 1);
    counter.textContent = (lightboxIndex + 1) + ' / ' + lightboxImages.length;
  }
  function lightboxPrev(e) {
    e.stopPropagation();
    if (lightboxIndex > 0) { lightboxIndex--; renderLightbox(); }
  }
  function lightboxNext(e) {
    e.stopPropagation();
    if (lightboxIndex < lightboxImages.length - 1) { lightboxIndex++; renderLightbox(); }
  }
  document.addEventListener('keydown', e => {
    if (!document.getElementById('lightbox').classList.contains('open')) return;
    if (e.key === 'Escape') closeLightbox();
    if (e.key === 'ArrowLeft') lightboxPrev(e);
    if (e.key === 'ArrowRight') lightboxNext(e);
  });
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
