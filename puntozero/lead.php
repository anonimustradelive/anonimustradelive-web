<?php
// Recibe los correos de quienes quieren que les avisemos de la próxima
// convocatoria de Punto Zerø y los guarda en leads.json.
//
// Mismo patrón de archivo plano que concurso/submissions.json: sin base de
// datos, protegido por .htaccess, y leído desde el panel admin (que vive en
// otro subdominio pero puede leer este archivo por filesystem).
//
// Responde SIEMPRE JSON: la página lo llama por fetch, sin recargar.

declare(strict_types=1);

define('LEADS_FILE', __DIR__ . '/leads.json');
define('MAX_EMAIL_LEN', 190);   // límite habitual de columna email
define('MAX_LEADS', 20000);     // tope duro para que el archivo no crezca sin control

header('Content-Type: application/json; charset=utf-8');

function salir(bool $ok, string $mensaje, int $codigo = 200): void {
    http_response_code($codigo);
    echo json_encode(['ok' => $ok, 'message' => $mensaje], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    salir(false, 'Método no permitido.', 405);
}

// Trampa para bots: es un campo oculto que una persona nunca llena.
// Se responde ok para no darle pistas al bot de que fue detectado.
if (trim($_POST['website'] ?? '') !== '') {
    salir(true, 'Listo.');
}

$email = strtolower(trim($_POST['email'] ?? ''));

if ($email === '') {
    salir(false, 'Escribe tu correo.');
}
if (strlen($email) > MAX_EMAIL_LEN || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    salir(false, 'Ese correo no parece válido. Revísalo.');
}

$raw   = @file_get_contents(LEADS_FILE);
$leads = $raw ? (json_decode($raw, true) ?: []) : [];
if (!is_array($leads)) $leads = [];

// Si ya se anotó, no se duplica ni se le dice que hubo un problema:
// desde su lado el resultado es el mismo, ya está en la lista.
foreach ($leads as $l) {
    if (strtolower($l['email'] ?? '') === $email) {
        salir(true, 'Ya estabas en la lista. Te avisamos.');
    }
}

if (count($leads) >= MAX_LEADS) {
    error_log('puntozero/lead.php: leads.json alcanzó el tope de ' . MAX_LEADS);
    salir(false, 'No pudimos guardarlo. Escríbenos a puntozero@anonimustradelive.com.');
}

$leads[] = [
    'email'  => $email,
    'fecha'  => date('c'),
    'origen' => substr(trim($_POST['origen'] ?? 'landing'), 0, 40),
];

$guardado = @file_put_contents(
    LEADS_FILE,
    json_encode($leads, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    LOCK_EX
);

if ($guardado === false) {
    error_log('puntozero/lead.php: no se pudo escribir en ' . LEADS_FILE . ' (¿permisos?)');
    salir(false, 'No pudimos guardarlo. Escríbenos a puntozero@anonimustradelive.com.');
}

salir(true, 'Listo. Te avisamos apenas abra la próxima convocatoria.');
