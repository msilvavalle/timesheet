<?php
// Clientes y sus proyectos en una sola tabla agrupada, con las acciones de
// cada fila. Reemplaza al alta de clientes.php y a proyectos.php.
$rolPagina = 'administrador';
require __DIR__ . '/config/arranque.php';

$traducciones += [
    'Administra clientes, sus proyectos y quién trabaja en cada uno.' => 'Manage clients, their projects and who works on each one.',
    'Nuevo cliente' => 'New client',
    'Nuevo proyecto' => 'New project',
    'Cliente / Proyecto' => 'Client / Project',
    'Detalle' => 'Details',
    'Horas acumuladas' => 'Total hours',
    'Consultores' => 'Consultants',
    'proyectos activos' => 'active projects',
    'Sin consultores' => 'No consultants',
    'Sin proyectos todavía.' => 'No projects yet.',
    'Todavía no hay clientes. Crea el primero con Nuevo cliente.' => 'There are no clients yet. Create the first one with New client.',
    '¿Confirmas el cambio de estado de este cliente? Si lo desactivas, sus consultores dejarán de ver sus proyectos.' => 'Confirm this client status change? If you deactivate it, consultants will stop seeing its projects.',
    '¿Confirmas el cambio de estado de este proyecto? Si lo desactivas, los consultores dejarán de verlo.' => 'Confirm this project status change? If you deactivate it, consultants will stop seeing it.'
];

$clientes = $pdo->query('SELECT id, nombre, activo FROM clientes ORDER BY activo DESC, nombre, id')->fetchAll();
$proyectos = $pdo->query('SELECT id, cliente_id, nombre, activo FROM proyectos ORDER BY activo DESC, nombre, id')->fetchAll();

$consultoresPorProyecto = [];

foreach ($pdo->query(
    "SELECT pc.proyecto_id, CONCAT(u.nombres, ' ', u.apellidos) AS nombre
     FROM proyecto_consultor pc
     INNER JOIN usuarios u ON u.id = pc.consultor_id
     WHERE pc.activo = 1 AND u.activo = 1
     ORDER BY u.apellidos, u.nombres"
) as $fila) {
    $consultoresPorProyecto[(int) $fila['proyecto_id']][] = $fila['nombre'];
}

$ahora = mejrAhora();
$horasMes = [];
$horasTotal = [];

$consultaMes = $pdo->prepare(
    'SELECT proyecto_id, SUM(horas) AS horas FROM registros_horas
     WHERE fecha >= :inicio AND fecha < :fin GROUP BY proyecto_id'
);
$consultaMes->execute([
    'inicio' => $ahora->format('Y-m-01'),
    'fin' => $ahora->modify('first day of next month')->format('Y-m-d')
]);

foreach ($consultaMes as $fila) {
    $horasMes[(int) $fila['proyecto_id']] = mejrEscalar($fila['horas'], 2);
}

foreach ($pdo->query('SELECT proyecto_id, SUM(horas) AS horas FROM registros_horas GROUP BY proyecto_id') as $fila) {
    $horasTotal[(int) $fila['proyecto_id']] = mejrEscalar($fila['horas'], 2);
}

$proyectosPorCliente = [];

foreach ($proyectos as $proyecto) {
    $proyectosPorCliente[(int) $proyecto['cliente_id']][] = $proyecto;
}

$tituloPagina = 'Clientes y proyectos';
$seccion = 'clientes';
$scriptsPagina = ['confirmar.js?v=1'];
require __DIR__ . '/config/plantilla-inicio.php';

