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
    border-radius: 20px; padding: 2.75rem 2.5rem; max-width: 460px; width: 100%;
    text-align: center;
  }
  .logo-row { display: flex; align-items: center; justify-content: center; gap: 10px; margin-bottom: 2rem; }
  .logo-row img { height: 28px; }
  .logo-row span { font-size: 0.75rem; font-weight: 700; letter-spacing: 0.15em; text-transform: uppercase; }
  .logo-row .live { color: #C0112B; }
  .lock-icon { font-size: 2.5rem; margin-bottom: 1rem; }
  h1 { font-size: 1.25rem; font-weight: 800; margin-bottom: 0.5rem; }
  .subtitle { font-size: 0.82rem; color: #7070A0; margin-bottom: 2rem; line-height: 1.6; }
  .steps { text-align: left; margin-bottom: 2rem; }
  .step {
    display: flex; gap: 14px; align-items: flex-start;
    padding: 14px 0; border-bottom: 1px solid rgba(124,58,237,0.1);
  }
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
  }
  .btn-tg {
    display: inline-flex; align-items: center; gap: 8px;
    background: #229ED9; color: #fff;
    padding: 13px 28px; border-radius: 6px;
    font-size: 0.78rem; font-weight: 700; letter-spacing: 0.06em;
    text-transform: uppercase; text-decoration: none;
    transition: opacity 0.2s; margin-bottom: 1rem; width: 100%; justify-content: center;
  }
  .btn-tg:hover { opacity: 0.88; }
  .btn-tg svg { width: 18px; height: 18px; fill: #fff; }
  .back { font-size: 0.72rem; color: #7070A0; text-decoration: none; }
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
  <p class="subtitle">Esta herramienta es exclusiva para miembros de la comunidad privada de AnonimusTrade Live.</p>

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
        Envía el comando <code>/acceso</code><br>
        El bot verificará que eres miembro de la comunidad.
      </div>
    </div>
    <div class="step">
      <div class="step-num">3</div>
      <div class="step-text">
        <b>Haz clic en el link</b> que te enviará el bot.<br>
        Tendrás acceso inmediato durante 8 horas.
      </div>
    </div>
  </div>

  <a class="btn-tg" href="https://t.me/AnonimusTradeLiveDonBot" target="_blank" rel="noopener">
    <svg viewBox="0 0 24 24"><path d="M12 0C5.373 0 0 5.373 0 12s5.373 12 12 12 12-5.373 12-12S18.627 0 12 0zm5.562 8.248-2.016 9.506c-.148.658-.537.818-1.084.508l-3-2.21-1.447 1.394c-.16.16-.295.295-.605.295l.213-3.053 5.56-5.023c.242-.213-.054-.333-.373-.12L7.08 14.4l-2.95-.924c-.641-.203-.654-.641.136-.949l11.521-4.442c.535-.194 1.002.131.775.163z"/></svg>
    Abrir @AnonimusTradeLiveDonBot
  </a>

  <br>
  <a class="back" href="/">← Volver al inicio</a>

</div>
</body>
</html><?php
}
