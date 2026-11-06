<?php
// Se carga después de conexion.php y de session_start().
// Si la sesión es válida, deja la cuenta en $cuentaSesion para que la página
// no tenga que volver a consultarla.
if (session_status() !== PHP_SESSION_ACTIVE) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');

    exit(
        ($idioma ?? 'es') === 'en'
            ? 'The session could not be verified.'
            : 'No se pudo comprobar la sesión.'
    );
}

require_once __DIR__ . '/idioma.php';

$traducciones += [
    'No se pudo comprobar el acceso. Inténtalo nuevamente.' => 'Access could not be verified. Try again.',
    'Tu sesión terminó. Ingresa nuevamente. Si cambiaste tu contraseña, usa la nueva.' => 'Your session has ended. Sign in again. If you changed your password, use the new one.'
];

if (!isset($_SESSION['usuario_id'])) {
    return;
}

try {
    $verificarSesion = $pdo->prepare(
        'SELECT id, nombres, apellidos, rol, activo, password_hash
         FROM usuarios WHERE id = :id'
    );
    $verificarSesion->execute(['id' => $_SESSION['usuario_id']]);
    $cuentaSesion = $verificarSesion->fetch();
} catch (PDOException $e) {
    error_log('MEJR: no se pudo comprobar la sesión.');
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');

    exit(mejrTraducir('No se pudo comprobar el acceso. Inténtalo nuevamente.'));
}

$marcaSesion = $_SESSION['credencial_version'] ?? null;

$sesionValida = $cuentaSesion
    && (int) $cuentaSesion['activo'] === 1
    && in_array($cuentaSesion['rol'], ['administrador', 'consultor'], true)
    && is_string($marcaSesion)
    && hash_equals(
        hash('sha256', $cuentaSesion['password_hash']),
        $marcaSesion
    )
    && time() - ($_SESSION['ultima_actividad'] ?? 0) <= 1800;

if (!$sesionValida) {
    $_SESSION = ['idioma' => $idioma];
    session_regenerate_id(true);

    $_SESSION['aviso_acceso'] =
        'Tu sesión terminó. Ingresa nuevamente. Si cambiaste tu contraseña, usa la nueva.';

    header('Location: login.php', true, 303);
    exit;
}