<?php
// Horas registradas por los consultores, con filtros, montos en US$ según la
// tarifa vigente en la fecha de cada registro, y Excel. Solo lectura.
$rolPagina = 'administrador';
require __DIR__ . '/config/arranque.php';
require_once __DIR__ . '/config/tarifas.php';

$traducciones += [
    'Período no válido. Usa meses entre 1000 y 9998, con "hasta" igual o posterior a "desde" y un máximo de 36 meses.' => 'Invalid period. Use months between 1000 and 9998, with "to" on or after "from", up to 36 months.',
    'Filtro no válido. Vuelve a abrir Horas.' => 'Invalid filter. Open Hours again.',
    'Horas registradas por los consultores, con su valor en dólares según las tarifas vigentes en cada fecha.' => 'Hours logged by consultants, valued in US dollars with the rates in force on each date.',
    'Desde' => 'From',
    'Hasta' => 'To',
    'Todos los consultores' => 'All consultants',
    'Todos los clientes' => 'All clients',
    'Todos los proyectos' => 'All projects',
    'Tarifas' => 'Rates',
    'Todas' => 'All',
    'Sin tarifa de cliente' => 'Missing client rate',
    'Sin tarifa de consultor' => 'Missing consultant rate',
    'Consultar' => 'View',
    'Restablecer filtros' => 'Reset filters',
    'Registros' => 'Entries',
    'Ingreso' => 'Revenue',
    'Costo' => 'Cost',
    'Margen' => 'Margin',
    'Incompleto: hay horas sin tarifa.' => 'Incomplete: some hours have no rate.',
    'Tarifa cliente' => 'Client rate',
    'Tarifa consultor' => 'Consultant rate',
    'No hay registros para el período y los filtros seleccionados.' => 'No entries match the selected period and filters.'
];

$periodo = mejrPeriodoMeses($_GET);

if ($periodo === null) {
    http_response_code(400);
    exit(texto('Período no válido. Usa meses entre 1000 y 9998, con "hasta" igual o posterior a "desde" y un máximo de 36 meses.'));
}

$filtros = [];

foreach (['consultor_id', 'cliente_id', 'proyecto_id'] as $campo) {
    $valor = $_GET[$campo] ?? '';
    $id = $valor === '' ? null : validarId($valor);

    if ($id === false) {
        http_response_code(400);
        exit(texto('Filtro no válido. Vuelve a abrir Horas.'));
    }

    $filtros[$campo] = $id;
}

$sinTarifa = $_GET['sin_tarifa'] ?? '';

if (!in_array($sinTarifa, ['', 'cliente', 'consultor'], true)) {
    http_response_code(400);
    exit(texto('Filtro no válido. Vuelve a abrir Horas.'));
}

[$sqlValorizados, $parametros] = mejrSqlValorizados(
    $periodo['desde']->format('Y-m-d'),
    $periodo['fin']->format('Y-m-d'),
    $filtros
);

$sql = "SELECT v.*, u.nombres, u.apellidos, c.nombre AS cliente, p.nombre AS proyecto
        FROM ($sqlValorizados) v
        INNER JOIN usuarios u ON u.id = v.consultor_id
        INNER JOIN proyectos p ON p.id = v.proyecto_id
        INNER JOIN clientes c ON c.id = v.cliente_id";

if ($sinTarifa !== '') {
    $sql .= $sinTarifa === 'cliente' ? ' WHERE v.tarifa_cliente IS NULL' : ' WHERE v.tarifa_consultor IS NULL';
}

$sql .= ' ORDER BY v.fecha DESC, u.apellidos, u.nombres, v.id DESC';

$consulta = $pdo->prepare($sql);
$consulta->execute($parametros);
$registros = $consulta->fetchAll();

$totales = ['horas' => 0, 'ingreso' => 0, 'costo' => 0, 'sinCobro' => 0, 'sinPago' => 0];

foreach ($registros as $indice => $registro) {
    $centesimas = mejrEscalar($registro['horas'], 2);
    $tarifaCliente = $registro['tarifa_cliente'] === null ? null : (int) $registro['tarifa_cliente'];
    $tarifaConsultor = $registro['tarifa_consultor'] === null ? null : (int) $registro['tarifa_consultor'];

    $registros[$indice]['centesimas'] = $centesimas;
    $registros[$indice]['ingreso'] = mejrMontoExacto($centesimas, $tarifaCliente);
    $registros[$indice]['costo'] = mejrMontoExacto($centesimas, $tarifaConsultor);

    $totales['horas'] += $centesimas;
    $totales['ingreso'] += $registros[$indice]['ingreso'] ?? 0;
    $totales['costo'] += $registros[$indice]['costo'] ?? 0;
    $totales['sinCobro'] += $tarifaCliente === null ? 1 : 0;
    $totales['sinPago'] += $tarifaConsultor === null ? 1 : 0;
}

$incompleto = $totales['sinCobro'] > 0 || $totales['sinPago'] > 0;

