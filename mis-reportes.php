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
    'Mi reporte mensual' => 'My monthly report',
    'Panel del consultor' => 'Consultant dashboard',
    'Esta sección corresponde al panel del consultor.' => 'This section is for consultants only.',
    'Mes no válido. Vuelve a abrir Mi reporte mensual.' => 'Invalid month. Open My monthly report again.',
    'Volver al panel' => 'Back to dashboard',
    'Registrar horas' => 'Record hours',
    'Consulta y descarga tus propias horas del mes seleccionado.' => 'View and download your own hours for the selected month.',
    'Mes' => 'Month',
    'Consultar' => 'View report',
    'Descargar mi Excel' => 'Download my Excel report',
    'Mis registros de' => 'My entries for',
    'Total del mes:' => 'Monthly total:',
    'horas' => 'hours',
    'Cantidad de registros:' => 'Number of entries:',
    'No tienes horas registradas en este mes.' => 'You have no time entries for this month.',
    'Fecha' => 'Date',
    'Cliente' => 'Client',
    'Proyecto' => 'Project',
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

if ($cuenta['rol'] !== 'consultor') {
    http_response_code(403);
    exit(textoReporte('Esta sección corresponde al panel del consultor.'));
}

$_SESSION['ultima_actividad'] = time();

function escapar($valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

function textoReporte(string $texto): string
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
    exit(textoReporte('Mes no válido. Vuelve a abrir Mi reporte mensual.'));
}

$inicio = new DateTimeImmutable($mes . '-01', $zona);
$fin = $inicio->modify('+1 month');

$consultaRegistros = $pdo->prepare(
    'SELECT r.fecha, r.horas, r.descripcion,
            c.nombre AS cliente, p.nombre AS proyecto
     FROM registros_horas r
     INNER JOIN proyectos p ON p.id = r.proyecto_id
     INNER JOIN clientes c ON c.id = p.cliente_id
     WHERE r.consultor_id = :consultor
       AND r.fecha >= :inicio
       AND r.fecha < :fin
     ORDER BY r.fecha DESC, r.id DESC'
);

$consultaRegistros->execute([
    'consultor' => $_SESSION['usuario_id'],
    'inicio' => $inicio->format('Y-m-d'),
    'fin' => $fin->format('Y-m-d')
]);

$registros = $consultaRegistros->fetchAll();
$totalCentesimas = 0;

foreach ($registros as $indice => $registro) {
    $totalCentesimas += (int) str_replace('.', '', $registro['horas']);

    $registros[$indice]['nombres'] = $cuenta['nombres'];
    $registros[$indice]['apellidos'] = $cuenta['apellidos'];
}

if (($_GET['exportar'] ?? '') === 'excel') {
    require __DIR__ . '/config/exportar-horas.php';

    descargarHorasExcel(
        $registros,
        $totalCentesimas,
        $inicio->format('m/Y')
    );
}
?>
<!DOCTYPE html>
<html lang="<?= escapar($idioma) ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <title><?= textoReporte('Mi reporte mensual') ?> | MEJR Timesheet</title>

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
          <?= textoReporte('Panel del consultor') ?>
        </span>
      </div>
    </div>
  </header>

  <main class="container py-4">

    <div class="d-flex flex-wrap gap-3 justify-content-between align-items-center mb-3">
      <div class="d-flex flex-wrap gap-2">
        <a href="panel.php" class="btn btn-outline-secondary">
          <?= textoReporte('Volver al panel') ?>
        </a>

        <a href="mis-horas.php" class="btn btn-outline-dark">
          <?= textoReporte('Registrar horas') ?>
        </a>
      </div>

      <nav aria-label="<?= textoReporte('Idioma') ?>">
        <div class="btn-group">
          <a
            href="mis-reportes.php?mes=<?= escapar($mes) ?>&amp;idioma=es"
            lang="es"
            class="btn btn-sm <?= $idioma === 'es' ? 'btn-dark' : 'btn-outline-secondary' ?>"
            <?= $idioma === 'es' ? 'aria-current="true"' : '' ?>>
            Español
          </a>

          <a
            href="mis-reportes.php?mes=<?= escapar($mes) ?>&amp;idioma=en"
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
        <?= textoReporte('Mi reporte mensual') ?>
      </h1>

      <p class="text-secondary">
        <?= textoReporte('Consulta y descarga tus propias horas del mes seleccionado.') ?>
      </p>

      <form method="get" action="mis-reportes.php">
        <input
          type="hidden"
          name="idioma"
          value="<?= escapar($idioma) ?>">

        <div class="row g-3 align-items-end">
          <div class="col-md-4">
            <label for="mes" class="form-label">
              <?= textoReporte('Mes') ?>
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

          <div class="col-md-8 d-flex flex-wrap gap-2">
            <button type="submit" class="btn btn-dark">
              <?= textoReporte('Consultar') ?>
            </button>

            <button
              type="submit"
              name="exportar"
              value="excel"
              class="btn btn-outline-dark">
              <?= textoReporte('Descargar mi Excel') ?>
            </button>
          </div>
        </div>
      </form>
    </section>

    <section
      class="bg-white border rounded p-4"
      aria-labelledby="titulo-registros">

      <h2 id="titulo-registros" class="h5">
        <?= textoReporte('Mis registros de') ?>
        <?= escapar($inicio->format('m/Y')) ?>
      </h2>

      <p>
        <?= textoReporte('Total del mes:') ?>

        <strong id="total-horas">
          <?= escapar(number_format(
              $totalCentesimas / 100,
              2,
              $idioma === 'en' ? '.' : ',',
              $idioma === 'en' ? ',' : '.'
          )) ?>
          <?= textoReporte('horas') ?>
        </strong>
      </p>

      <p class="small text-secondary">
        <?= textoReporte('Cantidad de registros:') ?>
        <?= count($registros) ?>
      </p>

      <?php if (!$registros): ?>
        <p class="text-secondary mb-0">
          <?= textoReporte('No tienes horas registradas en este mes.') ?>
        </p>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table align-middle mb-0">

            <thead>
              <tr>
                <th scope="col"><?= textoReporte('Fecha') ?></th>
                <th scope="col"><?= textoReporte('Cliente') ?></th>
                <th scope="col"><?= textoReporte('Proyecto') ?></th>
                <th scope="col"><?= textoReporte('Horas') ?></th>
                <th scope="col"><?= textoReporte('Descripción') ?></th>
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