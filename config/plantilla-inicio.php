<?php
// Cabecera común: <head>, barra de navegación por rol, idioma, menú de la
// cuenta y aviso pendiente. Se incluye después de config/arranque.php.
//
// La página define antes:
//   $tituloPagina      clave de traducción del título
//   $seccion           sección marcada en la barra ('tablero', 'horas', 'consultores',
//                      'clientes', 'mis-horas') o null
//   $parametrosIdioma  parámetros GET que se conservan al cambiar de idioma (opcional)

$seccionesBarra = $esAdmin
    ? [
        'tablero' => ['tablero.php', 'Dashboard'],
        'horas' => ['horas-admin.php', 'Horas'],
        'consultores' => ['consultores.php', 'Consultores'],
        'clientes' => ['clientes.php', 'Clientes y proyectos'],
    ]
    : [
        'mis-horas' => ['mis-horas.php', 'Mis horas'],
    ];

$paginaActual = basename($_SERVER['SCRIPT_NAME']);
$urlIdioma = static fn(string $codigo): string => $paginaActual . '?' . http_build_query(
    ($parametrosIdioma ?? []) + ['idioma' => $codigo],
    '', '&', PHP_QUERY_RFC3986
);

$aviso = $_SESSION['aviso'] ?? null;
unset($_SESSION['aviso']);
?>
<!DOCTYPE html>
<html lang="<?= escapar($idioma) ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <title><?= texto($tituloPagina) ?> | MEJR Timesheet</title>

  <link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css"
    rel="stylesheet"
    integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB"
    crossorigin="anonymous">

  <link rel="stylesheet" href="assets/css/mejr-marca.css?v=20261108">
  <link rel="stylesheet" href="assets/css/styles.css?v=20261108">
</head>

<body class="bg-light">

  <header class="mejr-barra">
    <div class="container-fluid mejr-ancho">
      <a class="mejr-logo" href="panel.php">MEJR Consulting</a>

      <nav class="mejr-nav" aria-label="<?= texto('Secciones') ?>">
        <?php foreach ($seccionesBarra as $clave => [$destino, $nombre]): ?>
          <a href="<?= escapar($destino) ?>"
            <?= ($seccion ?? null) === $clave ? 'aria-current="page"' : '' ?>>
            <?= texto($nombre) ?>
          </a>
        <?php endforeach; ?>
      </nav>

      <div class="mejr-cuenta">
        <div class="btn-group btn-group-sm" role="group" aria-label="<?= texto('Idioma') ?>">
          <a href="<?= escapar($urlIdioma('es')) ?>" lang="es"
            class="btn <?= $idioma === 'es' ? 'btn-dark' : 'btn-outline-secondary' ?>"
            <?= $idioma === 'es' ? 'aria-current="true"' : '' ?>>ES</a>
          <a href="<?= escapar($urlIdioma('en')) ?>" lang="en"
            class="btn <?= $idioma === 'en' ? 'btn-dark' : 'btn-outline-secondary' ?>"
            <?= $idioma === 'en' ? 'aria-current="true"' : '' ?>>EN</a>
        </div>

        <details class="mejr-usuario">
          <summary aria-label="<?= texto('Menú de la cuenta') ?>">
            <span class="mejr-usuario-nombre"><?= escapar($cuenta['nombres'] . ' ' . $cuenta['apellidos']) ?></span>
            <span class="mejr-usuario-rol"><?= texto($esAdmin ? 'Administración' : 'Consultor') ?></span>
          </summary>

          <div class="mejr-menu">
            <a href="cambiar-clave.php"><?= texto('Cambiar contraseña') ?></a>

            <form method="post" action="salir.php">
              <input type="hidden" name="csrf" value="<?= escapar($_SESSION['csrf']) ?>">
              <button type="submit"><?= texto('Cerrar sesión') ?></button>
            </form>
          </div>
        </details>
      </div>
    </div>
  </header>

  <main class="container-fluid mejr-ancho py-4">
    <?php if ($aviso): ?>
      <div class="alert alert-<?= escapar($aviso['tipo']) ?>" role="status">
        <?= escapar($aviso['texto']) ?>
        <?php if (($aviso['detalle'] ?? '') !== ''): ?>
          <strong><?= escapar($aviso['detalle']) ?></strong>
        <?php endif; ?>
      </div>
    <?php endif; ?>
