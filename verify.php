<?php
// ──────────────────────────────────────────────────────────────────
// verify.php
// Valida el token mágico enviado por el bot.
// Si es válido: crea sesión PHP y redirige a /utilidades/
// Si no:        muestra mensaje de error.
// ──────────────────────────────────────────────────────────────────

session_start();

$TOKENS_FILE = __DIR__ . '/auth-tokens.json';
$token       = trim($_GET['token'] ?? '');

// Sin token → redirigir a instrucciones
if (!$token) {
    header('Location: /utilidades/acceso.html');
    exit;
}

// Cargar tokens
$tokens = [];
if (file_exists($TOKENS_FILE)) {
    $tokens = json_decode(file_get_contents($TOKENS_FILE), true) ?? [];
}

$entry = $tokens[$token] ?? null;

// Token no existe
if (!$entry) {
    showError('El link no es válido o ya fue utilizado.');
    exit;
}

// Token expirado
if ($entry['expires'] < time()) {
    showError('El link expiró. Envía <b>/acceso</b> al bot para obtener uno nuevo.');
    exit;
}

// Token ya usado
if ($entry['used']) {
    showError('Este link ya fue utilizado. Envía <b>/acceso</b> al bot para obtener uno nuevo.');
    exit;
}

// ✅ Token válido — marcar como usado y crear sesión
$tokens[$token]['used'] = true;
file_put_contents($TOKENS_FILE, json_encode($tokens));

$_SESSION['tg_auth']    = true;
$_SESSION['tg_user_id'] = $entry['user_id'];
$_SESSION['tg_username']= $entry['username'];
$_SESSION['auth_time']  = time();

// Redirigir a las herramientas
header('Location: /utilidades/');
exit;

// ── Función de error ──
function showError(string $msg): void {
?><!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Acceso — AnonimusTrade Live</title>
<link rel="icon" type="image/png" href="/images/fab icon anonimustradelive.png">
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&display=swap" rel="stylesheet">
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  body {
    background: #080810; color: #F0EFFF;
    font-family: 'Montserrat', sans-serif;
    min-height: 100vh; display: flex; align-items: center; justify-content: center;
    padding: 2rem;
  }
  .card {
    background: #0E0E18; border: 1px solid rgba(192,17,43,0.3);
    border-radius: 16px; padding: 2.5rem; max-width: 420px; width: 100%;
    text-align: center;
  }
  .icon { font-size: 3rem; margin-bottom: 1rem; }
  h2 { font-size: 1.1rem; font-weight: 700; margin-bottom: 0.75rem; color: #f0614a; }
  p { font-size: 0.85rem; color: #7070A0; line-height: 1.6; }
  p b { color: #A78BFA; }
  .btn {
    display: inline-block; margin-top: 1.75rem;
    background: #7C3AED; color: #F0EFFF;
    padding: 10px 24px; border-radius: 4px;
    font-size: 0.75rem; font-weight: 700; letter-spacing: 0.08em;
    text-transform: uppercase; text-decoration: none;
    transition: opacity 0.2s;
  }
  .btn:hover { opacity: 0.85; }
</style>
</head>
<body>
<div class="card">
  <div class="icon">🔒</div>
  <h2>Link inválido</h2>
  <p><?= $msg ?></p>
  <a class="btn" href="/">Volver al inicio</a>
</div>
</body>
</html><?php
}
