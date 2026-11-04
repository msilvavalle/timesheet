<?php
// Cargar después de session_start() y antes de mostrar HTML.
$idiomasPermitidos = ['es', 'en'];
$idioma = null;

foreach ([
    $_GET['idioma'] ?? null,
    $_COOKIE['mejr_idioma'] ?? null,
    $_SESSION['idioma'] ?? null
] as $preferencia) {
    if (in_array($preferencia, $idiomasPermitidos, true)) {
        $idioma = $preferencia;
        break;
    }
}

if ($idioma === null) {
    $idioma = 'es';
    $prioridad = 0.0;
    $cabecera = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';

    foreach (explode(',', $cabecera) as $opcion) {
        if (preg_match(
            '/\A(es|en)(?:-[a-z0-9]+)*\s*(?:;\s*q=(0(?:\.[0-9]{1,3})?|1(?:\.0{1,3})?))?\z/i',
            trim($opcion),
            $coincidencias
        )) {
            $peso = isset($coincidencias[2])
                ? (float) $coincidencias[2] : 1.0;

            if ($peso > $prioridad) {
                $idioma = strtolower($coincidencias[1]);
                $prioridad = $peso;
            }
        }
    }
}

$_SESSION['idioma'] = $idioma;

if (in_array($_GET['idioma'] ?? null, $idiomasPermitidos, true)) {
    setcookie('mejr_idioma', $idioma, [
        'expires' => time() + 31536000,
        'path' => '/',
        'secure' => !empty($_SERVER['HTTPS'])
            && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
}

header('Content-Language: ' . $idioma);

$traducciones = [
    'Registro de horas' => 'Time tracking',
    'Ingresar' => 'Sign in',
    'Iniciar sesión' => 'Sign in',
    'Ingresa con tu cuenta asignada.' => 'Sign in with your assigned account.',
    'Usuario' => 'Username',
    'Contraseña' => 'Password',
    'Olvidé mi contraseña' => 'Forgot password?',
    'Solicita el restablecimiento de tu contraseña a administración.' => 'Contact your administrator to reset your password.',
    'Actualiza la página e intenta nuevamente.' => 'Refresh the page and try again.',
    'Usuario o contraseña incorrectos.' => 'Incorrect username or password.',
    'Tu sesión terminó. Ingresa nuevamente. Si cambiaste tu contraseña, usa la nueva.' => 'Your session has ended. Sign in again. If you changed your password, use the new one.',
    'Panel de administración' => 'Administration dashboard',
    'Panel del consultor' => 'Consultant dashboard',
    'Usuarios' => 'Users',
    'Clientes' => 'Clients',
    'Proyectos' => 'Projects',
    'Asignaciones' => 'Assignments',
    'Mis proyectos' => 'My projects',
    'Mis horas' => 'My hours',
    'Horas registradas' => 'Time entries',
    'Cambiar contraseña' => 'Change password',
    'Cerrar sesión' => 'Sign out',
    'Mostrar' => 'Show',
    'Ocultar' => 'Hide',
    'Has iniciado sesión correctamente.' => 'You have signed in successfully.',
    'Solicitud no válida.' => 'Invalid request.'
];

function mejrTraducir(string $texto): string {
    global $idioma, $traducciones;

    return $idioma === 'en'
        ? ($traducciones[$texto] ?? $texto)
        : $texto;
}