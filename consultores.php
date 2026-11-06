<?php
// Lista de consultores: toda la información en una tabla y las acciones de
// cada uno en su fila. Reemplaza a usuarios.php y al listado de asignaciones.php.
$rolPagina = 'administrador';
require __DIR__ . '/config/arranque.php';

$traducciones += [
    'Administra el acceso, los proyectos y las horas de cada consultor.' => 'Manage each consultant’s access, projects and hours.',
    'Nuevo consultor' => 'New consultant',
    'Buscar consultor' => 'Find a consultant',
    'Nombre, apellido, correo, usuario o especialidad' => 'First name, last name, email, username or specialty',
    'La búsqueda no es válida. Usa un máximo de 254 caracteres.' => 'Invalid search. Use a maximum of 254 characters.',
    'Acceso' => 'Access',
    'Correo pendiente' => 'Email required',
    'Sin proyectos' => 'No projects',
    'Clave' => 'Password',
    'Resultados' => 'Results',
    'No se encontraron consultores para esta búsqueda.' => 'No consultants match this search.',
    'Todavía no hay consultores. Crea el primero con Nuevo consultor.' => 'There are no consultants yet. Create the first one with New consultant.',
    'Completa el correo de los consultores marcados como Correo pendiente para que puedan iniciar sesión.' => 'Enter an email address for consultants marked Email required so they can sign in.',
    '¿Confirmas el cambio de acceso de este consultor? Sus horas se conservarán.' => 'Confirm this change to the consultant’s access? Their hours will be preserved.'
];

$busqueda = $_GET['buscar'] ?? '';

if (!is_string($busqueda) || preg_match('/\A[^\p{C}]{0,254}\z/u', trim($busqueda)) !== 1) {
    http_response_code(400);
    exit(texto('La búsqueda no es válida. Usa un máximo de 254 caracteres.'));
}

$busqueda = trim($busqueda);

$sql = "SELECT id, nombres, apellidos, especialidad, usuario, correo, activo
        FROM usuarios
        WHERE rol = 'consultor'";
$parametros = [];

foreach (preg_split('/\s+/u', $busqueda, -1, PREG_SPLIT_NO_EMPTY) as $indice => $palabra) {
    $patron = '%' . strtr($palabra, ['!' => '!!', '%' => '!%', '_' => '!_']) . '%';
    $condicion = [];

    foreach (['nombres', 'apellidos', 'usuario', 'correo', 'especialidad'] as $columna) {
        $condicion[] = "$columna LIKE :{$columna}_$indice ESCAPE '!'";
        $parametros["{$columna}_$indice"] = $patron;
    }

    $sql .= ' AND (' . implode(' OR ', $condicion) . ')';
}

$sql .= ' ORDER BY activo DESC, apellidos, nombres, id';
$consulta = $pdo->prepare($sql);
$consulta->execute($parametros);
$consultores = $consulta->fetchAll();

// Proyectos activos asignados y horas del mes, de una vez para toda la tabla.
$proyectosPorConsultor = [];

foreach ($pdo->query(
    'SELECT pc.consultor_id, c.nombre AS cliente, p.nombre AS proyecto
     FROM proyecto_consultor pc
     INNER JOIN proyectos p ON p.id = pc.proyecto_id
     INNER JOIN clientes c ON c.id = p.cliente_id
     WHERE pc.activo = 1 AND p.activo = 1 AND c.activo = 1
     ORDER BY c.nombre, p.nombre'
) as $fila) {
    $proyectosPorConsultor[(int) $fila['consultor_id']][] = $fila['cliente'] . ' / ' . $fila['proyecto'];
}

$ahora = mejrAhora();
$horasPorConsultor = [];
$consultaHoras = $pdo->prepare(
    'SELECT consultor_id, SUM(horas) AS horas
     FROM registros_horas
     WHERE fecha >= :inicio AND fecha < :fin
     GROUP BY consultor_id'
);
$consultaHoras->execute([
    'inicio' => $ahora->format('Y-m-01'),
    'fin' => $ahora->modify('first day of next month')->format('Y-m-d')
]);

foreach ($consultaHoras as $fila) {
    $horasPorConsultor[(int) $fila['consultor_id']] = mejrEscalar($fila['horas'], 2);
}

$faltaCorreo = (bool) array_filter($consultores, static fn($fila) => empty($fila['correo']));

