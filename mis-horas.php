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
    'Esta sección corresponde al panel del consultor.' => 'This section is for consultants.',
    'Solicitud no válida. Vuelve a abrir el formulario.' => 'Invalid request. Open the form again.',
    'Este formulario ya se utilizó o venció. Vuelve a abrir Mis horas.' => 'This form has already been used or has expired. Open My hours again.',
    'Selecciona una fecha válida.' => 'Select a valid date.',
    'Selecciona un proyecto válido.' => 'Select a valid project.',
    'Ingresa entre 0,01 y 24 horas, con un máximo de dos decimales.' => 'Enter between 0.01 and 24 hours, with up to two decimal places.',
    'Describe el trabajo realizado, con un máximo de 2000 caracteres.' => 'Describe the work completed using up to 2000 characters.',
    'Tu cuenta ya no tiene acceso para registrar horas.' => 'Your account no longer has access to log hours.',
    'Ese proyecto no está activo o no está asignado a tu cuenta.' => 'This project is inactive or is not assigned to your account.',
    'Solo puedes registrar fechas del mes actual, hasta hoy.' => 'You can only log dates in the current month, up to today.',
    'El total de tus registros para ese día no puede superar 24 horas.' => 'Your total time entries for that day cannot exceed 24 hours.',
    'No se pudo guardar el registro. Intenta nuevamente.' => 'The entry could not be saved. Try again.',
    'Volver al panel' => 'Back to dashboard',
    'Mi reporte mensual' => 'My monthly report',
    'Registra el trabajo realizado en tus proyectos asignados.' => 'Log the work completed on your assigned projects.',
    'Horas guardadas correctamente.' => 'Hours saved successfully.',
    'No tienes proyectos activos asignados. Consulta con administración.' => 'You have no active project assignments. Contact your administrator.',
    'Fecha' => 'Date',
    'Cliente / Proyecto' => 'Client / Project',
    'Selecciona un proyecto' => 'Select a project',
    'Horas trabajadas' => 'Hours worked',
    'Ejemplo: 2,5 equivale a 2 horas y 30 minutos.' => 'Example: 2.5 means 2 hours and 30 minutes.',
    'Descripción del trabajo' => 'Work description',
    'Registro habilitado para el mes actual, hasta hoy. Máximo total: 24 horas por día.' => 'You can log dates in the current month, up to today. Maximum total: 24 hours per day.',
    'Guardar horas' => 'Save hours',
    'Mis registros de' => 'My entries for',
    'Total del mes:' => 'Monthly total:',
    'horas' => 'hours',
    'Todavía no has registrado horas este mes.' => 'You have not logged any hours this month.',
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

if ($cuenta['rol'] !== 'consultor') {
    http_response_code(403);
    exit(textoHoras('Esta sección corresponde al panel del consultor.'));
}

$_SESSION['ultima_actividad'] = time();

