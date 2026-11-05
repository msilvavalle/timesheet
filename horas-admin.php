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

$traducciones += [
    'Idioma' => 'Language',
    'Horas registradas' => 'Time entries',
    'Panel de administración' => 'Administration dashboard',
    'Acceso reservado para administración.' => 'Access restricted to administrators.',
    'Mes no válido. Vuelve a abrir Horas registradas.' => 'Invalid month. Open Time entries again.',
    'Filtro no válido. Vuelve a abrir Horas registradas.' => 'Invalid filter. Open Time entries again.',
    'Volver al panel' => 'Back to dashboard',
    'Resumen mensual' => 'Monthly summary',
    'Consulta las horas de los consultores por mes, cliente y proyecto.' => 'View consultants’ hours by month, client and project.',
    'Mes' => 'Month',
    'Consultor' => 'Consultant',
    'Cliente' => 'Client',
    'Proyecto' => 'Project',
    'Todos los consultores' => 'All consultants',
    'Todos los clientes' => 'All clients',
    'Todos los proyectos' => 'All projects',
    'Consultar' => 'View report',
    'Descargar Excel' => 'Download Excel',
    'Restablecer filtros' => 'Reset filters',
    'Registros de' => 'Entries for',
    'Total de horas según los filtros:' => 'Total hours matching the filters:',
    'horas' => 'hours',
    'Cantidad de registros:' => 'Number of entries:',
    'No hay registros para el mes y los filtros seleccionados.' => 'No entries match the selected month and filters.',
    'Fecha' => 'Date',
    'Horas' => 'Hours',
    'Descripción' => 'Description'
];

if (
    !isset($_SESSION['usuario_id']) ||
    time() - ($_SESSION['ultima_actividad'] ?? 0) > 1800
) {
    $_SESSION = [];
    header('Location: login.php');
    exit;
}

require __DIR__ . '/config/conexion.php';
require_once __DIR__ . '/config/validar-sesion.php';

// validar-sesion.php ya leyó y validó la cuenta de esta sesión.
$cuenta = $cuentaSesion;

if (!$cuenta) {
    $_SESSION = [];
    header('Location: login.php');
    exit;
}

if ($cuenta['rol'] !== 'administrador') {
    http_response_code(403);
    exit(textoHorasAdmin('Acceso reservado para administración.'));
}

$_SESSION['ultima_actividad'] = time();