$tituloPagina = 'Consultores';
$seccion = 'consultores';
$parametrosIdioma = $busqueda !== '' ? ['buscar' => $busqueda] : [];
$scriptsPagina = ['confirmar.js?v=1'];
require __DIR__ . '/config/plantilla-inicio.php';
?>
    <div class="mejr-titulo">
      <div>
        <h1 class="h4"><?= texto('Consultores') ?></h1>
        <p class="text-secondary mb-0"><?= texto('Administra el acceso, los proyectos y las horas de cada consultor.') ?></p>
      </div>
      <a href="consultor.php" class="btn btn-dark"><?= texto('Nuevo consultor') ?></a>
    </div>

    <section class="bg-white border rounded p-4">
      <form method="get" action="consultores.php" class="row g-2 align-items-end mb-3" role="search">
        <div class="col-12 col-md-7 col-lg-6">
          <label for="buscar" class="form-label"><?= texto('Buscar consultor') ?></label>
          <input id="buscar" name="buscar" type="search" class="form-control" maxlength="254"
            value="<?= escapar($busqueda) ?>" autocomplete="off"
            placeholder="<?= texto('Nombre, apellido, correo, usuario o especialidad') ?>">
        </div>
        <div class="col-auto">
          <button type="submit" class="btn btn-outline-dark"><?= texto('Buscar') ?></button>
        </div>
        <?php if ($busqueda !== ''): ?>
          <div class="col-auto">
            <a href="consultores.php" class="btn btn-outline-secondary"><?= texto('Limpiar') ?></a>
          </div>
        <?php endif; ?>
      </form>

      <p class="small text-secondary"><?= texto('Resultados') ?>: <strong><?= count($consultores) ?></strong></p>

      <?php if ($faltaCorreo): ?>
        <p class="alert alert-warning" role="status">
          <?= texto('Completa el correo de los consultores marcados como Correo pendiente para que puedan iniciar sesión.') ?>
        </p>
      <?php endif; ?>

      <?php if (!$consultores): ?>
        <p class="text-secondary mb-0">
          <?= texto($busqueda !== ''
              ? 'No se encontraron consultores para esta búsqueda.'
              : 'Todavía no hay consultores. Crea el primero con Nuevo consultor.') ?>
        </p>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table align-middle mb-0">
            <thead>
              <tr>
                <th scope="col"><?= texto('Consultor') ?></th>
                <th scope="col"><?= texto('Acceso') ?></th>
                <th scope="col"><?= texto('Proyectos') ?></th>
                <th scope="col" class="mejr-numero"><?= texto('Horas del mes') ?></th>
                <th scope="col"><?= texto('Estado') ?></th>
                <th scope="col" class="text-end"><?= texto('Acciones') ?></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($consultores as $consultor): ?>
                <?php
                $id = (int) $consultor['id'];
                $activo = (int) $consultor['activo'] === 1;
                $nombre = $consultor['nombres'] . ' ' . $consultor['apellidos'];
                $ficha = 'consultor.php?id=' . $id;
                ?>
                <tr>
                  <td>
                    <a href="<?= escapar($ficha) ?>" class="fw-semibold"><?= escapar($nombre) ?></a>
                    <?php if (trim((string) $consultor['especialidad']) !== ''): ?>
                      <div class="small text-secondary"><?= escapar($consultor['especialidad']) ?></div>
                    <?php endif; ?>
                  </td>
                  <td class="text-break">
                    <?php if (empty($consultor['correo'])): ?>
                      <span class="badge text-bg-warning"><?= texto('Correo pendiente') ?></span>
                    <?php else: ?>
                      <?= escapar($consultor['correo']) ?>
                    <?php endif; ?>
                    <div class="small text-secondary"><?= escapar($consultor['usuario']) ?></div>
                  </td>
                  <td>
                    <?php if (empty($proyectosPorConsultor[$id])): ?>
                      <span class="small text-secondary"><?= texto('Sin proyectos') ?></span>
                    <?php else: ?>
                      <div class="mejr-etiquetas">
                        <?php foreach ($proyectosPorConsultor[$id] as $proyecto): ?>
                          <span class="badge"><?= escapar($proyecto) ?></span>
                        <?php endforeach; ?>
                      </div>
                    <?php endif; ?>
                  </td>
                  <td class="mejr-numero"><?= escapar(mejrHoras($horasPorConsultor[$id] ?? 0)) ?></td>
                  <td>
                    <span class="badge <?= $activo ? 'text-bg-success' : 'text-bg-secondary' ?>">
                      <?= texto($activo ? 'Activo' : 'Inactivo') ?>
                    </span>
                  </td>
                  <td class="mejr-col-acciones">
                    <div class="mejr-acciones">
                      <a class="btn btn-sm btn-outline-secondary" href="<?= escapar($ficha . '#datos') ?>"
                        aria-label="<?= escapar(mejrTraducir('Editar') . ': ' . $nombre) ?>"><?= texto('Editar') ?></a>
                      <a class="btn btn-sm btn-outline-secondary" href="<?= escapar($ficha . '#proyectos') ?>"
                        aria-label="<?= escapar(mejrTraducir('Proyectos') . ': ' . $nombre) ?>"><?= texto('Proyectos') ?></a>
                      <a class="btn btn-sm btn-outline-secondary" href="horas-admin.php?consultor_id=<?= $id ?>"
                        aria-label="<?= escapar(mejrTraducir('Horas') . ': ' . $nombre) ?>"><?= texto('Horas') ?></a>
                      <a class="btn btn-sm btn-outline-secondary" href="<?= escapar($ficha . '#clave') ?>"
                        aria-label="<?= escapar(mejrTraducir('Clave') . ': ' . $nombre) ?>"><?= texto('Clave') ?></a>
                      <form method="post" action="<?= escapar($ficha) ?>"
                        data-confirmar="<?= texto('¿Confirmas el cambio de acceso de este consultor? Sus horas se conservarán.') ?>">
                        <input type="hidden" name="csrf" value="<?= escapar($_SESSION['csrf']) ?>">
                        <input type="hidden" name="accion" value="estado">
                        <input type="hidden" name="estado" value="<?= $activo ? 'desactivar' : 'activar' ?>">
                        <input type="hidden" name="volver" value="lista">
                        <button type="submit" class="btn btn-sm <?= $activo ? 'btn-outline-danger' : 'btn-outline-dark' ?>"
                          aria-label="<?= escapar(mejrTraducir($activo ? 'Desactivar' : 'Activar') . ': ' . $nombre) ?>">
                          <?= texto($activo ? 'Desactivar' : 'Activar') ?>
                        </button>
                      </form>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </section>
<?php require __DIR__ . '/config/plantilla-fin.php'; ?>
