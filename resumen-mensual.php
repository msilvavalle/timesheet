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
    'Idioma' => 'Language',
    'Resumen mensual' => 'Monthly summary',
    'Panel de administración' => 'Administration dashboard',
    'Acceso reservado para administración.' => 'Access restricted to administrators.',
    'Mes no válido. Vuelve a abrir Resumen mensual.' => 'Invalid month. Open Monthly summary again.',
    'No se pudo cargar el resumen mensual. Intenta nuevamente.' => 'The monthly summary could not be loaded. Try again.',
    'Volver al panel' => 'Back to dashboard',
    'Horas registradas por consultor y por proyecto.' => 'Time entries by consultant and project.',
    'Mes' => 'Month',
    'Consultar resumen' => 'View summary',
    'Periodo' => 'Period',
    'Total de horas' => 'Total hours',
    'Consultores con registros' => 'Consultants with time entries',
    'Consultores activos sin registros' => 'Active consultants without time entries',
    'Horas por consultor' => 'Hours by consultant',
    'Incluye consultores activos sin registros y cuentas con horas en el mes.' => 'Includes active consultants without entries and accounts with hours recorded during the month.',
    'No hay consultores para mostrar en este periodo.' => 'There are no consultants to display for this period.',
    'Mayor total individual:' => 'Highest individual total:',
    'Menor total individual:' => 'Lowest individual total:',
    'horas' => 'hours',
    'Consultor' => 'Consultant',
    'Registros' => 'Entries',
    'Horas' => 'Hours',
    'Detalle' => 'Details',
    'Ver registros' => 'View entries',
    'Horas por proyecto' => 'Hours by project',
    'Proyectos con horas registradas en el mes.' => 'Projects with hours recorded during the month.',
    'No hay horas registradas por proyecto en este periodo.' => 'There are no hours recorded by project for this period.',
    'Cliente' => 'Client',
    'Proyecto' => 'Project'
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

$consulta = $pdo->prepare(
    'SELECT nombres, apellidos, rol
     FROM usuarios
     WHERE id = :id AND activo = 1'
);
$consulta->execute(['id' => $_SESSION['usuario_id']]);
$cuenta = $consulta->fetch();

if (!$cuenta) {
    $_SESSION = [];
    header('Location: login.php');
    exit;
}

if ($cuenta['rol'] !== 'administrador') {
    http_response_code(403);
    exit(textoResumen('Acceso reservado para administración.'));
}

$_SESSION['ultima_actividad'] = time();

function escapar($valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

function textoResumen(string $texto): string
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
    exit(textoResumen('Mes no válido. Vuelve a abrir Resumen mensual.'));
}

$inicio = new DateTimeImmutable($mes . '-01', $zona);
$fin = $inicio->modify('+1 month');

$parametros = [
    'inicio' => $inicio->format('Y-m-d'),
    'fin' => $fin->format('Y-m-d')
];

try {
    $pdo->beginTransaction();

    $consultaConsultores = $pdo->prepare(
        "SELECT u.id, u.nombres, u.apellidos,
                COUNT(r.id) AS registros,
                COALESCE(SUM(r.horas * 100), 0) AS centesimas
         FROM usuarios u
         LEFT JOIN registros_horas r
           ON r.consultor_id = u.id
          AND r.fecha >= :inicio AND r.fecha < :fin
         WHERE (u.rol = 'consultor' AND u.activo = 1)
            OR r.id IS NOT NULL
         GROUP BY u.id, u.nombres, u.apellidos
         ORDER BY centesimas DESC, u.apellidos, u.nombres, u.id"
    );

    $consultaConsultores->execute($parametros);
    $consultores = $consultaConsultores->fetchAll();

    $consultaProyectos = $pdo->prepare(
        'SELECT p.id, p.nombre AS proyecto, c.nombre AS cliente,
                COUNT(r.id) AS registros,
                SUM(r.horas * 100) AS centesimas
         FROM registros_horas r
         INNER JOIN proyectos p ON p.id = r.proyecto_id
         INNER JOIN clientes c ON c.id = p.cliente_id
         WHERE r.fecha >= :inicio AND r.fecha < :fin
         GROUP BY p.id, p.nombre, c.id, c.nombre
         ORDER BY centesimas DESC, c.nombre, p.nombre, p.id'
    );

    $consultaProyectos->execute($parametros);
    $proyectos = $consultaProyectos->fetchAll();

    $pdo->commit();
} catch (PDOException $excepcion) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log($excepcion->getMessage());
    http_response_code(500);
    exit(textoResumen('No se pudo cargar el resumen mensual. Intenta nuevamente.'));
}

