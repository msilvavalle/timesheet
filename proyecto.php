<?php
// Ficha del proyecto: nombre, consultores asignados, horas del mes y estado.
// Los proyectos se crean desde la ficha del cliente. El cliente de un
// proyecto no se cambia: movería horas ya facturadas a otro cliente.
$rolPagina = 'administrador';
require __DIR__ . '/config/arranque.php';
require_once __DIR__ . '/config/asignaciones.php';

$traducciones += [
    'Proyecto no válido.' => 'Invalid project.',
    'Proyecto no encontrado. Vuelve a Clientes y proyectos.' => 'Project not found. Return to Clients and projects.',
    'Acción no válida. Vuelve a abrir la ficha.' => 'Invalid action. Open the profile again.',
    'Datos' => 'Details',
    'Nombre del proyecto' => 'Project name',
    'El cliente de un proyecto no se puede cambiar.' => 'A project’s client cannot be changed.',
    'Escribe un nombre de proyecto de entre 1 y 150 caracteres, sin saltos de línea.' => 'Enter a project name between 1 and 150 characters, without line breaks.',
    'Ese proyecto ya está registrado para este cliente.' => 'That project is already registered for this client.',
    'No se pudo guardar el proyecto. Intenta nuevamente.' => 'The project could not be saved. Try again.',
    'Proyecto guardado.' => 'Project saved.',
    'Consultores asignados' => 'Assigned consultants',
    'Consultores asignados guardados.' => 'Assigned consultants saved.',
    'Marca los consultores que pueden registrar horas en este proyecto. Desmarcar no borra sus horas.' => 'Select the consultants who can log hours on this project. Unselecting does not delete their hours.',
    'Sin coincidencias.' => 'No matches.',
    'Nombre o apellido' => 'First or last name',
    'Buscar consultor' => 'Find a consultant',
    'No hay consultores activos. Créalos en Consultores.' => 'There are no active consultants. Create them in Consultants.',
    'Guardar consultores' => 'Save consultants',
    'El cliente está inactivo: los consultores no ven este proyecto aunque esté activo.' => 'The client is inactive: consultants cannot see this project even if it is active.',
    'Horas de' => 'Hours for',
    'Sin horas registradas este mes.' => 'No hours logged this month.',
    'Ver sus horas de los últimos 12 meses' => 'View its hours for the last 12 months',
    'Proyecto activado.' => 'Project activated.',
    'Proyecto desactivado. Los consultores ya no lo ven; las horas se conservan.' => 'Project deactivated. Consultants no longer see it; hours are preserved.',
    'No se realizó el cambio. Revisa el estado actual.' => 'No change was made. Check the current status.',
    'Desactivar un proyecto lo oculta a los consultores. Sus horas y asignaciones se conservan.' => 'Deactivating a project hides it from consultants. Its hours and assignments are preserved.',
    '¿Confirmas el cambio de estado de este proyecto? Si lo desactivas, los consultores dejarán de verlo.' => 'Confirm this project status change? If you deactivate it, consultants will stop seeing it.',
    'Secciones de la ficha' => 'Profile sections',
    'Sin especialidad' => 'No specialty'
];

$proyectoId = validarId($_GET['id'] ?? '');

if ($proyectoId === false) {
    http_response_code(400);
    exit(texto('Proyecto no válido.'));
}

$buscar = $pdo->prepare(
    'SELECT p.id, p.nombre, p.activo, p.cliente_id, c.nombre AS cliente, c.activo AS cliente_activo
     FROM proyectos p INNER JOIN clientes c ON c.id = p.cliente_id
     WHERE p.id = :id'
);
$buscar->execute(['id' => $proyectoId]);
$proyecto = $buscar->fetch();

if (!$proyecto) {
    http_response_code(404);
    exit(texto('Proyecto no encontrado. Vuelve a Clientes y proyectos.'));
}

$urlFicha = static fn(string $ancla = ''): string =>
    'proyecto.php?id=' . $proyectoId . ($ancla !== '' ? '#' . $ancla : '');

