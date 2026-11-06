<?php
// Pantalla única del consultor: registrar horas, corregir o borrar las del mes
// en curso, ver cualquier mes con su total, sus proyectos y el Excel.
// Reemplaza a mis-reportes.php y mis-proyectos.php.
$rolPagina = 'consultor';
require __DIR__ . '/config/arranque.php';

$traducciones += [
    'Mes no válido.' => 'Invalid month.',
    'Este formulario ya se utilizó o venció. Vuelve a abrir Mis horas.' => 'This form has already been used or has expired. Open My hours again.',
    'Selecciona una fecha válida.' => 'Select a valid date.',
    'Selecciona un proyecto válido.' => 'Select a valid project.',
    'Ingresa entre 0,01 y 24 horas, con un máximo de dos decimales.' => 'Enter between 0.01 and 24 hours, with up to two decimal places.',
    'Describe el trabajo realizado, con un máximo de 2000 caracteres.' => 'Describe the work completed using up to 2000 characters.',
    'Tu cuenta ya no tiene acceso para registrar horas.' => 'Your account no longer has access to log hours.',
    'Ese proyecto no está activo o no está asignado a tu cuenta.' => 'This project is inactive or is not assigned to your account.',
    'Ese proyecto ya no está activo: puedes corregir la fecha o la descripción, o bajar las horas, pero no subirlas.' => 'This project is no longer active: you can correct the date or description, or lower the hours, but not increase them.',
    'Solo puedes registrar fechas del mes actual, hasta hoy.' => 'You can only log dates in the current month, up to today.',
    'Ese registro es de un mes cerrado y ya no se puede cambiar.' => 'That entry belongs to a closed month and can no longer be changed.',
    'No se encontró el registro.' => 'The entry was not found.',
    'El total de tus registros para ese día no puede superar 24 horas.' => 'Your total time entries for that day cannot exceed 24 hours.',
    'No se pudo guardar el registro. Intenta nuevamente.' => 'The entry could not be saved. Try again.',
    'Horas guardadas correctamente.' => 'Hours saved successfully.',
    'Registro actualizado.' => 'Entry updated.',
    'Registro eliminado.' => 'Entry deleted.',
    'Registra y revisa el trabajo de tus proyectos asignados.' => 'Log and review the work on your assigned projects.',
    'Registrar horas' => 'Log hours',
    'Editar registro' => 'Edit entry',
    'No tienes proyectos activos asignados. Consulta con administración.' => 'You have no active project assignments. Contact your administrator.',
    'Cliente / Proyecto' => 'Client / Project',
    'Selecciona un proyecto' => 'Select a project',
    '(inactivo)' => '(inactive)',
    'Horas trabajadas' => 'Hours worked',
    'Ejemplo: 2,5 equivale a 2 horas y 30 minutos.' => 'Example: 2.5 means 2 hours and 30 minutes.',
    'Descripción del trabajo' => 'Work description',
    'Registro habilitado para el mes actual, hasta hoy. Máximo total: 24 horas por día. Al cambiar de mes, el mes anterior se cierra.' => 'You can log dates in the current month, up to today. Maximum total: 24 hours per day. When the month changes, the previous month is closed.',
    'Guardar horas' => 'Save hours',
    'Tus proyectos este mes' => 'Your projects this month',
    'Tus proyectos en el mes' => 'Your projects in the month',
    'Sin proyectos con horas en este mes.' => 'No projects with hours in this month.',
    'Mis registros' => 'My entries',
    'Ver mes' => 'View month',
    'Total del mes:' => 'Monthly total:',
    'horas' => 'hours',
    'No hay registros en este mes.' => 'There are no entries in this month.',
    'Mes cerrado: los registros son de solo lectura.' => 'Closed month: entries are read-only.',
    '¿Borrar este registro? No se puede deshacer.' => 'Delete this entry? This cannot be undone.'
];

$ahora = mejrAhora();
$hoy = $ahora->format('Y-m-d');
$inicioMesActual = $ahora->format('Y-m-01');

$mesVista = mejrMesValido($_GET['mes'] ?? $ahora->format('Y-m'));

if ($mesVista === null || $mesVista->format('Y-m') > $ahora->format('Y-m')) {
    http_response_code(400);
    exit(texto('Mes no válido.'));
}

$esMesActual = $mesVista->format('Y-m') === $ahora->format('Y-m');
$consultorId = (int) $_SESSION['usuario_id'];
$_SESSION['registro_token'] ??= bin2hex(random_bytes(16));

