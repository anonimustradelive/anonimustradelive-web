<?php
// ── Concurso de trading — formulario de participación ───────────────────────
// Guarda nombre + captura de pantalla del dashboard. Sin base de datos: el
// equipo revisa manualmente las capturas en /concurso/uploads/ para armar el
// ranking, siguiendo el mismo patrón de archivos planos que donors.json.

define('MAX_BYTES', 5 * 1024 * 1024); // 5 MB por imagen
define('MAX_FILES', 4); // el dashboard no cabe en una sola captura
define('ALLOWED_TYPES', [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
]);
define('UPLOADS_DIR', __DIR__ . '/uploads');
define('SUBMISSIONS_FILE', __DIR__ . '/submissions.json');
define('MIN_NAME_LEN', 3); // evita que llenen el campo con un punto u otro relleno
define('MAX_NOTE_LEN', 500);

$error = '';
$success = isset($_GET['ok']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name  = trim($_POST['last_name'] ?? '');
    $note       = trim($_POST['note'] ?? '');
    $raw_files  = $_FILES['screenshots'] ?? null;

    // Normaliza el array de $_FILES (agrupado por campo) a una lista de archivos individuales.
    $fileList = [];
    if ($raw_files && is_array($raw_files['name'])) {
        foreach ($raw_files['name'] as $i => $name) {
            if ($raw_files['error'][$i] === UPLOAD_ERR_NO_FILE) continue;
            $fileList[] = [
                'tmp_name' => $raw_files['tmp_name'][$i],
                'error'    => $raw_files['error'][$i],
                'size'     => $raw_files['size'][$i],
            ];
        }
    }

    if (mb_strlen($first_name) < MIN_NAME_LEN) {
        $error = 'Escribe tu nombre (mínimo 3 caracteres).';
    } elseif (mb_strlen($first_name) > 60) {
        $error = 'El nombre es demasiado largo.';
    } elseif (mb_strlen($last_name) < MIN_NAME_LEN) {
        $error = 'Escribe tu apellido (mínimo 3 caracteres).';
    } elseif (mb_strlen($last_name) > 60) {
        $error = 'El apellido es demasiado largo.';
    } elseif (mb_strlen($note) > MAX_NOTE_LEN) {
        $error = 'La nota no puede superar los ' . MAX_NOTE_LEN . ' caracteres.';
    } elseif (empty($fileList)) {
        $error = 'Debes subir al menos una captura de tu dashboard.';
    } elseif (count($fileList) > MAX_FILES) {
        $error = 'Puedes subir un máximo de ' . MAX_FILES . ' imágenes.';
    } else {
        // Se valida cada imagen antes de guardar ninguna (todo o nada).
        $validated = [];
        foreach ($fileList as $f) {
            if ($f['error'] !== UPLOAD_ERR_OK) {
                $error = ($f['error'] === UPLOAD_ERR_INI_SIZE || $f['error'] === UPLOAD_ERR_FORM_SIZE)
                    ? 'Una de las imágenes supera el límite de 5 MB.'
                    : 'Hubo un problema al subir una de las imágenes. Intenta de nuevo.';
                break;
            }
            if ($f['size'] > MAX_BYTES) {
                $error = 'Una de las imágenes supera el límite de 5 MB.';
                break;
            }
            $info     = @getimagesize($f['tmp_name']);
            $mimeType = $info['mime'] ?? null;
            if (!$info || !isset(ALLOWED_TYPES[$mimeType])) {
                $error = 'Una de las imágenes no es un formato válido. Solo se aceptan JPG, PNG o WebP.';
                break;
            }
            $validated[] = ['tmp_name' => $f['tmp_name'], 'ext' => ALLOWED_TYPES[$mimeType], 'size' => $f['size']];
        }

        if (!$error) {
            if (!is_dir(UPLOADS_DIR)) { mkdir(UPLOADS_DIR, 0755, true); }

            $savedFiles = [];
            foreach ($validated as $v) {
                $filename = date('Ymd_His') . '_' . bin2hex(random_bytes(5)) . '.' . $v['ext'];
                if (move_uploaded_file($v['tmp_name'], UPLOADS_DIR . '/' . $filename)) {
                    $savedFiles[] = ['filename' => $filename, 'size' => $v['size']];
                }
            }

            if (empty($savedFiles)) {
                $error = 'No se pudieron guardar las imágenes. Intenta de nuevo.';
            } else {
                $raw       = @file_get_contents(SUBMISSIONS_FILE);
                $entries   = $raw ? (json_decode($raw, true) ?: []) : [];
                $entries[] = [
                    'first_name'   => $first_name,
                    'last_name'    => $last_name,
                    'note'         => $note,
                    'files'        => $savedFiles,
                    'submitted_at' => date('c'),
                ];
                file_put_contents(SUBMISSIONS_FILE, json_encode($entries, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);

                header('Location: /concurso/?ok=1');
                exit;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Concurso de Trading — AnonimusTrade Live</title>
<link rel="icon" type="image/png" href="../images/fab icon anonimustradelive.png">
<link href="https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,400;0,500;0,600;0,700;0,800;0,900;1,400&display=swap" rel="stylesheet">
<style>
  :root {
    --red: #C0112B; --red-dark: #8B0D1F; --red-light: #E8153A;
    --black: #080810; --black2: #0E0E18; --black3: #14141F; --black4: #1A1A28;
    --white: #F0EFFF;
    --purple: #7C3AED; --purple-dark: #4C1D95; --purple-mid: #6D28D9;
    --purple-light: #A78BFA; --purple-glow: rgba(124,58,237,0.18);
    --gray: #252535; --gray2: #333348; --text-muted: #7070A0; --green: #22C55E;
  }
  * { margin:0; padding:0; box-sizing:border-box; }
  body {
    background: var(--black); color: var(--white); font-family:'Montserrat',sans-serif;
    min-height:100vh; display:flex; flex-direction:column; align-items:center;
    padding: 2.5rem 1.25rem 4rem;
  }
  .wrap { width:100%; max-width: 560px; }

  .header { text-align:center; margin-bottom: 2rem; }
  .logo { width:48px; height:48px; object-fit:contain; margin-bottom:0.75rem; filter: drop-shadow(0 0 10px rgba(124,58,237,0.35)); }
  .eyebrow {
    font-size:0.68rem; font-weight:800; letter-spacing:0.14em; text-transform:uppercase;
    color: var(--red-light); margin-bottom:0.5rem;
  }
  h1 { font-size:1.7rem; font-weight:800; letter-spacing:-0.01em; line-height:1.25; }
  h1 span { color: var(--red); }
  .subtitle { font-size:0.85rem; color: var(--text-muted); margin-top:0.75rem; line-height:1.6; }

  .prizes {
    background: var(--purple-glow); border:1px solid rgba(124,58,237,0.4); border-radius:10px;
    padding: 1.1rem 1.3rem; margin: 1.5rem 0 1.75rem;
  }
  .prizes h2 {
    font-size:0.68rem; font-weight:800; letter-spacing:0.1em; text-transform:uppercase;
    color: var(--purple-light); margin-bottom:0.85rem;
  }
  .prize-row { display:flex; align-items:center; gap:0.9rem; padding:0.55rem 0; }
  .prize-row + .prize-row { border-top:1px solid rgba(124,58,237,0.25); }
  .prize-medal { font-size:1.5rem; flex-shrink:0; width:34px; text-align:center; }
  .prize-place {
    font-size:0.66rem; font-weight:800; text-transform:uppercase;
    letter-spacing:0.04em; color: var(--purple-light);
  }
  .prize-detail { font-size:0.84rem; margin-top:0.15rem; }
  .prize-detail strong { color: var(--white); }

  .rules {
    background: var(--black3); border:1px solid var(--gray); border-radius:10px;
    padding: 1.25rem 1.4rem; margin-bottom: 1.75rem;
  }
  .rules h2 {
    font-size:0.68rem; font-weight:800; letter-spacing:0.1em; text-transform:uppercase;
    color: var(--text-muted); margin-bottom:0.9rem;
  }
  .rules ol { list-style:none; display:flex; flex-direction:column; gap:0.75rem; }
  .rules li { display:flex; gap:0.7rem; font-size:0.82rem; line-height:1.55; }
  .rules li .n {
    flex-shrink:0; width:20px; height:20px; border-radius:50%;
    background: rgba(192,17,43,0.15); color: var(--red-light);
    font-size:0.68rem; font-weight:800; display:flex; align-items:center; justify-content:center;
  }
  .rules li strong { color: var(--white); }
  .rules .dates { color: var(--purple-light); font-weight:700; }

  form { display:flex; flex-direction:column; gap:1.1rem; }
  .field label {
    display:block; font-size:0.72rem; font-weight:700; text-transform:uppercase;
    letter-spacing:0.05em; color: var(--text-muted); margin-bottom:0.45rem;
  }
  .field input[type="text"] {
    width:100%; background: var(--black3); border:1px solid var(--gray); border-radius:6px;
    padding: 0.85rem 1rem; color: var(--white); font-family:inherit; font-size:0.9rem; outline:none;
  }
  .field input[type="text"]:focus { border-color: var(--purple); }

  .field-row { display:grid; grid-template-columns:1fr 1fr; gap:0.75rem; }

  .field textarea {
    width:100%; background: var(--black3); border:1px solid var(--gray); border-radius:6px;
    padding: 0.85rem 1rem; color: var(--white); font-family:inherit; font-size:0.9rem; outline:none;
    resize: vertical; min-height: 90px;
  }
  .field textarea:focus { border-color: var(--purple); }
  .char-counter { font-size:0.65rem; color: var(--text-muted); margin-top:0.35rem; text-align:right; }

  .file-drop {
    position:relative; border:1.5px dashed var(--gray2); border-radius:8px;
    background: var(--black3); padding: 1.5rem 1rem; text-align:center; cursor:pointer;
    transition: border-color 0.2s, background 0.2s;
  }
  .file-drop:hover, .file-drop.has-file { border-color: var(--purple-light); background: var(--black4); }
  .file-drop input[type="file"] {
    position:absolute; inset:0; opacity:0; cursor:pointer; width:100%; height:100%;
  }
  .file-drop-icon { font-size:1.6rem; margin-bottom:0.4rem; }
  .file-drop-text { font-size:0.85rem; font-weight:700; }
  .file-drop-hint { font-size:0.68rem; color: var(--text-muted); margin-top:0.3rem; }
  .file-drop-name { font-size:0.78rem; color: var(--purple-light); margin-top:0.5rem; font-weight:600; word-break: break-all; }

  .flash {
    padding: 0.8rem 1rem; border-radius:6px; font-size:0.82rem; margin-bottom:1.1rem;
  }
  .flash-error { background: rgba(192,17,43,0.12); border:1px solid rgba(192,17,43,0.4); color:#FF6B6B; }

  .btn-submit {
    background: var(--red); color: var(--white); border:none; border-radius:6px;
    padding: 1rem; font-family:inherit; font-size:0.9rem; font-weight:800;
    letter-spacing:0.02em; text-transform:uppercase; cursor:pointer; transition: background 0.2s;
  }
  .btn-submit:hover { background: var(--red-dark); }
  .btn-submit:disabled { background: var(--gray2); color: var(--text-muted); cursor:not-allowed; }

  .success-box {
    text-align:center; background: rgba(34,197,94,0.1); border:1px solid rgba(34,197,94,0.4);
    border-radius:10px; padding: 2.5rem 1.5rem;
  }
  .success-box .icon { font-size:2.4rem; margin-bottom:0.75rem; }
  .success-box h2 { font-size:1.15rem; font-weight:800; margin-bottom:0.6rem; }
  .success-box p { font-size:0.85rem; color: var(--text-muted); line-height:1.6; }
  .success-box a {
    display:inline-block; margin-top:1.5rem; color: var(--purple-light); text-decoration:none;
    font-size:0.82rem; font-weight:700;
  }
  .success-box a:hover { color: var(--white); }

  .footer-back { text-align:center; margin-top:2.5rem; }
  .footer-back a { color: var(--text-muted); text-decoration:none; font-size:0.75rem; }
  .footer-back a:hover { color: var(--purple-light); }
</style>
</head>
<body>

<div class="wrap">

  <div class="header">
    <img class="logo" src="../images/logo.png" alt="AnonimusTrade Live">
    <p class="eyebrow">Concurso de Trading</p>
    <h1>Demuestra tu <span>consistencia</span>.</h1>
    <p class="subtitle">Estamos rifando <strong>4 cuentas de fondeo</strong> entre quienes tengan el mejor desempeño en el período del concurso. Sube tu nombre y las capturas de tu dashboard para participar.</p>
  </div>

  <div class="prizes">
    <h2>🏆 Premios en juego</h2>
    <div class="prize-row">
      <span class="prize-medal">🥇</span>
      <div>
        <div class="prize-place">1er lugar</div>
        <div class="prize-detail">Cuenta de fondeo de <strong>$50,000</strong> · Futuros</div>
      </div>
    </div>
    <div class="prize-row">
      <span class="prize-medal">🥈</span>
      <div>
        <div class="prize-place">2do lugar</div>
        <div class="prize-detail">Cuenta de fondeo de <strong>$10,000</strong> · CFDs</div>
      </div>
    </div>
    <div class="prize-row">
      <span class="prize-medal">🥉</span>
      <div>
        <div class="prize-place">3er y 4to lugar</div>
        <div class="prize-detail">Cuenta de fondeo de <strong>$5,000</strong> · CFDs, cada una</div>
      </div>
    </div>
  </div>

  <div class="rules">
    <h2>Reglas del concurso</h2>
    <ol>
      <li>
        <span class="n">1</span>
        <span>Solo cuenta el trading realizado entre el <span class="dates">lunes 20 de julio</span> y el <span class="dates">viernes 7 de agosto</span>.</span>
      </li>
      <li>
        <span class="n">2</span>
        <span>Las cuentas <strong>iniciadas antes del 20 de julio quedan descalificadas</strong>.</span>
      </li>
      <li>
        <span class="n">3</span>
        <span>Las cuentas que se <strong>quemen durante el proceso quedan descalificadas automáticamente</strong>.</span>
      </li>
      <li>
        <span class="n">4</span>
        <span>Sube <strong>una o varias capturas de tu dashboard</strong> que muestren tu rendimiento en ese período.</span>
      </li>
    </ol>
  </div>

  <?php if ($success): ?>
  <div class="success-box">
    <div class="icon">✅</div>
    <h2>¡Listo! Recibimos tu participación.</h2>
    <p>Guardamos tus capturas para el ranking.</p>
    <a href="/concurso/">← Enviar otra participación</a>
  </div>

  <div class="rules" style="margin-top:1.5rem; margin-bottom:0;">
    <h2>Para poder ganar</h2>
    <ol>
      <li>
        <span class="n">1</span>
        <span>Síguenos en <strong>TikTok</strong> y/o <strong>Instagram</strong>.</span>
      </li>
      <li>
        <span class="n">2</span>
        <span>Suscríbete a nuestro <strong>canal de YouTube</strong>.</span>
      </li>
      <li>
        <span class="n">3</span>
        <span>El día del anuncio debes estar <strong>presente en el chat en vivo</strong>. Si resultas ganador, te invitaremos a una <strong>videollamada privada</strong> donde deberás mostrarnos tu dashboard y refrescar la página en vivo, para verificar que los datos son reales.</span>
      </li>
    </ol>
  </div>
  <?php else: ?>

  <?php if ($error): ?>
  <div class="flash flash-error"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <form method="POST" enctype="multipart/form-data" id="concurso-form">
    <div class="field-row">
      <div class="field">
        <label for="first_name">Nombre</label>
        <input type="text" id="first_name" name="first_name" required minlength="3" maxlength="60" placeholder="Tu nombre" value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>">
      </div>
      <div class="field">
        <label for="last_name">Apellido</label>
        <input type="text" id="last_name" name="last_name" required minlength="3" maxlength="60" placeholder="Tu apellido" value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>">
      </div>
    </div>

    <div class="field">
      <label>Capturas de tu dashboard</label>
      <div class="file-drop" id="file-drop">
        <input type="file" id="screenshot" name="screenshots[]" accept="image/jpeg,image/png,image/webp" multiple required>
        <div class="file-drop-icon">📸</div>
        <div class="file-drop-text">Toca para elegir tus capturas</div>
        <div class="file-drop-hint">Puedes elegir varias si tu dashboard no cabe en una sola imagen · JPG, PNG o WebP · Máximo 5 MB cada una · Hasta <?= MAX_FILES ?> imágenes</div>
        <div class="file-drop-name" id="file-drop-name"></div>
      </div>
    </div>

    <div class="field">
      <label for="note">Nota (opcional)</label>
      <textarea id="note" name="note" maxlength="<?= MAX_NOTE_LEN ?>" placeholder="¿Algo que quieras contarnos?"><?= htmlspecialchars($_POST['note'] ?? '') ?></textarea>
      <div class="char-counter" id="note-counter">0/<?= MAX_NOTE_LEN ?></div>
    </div>

    <button type="submit" class="btn-submit" id="submit-btn" disabled>Enviar participación</button>
  </form>
  <?php endif; ?>

  <div class="footer-back">
    <a href="https://anonimustradelive.com">← Volver al sitio principal</a>
  </div>

</div>

<script>
  const MAX_BYTES    = <?= MAX_BYTES ?>;
  const MAX_FILES    = <?= MAX_FILES ?>;
  const MIN_NAME_LEN = <?= MIN_NAME_LEN ?>;
  const MAX_NOTE_LEN = <?= MAX_NOTE_LEN ?>;
  const ALLOWED      = ['image/jpeg', 'image/png', 'image/webp'];

  const fileInput  = document.getElementById('screenshot');
  const fileDrop   = document.getElementById('file-drop');
  const fileName   = document.getElementById('file-drop-name');
  const form       = document.getElementById('concurso-form');
  const submitBtn  = document.getElementById('submit-btn');
  const firstName  = document.getElementById('first_name');
  const lastName   = document.getElementById('last_name');
  const noteField  = document.getElementById('note');
  const noteCounter = document.getElementById('note-counter');

  function updateSubmitState() {
    if (!submitBtn || !firstName || !lastName) return;
    const ok = firstName.value.trim().length >= MIN_NAME_LEN && lastName.value.trim().length >= MIN_NAME_LEN;
    submitBtn.disabled = !ok;
  }
  if (firstName) firstName.addEventListener('input', updateSubmitState);
  if (lastName)  lastName.addEventListener('input', updateSubmitState);
  updateSubmitState();

  if (noteField && noteCounter) {
    const updateCounter = () => { noteCounter.textContent = noteField.value.length + '/' + MAX_NOTE_LEN; };
    noteField.addEventListener('input', updateCounter);
    updateCounter();
  }

  function resetFileField() {
    fileInput.value = '';
    fileName.textContent = '';
    fileDrop.classList.remove('has-file');
  }

  if (fileInput) {
    fileInput.addEventListener('change', () => {
      const files = [...fileInput.files];
      if (!files.length) { resetFileField(); return; }

      if (files.length > MAX_FILES) {
        alert('Puedes subir un máximo de ' + MAX_FILES + ' imágenes.');
        resetFileField();
        return;
      }
      for (const file of files) {
        if (!ALLOWED.includes(file.type)) {
          alert('Formato no válido: ' + file.name + '. Solo se aceptan imágenes JPG, PNG o WebP.');
          resetFileField();
          return;
        }
        if (file.size > MAX_BYTES) {
          alert('La imagen ' + file.name + ' supera el límite de 5 MB.');
          resetFileField();
          return;
        }
      }
      fileName.textContent = '✓ ' + files.length + (files.length > 1 ? ' imágenes: ' : ' imagen: ') + files.map(f => f.name).join(', ');
      fileDrop.classList.add('has-file');
    });
  }

  if (form) {
    form.addEventListener('submit', () => {
      submitBtn.disabled = true;
      submitBtn.textContent = 'Enviando…';
    });
  }
</script>
</body>
</html>
