<?php
session_start();
if (isset($_SESSION['admin'])) { header('Location: index.php'); exit; }
require_once __DIR__ . '/config.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (hash_equals(ADMIN_PASS, $_POST['password'] ?? '')) {
        $_SESSION['admin'] = true;
        header('Location: index.php');
        exit;
    }
    $error = 'Contraseña incorrecta.';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin — AnonimusTrade Live</title>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;800&display=swap" rel="stylesheet">
<style>
  :root { --red:#C0112B; --black:#080810; --black2:#0E0E18; --black3:#14141F; --white:#F0EFFF; --purple:#7C3AED; --purple-light:#A78BFA; --gray:#252535; --text-muted:#7070A0; }
  * { margin:0; padding:0; box-sizing:border-box; }
  body { font-family:'Montserrat',sans-serif; background:var(--black); color:var(--white); min-height:100vh; display:flex; align-items:center; justify-content:center; }
  .card { background:var(--black2); border:1px solid var(--gray); border-radius:8px; padding:2.5rem 2rem; width:100%; max-width:380px; }
  .logo { font-size:0.7rem; font-weight:700; letter-spacing:0.2em; text-transform:uppercase; color:var(--red); margin-bottom:0.5rem; }
  h1 { font-size:1.4rem; font-weight:800; margin-bottom:2rem; }
  label { font-size:0.72rem; font-weight:600; color:var(--text-muted); letter-spacing:0.08em; text-transform:uppercase; display:block; margin-bottom:0.4rem; }
  input { width:100%; background:var(--black3); border:1px solid var(--gray); border-radius:4px; padding:0.75rem 1rem; color:var(--white); font-family:inherit; font-size:0.88rem; outline:none; margin-bottom:1.25rem; }
  input:focus { border-color:var(--purple); }
  button { width:100%; background:var(--purple); color:var(--white); border:none; border-radius:4px; padding:0.85rem; font-family:inherit; font-size:0.82rem; font-weight:700; letter-spacing:0.08em; text-transform:uppercase; cursor:pointer; transition:background 0.2s; }
  button:hover { background:#6D28D9; }
  .error { background:rgba(192,17,43,0.12); border:1px solid rgba(192,17,43,0.4); border-radius:4px; padding:0.75rem 1rem; font-size:0.78rem; color:#FF6B6B; margin-bottom:1rem; }
</style>
</head>
<body>
<div class="card">
  <p class="logo">AnonimusTrade Live</p>
  <h1>Panel de registro</h1>
  <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
  <form method="POST">
    <label>Contraseña</label>
    <input type="password" name="password" autofocus autocomplete="current-password">
    <button type="submit">Entrar →</button>
  </form>
</div>
</body>
</html>