function escapar($valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

function textoHoras(string $texto): string
{
    return escapar(mejrTraducir($texto));
}

$zona = new DateTimeZone('America/Santiago');
$ahora = new DateTimeImmutable('now', $zona);
$hoy = $ahora->format('Y-m-d');
$inicioMes = $ahora->format('Y-m-01');
$proximoMes = $ahora->modify('first day of next month')->format('Y-m-01');

$_SESSION['csrf'] ??= bin2hex(random_bytes(32));
$_SESSION['registro_token'] ??= bin2hex(random_bytes(16));

$guardado = $_SESSION['horas_guardadas'] ?? false;
unset($_SESSION['horas_guardadas']);

$error = '';

$datos = [
    'fecha' => $hoy,
    'proyecto_id' => '',
    'horas' => '',
    'descripcion' => ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf'] ?? '';
    $token = $_POST['registro_token'] ?? '';

    if (!is_string($csrf) || !hash_equals($_SESSION['csrf'], $csrf)) {
        http_response_code(403);
        exit(textoHoras('Solicitud no válida. Vuelve a abrir el formulario.'));
    }

    if (
        !is_string($token) ||
        !hash_equals($_SESSION['registro_token'], $token)
    ) {
        http_response_code(409);
        exit(textoHoras('Este formulario ya se utilizó o venció. Vuelve a abrir Mis horas.'));
    }

    foreach ($datos as $campo => $valor) {
        $datos[$campo] = is_string($_POST[$campo] ?? null)
            ? trim($_POST[$campo])
            : '';
    }

    $fecha = DateTimeImmutable::createFromFormat(
        '!Y-m-d',
        $datos['fecha'],
        $zona
    );

    $proyectoId = filter_var(
        $datos['proyecto_id'],
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1, 'max_range' => 4294967295]]
    );

    $horasValidas = preg_match(
        '/\A([0-9]{1,2})(?:[.,]([0-9]{1,2}))?\z/',
        $datos['horas'],
        $partes
    );

    $centesimas = $horasValidas
        ? (int) $partes[1] * 100
            + (int) str_pad($partes[2] ?? '', 2, '0')
        : 0;

    if (!$fecha || $fecha->format('Y-m-d') !== $datos['fecha']) {
        $error = 'Selecciona una fecha válida.';
    } elseif ($proyectoId === false) {
        $error = 'Selecciona un proyecto válido.';
    } elseif (!$horasValidas || $centesimas < 1 || $centesimas > 2400) {
        $error = 'Ingresa entre 0,01 y 24 horas, con un máximo de dos decimales.';
    } elseif (
        !preg_match('/\A.{1,2000}\z/us', $datos['descripcion']) ||
        str_contains($datos['descripcion'], "\0")
    ) {
        $error = 'Describe el trabajo realizado, con un máximo de 2000 caracteres.';
    } else {
        try {
            $pdo->beginTransaction();

            $bloqueo = $pdo->prepare(
                "SELECT id
                 FROM usuarios
                 WHERE id = :id AND activo = 1 AND rol = 'consultor'
                 FOR UPDATE"
            );
            $bloqueo->execute(['id' => $_SESSION['usuario_id']]);

            if (!$bloqueo->fetch()) {
                throw new DomainException(
                    'Tu cuenta ya no tiene acceso para registrar horas.'
                );
            }

            $asignacion = $pdo->prepare(
                'SELECT pc.proyecto_id
                 FROM proyecto_consultor pc
                 INNER JOIN proyectos p ON p.id = pc.proyecto_id
                 INNER JOIN clientes c ON c.id = p.cliente_id
                 WHERE pc.consultor_id = :consultor
                   AND pc.proyecto_id = :proyecto
                   AND pc.activo = 1
                   AND p.activo = 1
                   AND c.activo = 1
                 FOR UPDATE'
            );

            $asignacion->execute([
                'consultor' => $_SESSION['usuario_id'],
                'proyecto' => $proyectoId
            ]);

            if (!$asignacion->fetch()) {
                throw new DomainException(
                    'Ese proyecto no está activo o no está asignado a tu cuenta.'
                );
            }

            $momento = new DateTimeImmutable('now', $zona);

            if (
                $datos['fecha'] < $momento->format('Y-m-01') ||
                $datos['fecha'] > $momento->format('Y-m-d')
            ) {
                throw new DomainException(
                    'Solo puedes registrar fechas del mes actual, hasta hoy.'
                );
            }

            $suma = $pdo->prepare(
                'SELECT COALESCE(SUM(horas * 100), 0)
                 FROM registros_horas
                 WHERE consultor_id = :consultor AND fecha = :fecha'
            );

            $suma->execute([
                'consultor' => $_SESSION['usuario_id'],
                'fecha' => $datos['fecha']
            ]);

            if ((int) $suma->fetchColumn() + $centesimas > 2400) {
                throw new DomainException(
                    'El total de tus registros para ese día no puede superar 24 horas.'
                );
            }

            $horasBD = intdiv($centesimas, 100) . '.' .
                str_pad(
                    (string) ($centesimas % 100),
                    2,
                    '0',
                    STR_PAD_LEFT
                );

            $insertar = $pdo->prepare(
                'INSERT INTO registros_horas
                    (consultor_id, proyecto_id, fecha, horas, descripcion)
                 VALUES
                    (:consultor, :proyecto, :fecha, :horas, :descripcion)'
            );

            $insertar->execute([
                'consultor' => $_SESSION['usuario_id'],
                'proyecto' => $proyectoId,
                'fecha' => $datos['fecha'],
                'horas' => $horasBD,
                'descripcion' => $datos['descripcion']
            ]);

            $pdo->commit();

            $_SESSION['registro_token'] = bin2hex(random_bytes(16));
            $_SESSION['horas_guardadas'] = true;

            header('Location: mis-horas.php', true, 303);
            exit;
        } catch (DomainException $excepcion) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $error = $excepcion->getMessage();
        } catch (PDOException $excepcion) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log($excepcion->getMessage());
            $error = 'No se pudo guardar el registro. Intenta nuevamente.';
        }
    }
}