$nombre = $proyecto['nombre'];
$errores = ['datos' => '', 'consultores' => ''];
$marcados = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfValido()) {
        http_response_code(403);
        exit(texto('Solicitud no válida. Vuelve a abrir la página.'));
    }

    $accion = $_POST['accion'] ?? '';

    if (!is_string($accion) || !in_array($accion, ['datos', 'consultores', 'estado'], true)) {
        http_response_code(400);
        exit(texto('Acción no válida. Vuelve a abrir la ficha.'));
    }

    $adminId = (int) $_SESSION['usuario_id'];

    if ($accion === 'datos') {
        $nombre = is_string($_POST['nombre'] ?? null) ? trim($_POST['nombre']) : '';

        if (preg_match('/\A[^\p{C}]{1,150}\z/u', $nombre) !== 1) {
            $errores['datos'] = 'Escribe un nombre de proyecto de entre 1 y 150 caracteres, sin saltos de línea.';
        } else {
            try {
                $pdo->prepare('UPDATE proyectos SET nombre = :nombre WHERE id = :id')
                    ->execute(['nombre' => $nombre, 'id' => $proyectoId]);

                error_log('MEJR: administrador ' . $adminId . ' actualizó el proyecto ' . $proyectoId . '.');
                avisar('success', 'Proyecto guardado.');
                redirigir($urlFicha('datos'));
            } catch (PDOException $e) {
                if ((int) ($e->errorInfo[1] ?? 0) === 1062) {
                    $errores['datos'] = 'Ese proyecto ya está registrado para este cliente.';
                } else {
                    error_log($e->getMessage());
                    $errores['datos'] = 'No se pudo guardar el proyecto. Intenta nuevamente.';
                }
            }
        }
    }

    if ($accion === 'consultores') {
        $marcados = $_POST['consultores'] ?? [];
        $marcados = is_array($marcados) ? array_filter(array_map('validarId', $marcados)) : [];

        try {
            // Sin la lista de opciones mostradas se consideran todas las disponibles.
            $mostrados = isset($_POST['mostrados']) && is_array($_POST['mostrados'])
                ? array_filter(array_map('validarId', $_POST['mostrados']))
                : null;
            [$activadas, $desactivadas] = mejrGuardarAsignaciones($pdo, 'proyecto', $proyectoId, $marcados, $mostrados);
            error_log(
                'MEJR: administrador ' . $adminId . ' cambió las asignaciones del proyecto ' . $proyectoId .
                ' (+' . $activadas . ' / -' . $desactivadas . ').'
            );
            avisar('success', 'Consultores asignados guardados.');
            redirigir($urlFicha('consultores'));
        } catch (Throwable $e) {
            error_log('MEJR: no se pudieron guardar asignaciones de un proyecto.');
            $errores['consultores'] = 'No se pudo guardar el cambio. Inténtalo nuevamente.';
        }
    }

    if ($accion === 'estado') {
        $estadoPedido = $_POST['estado'] ?? '';

        if (!in_array($estadoPedido, ['activar', 'desactivar'], true)) {
            http_response_code(400);
            exit(texto('Acción no válida. Vuelve a abrir la ficha.'));
        }

        $nuevoEstado = $estadoPedido === 'activar' ? 1 : 0;
        $volver = ($_POST['volver'] ?? '') === 'lista' ? 'clientes.php' : $urlFicha('estado');

        try {
            $cambio = $pdo->prepare('UPDATE proyectos SET activo = :nuevo WHERE id = :id AND activo = :anterior');
            $cambio->execute(['nuevo' => $nuevoEstado, 'id' => $proyectoId, 'anterior' => 1 - $nuevoEstado]);

            if ($cambio->rowCount() === 1) {
                error_log(
                    'MEJR: administrador ' . $adminId .
                    ($nuevoEstado === 1 ? ' activó' : ' desactivó') . ' el proyecto ' . $proyectoId . '.'
                );
                avisar('success', $nuevoEstado === 1
                    ? 'Proyecto activado.'
                    : 'Proyecto desactivado. Los consultores ya no lo ven; las horas se conservan.');
            } else {
                avisar('warning', 'No se realizó el cambio. Revisa el estado actual.');
            }
        } catch (PDOException $e) {
            error_log('MEJR: no se pudo cambiar el estado de un proyecto.');
            avisar('danger', 'No se pudo guardar el cambio. Inténtalo nuevamente.');
        }

        redirigir($volver);
    }
}

$opciones = mejrOpcionesAsignables($pdo, 'proyecto');
$asignados = $marcados !== null
    ? array_map('intval', $marcados)
    : mejrAsignacionesActivas($pdo, 'proyecto', $proyectoId);

$ahora = mejrAhora();
$consultaHoras = $pdo->prepare(
    'SELECT u.id, u.nombres, u.apellidos, SUM(r.horas) AS horas
     FROM registros_horas r
     INNER JOIN usuarios u ON u.id = r.consultor_id
     WHERE r.proyecto_id = :id AND r.fecha >= :inicio AND r.fecha < :fin
     GROUP BY u.id, u.nombres, u.apellidos
     ORDER BY u.apellidos, u.nombres'
);
$consultaHoras->execute([
    'id' => $proyectoId,
    'inicio' => $ahora->format('Y-m-01'),
    'fin' => $ahora->modify('first day of next month')->format('Y-m-d')
]);
$horasMes = $consultaHoras->fetchAll();
$totalMes = array_sum(array_map(static fn($fila) => mejrEscalar($fila['horas'], 2), $horasMes));

