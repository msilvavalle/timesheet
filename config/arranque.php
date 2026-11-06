<?php
// Arranque común de las páginas con sesión. Se incluye a nivel de archivo,
// no dentro de una función, porque idioma.php, conexion.php y validar-sesion.php
// dejan variables globales ($idioma, $traducciones, $pdo, $cuentaSesion).
//
// La página define antes:
//   $rolPagina = 'administrador' | 'consultor' | null (cualquiera de los dos)
// y agrega después sus textos con $traducciones += [...].
//
// Deja disponibles: $cuenta (fila del usuario), $idioma, $pdo y las funciones
// escapar(), texto(), validarId(), csrfValido(), avisar() y redirigir().

session_name('mejr_timesheet');
session_start([
    'use_strict_mode' => true,
    'gc_maxlifetime' => 1800,
    'use_only_cookies' => true,
    'cookie_httponly' => true,
    'cookie_samesite' => 'Lax',
    'cookie_secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'
]);

header('Cache-Control: no-store');

require_once __DIR__ . '/idioma.php';
require_once __DIR__ . '/formato.php';

function escapar($valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

function texto(string $texto): string
{
    return escapar(mejrTraducir($texto));
}

function validarId($valor): int|false
{
    if (!is_string($valor) && !is_int($valor)) {
        return false;
    }

    return filter_var($valor, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1, 'max_range' => 4294967295]
    ]);
}

function csrfValido(): bool
{
    $token = $_POST['csrf'] ?? '';

    return is_string($token) && hash_equals($_SESSION['csrf'], $token);
}

// Un solo aviso pendiente por sesión. $texto es una clave de traducción;
// $detalle es texto libre (por ejemplo un nombre) que se muestra tal cual.
function avisar(string $tipo, string $texto, string $detalle = ''): void
{
    $_SESSION['aviso'] = ['tipo' => $tipo, 'texto' => $texto, 'detalle' => $detalle];
}

function redirigir(string $url): void
{
    header('Location: ' . $url, true, 303);
    exit;
}

function mejrCerrarSesion(): void
{
    global $idioma;

    if (!csrfValido()) {
        http_response_code(403);
        exit(texto('Solicitud no válida. Vuelve a abrir la página.'));
    }

    $_SESSION = ['idioma' => $idioma];
    session_regenerate_id(true);
    redirigir('login.php');
}

if (!isset($_SESSION['usuario_id']) || time() - ($_SESSION['ultima_actividad'] ?? 0) > 1800) {
    $_SESSION = [];
    header('Location: login.php');
    exit;
}

require __DIR__ . '/conexion.php';
require_once __DIR__ . '/validar-sesion.php';

// validar-sesion.php ya comprobó que la cuenta existe, está activa y tiene un rol válido.
$cuenta = $cuentaSesion;

if (($rolPagina ?? null) !== null && $cuenta['rol'] !== $rolPagina) {
    http_response_code(403);
    exit(texto(
        $rolPagina === 'administrador'
            ? 'Acceso reservado para administración.'
            : 'Esta sección corresponde al panel del consultor.'
    ));
}

$_SESSION['ultima_actividad'] = time();
$_SESSION['csrf'] ??= bin2hex(random_bytes(32));

$esAdmin = $cuenta['rol'] === 'administrador';
