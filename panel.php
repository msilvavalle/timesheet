<?php
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

require_once __DIR__ . '/config/idioma.php';

if (!isset($_SESSION['usuario_id']) || time() - ($_SESSION['ultima_actividad'] ?? 0) > 1800) {
    $_SESSION = [];
    header('Location: login.php');
    exit;
}

require __DIR__ . '/config/conexion.php';
require_once __DIR__ . '/config/validar-sesion.php';

// validar-sesion.php ya leyó y validó la cuenta de esta sesión.
$cuenta = $cuentaSesion;

if (!$cuenta || !in_array($cuenta['rol'], ['administrador', 'consultor'], true)) {
    $_SESSION = [];
    header('Location: login.php');
    exit;
}

$_SESSION['ultima_actividad'] = time();
$_SESSION['csrf'] ??= bin2hex(random_bytes(32));

function escapar($valor): string {
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

function textoPanel(string $texto): string {
    return escapar(mejrTraducir($texto));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf'] ?? '';

    if (!is_string($token) || !hash_equals($_SESSION['csrf'], $token)) {
        http_response_code(403);
        exit(textoPanel('Solicitud no válida.'));
    }

    $_SESSION = ['idioma' => $idioma];
    session_regenerate_id(true);

    header('Location: login.php', true, 303);
    exit;
}

$nombreCompleto = $cuenta['nombres'] . ' ' . $cuenta['apellidos'];

$titulo = $cuenta['rol'] === 'administrador'
    ? 'Panel de administración'
    : 'Panel del consultor';
?>
<!DOCTYPE html>
<html lang="<?= escapar($idioma) ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <title><?= textoPanel($titulo) ?> | MEJR Timesheet</title>

  <link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css"
    rel="stylesheet"
    integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB"
    crossorigin="anonymous">

  <link rel="stylesheet" href="assets/css/mejr-marca.css?v=20261105">
  <link rel="stylesheet" href="assets/css/styles.css?v=20261105">
</head>

<body class="bg-light mejr-panel">

  <header class="bg-white border-bottom">
    <div class="container py-3 d-flex flex-wrap gap-3 justify-content-between align-items-center">
      <p class="fw-bold mb-0">MEJR CONSULTING</p>

      <div class="text-end">
        <p class="fw-semibold mb-0">
          <?= escapar($nombreCompleto) ?>
        </p>
        <span class="small text-secondary">
          <?= textoPanel($titulo) ?>
        </span>
      </div>
    </div>
  </header>

  <main class="container py-4">

    <nav
      class="d-flex justify-content-end mb-3"
      aria-label="<?= $idioma === 'en' ? 'Language' : 'Idioma' ?>">

      <div
        class="btn-group"
        role="group"
        aria-label="<?= $idioma === 'en' ? 'Language' : 'Idioma' ?>">

        <a
          href="panel.php?idioma=es"
          lang="es"
          class="btn btn-sm <?= $idioma === 'es' ? 'btn-dark' : 'btn-outline-secondary' ?>"
          <?= $idioma === 'es' ? 'aria-current="true"' : '' ?>>
          Español
        </a>

        <a
          href="panel.php?idioma=en"
          lang="en"
          class="btn btn-sm <?= $idioma === 'en' ? 'btn-dark' : 'btn-outline-secondary' ?>"
          <?= $idioma === 'en' ? 'aria-current="true"' : '' ?>>
          English
        </a>
      </div>
    </nav>

    <section class="bg-white border rounded p-4">
      <h1 class="h4">
        <?= textoPanel($titulo) ?>
      </h1>

      <p class="text-secondary">
        <?= textoPanel('Has iniciado sesión correctamente.') ?>
      </p>

      <div class="d-flex flex-wrap gap-2 mb-3">

        <?php if ($cuenta['rol'] === 'administrador'): ?>

          <a href="usuarios.php" class="btn btn-dark">
            <?= textoPanel('Usuarios') ?>
          </a>

          <a href="clientes.php" class="btn btn-outline-dark">
            <?= textoPanel('Clientes') ?>
          </a>

          <a href="proyectos.php" class="btn btn-outline-dark">
            <?= textoPanel('Proyectos') ?>
          </a>

          <a href="asignaciones.php" class="btn btn-outline-dark">
            <?= textoPanel('Asignaciones') ?>
          </a>

          <a href="horas-admin.php" class="btn btn-outline-dark">
            <?= textoPanel('Horas registradas') ?>
          </a>

        <?php endif; ?>

        <?php if ($cuenta['rol'] === 'consultor'): ?>

          <a href="mis-proyectos.php" class="btn btn-dark">
            <?= textoPanel('Mis proyectos') ?>
          </a>

          <a href="mis-horas.php" class="btn btn-outline-dark">
            <?= textoPanel('Mis horas') ?>
          </a>

        <?php endif; ?>

        <a href="cambiar-clave.php" class="btn btn-outline-secondary">
          <?= textoPanel('Cambiar contraseña') ?>
        </a>
      </div>

      <form method="post" action="panel.php">
        <input
          type="hidden"
          name="csrf"
          value="<?= escapar($_SESSION['csrf']) ?>">

        <button type="submit" class="btn btn-outline-secondary">
          <?= textoPanel('Cerrar sesión') ?>
        </button>
      </form>
    </section>
  </main>

</body>
</html>