function escapar($valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

function textoHorasAdmin(string $texto): string
{
    return escapar(mejrTraducir($texto));
}

$zona = new DateTimeZone('America/Santiago');

$mes = $_GET['mes']
    ?? (new DateTimeImmutable('now', $zona))->format('Y-m');

if (
    !is_string($mes) ||
    !preg_match('/\A[0-9]{4}-(0[1-9]|1[0-2])\z/', $mes) ||
    $mes < '1000-01' ||
    $mes > '9998-12'
) {
    http_response_code(400);
    exit(textoHorasAdmin('Mes no válido. Vuelve a abrir Horas registradas.'));
}

$inicio = new DateTimeImmutable($mes . '-01', $zona);
$fin = $inicio->modify('+1 month');

$filtros = [];

foreach (['consultor_id', 'cliente_id', 'proyecto_id'] as $campo) {
    $valor = $_GET[$campo] ?? '';

    if (!is_string($valor)) {
        http_response_code(400);
        exit(textoHorasAdmin('Filtro no válido. Vuelve a abrir Horas registradas.'));
    }

    $id = $valor === ''
        ? null
        : filter_var($valor, FILTER_VALIDATE_INT, [
            'options' => [
                'min_range' => 1,
                'max_range' => 4294967295
            ]
        ]);

    if ($id === false) {
        http_response_code(400);
        exit(textoHorasAdmin('Filtro no válido. Vuelve a abrir Horas registradas.'));
    }

    $filtros[$campo] = $id;
}

$consultores = $pdo->query(
    "SELECT u.id, u.nombres, u.apellidos, u.usuario
     FROM usuarios u
     WHERE u.rol = 'consultor'
        OR EXISTS (
            SELECT 1
            FROM registros_horas r
            WHERE r.consultor_id = u.id
        )
     ORDER BY u.apellidos, u.nombres, u.id"
)->fetchAll();

$clientes = $pdo->query(
    'SELECT id, nombre FROM clientes ORDER BY nombre, id'
)->fetchAll();

$proyectos = $pdo->query(
    'SELECT p.id, p.nombre, c.nombre AS cliente
     FROM proyectos p
     INNER JOIN clientes c ON c.id = p.cliente_id
     ORDER BY c.nombre, p.nombre, p.id'
)->fetchAll();

$sql = 'SELECT r.fecha, r.horas, r.descripcion, r.consultor_id,
               u.nombres, u.apellidos,
               c.nombre AS cliente, p.nombre AS proyecto
        FROM registros_horas r
        INNER JOIN usuarios u ON u.id = r.consultor_id
        INNER JOIN proyectos p ON p.id = r.proyecto_id
        INNER JOIN clientes c ON c.id = p.cliente_id
        WHERE r.fecha >= :inicio AND r.fecha < :fin';

$parametros = [
    'inicio' => $inicio->format('Y-m-d'),
    'fin' => $fin->format('Y-m-d')
];

$columnas = [
    'consultor_id' => 'r.consultor_id',
    'cliente_id' => 'c.id',
    'proyecto_id' => 'r.proyecto_id'
];

foreach ($columnas as $campo => $columna) {
    if ($filtros[$campo] !== null) {
        $sql .= ' AND ' . $columna . ' = :' . $campo;
        $parametros[$campo] = $filtros[$campo];
    }
}

$sql .= ' ORDER BY r.fecha DESC, u.apellidos, u.nombres, r.id DESC';

$consultaRegistros = $pdo->prepare($sql);
$consultaRegistros->execute($parametros);
$registros = $consultaRegistros->fetchAll();

$totalCentesimas = 0;

foreach ($registros as $registro) {
    $totalCentesimas += (int) str_replace('.', '', $registro['horas']);
}

if (($_GET['exportar'] ?? '') === 'excel') {
    require __DIR__ . '/config/exportar-horas.php';

    descargarHorasExcel(
        $registros,
        $totalCentesimas,
        $inicio->format('m/Y')
    );
}

$parametrosIdioma = ['mes' => $mes] + $filtros;

$urlEspanol = 'horas-admin.php?' . http_build_query(
    $parametrosIdioma + ['idioma' => 'es']
);

$urlIngles = 'horas-admin.php?' . http_build_query(
    $parametrosIdioma + ['idioma' => 'en']
);
?>
<!DOCTYPE html>
<html lang="<?= escapar($idioma) ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <title><?= textoHorasAdmin('Horas registradas') ?> | MEJR Timesheet</title>

  <link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css"
    rel="stylesheet"
    integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB"
    crossorigin="anonymous">

  <link rel="stylesheet" href="assets/css/mejr-marca.css?v=20261105">
  <link rel="stylesheet" href="assets/css/styles.css?v=20261105">
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
          <?= textoHorasAdmin('Panel de administración') ?>
        </span>
      </div>
    </div>
  </header>

  <main class="container py-4">

    <div class="d-flex flex-wrap gap-3 justify-content-between align-items-center mb-3">
      <div class="d-flex flex-wrap gap-2">
        <a href="panel.php" class="btn btn-outline-secondary">
          <?= textoHorasAdmin('Volver al panel') ?>
        </a>

        <a href="resumen-mensual.php" class="btn btn-outline-dark">
          <?= textoHorasAdmin('Resumen mensual') ?>
        </a>
      </div>

      <nav aria-label="<?= textoHorasAdmin('Idioma') ?>">
        <div class="btn-group">
          <a
            href="<?= escapar($urlEspanol) ?>"
            lang="es"
            class="btn btn-sm <?= $idioma === 'es' ? 'btn-dark' : 'btn-outline-secondary' ?>"
            <?= $idioma === 'es' ? 'aria-current="true"' : '' ?>>
            Español
          </a>

          <a
            href="<?= escapar($urlIngles) ?>"
            lang="en"
            class="btn btn-sm <?= $idioma === 'en' ? 'btn-dark' : 'btn-outline-secondary' ?>"
            <?= $idioma === 'en' ? 'aria-current="true"' : '' ?>>
            English
          </a>
        </div>
      </nav>
    </div>

    <section class="bg-white border rounded p-4 mb-4">
      <h1 class="h4">
        <?= textoHorasAdmin('Horas registradas') ?>
      </h1>

      <p class="text-secondary">
        <?= textoHorasAdmin('Consulta las horas de los consultores por mes, cliente y proyecto.') ?>
      </p>

      <form method="get" action="horas-admin.php">
        <input
          type="hidden"
          name="idioma"
          value="<?= escapar($idioma) ?>">

        <div class="row g-3">
          <div class="col-md-3">
            <label for="mes" class="form-label">
              <?= textoHorasAdmin('Mes') ?>
            </label>

            <input
              id="mes"
              name="mes"
              type="month"
              class="form-control"
              min="1000-01"
              max="9998-12"
              value="<?= escapar($mes) ?>"
              required>
          </div>

          <div class="col-md-3">
            <label for="consultor_id" class="form-label">
              <?= textoHorasAdmin('Consultor') ?>
            </label>

            <select
              id="consultor_id"
              name="consultor_id"
              class="form-select">

              <option value="">
                <?= textoHorasAdmin('Todos los consultores') ?>
              </option>

              <?php foreach ($consultores as $consultor): ?>
                <option
                  value="<?= escapar($consultor['id']) ?>"
                  <?= $filtros['consultor_id'] === (int) $consultor['id'] ? 'selected' : '' ?>>
                  <?= escapar(
                      $consultor['nombres'] . ' ' .
                      $consultor['apellidos'] . ' (' .
                      $consultor['usuario'] . ')'
                  ) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="col-md-3">
            <label for="cliente_id" class="form-label">
              <?= textoHorasAdmin('Cliente') ?>
            </label>

            <select
              id="cliente_id"
              name="cliente_id"
              class="form-select">

              <option value="">
                <?= textoHorasAdmin('Todos los clientes') ?>
              </option>

              <?php foreach ($clientes as $cliente): ?>
                <option
                  value="<?= escapar($cliente['id']) ?>"
                  <?= $filtros['cliente_id'] === (int) $cliente['id'] ? 'selected' : '' ?>>
                  <?= escapar($cliente['nombre']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="col-md-3">
            <label for="proyecto_id" class="form-label">
              <?= textoHorasAdmin('Proyecto') ?>
            </label>

            <select
              id="proyecto_id"
              name="proyecto_id"
              class="form-select">

              <option value="">
                <?= textoHorasAdmin('Todos los proyectos') ?>
              </option>

              <?php foreach ($proyectos as $proyecto): ?>
                <option
                  value="<?= escapar($proyecto['id']) ?>"
                  <?= $filtros['proyecto_id'] === (int) $proyecto['id'] ? 'selected' : '' ?>>
                  <?= escapar($proyecto['cliente'] . ' / ' . $proyecto['nombre']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="d-flex flex-wrap gap-2 mt-3">
          <button type="submit" class="btn btn-dark">
            <?= textoHorasAdmin('Consultar') ?>
          </button>

          <button
            type="submit"
            name="exportar"
            value="excel"
            class="btn btn-outline-dark">
            <?= textoHorasAdmin('Descargar Excel') ?>
          </button>

          <a
            href="horas-admin.php?idioma=<?= escapar($idioma) ?>"
            class="btn btn-outline-secondary">
            <?= textoHorasAdmin('Restablecer filtros') ?>
          </a>
        </div>
      </form>
    </section>

    <section
      class="bg-white border rounded p-4"
      aria-labelledby="titulo-registros">

      <h2 id="titulo-registros" class="h5">
        <?= textoHorasAdmin('Registros de') ?>
        <?= escapar($inicio->format('m/Y')) ?>
      </h2>

      <p>
        <?= textoHorasAdmin('Total de horas según los filtros:') ?>

        <strong id="total-horas">
          <?= escapar(number_format(
              $totalCentesimas / 100,
              2,
              $idioma === 'en' ? '.' : ',',
              $idioma === 'en' ? ',' : '.'
          )) ?>
          <?= textoHorasAdmin('horas') ?>
        </strong>
      </p>

      <p class="small text-secondary">
        <?= textoHorasAdmin('Cantidad de registros:') ?>
        <?= count($registros) ?>
      </p>

      <?php if (!$registros): ?>
        <p class="text-secondary mb-0">
          <?= textoHorasAdmin('No hay registros para el mes y los filtros seleccionados.') ?>
        </p>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table align-middle mb-0">

            <thead>
              <tr>
                <th scope="col"><?= textoHorasAdmin('Fecha') ?></th>
                <th scope="col"><?= textoHorasAdmin('Consultor') ?></th>
                <th scope="col"><?= textoHorasAdmin('Cliente') ?></th>
                <th scope="col"><?= textoHorasAdmin('Proyecto') ?></th>
                <th scope="col"><?= textoHorasAdmin('Horas') ?></th>
                <th scope="col"><?= textoHorasAdmin('Descripción') ?></th>
              </tr>
            </thead>

            <tbody>
              <?php foreach ($registros as $registro): ?>
                <tr>
                  <td>
                    <?= escapar(
                        DateTimeImmutable::createFromFormat(
                            '!Y-m-d',
                            $registro['fecha']
                        )->format($idioma === 'en' ? 'm/d/Y' : 'd/m/Y')
                    ) ?>
                  </td>

                  <td>
                    <?= escapar($registro['nombres'] . ' ' . $registro['apellidos']) ?>
                  </td>

                  <td>
                    <?= escapar($registro['cliente']) ?>
                  </td>

                  <td>
                    <?= escapar($registro['proyecto']) ?>
                  </td>

                  <td>
                    <?= escapar(
                        $idioma === 'en'
                            ? $registro['horas']
                            : str_replace('.', ',', $registro['horas'])
                    ) ?>
                  </td>

                  <td class="text-break">
                    <?= nl2br(escapar($registro['descripcion'])) ?>
                  </td>
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