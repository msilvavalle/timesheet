<?php
// Ficha del cliente. Sin ?id= es el alta; con ?id= reúne datos, proyectos
// (con alta de proyecto en la misma página) y estado.
$rolPagina = 'administrador';
require __DIR__ . '/config/arranque.php';
require_once __DIR__ . '/config/tarifas.php';

$traducciones += [
    'Nuevo cliente' => 'New client',
    'Nuevo proyecto' => 'New project',
    'Cliente no válido.' => 'Invalid client.',
    'Cliente no encontrado. Vuelve a Clientes y proyectos.' => 'Client not found. Return to Clients and projects.',
    'Acción no válida. Vuelve a abrir la ficha.' => 'Invalid action. Open the profile again.',
    'Datos' => 'Details',
    'Nombre del cliente' => 'Client name',
    'Nombre del proyecto' => 'Project name',
    'Crear cliente' => 'Create client',
    'Agregar proyecto' => 'Add project',
    'Escribe un nombre de cliente de entre 1 y 150 caracteres, sin saltos de línea.' => 'Enter a client name between 1 and 150 characters, without line breaks.',
    'Escribe un nombre de proyecto de entre 1 y 150 caracteres, sin saltos de línea.' => 'Enter a project name between 1 and 150 characters, without line breaks.',
    'Ese cliente ya está registrado.' => 'That client is already registered.',
    'El cliente está inactivo. Actívalo antes de agregar proyectos.' => 'The client is inactive. Activate it before adding projects.',
    'Ese proyecto ya está registrado para este cliente.' => 'That project is already registered for this client.',
    'No se pudo guardar el cliente. Intenta nuevamente.' => 'The client could not be saved. Try again.',
    'No se pudo guardar el proyecto. Intenta nuevamente.' => 'The project could not be saved. Try again.',
    'Cliente creado. Agrega su primer proyecto:' => 'Client created. Add the first project for:',
    'Cliente guardado.' => 'Client saved.',
    'Proyecto creado. Asígnale consultores:' => 'Project created. Assign consultants to:',
    'Cliente activado.' => 'Client activated.',
    'Cliente desactivado. Sus consultores ya no ven sus proyectos; las horas se conservan.' => 'Client deactivated. Consultants no longer see its projects; hours are preserved.',
    'No se realizó el cambio. Revisa el estado actual.' => 'No change was made. Check the current status.',
    'Consultores' => 'Consultants',
    'Horas acumuladas' => 'Total hours',
    'Sin proyectos todavía.' => 'No projects yet.',
    'Desactivar un cliente oculta sus proyectos a los consultores. Sus horas y asignaciones se conservan.' => 'Deactivating a client hides its projects from consultants. Its hours and assignments are preserved.',
    '¿Confirmas el cambio de estado de este cliente? Si lo desactivas, sus consultores dejarán de ver sus proyectos.' => 'Confirm this client status change? If you deactivate it, consultants will stop seeing its projects.',
    'Secciones de la ficha' => 'Profile sections',
    'Ver todas sus horas' => 'View all its hours'
];

$idTexto = $_GET['id'] ?? null;
$clienteId = $idTexto === null ? null : validarId($idTexto);

if ($clienteId === false) {
    http_response_code(400);
    exit(texto('Cliente no válido.'));
}

$cliente = null;

if ($clienteId !== null) {
    $buscar = $pdo->prepare('SELECT id, nombre, activo FROM clientes WHERE id = :id');
    $buscar->execute(['id' => $clienteId]);
    $cliente = $buscar->fetch();

    if (!$cliente) {
        http_response_code(404);
        exit(texto('Cliente no encontrado. Vuelve a Clientes y proyectos.'));
    }
}

$urlFicha = static fn(string $ancla = ''): string =>
    'cliente.php?id=' . $clienteId . ($ancla !== '' ? '#' . $ancla : '');

