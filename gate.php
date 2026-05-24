<?php
// ──────────────────────────────────────────────────────────────────
// gate.php
// Incluir al inicio de cualquier página protegida con:
//   require_once __DIR__ . '/../gate.php';
// Si no hay sesión válida, muestra la página de instrucciones
// y detiene la ejecución.
// ──────────────────────────────────────────────────────────────────

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$SESSION_TTL = 8 * 3600; // sesión válida por 8 horas

$authenticated = (
    isset($_SESSION['tg_auth'])   &&
    $_SESSION['tg_auth'] === true &&
    isset($_SESSION['auth_time']) &&
    (time() - $_SESSION['auth_time']) < $SESSION_TTL
);

if (!$authenticated) {
    // Limpiar sesión expirada
    session_unset();
    session_destroy();
    // Mostrar página de acceso denegado con instrucciones
    showAccessPage();
    exit;
}

// ── Página de instrucciones de acceso ──
function showAccessPage(): void {
?><!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Acceso — AnonimusTrade Live</title>
<link rel="icon" type="image/png" href="/images/fab icon anonimustradelive.png">
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  body {
    background: #080810; color: #F0EFFF;
    font-family: 'Montserrat', sans-serif;
    min-height: 100vh; display: flex; align-items: center; justify-content: center;
    padding: 2rem;
  }
  .card {
    background: #0E0E18; border: 1px solid rgba(124,58,237,0.25);
    border-radius: 20px; padding: 2.75rem 2.5rem; max-width: 480px; width: 100%;
    text-align: center;
  }
  .logo-row { display: flex; align-items: center; justify-content: center; gap: 10px; margin-bottom: 2rem; }
  .logo-row img { height: 28px; }
  .logo-row span { font-size: 0.75rem; font-weight: 700; letter-spacing: 0.15em; text-transform: uppercase; }
  .logo-row .live { color: #C0112B; }
  .lock-icon { font-size: 2.5rem; margin-bottom: 1rem; }
  h1 { font-size: 1.25rem; font-weight: 800; margin-bottom: 0.5rem; }
  .subtitle { font-size: 0.82rem; color: #7070A0; margin-bottom: 2rem; line-height: 1.6; }

  /* Tabs */
  .tabs { display: flex; gap: 0; margin-bottom: 1.75rem; border-radius: 8px; overflow: hidden; border: 1px solid rgba(124,58,237,0.2); }
  .tab {
    flex: 1; padding: 10px 8px; font-size: 0.7rem; font-weight: 700;
    letter-spacing: 0.07em; text-transform: uppercase; cursor: pointer;
    border: none; background: transparent; color: #7070A0; transition: all 0.2s;
  }
  .tab.active { background: rgba(124,58,237,0.18); color: #F0EFFF; }
  .tab:hover:not(.active) { color: #A78BFA; }

  /* Paneles */
  .panel { display: none; }
  .panel.active { display: block; }

  /* Steps */
  .steps { text-align: left; margin-bottom: 1.5rem; }
  .step { display: flex; gap: 14px; align-items: flex-start; padding: 13px 0; border-bottom: 1px solid rgba(124,58,237,0.1); }
  .step:last-child { border-bottom: none; }
  .step-num {
    width: 28px; height: 28px; border-radius: 50%; flex-shrink: 0;
    background: rgba(124,58,237,0.15); border: 1px solid rgba(124,58,237,0.3);
    display: flex; align-items: center; justify-content: center;
    font-size: 0.72rem; font-weight: 800; color: #A78BFA;
  }
  .step-text { font-size: 0.82rem; color: #A0A0C0; line-height: 1.55; padding-top: 4px; }
  .step-text b { color: #F0EFFF; }
  .step-text code {
    background: rgba(124,58,237,0.12); border: 1px solid rgba(124,58,237,0.25);
    border-radius: 4px; padding: 2px 8px; font-size: 0.82rem;
    color: #A78BFA; font-family: monospace;
    cursor: pointer; user-select: none; transition: background 0.2s;
  }
  .step-text code.copied {
    background: rgba(34,197,94,0.15); border-color: rgba(34,197,94,0.35); color: #22C55E;
  }

  /* Botones */
  .btn {
    display: inline-flex; align-items: center; justify-content: center; gap: 8px;
    padding: 13px 20px; border-radius: 6px; width: 100%;
    font-size: 0.78rem; font-weight: 700; letter-spacing: 0.06em;
    text-transform: uppercase; text-decoration: none; transition: opacity 0.2s;
    margin-bottom: 10px;
  }
  .btn svg { width: 18px; height: 18px; fill: currentColor; flex-shrink: 0; }
  .btn-tg  { background: #229ED9; color: #fff; }
  .btn-wa  { background: #25D366; color: #fff; }
  .btn-tg:hover, .btn-wa:hover { opacity: 0.88; }

  /* Info membresía */
  .membership-info {
    background: rgba(192,17,43,0.06); border: 1px solid rgba(192,17,43,0.2);
    border-radius: 10px; padding: 1rem 1.1rem; margin-bottom: 1.5rem; text-align: left;
  }
  .membership-info p { font-size: 0.8rem; color: #A0A0C0; line-height: 1.6; margin-bottom: 0.5rem; }
  .membership-info p:last-child { margin-bottom: 0; }
  .membership-info b { color: #F0EFFF; }

  .divider { display: flex; align-items: center; gap: 10px; margin: 1rem 0; }
  .divider hr { flex: 1; border: none; border-top: 1px solid rgba(124,58,237,0.12); }
  .divider span { font-size: 0.68rem; color: #7070A0; font-weight: 600; letter-spacing: 0.08em; text-transform: uppercase; }

  .back { font-size: 0.72rem; color: #7070A0; text-decoration: none; display: inline-block; margin-top: 0.5rem; }
  .back:hover { color: #A78BFA; }
</style>
</head>
<body>
<div class="card">

  <div class="logo-row">
    <img src="/images/fab icon anonimustradelive.png" alt="AT Live">
    <span>AnonimusTrade<span class="live"> Live</span></span>
  </div>

  <div class="lock-icon">🔐</div>
  <h1>Área de miembros</h1>
  <p class="subtitle">Las herramientas son exclusivas para la comunidad privada de AnonimusTrade Live.</p>

  <div class="tabs">
    <button class="tab active" onclick="showTab('miembro')">Ya soy miembro</button>
    <button class="tab"        onclick="showTab('unirse')">Quiero unirme</button>
  </div>

  <!-- TAB: Ya soy miembro -->
  <div class="panel active" id="panel-miembro">
    <div class="steps">
      <div class="step">
        <div class="step-num">1</div>
        <div class="step-text">
          Abre el bot en Telegram:<br>
          <b>@AnonimusTradeLiveDonBot</b>
        </div>
      </div>
      <div class="step">
        <div class="step-num">2</div>
        <div class="step-text">
          Envía el comando <code onclick="copyAcceso(this)" title="Clic para copiar">/acceso</code> <span id="copy-hint" style="font-size:0.7rem;color:#7070A0;">· toca para copiar</span><br>
          El bot verificará que eres miembro.
        </div>
      </div>
      <div class="step">
        <div class="step-num">3</div>
        <div class="step-text">
          <b>Haz clic en el link</b> que te enviará el bot.<br>
          Acceso inmediato por 8 horas.
        </div>
      </div>
    </div>
    <a class="btn btn-tg" href="https://t.me/AnonimusTradeLiveDonBot" target="_blank" rel="noopener">
      <svg viewBox="0 0 24 24"><path d="M12 0C5.373 0 0 5.373 0 12s5.373 12 12 12 12-5.373 12-12S18.627 0 12 0zm5.562 8.248-2.016 9.506c-.148.658-.537.818-1.084.508l-3-2.21-1.447 1.394c-.16.16-.295.295-.605.295l.213-3.053 5.56-5.023c.242-.213-.054-.333-.373-.12L7.08 14.4l-2.95-.924c-.641-.203-.654-.641.136-.949l11.521-4.442c.535-.194 1.002.131.775.163z"/></svg>
      Ir al bot
    </a>
  </div>

  <!-- TAB: Quiero unirme -->
  <div class="panel" id="panel-unirse">
    <div class="membership-info">
      <p>La comunidad de <b>AnonimusTrade Live</b> es <b>gratuita</b>. Incluye acceso a sesiones de trading en vivo, análisis de mercado diario y todas las herramientas premium del sitio.</p>
      <p>Para unirte solo necesitas ser referido a través de uno de nuestros brokers o exchanges asociados. Contáctanos y te explicamos cómo.</p>
    </div>
    <a class="btn btn-wa"
       href="https://wa.me/18495683020?text=Hola%2C%20estoy%20interesado%20en%20unirme%20a%20la%20comunidad%20de%20AnonimusTrade%20Live."
       target="_blank" rel="noopener">
      <svg viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
      Contactar por WhatsApp
    </a>
  </div>

  <br>
  <a class="back" href="/">← Volver al inicio</a>

</div>
<script>
function copyAcceso(el) {
  navigator.clipboard.writeText('/acceso').then(() => {
    el.textContent = '✓ copiado'; el.classList.add('copied');
    document.getElementById('copy-hint').style.display = 'none';
    setTimeout(() => { el.textContent = '/acceso'; el.classList.remove('copied'); document.getElementById('copy-hint').style.display = ''; }, 2000);
  });
}
function showTab(id) {
  document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
  document.querySelectorAll('.panel').forEach(p => p.classList.remove('active'));
  document.getElementById('panel-' + id).classList.add('active');
  event.target.classList.add('active');
}
</script>
</body>
</html><?php
}