$activo = (int) $proyecto['activo'] === 1;

$tituloPagina = 'Clientes y proyectos';
$seccion = 'clientes';
$parametrosIdioma = ['id' => $proyectoId];
$scriptsPagina = ['filtro.js?v=2', 'confirmar.js?v=1'];
require __DIR__ . '/config/plantilla-inicio.php';
?>
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="clientes.php"><?= texto('Clientes y proyectos') ?></a></li>
        <li class="breadcrumb-item">
          <a href="cliente.php?id=<?= escapar($proyecto['cliente_id']) ?>"><?= escapar($proyecto['cliente']) ?></a>
        </li>
        <li class="breadcrumb-item active" aria-current="page"><?= escapar($proyecto['nombre']) ?></li>
      </ol>
    </nav>

    <div class="mejr-titulo">
      <div>
        <h1 class="h4">
          <?= escapar($proyecto['nombre']) ?>
          <span class="badge <?= $activo ? 'text-bg-success' : 'text-bg-secondary' ?> align-middle">
            <?= texto($activo ? 'Activo' : 'Inactivo') ?>
          </span>
        </h1>
        <p class="text-secondary mb-0"><?= escapar($proyecto['cliente']) ?></p>
      </div>
      <a class="btn btn-outline-dark" href="<?= escapar('horas-admin.php?' . http_build_query(['proyecto_id' => $proyectoId] + mejrUltimos12Meses())) ?>"><?= texto('Ver sus horas de los últimos 12 meses') ?></a>
    </div>

    <?php if ((int) $proyecto['cliente_activo'] !== 1): ?>
      <p class="alert alert-warning"><?= texto('El cliente está inactivo: los consultores no ven este proyecto aunque esté activo.') ?></p>
    <?php endif; ?>

    <nav class="mejr-ficha-indice" aria-label="<?= texto('Secciones de la ficha') ?>">
      <a class="btn btn-sm btn-outline-secondary" href="#datos"><?= texto('Datos') ?></a>
      <a class="btn btn-sm btn-outline-secondary" href="#consultores"><?= texto('Consultores asignados') ?></a>
      <a class="btn btn-sm btn-outline-secondary" href="#horas"><?= texto('Horas del mes') ?></a>
      <a class="btn btn-sm btn-outline-secondary" href="#estado"><?= texto('Estado') ?></a>
    </nav>

    <div class="mejr-ficha">
      <section id="datos" class="bg-white border rounded p-4" aria-labelledby="titulo-datos">
        <h2 id="titulo-datos" class="h5"><?= texto('Datos') ?></h2>

        <?php if ($errores['datos'] !== ''): ?>
          <div class="alert alert-danger" role="alert"><?= texto($errores['datos']) ?></div>
        <?php endif; ?>

        <form method="post" action="<?= escapar($urlFicha('datos')) ?>">
          <input type="hidden" name="csrf" value="<?= escapar($_SESSION['csrf']) ?>">
          <input type="hidden" name="accion" value="datos">

          <div class="row g-3 align-items-end">
            <div class="col-md-8">
              <label for="nombre" class="form-label"><?= texto('Nombre del proyecto') ?></label>
              <input id="nombre" name="nombre" class="form-control" maxlength="150"
                value="<?= escapar($nombre) ?>" aria-describedby="ayuda-cliente" required>
              <div id="ayuda-cliente" class="form-text"><?= texto('El cliente de un proyecto no se puede cambiar.') ?></div>
            </div>
            <div class="col-md-4">
              <button type="submit" class="btn btn-dark"><?= texto('Guardar cambios') ?></button>
            </div>
          </div>
        </form>
      </section>

      <section id="consultores" class="bg-white border rounded p-4 mejr-asignacion" aria-labelledby="titulo-consultores">
        <h2 id="titulo-consultores" class="h5 mejr-asignacion-titulo"><span class="mejr-asignacion-icono" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round" stroke-linecap="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2 M13 7a4 4 0 1 1-8 0 4 4 0 0 1 8 0 M17 4a4 4 0 0 1 0 8 M22 21v-2a4 4 0 0 0-4-4"/></svg></span><?= texto('Consultores asignados') ?></h2>
        <p class="text-secondary"><?= texto('Marca los consultores que pueden registrar horas en este proyecto. Desmarcar no borra sus horas.') ?></p>

        <?php if ($errores['consultores'] !== ''): ?>
          <div class="alert alert-danger" role="alert"><?= texto($errores['consultores']) ?></div>
        <?php endif; ?>

        <?php if (!$opciones): ?>
          <p class="mb-0"><?= texto('No hay consultores activos. Créalos en Consultores.') ?></p>
        <?php else: ?>
          <form method="post" action="<?= escapar($urlFicha('consultores')) ?>">
            <input type="hidden" name="csrf" value="<?= escapar($_SESSION['csrf']) ?>">
            <input type="hidden" name="accion" value="consultores">

            <div class="mb-3" data-filtro-caja hidden>
              <label for="buscar-consultor" class="form-label"><?= texto('Buscar consultor') ?></label>
              <input id="buscar-consultor" type="search" class="form-control" data-filtro="lista-consultores" aria-controls="lista-consultores" autocomplete="off" placeholder="<?= texto('Nombre o apellido') ?>">
            </div>

            <p class="small text-secondary mb-2" data-filtro-vacio role="status" hidden><?= texto('Sin coincidencias.') ?></p>
            <div id="lista-consultores" class="mejr-casillas">
              <?php foreach ($opciones as $opcion): ?>
                <div class="form-check mejr-asignacion-opcion" data-filtro-item data-filtro-texto="<?= escapar($opcion['nombre']) ?>">
                  <input type="hidden" name="mostrados[]" value="<?= escapar($opcion['id']) ?>">
                  <input class="form-check-input" type="checkbox" name="consultores[]"
                    id="consultor-<?= escapar($opcion['id']) ?>" value="<?= escapar($opcion['id']) ?>"
                    <?= in_array((int) $opcion['id'], $asignados, true) ? 'checked' : '' ?>>
                  <label class="form-check-label" for="consultor-<?= escapar($opcion['id']) ?>">
                    <?= escapar($opcion['nombre']) ?>
                    <span class="small text-secondary">
                      · <?= trim((string) $opcion['grupo']) !== '' ? escapar($opcion['grupo']) : texto('Sin especialidad') ?>
                    </span>
                  </label>
                </div>
              <?php endforeach; ?>
            </div>

            <button type="submit" class="btn btn-dark mt-3"><?= texto('Guardar consultores') ?></button>
          </form>
        <?php endif; ?>
      </section>

      <section id="horas" class="bg-white border rounded p-4" aria-labelledby="titulo-horas">
        <h2 id="titulo-horas" class="h5">
          <?= texto('Horas de') ?> <?= escapar(mejrNombreMes($ahora)) ?>: <?= escapar(mejrHoras($totalMes)) ?>
        </h2>

        <?php if (!$horasMes): ?>
          <p class="text-secondary mb-0"><?= texto('Sin horas registradas este mes.') ?></p>
        <?php else: ?>
          <table class="table table-sm align-middle mb-0">
            <thead>
              <tr>
                <th scope="col"><?= texto('Consultor') ?></th>
                <th scope="col" class="mejr-numero"><?= texto('Horas') ?></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($horasMes as $fila): ?>
                <tr>
                  <td><a href="consultor.php?id=<?= (int) $fila['id'] ?>"><?= escapar($fila['nombres'] . ' ' . $fila['apellidos']) ?></a></td>
                  <td class="mejr-numero"><?= escapar(mejrHoras(mejrEscalar($fila['horas'], 2))) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </section>

      <section id="estado" class="bg-white border rounded p-4" aria-labelledby="titulo-estado">
        <h2 id="titulo-estado" class="h5"><?= texto('Estado') ?></h2>
        <p class="text-secondary"><?= texto('Desactivar un proyecto lo oculta a los consultores. Sus horas y asignaciones se conservan.') ?></p>

        <form method="post" action="<?= escapar($urlFicha('estado')) ?>"
          data-confirmar="<?= texto('¿Confirmas el cambio de estado de este proyecto? Si lo desactivas, los consultores dejarán de verlo.') ?>">
          <input type="hidden" name="csrf" value="<?= escapar($_SESSION['csrf']) ?>">
          <input type="hidden" name="accion" value="estado">
          <input type="hidden" name="estado" value="<?= $activo ? 'desactivar' : 'activar' ?>">
          <button type="submit" class="btn <?= $activo ? 'btn-outline-danger' : 'btn-dark' ?>">
            <?= texto($activo ? 'Desactivar' : 'Activar') ?>
          </button>
        </form>
      </section>
    </div>
<?php require __DIR__ . '/config/plantilla-fin.php'; ?>