if (($_GET['exportar'] ?? '') === 'excel') {
    require __DIR__ . '/config/exportar-horas.php';
    descargarHorasExcel($registros, $totales['horas'], $periodo['etiqueta'], true);
}

$consultores = $pdo->query(
    "SELECT u.id, u.nombres, u.apellidos
     FROM usuarios u
     WHERE u.rol = 'consultor' OR EXISTS (SELECT 1 FROM registros_horas r WHERE r.consultor_id = u.id)
     ORDER BY u.apellidos, u.nombres, u.id"
)->fetchAll();
$clientes = $pdo->query('SELECT id, nombre FROM clientes ORDER BY nombre, id')->fetchAll();
$proyectos = $pdo->query(
    'SELECT p.id, p.nombre, c.nombre AS cliente
     FROM proyectos p INNER JOIN clientes c ON c.id = p.cliente_id
     ORDER BY c.nombre, p.nombre, p.id'
)->fetchAll();

$parametrosVista = ['desde' => $periodo['desdeMes'], 'hasta' => $periodo['hastaMes']]
    + array_filter($filtros, static fn($valor) => $valor !== null)
    + ($sinTarifa !== '' ? ['sin_tarifa' => $sinTarifa] : []);

$tituloPagina = 'Horas';
$seccion = 'horas';
$parametrosIdioma = $parametrosVista;
require __DIR__ . '/config/plantilla-inicio.php';
?>
    <div class="mejr-titulo">
      <div>
        <h1 class="h4"><?= texto('Horas') ?></h1>
        <p class="text-secondary mb-0"><?= texto('Horas registradas por los consultores, con su valor en dólares según las tarifas vigentes en cada fecha.') ?></p>
      </div>
      <a class="btn btn-outline-dark"
        href="<?= escapar('horas-admin.php?' . http_build_query($parametrosVista + ['exportar' => 'excel'])) ?>"><?= texto('Descargar Excel') ?></a>
    </div>

    <section class="bg-white border rounded p-4 mb-4">
      <form method="get" action="horas-admin.php">
        <div class="row g-3">
          <div class="col-sm-6 col-lg-3 col-xxl-2">
            <label for="desde" class="form-label"><?= texto('Desde') ?></label>
            <input id="desde" name="desde" type="month" class="form-control" value="<?= escapar($periodo['desdeMes']) ?>" required>
          </div>
          <div class="col-sm-6 col-lg-3 col-xxl-2">
            <label for="hasta" class="form-label"><?= texto('Hasta') ?></label>
            <input id="hasta" name="hasta" type="month" class="form-control" value="<?= escapar($periodo['hastaMes']) ?>" required>
          </div>
          <div class="col-sm-6 col-lg-3 col-xxl-2">
            <label for="consultor_id" class="form-label"><?= texto('Consultor') ?></label>
            <select id="consultor_id" name="consultor_id" class="form-select">
              <option value=""><?= texto('Todos los consultores') ?></option>
              <?php foreach ($consultores as $consultor): ?>
                <option value="<?= escapar($consultor['id']) ?>" <?= $filtros['consultor_id'] === (int) $consultor['id'] ? 'selected' : '' ?>>
                  <?= escapar($consultor['apellidos'] . ', ' . $consultor['nombres']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-sm-6 col-lg-3 col-xxl-2">
            <label for="cliente_id" class="form-label"><?= texto('Cliente') ?></label>
            <select id="cliente_id" name="cliente_id" class="form-select">
              <option value=""><?= texto('Todos los clientes') ?></option>
              <?php foreach ($clientes as $cliente): ?>
                <option value="<?= escapar($cliente['id']) ?>" <?= $filtros['cliente_id'] === (int) $cliente['id'] ? 'selected' : '' ?>>
                  <?= escapar($cliente['nombre']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-sm-6 col-lg-3 col-xxl-2">
            <label for="proyecto_id" class="form-label"><?= texto('Proyecto') ?></label>
            <select id="proyecto_id" name="proyecto_id" class="form-select">
              <option value=""><?= texto('Todos los proyectos') ?></option>
              <?php foreach ($proyectos as $proyecto): ?>
                <option value="<?= escapar($proyecto['id']) ?>" <?= $filtros['proyecto_id'] === (int) $proyecto['id'] ? 'selected' : '' ?>>
                  <?= escapar($proyecto['cliente'] . ' / ' . $proyecto['nombre']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-sm-6 col-lg-3 col-xxl-2">
            <label for="sin_tarifa" class="form-label"><?= texto('Tarifas') ?></label>
            <select id="sin_tarifa" name="sin_tarifa" class="form-select">
              <option value=""><?= texto('Todas') ?></option>
              <option value="cliente" <?= $sinTarifa === 'cliente' ? 'selected' : '' ?>><?= texto('Sin tarifa de cliente') ?></option>
              <option value="consultor" <?= $sinTarifa === 'consultor' ? 'selected' : '' ?>><?= texto('Sin tarifa de consultor') ?></option>
            </select>
          </div>
        </div>

        <div class="d-flex flex-wrap gap-2 mt-3">
          <button type="submit" class="btn btn-dark"><?= texto('Consultar') ?></button>
          <a href="horas-admin.php" class="btn btn-outline-secondary"><?= texto('Restablecer filtros') ?></a>
        </div>
      </form>
    </section>

    <div class="mejr-kpis">
      <div class="mejr-kpi">
        <div class="mejr-kpi-etiqueta"><?= texto('Horas') ?> · <?= escapar($periodo['etiqueta']) ?></div>
        <div class="mejr-kpi-valor" id="total-horas"><?= escapar(mejrHoras($totales['horas'])) ?></div>
        <div class="mejr-kpi-nota"><?= texto('Registros') ?>: <?= count($registros) ?></div>
      </div>
      <div class="mejr-kpi">
        <div class="mejr-kpi-etiqueta"><?= texto('Ingreso') ?></div>
        <div class="mejr-kpi-valor" id="total-ingreso"><?= escapar(mejrUsdExacto($totales['ingreso'])) ?></div>
      </div>
      <div class="mejr-kpi">
        <div class="mejr-kpi-etiqueta"><?= texto('Costo') ?></div>
        <div class="mejr-kpi-valor" id="total-costo"><?= escapar(mejrUsdExacto($totales['costo'])) ?></div>
      </div>
      <div class="mejr-kpi">
        <div class="mejr-kpi-etiqueta"><?= texto('Margen') ?></div>
        <div class="mejr-kpi-valor" id="total-margen"><?= escapar(mejrUsdExacto($totales['ingreso'] - $totales['costo'])) ?></div>
        <?php if ($incompleto): ?>
          <div class="mejr-kpi-nota"><span class="badge text-bg-warning"><?= texto('Incompleto: hay horas sin tarifa.') ?></span></div>
        <?php endif; ?>
      </div>
    </div>

    <section class="bg-white border rounded p-4">
      <?php if (!$registros): ?>
        <p class="text-secondary mb-0"><?= texto('No hay registros para el período y los filtros seleccionados.') ?></p>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table align-middle mb-0">
            <thead>
              <tr>
                <th scope="col"><?= texto('Fecha') ?></th>
                <th scope="col"><?= texto('Consultor') ?></th>
                <th scope="col"><?= texto('Cliente') ?></th>
                <th scope="col"><?= texto('Proyecto') ?></th>
                <th scope="col" class="mejr-numero"><?= texto('Horas') ?></th>
                <th scope="col" class="mejr-numero"><?= texto('Tarifa cliente') ?></th>
                <th scope="col" class="mejr-numero"><?= texto('Ingreso') ?></th>
                <th scope="col" class="mejr-numero"><?= texto('Tarifa consultor') ?></th>
                <th scope="col" class="mejr-numero"><?= texto('Costo') ?></th>
                <th scope="col"><?= texto('Descripción') ?></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($registros as $registro): ?>
                <tr>
                  <td><?= escapar(mejrFecha($registro['fecha'])) ?></td>
                  <td><a href="consultor.php?id=<?= (int) $registro['consultor_id'] ?>"><?= escapar($registro['nombres'] . ' ' . $registro['apellidos']) ?></a></td>
                  <td><?= escapar($registro['cliente']) ?></td>
                  <td><a href="proyecto.php?id=<?= (int) $registro['proyecto_id'] ?>"><?= escapar($registro['proyecto']) ?></a></td>
                  <td class="mejr-numero"><?= escapar(mejrHoras($registro['centesimas'])) ?></td>
                  <td class="mejr-numero">
                    <?php if ($registro['tarifa_cliente'] === null): ?>
                      <a class="badge text-bg-warning" href="cliente.php?id=<?= (int) $registro['cliente_id'] ?>#tarifa"><?= texto('Sin tarifa') ?></a>
                    <?php else: ?>
                      <?= escapar(mejrUsd((int) $registro['tarifa_cliente'])) ?>
                    <?php endif; ?>
                  </td>
                  <td class="mejr-numero"><?= escapar(mejrUsdExacto($registro['ingreso'])) ?></td>
                  <td class="mejr-numero">
                    <?php if ($registro['tarifa_consultor'] === null): ?>
                      <a class="badge text-bg-warning" href="consultor.php?id=<?= (int) $registro['consultor_id'] ?>#tarifa"><?= texto('Sin tarifa') ?></a>
                    <?php else: ?>
                      <?= escapar(mejrUsd((int) $registro['tarifa_consultor'])) ?>
                    <?php endif; ?>
                  </td>
                  <td class="mejr-numero"><?= escapar(mejrUsdExacto($registro['costo'])) ?></td>
                  <td class="text-break"><?= nl2br(escapar($registro['descripcion'])) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </section>
<?php require __DIR__ . '/config/plantilla-fin.php'; ?>
