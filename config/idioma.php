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
    'Contraseña' => 'Password',
    'Olvidé mi contraseña' => 'Forgot password?',
    'Solicita el restablecimiento de tu contraseña a administración.' => 'Contact your administrator to reset your password.',
    'Actualiza la página e intenta nuevamente.' => 'Refresh the page and try again.',
    'Tu sesión terminó. Ingresa nuevamente. Si cambiaste tu contraseña, usa la nueva.' => 'Your session has ended. Sign in again. If you changed your password, use the new one.',
    'Mis horas' => 'My hours',
    'Cambiar contraseña' => 'Change password',
    'Cerrar sesión' => 'Sign out',

    // Barra, menú de la cuenta y accesos (config/arranque.php y plantilla-inicio.php).
    'Secciones' => 'Sections',
    'Idioma' => 'Language',
    'Dashboard' => 'Dashboard',
    'Horas' => 'Hours',
    'Consultores' => 'Consultants',
    'Clientes y proyectos' => 'Clients and projects',
    'Menú de la cuenta' => 'Account menu',
    'Administración' => 'Administration',
    'Consultor' => 'Consultant',
    'Acceso reservado para administración.' => 'Access restricted to administrators.',
    'Esta sección corresponde al panel del consultor.' => 'This section is for consultants.',
    'Solicitud no válida. Vuelve a abrir la página.' => 'Invalid request. Open the page again.',

    // Palabras comunes de tablas y formularios.
    'Acciones' => 'Actions',
    'Estado' => 'Status',
    'Activo' => 'Active',
    'Inactivo' => 'Inactive',
    'Activa' => 'Active',
    'Inactiva' => 'Inactive',
    'Activar' => 'Activate',
    'Desactivar' => 'Deactivate',
    'Editar' => 'Edit',
    'Borrar' => 'Delete',
    'Guardar cambios' => 'Save changes',
    'Cancelar' => 'Cancel',
    'Buscar' => 'Search',
    'Limpiar' => 'Clear',
    'Fecha' => 'Date',
    'Mes' => 'Month',
    'Cliente' => 'Client',
    'Proyecto' => 'Project',
    'Proyectos' => 'Projects',
    'Descripción' => 'Description',
    'Total' => 'Total',
    'Horas del mes' => 'Hours this month',
    'Descargar Excel' => 'Download Excel',
    'Mes anterior' => 'Previous month',
    'Mes siguiente' => 'Next month',
    'Sin tarifa' => 'No rate',
    'Tarifa' => 'Rate',
    'Volver' => 'Back',
    'No se pudo guardar el cambio. Inténtalo nuevamente.' => 'The change could not be saved. Try again.'
];

function mejrTraducir(string $texto): string {
    global $idioma, $traducciones;

    return $idioma === 'en'
        ? ($traducciones[$texto] ?? $texto)
        : $texto;
}