$nombre = $cliente ? $cliente['nombre'] : '';
$nombreProyecto = '';
$errores = ['datos' => '', 'proyectos' => ''];
$errorTarifa = '';
$valorTarifa = '';
$desdeTarifa = '';
$nombreValido = static fn(string $texto): bool => preg_match('/\A[^\p{C}]{1,150}\z/u', $texto) === 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfValido()) {
        http_response_code(403);
        exit(texto('Solicitud no válida. Vuelve a abrir la página.'));
    }

    $accion = $_POST['accion'] ?? '';
    $permitidas = $cliente ? ['datos', 'proyecto_nuevo', 'tarifa', 'tarifa_borrar', 'estado'] : ['crear'];

    if (!is_string($accion) || !in_array($accion, $permitidas, true)) {
        http_response_code(400);
        exit(texto('Acción no válida. Vuelve a abrir la ficha.'));
    }

    $adminId = (int) $_SESSION['usuario_id'];

    if ($accion === 'crear' || $accion === 'datos') {
        $nombre = is_string($_POST['nombre'] ?? null) ? trim($_POST['nombre']) : '';

        if (!$nombreValido($nombre)) {
            $errores['datos'] = 'Escribe un nombre de cliente de entre 1 y 150 caracteres, sin saltos de línea.';
        } else {
            try {
                if ($accion === 'crear') {
                    $pdo->prepare('INSERT INTO clientes (nombre, activo) VALUES (:nombre, 1)')
                        ->execute(['nombre' => $nombre]);
                    $nuevoId = (int) $pdo->lastInsertId();

                    error_log('MEJR: administrador ' . $adminId . ' creó el cliente ' . $nuevoId . '.');
                    avisar('success', 'Cliente creado. Agrega su primer proyecto:', $nombre);
                    redirigir('cliente.php?id=' . $nuevoId . '#proyectos');
                }

                $pdo->prepare('UPDATE clientes SET nombre = :nombre WHERE id = :id')
                    ->execute(['nombre' => $nombre, 'id' => $clienteId]);

                error_log('MEJR: administrador ' . $adminId . ' actualizó el cliente ' . $clienteId . '.');
                avisar('success', 'Cliente guardado.');
                redirigir($urlFicha('datos'));
            } catch (PDOException $e) {
                if ((int) ($e->errorInfo[1] ?? 0) === 1062) {
                    $errores['datos'] = 'Ese cliente ya está registrado.';
                } else {
                    error_log($e->getMessage());
                    $errores['datos'] = 'No se pudo guardar el cliente. Intenta nuevamente.';
                }
            }
        }
    }

    if ($accion === 'proyecto_nuevo') {
        $nombreProyecto = is_string($_POST['nombre_proyecto'] ?? null) ? trim($_POST['nombre_proyecto']) : '';

        if ((int) $cliente['activo'] !== 1) {
            $errores['proyectos'] = 'El cliente está inactivo. Actívalo antes de agregar proyectos.';
        } elseif (!$nombreValido($nombreProyecto)) {
            $errores['proyectos'] = 'Escribe un nombre de proyecto de entre 1 y 150 caracteres, sin saltos de línea.';
        } else {
            try {
                $pdo->prepare('INSERT INTO proyectos (cliente_id, nombre, activo) VALUES (:cliente, :nombre, 1)')
                    ->execute(['cliente' => $clienteId, 'nombre' => $nombreProyecto]);
                $nuevoId = (int) $pdo->lastInsertId();

                error_log('MEJR: administrador ' . $adminId . ' creó el proyecto ' . $nuevoId . '.');
                avisar('success', 'Proyecto creado. Asígnale consultores:', $nombreProyecto);
                redirigir('proyecto.php?id=' . $nuevoId . '#consultores');
            } catch (PDOException $e) {
                if ((int) ($e->errorInfo[1] ?? 0) === 1062) {
                    $errores['proyectos'] = 'Ese proyecto ya está registrado para este cliente.';
                } else {
                    error_log($e->getMessage());
                    $errores['proyectos'] = 'No se pudo guardar el proyecto. Intenta nuevamente.';
                }
            }
        }
    }

    if ($accion === 'tarifa' || $accion === 'tarifa_borrar') {
        $valorTarifa = is_string($_POST['valor'] ?? null) ? $_POST['valor'] : '';
        $desdeTarifa = is_string($_POST['desde'] ?? null) ? $_POST['desde'] : '';
        $errorTarifa = mejrProcesarTarifa($pdo, 'cliente', $clienteId, $accion, $urlFicha('tarifa'));
    }

    if ($accion === 'estado') {
        $nuevoEstado = ($_POST['estado'] ?? '') === 'activar' ? 1 : 0;
        $volver = ($_POST['volver'] ?? '') === 'lista' ? 'clientes.php' : $urlFicha('estado');

        try {
            $cambio = $pdo->prepare('UPDATE clientes SET activo = :nuevo WHERE id = :id AND activo = :anterior');
            $cambio->execute(['nuevo' => $nuevoEstado, 'id' => $clienteId, 'anterior' => 1 - $nuevoEstado]);

            if ($cambio->rowCount() === 1) {
                error_log(
                    'MEJR: administrador ' . $adminId .
                    ($nuevoEstado === 1 ? ' activó' : ' desactivó') . ' el cliente ' . $clienteId . '.'
                );
                avisar('success', $nuevoEstado === 1
                    ? 'Cliente activado.'
                    : 'Cliente desactivado. Sus consultores ya no ven sus proyectos; las horas se conservan.');
            } else {
                avisar('warning', 'No se realizó el cambio. Revisa el estado actual.');
            }
        } catch (PDOException $e) {
            error_log('MEJR: no se pudo cambiar el estado de un cliente.');
            avisar('danger', 'No se pudo guardar el cambio. Inténtalo nuevamente.');
        }

        redirigir($volver);
    }
}

