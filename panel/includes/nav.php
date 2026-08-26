<?php $panel_title = $panel_title ?? 'Panel Admin'; ?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($panel_title) ?> — AnonimusTrade Live</title>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/style.css">
</head>
<body>
<header class="panel-nav">
  <div class="panel-nav-brand">AnonimusTrade <span>Live</span> · Panel Admin</div>
  <nav class="panel-nav-tabs">
    <a href="/facturas/" class="tab <?= ($active ?? '') === 'facturas' ? 'active' : '' ?>">🧾 Facturación</a>
    <a href="/registros/" class="tab <?= ($active ?? '') === 'registros' ? 'active' : '' ?>">📋 Registros</a>
    <a href="/concurso/" class="tab <?= ($active ?? '') === 'concurso' ? 'active' : '' ?>">🏆 Concurso</a>
    <a href="/leads/" class="tab <?= ($active ?? '') === 'leads' ? 'active' : '' ?>">🎓 Leads</a>
  </nav>
  <a href="/logout.php" class="panel-nav-logout">Cerrar sesión →</a>
</header>
<main class="panel-main<?= !empty($panel_flush) ? ' panel-main--flush' : '' ?>">