$datos = ['fecha' => $hoy, 'proyecto_id' => '', 'horas' => '', 'descripcion' => ''];
$editando = null;
$error = '';

// Registro propio por id, o null.
$buscarRegistro = static function (int $id, bool $bloquear = false) use ($pdo, $consultorId): ?array {
    $consulta = $pdo->prepare(
        'SELECT id, proyecto_id, fecha, horas, descripcion
         FROM registros_horas
         WHERE id = :id AND consultor_id = :consultor' . ($bloquear ? ' FOR UPDATE' : '')
    );
    $consulta->execute(['id' => $id, 'consultor' => $consultorId]);

    return $consulta->fetch() ?: null;
};

if (isset($_GET['editar']) && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $id = validarId($_GET['editar']);
    $registro = $id ? $buscarRegistro($id) : null;

    if (!$registro) {
        avisar('warning', 'No se encontró el registro.');
        redirigir('mis-horas.php');
    }

    if ($registro['fecha'] < $inicioMesActual) {
        avisar('warning', 'Ese registro es de un mes cerrado y ya no se puede cambiar.');
        redirigir('mis-horas.php?mes=' . substr($registro['fecha'], 0, 7));
    }

    $editando = ['id' => (int) $registro['id'], 'proyecto_id' => (string) $registro['proyecto_id']];
    $datos = [
        'fecha' => $registro['fecha'],
        'proyecto_id' => (string) $registro['proyecto_id'],
        'horas' => $idioma === 'en' ? $registro['horas'] : str_replace('.', ',', $registro['horas']),
        'descripcion' => $registro['descripcion']
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfValido()) {
        http_response_code(403);
        exit(texto('Solicitud no válida. Vuelve a abrir la página.'));
    }

    $accion = $_POST['accion'] ?? 'guardar';
    $registroId = isset($_POST['registro_id']) ? validarId($_POST['registro_id']) : null;

    if (!in_array($accion, ['guardar', 'borrar'], true) || $registroId === false
        || ($accion === 'borrar' && $registroId === null)) {
        http_response_code(400);
        exit(texto('Solicitud no válida. Vuelve a abrir la página.'));
    }

    if ($accion === 'guardar' && $registroId === null) {
        $token = $_POST['registro_token'] ?? '';

        if (!is_string($token) || !hash_equals($_SESSION['registro_token'], $token)) {
            http_response_code(409);
            exit(texto('Este formulario ya se utilizó o venció. Vuelve a abrir Mis horas.'));
        }
    }

    foreach ($datos as $campo => $valor) {
        $datos[$campo] = is_string($_POST[$campo] ?? null) ? trim($_POST[$campo]) : '';
    }

    $fecha = DateTimeImmutable::createFromFormat('!Y-m-d', $datos['fecha']);
    $proyectoId = validarId($datos['proyecto_id']);
    $centesimas = mejrLeerHoras($datos['horas']);

    if ($accion === 'guardar') {
        if (!$fecha || $fecha->format('Y-m-d') !== $datos['fecha']) {
            $error = 'Selecciona una fecha válida.';
        } elseif ($proyectoId === false) {
            $error = 'Selecciona un proyecto válido.';
        } elseif ($centesimas === null || $centesimas < 1 || $centesimas > 2400) {
            $error = 'Ingresa entre 0,01 y 24 horas, con un máximo de dos decimales.';
        } elseif (!preg_match('/\A.{1,2000}\z/us', $datos['descripcion'])
            || str_contains($datos['descripcion'], "\0")) {
            $error = 'Describe el trabajo realizado, con un máximo de 2000 caracteres.';
        }
    }

    if ($error === '') {
        try {
            $pdo->beginTransaction();

            $bloqueo = $pdo->prepare(
                "SELECT id FROM usuarios
                 WHERE id = :id AND activo = 1 AND rol = 'consultor'
                 FOR UPDATE"
            );
            $bloqueo->execute(['id' => $consultorId]);

            if (!$bloqueo->fetch()) {
                throw new DomainException('Tu cuenta ya no tiene acceso para registrar horas.');
            }

            // "Hoy" se toma de nuevo dentro de la transacción y en hora de Chile:
            // un formulario abierto el último día y enviado al siguiente se rechaza.
            $momento = mejrAhora();
            $inicioMes = $momento->format('Y-m-01');
            $anterior = null;

            if ($registroId !== null) {
                $anterior = $buscarRegistro($registroId, true);

                if (!$anterior) {
                    throw new DomainException('No se encontró el registro.');
                }

                if ($anterior['fecha'] < $inicioMes) {
                    throw new DomainException('Ese registro es de un mes cerrado y ya no se puede cambiar.');
                }
            }

            if ($accion === 'borrar') {
                $pdo->prepare('DELETE FROM registros_horas WHERE id = :id AND consultor_id = :consultor')
                    ->execute(['id' => $registroId, 'consultor' => $consultorId]);
                $pdo->commit();

                error_log('MEJR: consultor ' . $consultorId . ' eliminó el registro ' . $registroId . '.');
                avisar('success', 'Registro eliminado.');
                redirigir('mis-horas.php');
            }

            $asignacion = $pdo->prepare(
                'SELECT pc.proyecto_id
                 FROM proyecto_consultor pc
                 INNER JOIN proyectos p ON p.id = pc.proyecto_id
                 INNER JOIN clientes c ON c.id = p.cliente_id
                 WHERE pc.consultor_id = :consultor
                   AND pc.proyecto_id = :proyecto
                   AND pc.activo = 1 AND p.activo = 1 AND c.activo = 1
                 FOR UPDATE'
            );
            $asignacion->execute(['consultor' => $consultorId, 'proyecto' => $proyectoId]);
            $asignacionActiva = (bool) $asignacion->fetch();

            $mismoProyecto = $anterior && (int) $anterior['proyecto_id'] === $proyectoId;

            if (!$asignacionActiva && !$mismoProyecto) {
                throw new DomainException('Ese proyecto no está activo o no está asignado a tu cuenta.');
            }

            if (!$asignacionActiva && $centesimas > mejrEscalar($anterior['horas'], 2)) {
                throw new DomainException('Ese proyecto ya no está activo: puedes corregir la fecha o la descripción, o bajar las horas, pero no subirlas.');
            }

            if ($datos['fecha'] < $inicioMes || $datos['fecha'] > $momento->format('Y-m-d')) {
                throw new DomainException('Solo puedes registrar fechas del mes actual, hasta hoy.');
            }

            $suma = $pdo->prepare(
                'SELECT COALESCE(SUM(horas), 0)
                 FROM registros_horas
                 WHERE consultor_id = :consultor AND fecha = :fecha AND id <> :excluir'
            );
            $suma->execute([
                'consultor' => $consultorId,
                'fecha' => $datos['fecha'],
                'excluir' => $registroId ?? 0
            ]);

            if (mejrEscalar($suma->fetchColumn(), 2) + $centesimas > 2400) {
                throw new DomainException('El total de tus registros para ese día no puede superar 24 horas.');
            }

            $valores = [
                'consultor' => $consultorId,
                'proyecto' => $proyectoId,
                'fecha' => $datos['fecha'],
                'horas' => mejrHorasBd($centesimas),
                'descripcion' => $datos['descripcion']
            ];

            if ($registroId === null) {
                $pdo->prepare(
                    'INSERT INTO registros_horas (consultor_id, proyecto_id, fecha, horas, descripcion)
                     VALUES (:consultor, :proyecto, :fecha, :horas, :descripcion)'
                )->execute($valores);
            } else {
                $pdo->prepare(
                    'UPDATE registros_horas
                     SET proyecto_id = :proyecto, fecha = :fecha, horas = :horas, descripcion = :descripcion
                     WHERE id = :id AND consultor_id = :consultor'
                )->execute($valores + ['id' => $registroId]);
            }

            $pdo->commit();

            if ($registroId === null) {
                $_SESSION['registro_token'] = bin2hex(random_bytes(16));
                avisar('success', 'Horas guardadas correctamente.');
            } else {
                error_log('MEJR: consultor ' . $consultorId . ' editó el registro ' . $registroId . '.');
                avisar('success', 'Registro actualizado.');
            }

            redirigir('mis-horas.php');
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

    // Si falla un borrado, se vuelve a la vista normal; si falla una edición,
    // el formulario sigue en modo edición con lo que escribió la persona.
    if ($accion === 'borrar') {
        avisar('danger', $error);
        redirigir('mis-horas.php');
    }

    // El proyecto que se agrega al selector sale del registro guardado de este
    // consultor, nunca del POST: así no se puede consultar un proyecto ajeno.
    if ($registroId !== null) {
        $registroGuardado = $buscarRegistro($registroId);
        $editando = $registroGuardado
            ? ['id' => $registroId, 'proyecto_id' => (string) $registroGuardado['proyecto_id']]
            : null;
    }
}

$consultaProyectos = $pdo->prepare(
    'SELECT p.id, p.nombre AS proyecto, c.nombre AS cliente
     FROM proyecto_consultor pc
     INNER JOIN proyectos p ON p.id = pc.proyecto_id
     INNER JOIN clientes c ON c.id = p.cliente_id
     WHERE pc.consultor_id = :consultor
       AND pc.activo = 1 AND p.activo = 1 AND c.activo = 1
     ORDER BY c.nombre, p.nombre, p.id'
);
$consultaProyectos->execute(['consultor' => $consultorId]);
$proyectos = $consultaProyectos->fetchAll();

// Al editar un registro de un proyecto que dejó de estar activo, se agrega al
// selector marcado como inactivo para poder conservarlo.
$idsActivos = array_map('intval', array_column($proyectos, 'id'));
$proyectoEditado = $editando ? validarId($editando['proyecto_id']) : false;

if ($proyectoEditado && !in_array($proyectoEditado, $idsActivos, true)) {
    $extra = $pdo->prepare(
        'SELECT p.id, p.nombre AS proyecto, c.nombre AS cliente
         FROM proyectos p INNER JOIN clientes c ON c.id = p.cliente_id
         WHERE p.id = :id'
    );
    $extra->execute(['id' => $proyectoEditado]);

    if ($fila = $extra->fetch()) {
        $fila['inactivo'] = true;
        $proyectos[] = $fila;
    }
}

$consultaRegistros = $pdo->prepare(
    'SELECT r.id, r.proyecto_id, r.fecha, r.horas, r.descripcion,
            c.nombre AS cliente, p.nombre AS proyecto
     FROM registros_horas r
     INNER JOIN proyectos p ON p.id = r.proyecto_id
     INNER JOIN clientes c ON c.id = p.cliente_id
     WHERE r.consultor_id = :consultor
       AND r.fecha >= :inicio AND r.fecha < :fin
     ORDER BY r.fecha DESC, r.id DESC'
);
$consultaRegistros->execute([
    'consultor' => $consultorId,
    'inicio' => $mesVista->format('Y-m-d'),
    'fin' => $mesVista->modify('+1 month')->format('Y-m-d')
]);
$registros = $consultaRegistros->fetchAll();

$totalCentesimas = 0;
$porProyecto = [];

// En el mes en curso se listan todos los proyectos asignados, aunque tengan 0 h.
if ($esMesActual) {
    foreach ($proyectos as $proyecto) {
        if (empty($proyecto['inactivo'])) {
            $porProyecto[(int) $proyecto['id']] = [
                'nombre' => $proyecto['cliente'] . ' / ' . $proyecto['proyecto'],
                'centesimas' => 0
            ];
        }
    }
}

foreach ($registros as $indice => $registro) {
    $centesimasRegistro = mejrEscalar($registro['horas'], 2);
    $totalCentesimas += $centesimasRegistro;

    $porProyecto[(int) $registro['proyecto_id']] ??= [
        'nombre' => $registro['cliente'] . ' / ' . $registro['proyecto'],
        'centesimas' => 0
    ];
    $porProyecto[(int) $registro['proyecto_id']]['centesimas'] += $centesimasRegistro;

    $registros[$indice]['nombres'] = $cuenta['nombres'];
    $registros[$indice]['apellidos'] = $cuenta['apellidos'];
}

if (($_GET['exportar'] ?? '') === 'excel') {
    require __DIR__ . '/config/exportar-horas.php';
    descargarHorasExcel($registros, $totalCentesimas, $mesVista->format('m/Y'));
}

$mesAnterior = $mesVista->modify('-1 month')->format('Y-m');
$mesSiguiente = $esMesActual ? null : $mesVista->modify('+1 month')->format('Y-m');

$tituloPagina = 'Mis horas';
$seccion = 'mis-horas';
$parametrosIdioma = ($esMesActual ? [] : ['mes' => $mesVista->format('Y-m')])
    + ($editando && $_SERVER['REQUEST_METHOD'] !== 'POST' ? ['editar' => $editando['id']] : []);
$scriptsPagina = ['confirmar.js?v=1'];
require __DIR__ . '/config/plantilla-inicio.php';
?>
    <div class="mejr-titulo">
      <div>
        <h1 class="h4"><?= texto('Mis horas') ?></h1>
        <p class="text-secondary mb-0"><?= texto('Registra y revisa el trabajo de tus proyectos asignados.') ?></p>
      </div>
    </div>

    <div class="row g-4 mb-4">
      <div class="col-lg-8">
        <section id="registro" class="bg-white border rounded p-4 h-100">
          <h2 class="h5"><?= texto($editando ? 'Editar registro' : 'Registrar horas') ?></h2>

          <?php if ($error !== ''): ?>
            <div class="alert alert-danger" role="alert"><?= texto($error) ?></div>
          <?php endif; ?>

          <?php if (!$proyectos): ?>
            <p><?= texto('No tienes proyectos activos asignados. Consulta con administración.') ?></p>
          <?php endif; ?>

          <form method="post" action="mis-horas.php">
            <input type="hidden" name="csrf" value="<?= escapar($_SESSION['csrf']) ?>">
            <input type="hidden" name="accion" value="guardar">
            <?php if ($editando): ?>
              <input type="hidden" name="registro_id" value="<?= escapar($editando['id']) ?>">
            <?php else: ?>
              <input type="hidden" name="registro_token" value="<?= escapar($_SESSION['registro_token']) ?>">
            <?php endif; ?>

            <div class="row g-3">
              <div class="col-md-4">
                <label for="fecha" class="form-label"><?= texto('Fecha') ?></label>
                <input id="fecha" name="fecha" type="date" class="form-control"
                  min="<?= escapar($inicioMesActual) ?>" max="<?= escapar($hoy) ?>"
                  value="<?= escapar($datos['fecha']) ?>" required>
              </div>

              <div class="col-md-8">
                <label for="proyecto_id" class="form-label"><?= texto('Cliente / Proyecto') ?></label>
                <select id="proyecto_id" name="proyecto_id" class="form-select" required>
                  <option value=""><?= texto('Selecciona un proyecto') ?></option>
                  <?php foreach ($proyectos as $proyecto): ?>
                    <option value="<?= escapar($proyecto['id']) ?>"
                      <?= (string) $proyecto['id'] === $datos['proyecto_id'] ? 'selected' : '' ?>>
                      <?= escapar($proyecto['cliente'] . ' / ' . $proyecto['proyecto']) ?>
                      <?= !empty($proyecto['inactivo']) ? texto('(inactivo)') : '' ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="col-md-4">
                <label for="horas" class="form-label"><?= texto('Horas trabajadas') ?></label>
                <input id="horas" name="horas" type="text" inputmode="decimal" class="form-control"
                  maxlength="5" pattern="[0-9]{1,2}([.,][0-9]{1,2})?"
                  value="<?= escapar($datos['horas']) ?>" aria-describedby="ayuda-horas" required>
                <div id="ayuda-horas" class="form-text"><?= texto('Ejemplo: 2,5 equivale a 2 horas y 30 minutos.') ?></div>
              </div>

              <div class="col-12">
                <label for="descripcion" class="form-label"><?= texto('Descripción del trabajo') ?></label>
                <textarea id="descripcion" name="descripcion" class="form-control" rows="3"
                  maxlength="2000" required><?= escapar($datos['descripcion']) ?></textarea>
              </div>
            </div>

            <p class="small text-secondary mt-3">
              <?= texto('Registro habilitado para el mes actual, hasta hoy. Máximo total: 24 horas por día. Al cambiar de mes, el mes anterior se cierra.') ?>
            </p>

            <div class="d-flex flex-wrap gap-2">
              <button type="submit" class="btn btn-dark" <?= !$proyectos ? 'disabled' : '' ?>>
                <?= texto($editando ? 'Guardar cambios' : 'Guardar horas') ?>
              </button>
              <?php if ($editando): ?>
                <a href="mis-horas.php" class="btn btn-outline-secondary"><?= texto('Cancelar') ?></a>
              <?php endif; ?>
            </div>
          </form>
        </section>
      </div>

      <div class="col-lg-4">
        <section class="bg-white border rounded p-4 h-100" aria-labelledby="titulo-proyectos">
          <h2 id="titulo-proyectos" class="h5">
            <?= texto($esMesActual ? 'Tus proyectos este mes' : 'Tus proyectos en el mes') ?>
          </h2>

          <?php if (!$porProyecto): ?>
            <p class="text-secondary mb-0"><?= texto('Sin proyectos con horas en este mes.') ?></p>
          <?php else: ?>
            <table class="table table-sm align-middle mb-0">
              <tbody>
                <?php foreach ($porProyecto as $resumen): ?>
                  <tr>
                    <td><?= escapar($resumen['nombre']) ?></td>
                    <td class="mejr-numero"><?= escapar(mejrHoras($resumen['centesimas'])) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          <?php endif; ?>
        </section>
      </div>
    </div>

    <section class="bg-white border rounded p-4" aria-labelledby="titulo-registros">
      <div class="mejr-titulo">
        <div>
          <h2 id="titulo-registros" class="h5 mb-1">
            <?= texto('Mis registros') ?> · <?= escapar(mejrNombreMes($mesVista)) ?>
          </h2>
          <p class="mb-0">
            <?= texto('Total del mes:') ?>
            <strong><?= escapar(mejrHoras($totalCentesimas)) ?> <?= texto('horas') ?></strong>
          </p>
        </div>

        <div class="d-flex flex-wrap align-items-end gap-2">
          <a class="btn btn-sm btn-outline-secondary" href="mis-horas.php?mes=<?= escapar($mesAnterior) ?>">
            ‹ <?= texto('Mes anterior') ?>
          </a>
          <?php if ($mesSiguiente !== null): ?>
            <a class="btn btn-sm btn-outline-secondary" href="mis-horas.php?mes=<?= escapar($mesSiguiente) ?>">
              <?= texto('Mes siguiente') ?> ›
            </a>
          <?php endif; ?>

          <form method="get" action="mis-horas.php" class="d-flex gap-2">
            <label for="mes" class="visually-hidden"><?= texto('Mes') ?></label>
            <input id="mes" name="mes" type="month" class="form-control form-control-sm"
              max="<?= escapar($ahora->format('Y-m')) ?>" value="<?= escapar($mesVista->format('Y-m')) ?>" required>
            <button type="submit" class="btn btn-sm btn-outline-secondary"><?= texto('Ver mes') ?></button>
          </form>

          <a class="btn btn-sm btn-outline-dark"
            href="<?= escapar('mis-horas.php?' . http_build_query(['mes' => $mesVista->format('Y-m'), 'exportar' => 'excel'])) ?>">
            <?= texto('Descargar Excel') ?>
          </a>
        </div>
      </div>

      <?php if (!$esMesActual): ?>
        <p class="small text-secondary"><?= texto('Mes cerrado: los registros son de solo lectura.') ?></p>
      <?php endif; ?>

      <?php if (!$registros): ?>
        <p class="text-secondary mb-0"><?= texto('No hay registros en este mes.') ?></p>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table align-middle mb-0">
            <thead>
              <tr>
                <th scope="col"><?= texto('Fecha') ?></th>
                <th scope="col"><?= texto('Cliente / Proyecto') ?></th>
                <th scope="col" class="mejr-numero"><?= texto('Horas') ?></th>
                <th scope="col"><?= texto('Descripción') ?></th>
                <?php if ($esMesActual): ?>
                  <th scope="col" class="text-end"><?= texto('Acciones') ?></th>
                <?php endif; ?>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($registros as $registro): ?>
                <tr>
                  <td><?= escapar(mejrFecha($registro['fecha'])) ?></td>
                  <td><?= escapar($registro['cliente'] . ' / ' . $registro['proyecto']) ?></td>
                  <td class="mejr-numero"><?= escapar(mejrHoras(mejrEscalar($registro['horas'], 2))) ?></td>
                  <td class="text-break"><?= nl2br(escapar($registro['descripcion'])) ?></td>
                  <?php if ($esMesActual): ?>
                    <td class="mejr-col-acciones">
                      <div class="mejr-acciones">
                        <a class="btn btn-sm btn-outline-secondary"
                          href="mis-horas.php?editar=<?= escapar($registro['id']) ?>#registro"><?= texto('Editar') ?></a>
                        <form method="post" action="mis-horas.php"
                          data-confirmar="<?= texto('¿Borrar este registro? No se puede deshacer.') ?>">
                          <input type="hidden" name="csrf" value="<?= escapar($_SESSION['csrf']) ?>">
                          <input type="hidden" name="accion" value="borrar">
                          <input type="hidden" name="registro_id" value="<?= escapar($registro['id']) ?>">
                          <button type="submit" class="btn btn-sm btn-outline-danger"><?= texto('Borrar') ?></button>
                        </form>
                      </div>
                    </td>
                  <?php endif; ?>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </section>
<?php require __DIR__ . '/config/plantilla-fin.php'; ?>