$botonEstado = static function (string $accion, string $estadoActual, string $confirmar, string $nombre): string {
    $activo = $estadoActual === '1';
    ob_start();
    ?>
                        <form method="post" action="<?= escapar($accion) ?>" data-confirmar="<?= texto($confirmar) ?>">
                          <input type="hidden" name="csrf" value="<?= escapar($_SESSION['csrf']) ?>">
                          <input type="hidden" name="accion" value="estado">
                          <input type="hidden" name="estado" value="<?= $activo ? 'desactivar' : 'activar' ?>">
                          <input type="hidden" name="volver" value="lista">
                          <button type="submit" class="btn btn-sm <?= $activo ? 'btn-outline-danger' : 'btn-outline-dark' ?>"
                            aria-label="<?= escapar(mejrTraducir($activo ? 'Desactivar' : 'Activar') . ': ' . $nombre) ?>">
                            <?= texto($activo ? 'Desactivar' : 'Activar') ?>
                          </button>
                        </form>
    <?php
    return ob_get_clean();
};
?>
    <div class="mejr-titulo">
      <div>
        <h1 class="h4"><?= texto('Clientes y proyectos') ?></h1>
        <p class="text-secondary mb-0"><?= texto('Administra clientes, sus proyectos y quién trabaja en cada uno.') ?></p>
      </div>
      <a href="cliente.php" class="btn btn-dark"><?= texto('Nuevo cliente') ?></a>
    </div>

    <section class="bg-white border rounded p-4">
      <?php if (!$clientes): ?>
        <p class="text-secondary mb-0"><?= texto('Todavía no hay clientes. Crea el primero con Nuevo cliente.') ?></p>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table align-middle mb-0">
            <thead>
              <tr>
                <th scope="col"><?= texto('Cliente / Proyecto') ?></th>
                <th scope="col"><?= texto('Detalle') ?></th>
                <th scope="col" class="mejr-numero"><?= texto('Horas del mes') ?></th>
                <th scope="col" class="mejr-numero"><?= texto('Horas acumuladas') ?></th>
                <th scope="col"><?= texto('Estado') ?></th>
                <th scope="col" class="text-end"><?= texto('Acciones') ?></th>
              </tr>
            </thead>
            <?php foreach ($clientes as $cliente): ?>
              <?php
              $clienteId = (int) $cliente['id'];
              $suyos = $proyectosPorCliente[$clienteId] ?? [];
              $activos = count(array_filter($suyos, static fn($p) => (int) $p['activo'] === 1));
              $mesCliente = 0;
              $totalCliente = 0;

              foreach ($suyos as $proyecto) {
                  $mesCliente += $horasMes[(int) $proyecto['id']] ?? 0;
                  $totalCliente += $horasTotal[(int) $proyecto['id']] ?? 0;
              }

              $fichaCliente = 'cliente.php?id=' . $clienteId;
              $clienteActivo = (int) $cliente['activo'] === 1;
              ?>
              <tbody>
                <tr class="mejr-fila-grupo">
                  <td><a href="<?= escapar($fichaCliente) ?>"><?= escapar($cliente['nombre']) ?></a></td>
                  <td class="small"><?= $activos ?> <?= texto('proyectos activos') ?></td>
                  <td class="mejr-numero"><?= escapar(mejrHoras($mesCliente)) ?></td>
                  <td class="mejr-numero"><?= escapar(mejrHoras($totalCliente)) ?></td>
                  <td>
                    <span class="badge <?= $clienteActivo ? 'text-bg-success' : 'text-bg-secondary' ?>">
                      <?= texto($clienteActivo ? 'Activo' : 'Inactivo') ?>
                    </span>
                  </td>
                  <td class="mejr-col-acciones">
                    <div class="mejr-acciones">
                      <a class="btn btn-sm btn-outline-secondary" href="<?= escapar($fichaCliente . '#datos') ?>"><?= texto('Editar') ?></a>
                      <a class="btn btn-sm btn-outline-secondary" href="<?= escapar($fichaCliente . '#proyectos') ?>"><?= texto('Nuevo proyecto') ?></a>
                      <a class="btn btn-sm btn-outline-secondary" href="horas-admin.php?cliente_id=<?= $clienteId ?>"><?= texto('Horas') ?></a>
                      <?= $botonEstado(
                          $fichaCliente,
                          (string) $cliente['activo'],
                          '¿Confirmas el cambio de estado de este cliente? Si lo desactivas, sus consultores dejarán de ver sus proyectos.',
                          $cliente['nombre']
                      ) ?>
                    </div>
                  </td>
                </tr>

                <?php if (!$suyos): ?>
                  <tr class="mejr-fila-hija">
                    <td colspan="6" class="small text-secondary"><?= texto('Sin proyectos todavía.') ?></td>
                  </tr>
                <?php endif; ?>

                <?php foreach ($suyos as $proyecto): ?>
                  <?php
                  $proyectoId = (int) $proyecto['id'];
                  $fichaProyecto = 'proyecto.php?id=' . $proyectoId;
                  $proyectoActivo = (int) $proyecto['activo'] === 1;
                  ?>
                  <tr class="mejr-fila-hija">
                    <td><a href="<?= escapar($fichaProyecto) ?>"><?= escapar($proyecto['nombre']) ?></a></td>
                    <td>
                      <?php if (empty($consultoresPorProyecto[$proyectoId])): ?>
                        <span class="small text-secondary"><?= texto('Sin consultores') ?></span>
                      <?php else: ?>
                        <div class="mejr-etiquetas">
                          <?php foreach ($consultoresPorProyecto[$proyectoId] as $nombre): ?>
                            <span class="badge"><?= escapar($nombre) ?></span>
                          <?php endforeach; ?>
                        </div>
                      <?php endif; ?>
                    </td>
                    <td class="mejr-numero"><?= escapar(mejrHoras($horasMes[$proyectoId] ?? 0)) ?></td>
                    <td class="mejr-numero"><?= escapar(mejrHoras($horasTotal[$proyectoId] ?? 0)) ?></td>
                    <td>
                      <span class="badge <?= $proyectoActivo ? 'text-bg-success' : 'text-bg-secondary' ?>">
                        <?= texto($proyectoActivo ? 'Activo' : 'Inactivo') ?>
                      </span>
                    </td>
                    <td class="mejr-col-acciones">
                      <div class="mejr-acciones">
                        <a class="btn btn-sm btn-outline-secondary" href="<?= escapar($fichaProyecto . '#datos') ?>"><?= texto('Editar') ?></a>
                        <a class="btn btn-sm btn-outline-secondary" href="<?= escapar($fichaProyecto . '#consultores') ?>"><?= texto('Consultores') ?></a>
                        <a class="btn btn-sm btn-outline-secondary" href="horas-admin.php?proyecto_id=<?= $proyectoId ?>"><?= texto('Horas') ?></a>
                        <?= $botonEstado(
                            $fichaProyecto,
                            (string) $proyecto['activo'],
                            '¿Confirmas el cambio de estado de este proyecto? Si lo desactivas, los consultores dejarán de verlo.',
                            $proyecto['nombre']
                        ) ?>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            <?php endforeach; ?>
          </table>
        </div>
      <?php endif; ?>
    </section>
<?php require __DIR__ . '/config/plantilla-fin.php'; ?>