$totalCentesimas = 0;
$conRegistros = 0;
$sinRegistros = 0;

foreach ($consultores as $consultor) {
    $totalCentesimas += (int) $consultor['centesimas'];

    if ((int) $consultor['registros'] > 0) {
        $conRegistros++;
    } else {
        $sinRegistros++;
    }
}

$mayor = $consultores
    ? (int) $consultores[0]['centesimas']
    : null;

$menor = $consultores
    ? (int) $consultores[count($consultores) - 1]['centesimas']
    : null;

function horas(?int $centesimas): string
{
    global $idioma;

    return $centesimas === null
        ? '—'
        : number_format(
            $centesimas / 100,
            2,
            $idioma === 'en' ? '.' : ',',
            $idioma === 'en' ? ',' : '.'
        );
}
?>
<!DOCTYPE html>
<html lang="<?= escapar($idioma) ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <title><?= textoResumen('Resumen mensual') ?> | MEJR Timesheet</title>

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
          <?= textoResumen('Panel de administración') ?>
        </span>
      </div>
    </div>
  </header>

  <main class="container py-4">

    <div class="d-flex flex-wrap gap-3 justify-content-between align-items-center mb-3">
      <a href="panel.php" class="btn btn-outline-secondary">
        <?= textoResumen('Volver al panel') ?>
      </a>

      <nav aria-label="<?= textoResumen('Idioma') ?>">
        <div class="btn-group">
          <a
            href="resumen-mensual.php?mes=<?= escapar($mes) ?>&amp;idioma=es"
            lang="es"
            class="btn btn-sm <?= $idioma === 'es' ? 'btn-dark' : 'btn-outline-secondary' ?>"
            <?= $idioma === 'es' ? 'aria-current="true"' : '' ?>>
            Español
          </a>

          <a
            href="resumen-mensual.php?mes=<?= escapar($mes) ?>&amp;idioma=en"
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
        <?= textoResumen('Resumen mensual') ?>
      </h1>

      <p class="text-secondary">
        <?= textoResumen('Horas registradas por consultor y por proyecto.') ?>
      </p>

      <form method="get" action="resumen-mensual.php">
        <input
          type="hidden"
          name="idioma"
          value="<?= escapar($idioma) ?>">

        <div class="row g-3 align-items-end">
          <div class="col-md-4">
            <label for="mes" class="form-label">
              <?= textoResumen('Mes') ?>
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

          <div class="col-md-8">
            <button type="submit" class="btn btn-dark">
              <?= textoResumen('Consultar resumen') ?>
            </button>
          </div>
        </div>
      </form>
    </section>

    <h2 class="h5 mb-3">
      <?= textoResumen('Periodo') ?>
      <?= escapar($inicio->format('m/Y')) ?>
    </h2>

    <div class="row g-3 mb-4">
      <div class="col-md-4">
        <div class="bg-white border rounded p-3 h-100">
          <p class="text-secondary mb-1">
            <?= textoResumen('Total de horas') ?>
          </p>

          <p class="h4 mb-0" id="total-horas">
            <?= horas($totalCentesimas) ?>
          </p>
        </div>
      </div>

      <div class="col-md-4">
        <div class="bg-white border rounded p-3 h-100">
          <p class="text-secondary mb-1">
            <?= textoResumen('Consultores con registros') ?>
          </p>

          <p class="h4 mb-0" id="con-registros">
            <?= $conRegistros ?>
          </p>
        </div>
      </div>

      <div class="col-md-4">
        <div class="bg-white border rounded p-3 h-100">
          <p class="text-secondary mb-1">
            <?= textoResumen('Consultores activos sin registros') ?>
          </p>

          <p class="h4 mb-0" id="sin-registros">
            <?= $sinRegistros ?>
          </p>
        </div>
      </div>
    </div>

    <section class="bg-white border rounded p-4 mb-4">
      <h2 class="h5">
        <?= textoResumen('Horas por consultor') ?>
      </h2>

      <p class="small text-secondary">
        <?= textoResumen('Incluye consultores activos sin registros y cuentas con horas en el mes.') ?>
      </p>

      <?php if (!$consultores): ?>
        <p class="text-secondary mb-0">
          <?= textoResumen('No hay consultores para mostrar en este periodo.') ?>
        </p>
      <?php else: ?>
        <p class="small">
          <?= textoResumen('Mayor total individual:') ?>
          <strong id="mayor-total"><?= horas($mayor) ?></strong>
          <?= textoResumen('horas') ?>.

          <?= textoResumen('Menor total individual:') ?>
          <strong id="menor-total"><?= horas($menor) ?></strong>
          <?= textoResumen('horas') ?>.
        </p>

        <div class="table-responsive">
          <table id="tabla-consultores" class="table align-middle mb-0">
            <thead>
              <tr>
                <th scope="col"><?= textoResumen('Consultor') ?></th>
                <th scope="col"><?= textoResumen('Registros') ?></th>
                <th scope="col"><?= textoResumen('Horas') ?></th>
                <th scope="col"><?= textoResumen('Detalle') ?></th>
              </tr>
            </thead>

            <tbody>
              <?php foreach ($consultores as $consultor): ?>
                <tr>
                  <td>
                    <?= escapar($consultor['nombres'] . ' ' . $consultor['apellidos']) ?>
                  </td>

                  <td><?= (int) $consultor['registros'] ?></td>

                  <td><?= horas((int) $consultor['centesimas']) ?></td>

                  <td>
                    <a href="<?= escapar(
                        'horas-admin.php?' . http_build_query([
                            'mes' => $mes,
                            'idioma' => $idioma,
                            'consultor_id' => $consultor['id']
                        ])
                    ) ?>">
                      <?= textoResumen('Ver registros') ?>
                    </a>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </section>

    <section class="bg-white border rounded p-4">
      <h2 class="h5">
        <?= textoResumen('Horas por proyecto') ?>
      </h2>

      <p class="small text-secondary">
        <?= textoResumen('Proyectos con horas registradas en el mes.') ?>
      </p>

      <?php if (!$proyectos): ?>
        <p class="text-secondary mb-0">
          <?= textoResumen('No hay horas registradas por proyecto en este periodo.') ?>
        </p>
      <?php else: ?>
        <div class="table-responsive">
          <table id="tabla-proyectos" class="table align-middle mb-0">
            <thead>
              <tr>
                <th scope="col"><?= textoResumen('Cliente') ?></th>
                <th scope="col"><?= textoResumen('Proyecto') ?></th>
                <th scope="col"><?= textoResumen('Registros') ?></th>
                <th scope="col"><?= textoResumen('Horas') ?></th>
                <th scope="col"><?= textoResumen('Detalle') ?></th>
              </tr>
            </thead>

            <tbody>
              <?php foreach ($proyectos as $proyecto): ?>
                <tr>
                  <td><?= escapar($proyecto['cliente']) ?></td>

                  <td><?= escapar($proyecto['proyecto']) ?></td>

                  <td><?= (int) $proyecto['registros'] ?></td>

                  <td><?= horas((int) $proyecto['centesimas']) ?></td>

                  <td>
                    <a href="<?= escapar(
                        'horas-admin.php?' . http_build_query([
                            'mes' => $mes,
                            'idioma' => $idioma,
                            'proyecto_id' => $proyecto['id']
                        ])
                    ) ?>">
                      <?= textoResumen('Ver registros') ?>
                    </a>
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