$consultaProyectos = $pdo->prepare(
    'SELECT p.id, p.nombre AS proyecto, c.nombre AS cliente
     FROM proyecto_consultor pc
     INNER JOIN proyectos p ON p.id = pc.proyecto_id
     INNER JOIN clientes c ON c.id = p.cliente_id
     WHERE pc.consultor_id = :consultor
       AND pc.activo = 1
       AND p.activo = 1
       AND c.activo = 1
     ORDER BY c.nombre, p.nombre, p.id'
);

$consultaProyectos->execute([
    'consultor' => $_SESSION['usuario_id']
]);

$proyectos = $consultaProyectos->fetchAll();

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
    'inicio' => $inicioMes,
    'fin' => $proximoMes
]);

$registros = $consultaRegistros->fetchAll();

$totalCentesimas = 0;

foreach ($registros as $registro) {
    $totalCentesimas += (int) str_replace('.', '', $registro['horas']);
}
?>
<!DOCTYPE html>
<html lang="<?= escapar($idioma) ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <title><?= textoHoras('Mis horas') ?> | MEJR Timesheet</title>

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
          <?= textoHoras('Panel del consultor') ?>
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
          href="mis-horas.php?idioma=es"
          lang="es"
          class="btn btn-sm <?= $idioma === 'es' ? 'btn-dark' : 'btn-outline-secondary' ?>"
          <?= $idioma === 'es' ? 'aria-current="true"' : '' ?>>
          Español
        </a>

        <a
          href="mis-horas.php?idioma=en"
          lang="en"
          class="btn btn-sm <?= $idioma === 'en' ? 'btn-dark' : 'btn-outline-secondary' ?>"
          <?= $idioma === 'en' ? 'aria-current="true"' : '' ?>>
          English
        </a>
      </div>
    </nav>

    <a href="panel.php" class="btn btn-outline-secondary mb-3">
      <?= textoHoras('Volver al panel') ?>
    </a>

    <a href="mis-reportes.php" class="btn btn-outline-dark mb-3">
      <?= textoHoras('Mi reporte mensual') ?>
    </a>

    <section class="bg-white border rounded p-4 mb-4">
      <h1 class="h4"><?= textoHoras('Mis horas') ?></h1>

      <p class="text-secondary">
        <?= textoHoras('Registra el trabajo realizado en tus proyectos asignados.') ?>
      </p>

      <?php if ($guardado): ?>
        <div class="alert alert-success" role="status">
          <?= textoHoras('Horas guardadas correctamente.') ?>
        </div>
      <?php endif; ?>

      <?php if ($error !== ''): ?>
        <div class="alert alert-danger" role="alert">
          <?= textoHoras($error) ?>
        </div>
      <?php endif; ?>

      <?php if (!$proyectos): ?>
        <p>
          <?= textoHoras('No tienes proyectos activos asignados. Consulta con administración.') ?>
        </p>
      <?php endif; ?>

      <form method="post" action="mis-horas.php">
        <input
          type="hidden"
          name="csrf"
          value="<?= escapar($_SESSION['csrf']) ?>">

        <input
          type="hidden"
          name="registro_token"
          value="<?= escapar($_SESSION['registro_token']) ?>">

        <div class="row g-3">
          <div class="col-md-4">
            <label for="fecha" class="form-label">
              <?= textoHoras('Fecha') ?>
            </label>

            <input
              id="fecha"
              name="fecha"
              type="date"
              class="form-control"
              min="<?= escapar($inicioMes) ?>"
              max="<?= escapar($hoy) ?>"
              value="<?= escapar($datos['fecha']) ?>"
              required>
          </div>

          <div class="col-md-8">
            <label for="proyecto_id" class="form-label">
              <?= textoHoras('Cliente / Proyecto') ?>
            </label>

            <select
              id="proyecto_id"
              name="proyecto_id"
              class="form-select"
              required>

              <option value="">
                <?= textoHoras('Selecciona un proyecto') ?>
              </option>

              <?php foreach ($proyectos as $proyecto): ?>
                <option
                  value="<?= escapar($proyecto['id']) ?>"
                  <?= (string) $proyecto['id'] === $datos['proyecto_id'] ? 'selected' : '' ?>>
                  <?= escapar($proyecto['cliente'] . ' / ' . $proyecto['proyecto']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="col-md-4">
            <label for="horas" class="form-label">
              <?= textoHoras('Horas trabajadas') ?>
            </label>

            <input
              id="horas"
              name="horas"
              type="text"
              inputmode="decimal"
              class="form-control"
              maxlength="5"
              pattern="[0-9]{1,2}([.,][0-9]{1,2})?"
              value="<?= escapar($datos['horas']) ?>"
              aria-describedby="ayuda-horas"
              required>

            <div id="ayuda-horas" class="form-text">
              <?= textoHoras('Ejemplo: 2,5 equivale a 2 horas y 30 minutos.') ?>
            </div>
          </div>

          <div class="col-12">
            <label for="descripcion" class="form-label">
              <?= textoHoras('Descripción del trabajo') ?>
            </label>

            <textarea
              id="descripcion"
              name="descripcion"
              class="form-control"
              rows="3"
              maxlength="2000"
              required><?= escapar($datos['descripcion']) ?></textarea>
          </div>
        </div>

        <p class="small text-secondary mt-3">
          <?= textoHoras('Registro habilitado para el mes actual, hasta hoy. Máximo total: 24 horas por día.') ?>
        </p>

        <button
          type="submit"
          class="btn btn-dark"
          <?= !$proyectos ? 'disabled' : '' ?>>
          <?= textoHoras('Guardar horas') ?>
        </button>
      </form>
    </section>

    <section
      class="bg-white border rounded p-4"
      aria-labelledby="titulo-historial">

      <h2 id="titulo-historial" class="h5">
        <?= textoHoras('Mis registros de') ?>
        <?= escapar($ahora->format('m/Y')) ?>
      </h2>

      <p>
        <?= textoHoras('Total del mes:') ?>
        <strong>
          <?= number_format(
              $totalCentesimas / 100,
              2,
              $idioma === 'en' ? '.' : ',',
              $idioma === 'en' ? ',' : '.'
          ) ?>
          <?= textoHoras('horas') ?>
        </strong>
      </p>

      <?php if (!$registros): ?>
        <p class="text-secondary mb-0">
          <?= textoHoras('Todavía no has registrado horas este mes.') ?>
        </p>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table align-middle mb-0">
            <thead>
              <tr>
                <th scope="col"><?= textoHoras('Fecha') ?></th>
                <th scope="col"><?= textoHoras('Cliente / Proyecto') ?></th>
                <th scope="col"><?= textoHoras('Horas') ?></th>
                <th scope="col"><?= textoHoras('Descripción') ?></th>
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
                    <?= escapar($registro['cliente'] . ' / ' . $registro['proyecto']) ?>
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