<?php
// Lee los correos de la lista de espera de Punto Zerø DIRECTAMENTE de
// puntozero/leads.json en el docroot principal (anonimustradelive.com) —
// mismo patrón de docroots hermanos que ads_pricing.php y concurso_data.php,
// sin duplicar datos entre subdominios.

function getLeadsDir(): ?string {
    static $resolved = false; // false = todavía no se intentó resolver

    if ($resolved !== false) return $resolved;

    $candidates = [];
    if (defined('PUNTOZERO_DIR')) $candidates[] = PUNTOZERO_DIR;
    // Mismo árbol de archivos (repo local / hosting con un solo document root)
    $candidates[] = __DIR__ . '/../../puntozero';
    // Producción: panel.anonimustradelive.com y anonimustradelive.com son
    // document roots distintos pero hermanos bajo el mismo home de cPanel
    $candidates[] = __DIR__ . '/../../anonimustradelive.com/puntozero';

    foreach ($candidates as $c) {
        if ($c && is_dir($c)) { $resolved = realpath($c) ?: $c; return $resolved; }
    }

    error_log('leads_data: no se encontró la carpeta puntozero/ en ninguna ruta candidata. ' .
        'Define PUNTOZERO_DIR en config.php si la estructura del servidor cambió.');
    $resolved = null;
    return null;
}

function getLeads(): array {
    $dir = getLeadsDir();
    if (!$dir) return [];
    $raw = @file_get_contents($dir . '/leads.json');
    if (!$raw) return [];
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

// Agrega un correo a mano desde el panel. Es para los que nos llegan por
// TikTok, Instagram, WhatsApp o Telegram, que nunca pasan por el formulario
// de la landing. Misma validación y mismo antiduplicado que puntozero/lead.php.
function addLead(string $email, string $origen): array {
    $dir = getLeadsDir();
    if (!$dir) return ['ok' => false, 'message' => 'No se encontró la carpeta de Punto Zerø.'];

    $email = strtolower(trim($email));
    if ($email === '') {
        return ['ok' => false, 'message' => 'Escribe un correo.'];
    }
    if (strlen($email) > 190 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'message' => 'Ese correo no parece válido: ' . $email];
    }

    $origen = substr(trim($origen), 0, 40);
    if ($origen === '') $origen = 'manual';

    $file = $dir . '/leads.json';
    $raw  = @file_get_contents($file);
    $all  = $raw ? (json_decode($raw, true) ?: []) : [];
    if (!is_array($all)) $all = [];

    // Que esté repetido no es un error: el correo ya está en la lista, que es
    // justo lo que se quería. Se avisa y no se duplica.
    foreach ($all as $l) {
        if (strtolower($l['email'] ?? '') === $email) {
            return ['ok' => true, 'message' => "$email ya estaba en la lista (origen: " . ($l['origen'] ?? '—') . "). No se duplicó."];
        }
    }

    $all[] = ['email' => $email, 'fecha' => date('c'), 'origen' => $origen];

    $ok = @file_put_contents(
        $file,
        json_encode($all, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        LOCK_EX
    );
    if ($ok === false) {
        error_log('leads_data: no se pudo escribir en ' . $file . ' (¿permisos?)');
        return ['ok' => false, 'message' => 'No se pudo escribir el archivo. Revisa los permisos de puntozero/.'];
    }

    return ['ok' => true, 'message' => "Se agregó $email a la lista."];
}

// Borra un correo por posición, verificando que la fecha coincida con la que
// se mostró en pantalla. Evita borrar el equivocado si entró un lead nuevo
// entre que se cargó la página y se envió el formulario de borrado.
function deleteLead(int $index, string $expectedFecha): array {
    $dir = getLeadsDir();
    if (!$dir) return ['ok' => false, 'message' => 'No se encontró la carpeta de Punto Zerø.'];

    $file = $dir . '/leads.json';
    $raw  = @file_get_contents($file);
    $all  = $raw ? (json_decode($raw, true) ?: []) : [];

    if (!isset($all[$index]) || ($all[$index]['fecha'] ?? '') !== $expectedFecha) {
        return ['ok' => false, 'message' => 'La lista cambió — recarga la página e intenta de nuevo.'];
    }

    $email = $all[$index]['email'] ?? '';
    unset($all[$index]);
    $all = array_values($all); // reindexa: si no, el JSON pasa a ser objeto con huecos

    $ok = @file_put_contents(
        $file,
        json_encode($all, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        LOCK_EX
    );
    if ($ok === false) return ['ok' => false, 'message' => 'No se pudo escribir el archivo.'];

    return ['ok' => true, 'message' => "Se eliminó $email de la lista."];
}
