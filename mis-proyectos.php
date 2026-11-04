<?php
session_name('mejr_timesheet');
session_start([
    'use_strict_mode' => true,
    'use_only_cookies' => true,
    'cookie_httponly' => true,
    'cookie_samesite' => 'Lax',
    'cookie_secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'
]);
header('Cache-Control: no-store');

require_once __DIR__ . '/config/idioma.php';

$traducciones += [
    'Mis proyectos' => 'My projects',
    'Panel del consultor' => 'Consultant dashboard',
    'Esta sección corresponde al panel del consultor.' => 'This section is for consultants only.',
    'Volver al panel' => 'Back to dashboard',
    'Idioma' => 'Language',
    'Proyectos activos que administración te ha asignado.' => 'Active projects assigned to you by your administrator.',
    'Todavía no tienes proyectos activos asignados.' => 'You have no active projects assigned yet.',
    'Consulta con administración.' => 'Contact your administrator.',
    'Cliente' => 'Client',
    'Proyecto' => 'Project'
];

if (!isset($_SESSION['usuario_id']) || time() - ($_SESSION['ultima_actividad'] ?? 0) > 1800) {
    $_SESSION = [];
    header('Location: login.php');
    exit;
}

require __DIR__ . '/config/conexion.php';
require_once __DIR__ . '/config/validar-sesion.php';

$consulta = $pdo->prepare(
    'SELECT nombres, apellidos, rol
     FROM usuarios WHERE id = :id AND activo = 1'
);
$consulta->execute(['id' => $_SESSION['usuario_id']]);
$cuenta = $consulta->fetch();

if (!$cuenta) {
    $_SESSION = [];
    header('Location: login.php');
    exit;
}

if ($cuenta['rol'] !== 'consultor') {
    http_response_code(403);
    exit(textoProyectos('Esta sección corresponde al panel del consultor.'));
}

$_SESSION['ultima_actividad'] = time();

$consultaProyectos = $pdo->prepare(
    'SELECT c.nombre AS cliente, p.nombre AS proyecto
     FROM proyecto_consultor pc
     INNER JOIN proyectos p ON p.id = pc.proyecto_id
     INNER JOIN clientes c ON c.id = p.cliente_id
     WHERE pc.consultor_id = :consultor_id
       AND pc.activo = 1
       AND p.activo = 1
       AND c.activo = 1
     ORDER BY c.nombre, p.nombre, p.id'
);
$consultaProyectos->execute([
    'consultor_id' => $_SESSION['usuario_id']
]);
$misProyectos = $consultaProyectos->fetchAll();

function escapar($valor): string {
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

function textoProyectos(string $texto): string {
    return escapar(mejrTraducir($texto));
}
?>
<!DOCTYPE html>
<html lang="<?= escapar($idioma) ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <title><?= textoProyectos('Mis proyectos') ?> | MEJR Timesheet</title>

  <link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css"
    rel="stylesheet"
    integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB"
    crossorigin="anonymous">

  <link rel="stylesheet" href="assets/css/styles.css">
</head>

<body class="bg-light">

  <header class="bg-white border-bottom">
    <div class="container py-3 d-flex flex-wrap gap-3 justify-content-between align-items-center">
      <p class="fw-bold mb-0">MEJR CONSULTING</p>

      <div class="text-end">
        <p class="fw-semibold mb-0">
          <?= escapar($cuenta['nombres'] . ' ' . $cuenta['apellidos']) ?>
        </p>

        <span class="small text-secondary">
          <?= textoProyectos('Panel del consultor') ?>
        </span>
      </div>
    </div>
  </header>

  <main class="container py-4">

    <div class="d-flex flex-wrap gap-3 justify-content-between align-items-center mb-3">
      <a href="panel.php" class="btn btn-outline-secondary">
        <?= textoProyectos('Volver al panel') ?>
      </a>

      <nav aria-label="<?= textoProyectos('Idioma') ?>">
        <div class="btn-group">
          <a
            href="mis-proyectos.php?idioma=es"
            lang="es"
            class="btn btn-sm <?= $idioma === 'es' ? 'btn-dark' : 'btn-outline-secondary' ?>"
            <?= $idioma === 'es' ? 'aria-current="true"' : '' ?>>
            Español
          </a>

          <a
            href="mis-proyectos.php?idioma=en"
            lang="en"
            class="btn btn-sm <?= $idioma === 'en' ? 'btn-dark' : 'btn-outline-secondary' ?>"
            <?= $idioma === 'en' ? 'aria-current="true"' : '' ?>>
            English
          </a>
        </div>
      </nav>
    </div>

    <section class="bg-white border rounded p-4" aria-labelledby="titulo-proyectos">
      <h1 id="titulo-proyectos" class="h4">
        <?= textoProyectos('Mis proyectos') ?>
      </h1>

      <p class="text-secondary">
        <?= textoProyectos('Proyectos activos que administración te ha asignado.') ?>
      </p>

      <?php if (!$misProyectos): ?>
        <p class="mb-0">
          <?= textoProyectos('Todavía no tienes proyectos activos asignados.') ?>
          <?= textoProyectos('Consulta con administración.') ?>
        </p>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table align-middle mb-0">

            <thead>
              <tr>
                <th scope="col">
                  <?= textoProyectos('Cliente') ?>
                </th>

                <th scope="col">
                  <?= textoProyectos('Proyecto') ?>
                </th>
              </tr>
            </thead>

            <tbody>
              <?php foreach ($misProyectos as $proyecto): ?>
                <tr>
                  <td><?= escapar($proyecto['cliente']) ?></td>
                  <td><?= escapar($proyecto['proyecto']) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>

          </table>
        </div>
      <?php endif; ?>
    </section>

  </main>

</body>
</html>