$proyectos = [];

if ($cliente) {
    $consulta = $pdo->prepare(
        'SELECT p.id, p.nombre, p.activo,
                (SELECT COUNT(*) FROM proyecto_consultor pc
                  INNER JOIN usuarios u ON u.id = pc.consultor_id
                  WHERE pc.proyecto_id = p.id AND pc.activo = 1 AND u.activo = 1) AS consultores,
                (SELECT COALESCE(SUM(r.horas), 0) FROM registros_horas r WHERE r.proyecto_id = p.id) AS horas
         FROM proyectos p
         WHERE p.cliente_id = :id
         ORDER BY p.activo DESC, p.nombre, p.id'
    );
    $consulta->execute(['id' => $clienteId]);
    $proyectos = $consulta->fetchAll();
}

$activo = $cliente && (int) $cliente['activo'] === 1;
$tipoTarifa = 'cliente';
$historialTarifas = $cliente ? mejrHistorialTarifas($pdo, 'cliente', $clienteId) : [];

$tituloPagina = $cliente ? 'Clientes y proyectos' : 'Nuevo cliente';
$seccion = 'clientes';
$parametrosIdioma = $cliente ? ['id' => $clienteId] : [];
$scriptsPagina = ['confirmar.js?v=1'];
require __DIR__ . '/config/plantilla-inicio.php';
?>
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="clientes.php"><?= texto('Clientes y proyectos') ?></a></li>
        <li class="breadcrumb-item active" aria-current="page">
          <?= $cliente ? escapar($cliente['nombre']) : texto('Nuevo cliente') ?>
        </li>
      </ol>
    </nav>

    <div class="mejr-titulo">
      <div>
        <h1 class="h4">
          <?= $cliente ? escapar($cliente['nombre']) : texto('Nuevo cliente') ?>
          <?php if ($cliente): ?>
            <span class="badge <?= $activo ? 'text-bg-success' : 'text-bg-secondary' ?> align-middle">
              <?= texto($activo ? 'Activo' : 'Inactivo') ?>
            </span>
          <?php endif; ?>
        </h1>
      </div>
      <?php if ($cliente): ?>
        <a class="btn btn-outline-dark" href="horas-admin.php?cliente_id=<?= escapar($clienteId) ?>"><?= texto('Ver todas sus horas') ?></a>
      <?php endif; ?>
    </div>

    <?php if ($cliente): ?>
      <nav class="mejr-ficha-indice" aria-label="<?= texto('Secciones de la ficha') ?>">
        <a class="btn btn-sm btn-outline-secondary" href="#datos"><?= texto('Datos') ?></a>
        <a class="btn btn-sm btn-outline-secondary" href="#proyectos"><?= texto('Proyectos') ?></a>
        <a class="btn btn-sm btn-outline-secondary" href="#tarifa"><?= texto('Tarifa') ?></a>
        <a class="btn btn-sm btn-outline-secondary" href="#estado"><?= texto('Estado') ?></a>
      </nav>
    <?php endif; ?>

    <div class="mejr-ficha">
      <section id="datos" class="bg-white border rounded p-4" aria-labelledby="titulo-datos">
        <h2 id="titulo-datos" class="h5"><?= texto('Datos') ?></h2>

        <?php if ($errores['datos'] !== ''): ?>
          <div class="alert alert-danger" role="alert"><?= texto($errores['datos']) ?></div>
        <?php endif; ?>

        <form method="post" action="<?= escapar($cliente ? $urlFicha('datos') : 'cliente.php') ?>">
          <input type="hidden" name="csrf" value="<?= escapar($_SESSION['csrf']) ?>">
          <input type="hidden" name="accion" value="<?= $cliente ? 'datos' : 'crear' ?>">

          <div class="row g-3 align-items-end">
            <div class="col-md-8">
              <label for="nombre" class="form-label"><?= texto('Nombre del cliente') ?></label>
              <input id="nombre" name="nombre" class="form-control" maxlength="150"
                value="<?= escapar($nombre) ?>" required>
            </div>
            <div class="col-md-4 d-flex gap-2">
              <button type="submit" class="btn btn-dark"><?= texto($cliente ? 'Guardar cambios' : 'Crear cliente') ?></button>
              <a href="clientes.php" class="btn btn-outline-secondary"><?= texto('Cancelar') ?></a>
            </div>
          </div>
        </form>
      </section>

      <?php if ($cliente): ?>
        <section id="proyectos" class="bg-white border rounded p-4" aria-labelledby="titulo-proyectos">
          <h2 id="titulo-proyectos" class="h5"><?= texto('Proyectos') ?></h2>

          <?php if ($errores['proyectos'] !== ''): ?>
            <div class="alert alert-danger" role="alert"><?= texto($errores['proyectos']) ?></div>
          <?php endif; ?>

          <form method="post" action="<?= escapar($urlFicha('proyectos')) ?>" class="row g-2 align-items-end mb-3">
            <input type="hidden" name="csrf" value="<?= escapar($_SESSION['csrf']) ?>">
            <input type="hidden" name="accion" value="proyecto_nuevo">
            <div class="col-md-8">
              <label for="nombre_proyecto" class="form-label"><?= texto('Nuevo proyecto') ?></label>
              <input id="nombre_proyecto" name="nombre_proyecto" class="form-control" maxlength="150"
                placeholder="<?= texto('Nombre del proyecto') ?>" value="<?= escapar($nombreProyecto) ?>" required>
            </div>
            <div class="col-md-4">
              <button type="submit" class="btn btn-dark"><?= texto('Agregar proyecto') ?></button>
            </div>
          </form>

          <?php if (!$proyectos): ?>
            <p class="text-secondary mb-0"><?= texto('Sin proyectos todavía.') ?></p>
          <?php else: ?>
            <div class="table-responsive">
              <table class="table align-middle mb-0">
                <thead>
                  <tr>
                    <th scope="col"><?= texto('Proyecto') ?></th>
                    <th scope="col" class="mejr-numero"><?= texto('Consultores') ?></th>
                    <th scope="col" class="mejr-numero"><?= texto('Horas acumuladas') ?></th>
                    <th scope="col"><?= texto('Estado') ?></th>
                    <th scope="col" class="text-end"><?= texto('Acciones') ?></th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($proyectos as $proyecto): ?>
                    <?php $fichaProyecto = 'proyecto.php?id=' . (int) $proyecto['id']; ?>
                    <tr>
                      <td><a href="<?= escapar($fichaProyecto) ?>"><?= escapar($proyecto['nombre']) ?></a></td>
                      <td class="mejr-numero"><?= (int) $proyecto['consultores'] ?></td>
                      <td class="mejr-numero"><?= escapar(mejrHoras(mejrEscalar($proyecto['horas'], 2))) ?></td>
                      <td>
                        <span class="badge <?= (int) $proyecto['activo'] === 1 ? 'text-bg-success' : 'text-bg-secondary' ?>">
                          <?= texto((int) $proyecto['activo'] === 1 ? 'Activo' : 'Inactivo') ?>
                        </span>
                      </td>
                      <td class="mejr-col-acciones">
                        <div class="mejr-acciones">
                          <a class="btn btn-sm btn-outline-secondary" href="<?= escapar($fichaProyecto . '#datos') ?>"><?= texto('Editar') ?></a>
                          <a class="btn btn-sm btn-outline-secondary" href="<?= escapar($fichaProyecto . '#consultores') ?>"><?= texto('Consultores') ?></a>
                          <a class="btn btn-sm btn-outline-secondary" href="horas-admin.php?proyecto_id=<?= (int) $proyecto['id'] ?>"><?= texto('Horas') ?></a>
                        </div>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </section>

<?php require __DIR__ . '/config/seccion-tarifa.php'; ?>

        <section id="estado" class="bg-white border rounded p-4" aria-labelledby="titulo-estado">
          <h2 id="titulo-estado" class="h5"><?= texto('Estado') ?></h2>
          <p class="text-secondary"><?= texto('Desactivar un cliente oculta sus proyectos a los consultores. Sus horas y asignaciones se conservan.') ?></p>

          <form method="post" action="<?= escapar($urlFicha('estado')) ?>"
            data-confirmar="<?= texto('¿Confirmas el cambio de estado de este cliente? Si lo desactivas, sus consultores dejarán de ver sus proyectos.') ?>">
            <input type="hidden" name="csrf" value="<?= escapar($_SESSION['csrf']) ?>">
            <input type="hidden" name="accion" value="estado">
            <input type="hidden" name="estado" value="<?= $activo ? 'desactivar' : 'activar' ?>">
            <button type="submit" class="btn <?= $activo ? 'btn-outline-danger' : 'btn-dark' ?>">
              <?= texto($activo ? 'Desactivar' : 'Activar') ?>
            </button>
          </form>
        </section>
      <?php endif; ?>
    </div>
<?php require __DIR__ . '/config/plantilla-fin.php'; ?>
