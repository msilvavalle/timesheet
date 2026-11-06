<?php
// Dashboard del administrador: horas registradas convertidas a US$ con las
// tarifas vigentes en cada fecha (cobro por cliente, pago por consultor).
// Reemplaza a resumen-mensual.php y es la pantalla de entrada del admin.
$rolPagina = 'administrador';
require __DIR__ . '/config/arranque.php';
require_once __DIR__ . '/config/tarifas.php';

$traducciones += [
    'Período no válido. Usa meses entre 1000 y 9998, con "hasta" igual o posterior a "desde" y un máximo de 36 meses.' => 'Invalid period. Use months between 1000 and 9998, with "to" on or after "from", up to 36 months.',
    'Filtro no válido. Vuelve a abrir el Dashboard.' => 'Invalid filter. Open the Dashboard again.',
    'Horas registradas y su valor en dólares: lo que se cobra a cada cliente, lo que se paga a cada consultor y el margen.' => 'Logged hours and their value in US dollars: what each client is billed, what each consultant is paid, and the margin.',
    'en curso, provisional' => 'in progress, provisional',
    'Desde' => 'From',
    'Hasta' => 'To',
    'Todos los clientes' => 'All clients',
    'Ver' => 'View',
    'Este mes' => 'This month',
    'Últimos 3 meses' => 'Last 3 months',
    'Año actual' => 'This year',
    'Ingresos' => 'Revenue',
    'Costo consultores' => 'Consultant cost',
    'Margen' => 'Margin',
    'Consultores con horas' => 'Consultants with hours',
    'activos sin horas' => 'active with no hours',
    'Horas con tarifa' => 'Hours with rates',
    'Incompleto' => 'Incomplete',
    'Faltan tarifas' => 'Missing rates',
    'Estas horas no tienen tarifa en su fecha y suman US$ 0 hasta que la cargues. Una tarifa con fecha anterior a todas las existentes se acepta siempre.' => 'These hours have no rate on their date and count as US$ 0 until you add one. A rate dated before all existing ones is always accepted.',
    'Tarifa de cobro' => 'Billing rate',
    'Tarifa de pago' => 'Pay rate',
    'Horas sin tarifa' => 'Hours without rate',
    'Desde el' => 'Since',
    'Agregar tarifa' => 'Add rate',
    'Ver horas' => 'View hours',
    'Por cliente' => 'By client',
    'Por proyecto' => 'By project',
    'Por consultor' => 'By consultant',
    'Ingreso' => 'Revenue',
    'Costo' => 'Cost',
    'Margen %' => 'Margin %',
    'Horas acumuladas' => 'Total hours',
    'Ingreso generado' => 'Revenue generated',
    'Ficha' => 'Profile',
    'Sin horas registradas en el período.' => 'No hours logged in the period.',
    'Últimos 12 meses' => 'Last 12 months',
    'Excel del dashboard' => 'Dashboard Excel',
    'incompleto' => 'incomplete'
];

$periodo = mejrPeriodoMeses($_GET);

if ($periodo === null) {
    http_response_code(400);
    exit(texto('Período no válido. Usa meses entre 1000 y 9998, con "hasta" igual o posterior a "desde" y un máximo de 36 meses.'));
}

$clienteFiltro = ($_GET['cliente_id'] ?? '') === '' ? null : validarId($_GET['cliente_id']);

if ($clienteFiltro === false) {
    http_response_code(400);
    exit(texto('Filtro no válido. Vuelve a abrir el Dashboard.'));
}

$ahora = mejrAhora();
$mesActual = $ahora->format('Y-m');
$provisional = $periodo['hastaMes'] >= $mesActual;
$filtros = ['cliente_id' => $clienteFiltro];

// Todas las consultas en una transacción de lectura: tarjetas, tablas y
// tendencia salen de la misma foto de los datos.
$pdo->beginTransaction();

try {
    [$sqlPeriodo, $parametros] = mejrSqlValorizados(
        $periodo['desde']->format('Y-m-d'), $periodo['fin']->format('Y-m-d'), $filtros
    );

    $agregado = $pdo->prepare(
        "SELECT v.cliente_id, v.proyecto_id, v.consultor_id,
                SUM(v.horas) AS horas,
                SUM(v.horas * v.tarifa_cliente) AS ingreso,
                SUM(v.horas * v.tarifa_consultor) AS costo,
                SUM(CASE WHEN v.tarifa_cliente IS NULL THEN v.horas ELSE 0 END) AS horas_sin_cobro,
                SUM(CASE WHEN v.tarifa_consultor IS NULL THEN v.horas ELSE 0 END) AS horas_sin_pago,
                SUM(CASE WHEN v.tarifa_cliente IS NOT NULL AND v.tarifa_consultor IS NOT NULL THEN v.horas ELSE 0 END) AS horas_completas,
                MIN(CASE WHEN v.tarifa_cliente IS NULL THEN v.fecha END) AS fecha_sin_cobro,
                MIN(CASE WHEN v.tarifa_consultor IS NULL THEN v.fecha END) AS fecha_sin_pago
         FROM ($sqlPeriodo) v
         GROUP BY v.cliente_id, v.proyecto_id, v.consultor_id"
    );
    $agregado->execute($parametros);
    $grupos = $agregado->fetchAll();

    $inicioTendencia = $periodo['hasta']->modify('-11 months');
    [$sqlTendencia, $parametrosTendencia] = mejrSqlValorizados(
        $inicioTendencia->format('Y-m-d'), $periodo['fin']->format('Y-m-d'), $filtros
    );
    $tendencia = $pdo->prepare(
        "SELECT YEAR(v.fecha) AS anio, MONTH(v.fecha) AS mes,
                SUM(v.horas) AS horas,
                SUM(v.horas * v.tarifa_cliente) AS ingreso,
                SUM(v.horas * v.tarifa_consultor) AS costo
         FROM ($sqlTendencia) v
         GROUP BY YEAR(v.fecha), MONTH(v.fecha)"
    );
    $tendencia->execute($parametrosTendencia);
    $filasTendencia = $tendencia->fetchAll();

    $clientes = $pdo->query('SELECT id, nombre FROM clientes ORDER BY nombre, id')->fetchAll(PDO::FETCH_KEY_PAIR);
    $proyectos = [];

    foreach ($pdo->query('SELECT id, nombre, cliente_id FROM proyectos') as $fila) {
        $proyectos[(int) $fila['id']] = $fila;
    }

    $consultores = [];

    foreach ($pdo->query("SELECT id, nombres, apellidos, activo, rol FROM usuarios") as $fila) {
        $consultores[(int) $fila['id']] = $fila;
    }

    $acumuladas = [];

    foreach ($pdo->query('SELECT proyecto_id, SUM(horas) AS horas FROM registros_horas GROUP BY proyecto_id') as $fila) {
        $acumuladas[(int) $fila['proyecto_id']] = mejrEscalar($fila['horas'], 2);
    }

    $pdo->commit();
} catch (PDOException $excepcion) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    throw $excepcion;
}

// Suma en PHP desde una sola agregación: los totales siempre cuadran.
$vacio = ['horas' => 0, 'ingreso' => 0, 'costo' => 0, 'sinCobro' => 0, 'sinPago' => 0, 'completas' => 0];
$total = $vacio;
$porCliente = [];
$porProyecto = [];
$porConsultor = [];
$faltanCobro = [];
$faltanPago = [];

foreach ($grupos as $grupo) {
    $valores = [
        'horas' => mejrEscalar($grupo['horas'], 2),
        'ingreso' => mejrEscalar($grupo['ingreso'], 2),
        'costo' => mejrEscalar($grupo['costo'], 2),
        'sinCobro' => mejrEscalar($grupo['horas_sin_cobro'], 2),
        'sinPago' => mejrEscalar($grupo['horas_sin_pago'], 2),
        'completas' => mejrEscalar($grupo['horas_completas'], 2),
    ];

    $claves = [
        'cliente' => (int) $grupo['cliente_id'],
        'proyecto' => (int) $grupo['proyecto_id'],
        'consultor' => (int) $grupo['consultor_id'],
    ];

    foreach ($valores as $campo => $valor) {
        $total[$campo] += $valor;
        $porCliente[$claves['cliente']][$campo] = ($porCliente[$claves['cliente']][$campo] ?? 0) + $valor;
        $porProyecto[$claves['proyecto']][$campo] = ($porProyecto[$claves['proyecto']][$campo] ?? 0) + $valor;
        $porConsultor[$claves['consultor']][$campo] = ($porConsultor[$claves['consultor']][$campo] ?? 0) + $valor;
    }

    if ($valores['sinCobro'] > 0) {
        $actual = $faltanCobro[$claves['cliente']] ?? ['horas' => 0, 'desde' => $grupo['fecha_sin_cobro']];
        $faltanCobro[$claves['cliente']] = [
            'horas' => $actual['horas'] + $valores['sinCobro'],
            'desde' => min($actual['desde'], $grupo['fecha_sin_cobro']),
        ];
    }

    if ($valores['sinPago'] > 0) {
        $actual = $faltanPago[$claves['consultor']] ?? ['horas' => 0, 'desde' => $grupo['fecha_sin_pago']];
        $faltanPago[$claves['consultor']] = [
            'horas' => $actual['horas'] + $valores['sinPago'],
            'desde' => min($actual['desde'], $grupo['fecha_sin_pago']),
        ];
    }
}

// Los consultores activos sin horas también aparecen, con cero.
$activosSinHoras = 0;

foreach ($consultores as $id => $consultor) {
    if ($consultor['rol'] === 'consultor' && (int) $consultor['activo'] === 1 && !isset($porConsultor[$id])) {
        $porConsultor[$id] = $vacio;
        $activosSinHoras++;
    }
}

$consultoresConHoras = count(array_filter($porConsultor, static fn($fila) => $fila['horas'] > 0));
$incompleto = $total['sinCobro'] > 0 || $total['sinPago'] > 0;

$ordenar = static function (array &$filas): void {
    uasort($filas, static fn($a, $b) => [$b['ingreso'], $b['horas']] <=> [$a['ingreso'], $a['horas']]);
};
$ordenar($porCliente);
$ordenar($porProyecto);
$ordenar($porConsultor);

$meses = [];

for ($mes = $inicioTendencia; $mes <= $periodo['hasta']; $mes = $mes->modify('+1 month')) {
    $meses[$mes->format('Y-m')] = ['etiqueta' => mejrNombreMes($mes)] + $vacio;
}

foreach ($filasTendencia as $fila) {
    $clave = sprintf('%04d-%02d', $fila['anio'], $fila['mes']);
    $meses[$clave]['horas'] = mejrEscalar($fila['horas'], 2);
    $meses[$clave]['ingreso'] = mejrEscalar($fila['ingreso'], 2);
    $meses[$clave]['costo'] = mejrEscalar($fila['costo'], 2);
}

$maximoHoras = max(1, ...array_column($meses, 'horas'));

$parametrosVista = ['desde' => $periodo['desdeMes'], 'hasta' => $periodo['hastaMes']]
    + ($clienteFiltro !== null ? ['cliente_id' => $clienteFiltro] : []);

if (($_GET['exportar'] ?? '') === 'excel') {
    require __DIR__ . '/config/exportar-tablero.php';
    descargarTableroExcel($periodo['etiqueta'], $porCliente, $porProyecto, $porConsultor, $clientes, $proyectos, $consultores);
}

$urlHoras = static fn(array $extra): string =>
    'horas-admin.php?' . http_build_query(['desde' => $periodo['desdeMes'], 'hasta' => $periodo['hastaMes']] + $extra);

$atajos = [
    'Este mes' => [$mesActual, $mesActual],
    'Mes anterior' => [$ahora->modify('first day of last month')->format('Y-m'), $ahora->modify('first day of last month')->format('Y-m')],
    'Últimos 3 meses' => [$ahora->modify('first day of -2 months')->format('Y-m'), $mesActual],
    'Año actual' => [$ahora->format('Y') . '-01', $mesActual],
];

$margen = static fn(array $fila): int => $fila['ingreso'] - $fila['costo'];
$porcentaje = static fn(array $fila): string => $fila['ingreso'] > 0
    ? mejrPorcentaje($fila['ingreso'] - $fila['costo'], $fila['ingreso'])
    : '—';
$marcaIncompleta = static fn(array $fila): string => ($fila['sinCobro'] > 0 || $fila['sinPago'] > 0)
    ? ' <span class="badge text-bg-warning">' . texto('incompleto') . '</span>'
    : '';
$nombreConsultor = static fn(int $id): string =>
    isset($consultores[$id]) ? $consultores[$id]['nombres'] . ' ' . $consultores[$id]['apellidos'] : '#' . $id;

$tituloPagina = 'Dashboard';
$seccion = 'tablero';
$parametrosIdioma = $parametrosVista;
require __DIR__ . '/config/plantilla-inicio.php';
?>
    <div class="mejr-titulo">
      <div>
        <h1 class="h4">
          <?= texto('Dashboard') ?> · <?= escapar($periodo['etiqueta']) ?>
          <?php if ($provisional): ?>
            <span class="badge text-bg-light border align-middle"><?= texto('en curso, provisional') ?></span>
          <?php endif; ?>
        </h1>
        <p class="text-secondary mb-0"><?= texto('Horas registradas y su valor en dólares: lo que se cobra a cada cliente, lo que se paga a cada consultor y el margen.') ?></p>
      </div>
      <a class="btn btn-outline-dark"
        href="<?= escapar('tablero.php?' . http_build_query($parametrosVista + ['exportar' => 'excel'])) ?>"><?= texto('Excel del dashboard') ?></a>
    </div>

    <section class="bg-white border rounded p-3 mb-4">
      <form method="get" action="tablero.php" class="row g-2 align-items-end">
        <div class="col-sm-6 col-lg-3 col-xl-2">
          <label for="desde" class="form-label"><?= texto('Desde') ?></label>
          <input id="desde" name="desde" type="month" class="form-control" value="<?= escapar($periodo['desdeMes']) ?>" required>
        </div>
        <div class="col-sm-6 col-lg-3 col-xl-2">
          <label for="hasta" class="form-label"><?= texto('Hasta') ?></label>
          <input id="hasta" name="hasta" type="month" class="form-control" value="<?= escapar($periodo['hastaMes']) ?>" required>
        </div>
        <div class="col-sm-8 col-lg-4 col-xl-3">
          <label for="cliente_id" class="form-label"><?= texto('Cliente') ?></label>
          <select id="cliente_id" name="cliente_id" class="form-select">
            <option value=""><?= texto('Todos los clientes') ?></option>
            <?php foreach ($clientes as $id => $nombre): ?>
              <option value="<?= escapar($id) ?>" <?= $clienteFiltro === (int) $id ? 'selected' : '' ?>><?= escapar($nombre) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-auto">
          <button type="submit" class="btn btn-dark"><?= texto('Ver') ?></button>
        </div>
        <div class="col-12 col-lg d-flex flex-wrap gap-2 justify-content-lg-end">
          <?php foreach ($atajos as $nombre => [$desde, $hasta]): ?>
            <a class="btn btn-sm btn-outline-secondary"
              <?= $desde === $periodo['desdeMes'] && $hasta === $periodo['hastaMes'] ? 'aria-current="true"' : '' ?>
              href="<?= escapar('tablero.php?' . http_build_query(['desde' => $desde, 'hasta' => $hasta]
                  + ($clienteFiltro !== null ? ['cliente_id' => $clienteFiltro] : []))) ?>"><?= texto($nombre) ?></a>
          <?php endforeach; ?>
        </div>
      </form>
    </section>

    <div class="mejr-kpis">
      <div class="mejr-kpi">
        <div class="mejr-kpi-etiqueta"><?= texto('Horas') ?></div>
        <div class="mejr-kpi-valor" id="kpi-horas"><?= escapar(mejrHoras($total['horas'])) ?></div>
      </div>
      <div class="mejr-kpi">
        <div class="mejr-kpi-etiqueta"><?= texto('Ingresos') ?></div>
        <div class="mejr-kpi-valor" id="kpi-ingreso"><?= escapar(mejrUsdExacto($total['ingreso'])) ?></div>
        <?php if ($total['sinCobro'] > 0): ?>
          <div class="mejr-kpi-nota"><span class="badge text-bg-warning"><?= texto('Incompleto') ?></span></div>
        <?php endif; ?>
      </div>
      <div class="mejr-kpi">
        <div class="mejr-kpi-etiqueta"><?= texto('Costo consultores') ?></div>
        <div class="mejr-kpi-valor" id="kpi-costo"><?= escapar(mejrUsdExacto($total['costo'])) ?></div>
        <?php if ($total['sinPago'] > 0): ?>
          <div class="mejr-kpi-nota"><span class="badge text-bg-warning"><?= texto('Incompleto') ?></span></div>
        <?php endif; ?>
      </div>
      <div class="mejr-kpi">
        <div class="mejr-kpi-etiqueta"><?= texto('Margen') ?></div>
        <div class="mejr-kpi-valor" id="kpi-margen"><?= escapar(mejrUsdExacto($margen($total))) ?></div>
        <div class="mejr-kpi-nota" id="kpi-margen-porcentaje">
          <?= escapar($porcentaje($total)) ?>
          <?php if ($incompleto): ?><span class="badge text-bg-warning"><?= texto('Incompleto') ?></span><?php endif; ?>
        </div>
      </div>
      <div class="mejr-kpi">
        <div class="mejr-kpi-etiqueta"><?= texto('Consultores con horas') ?></div>
        <div class="mejr-kpi-valor"><?= $consultoresConHoras ?></div>
        <div class="mejr-kpi-nota"><?= $activosSinHoras ?> <?= texto('activos sin horas') ?></div>
      </div>
      <div class="mejr-kpi">
        <div class="mejr-kpi-etiqueta"><?= texto('Horas con tarifa') ?></div>
        <div class="mejr-kpi-valor">
          <?= escapar($total['horas'] > 0
              ? mejrPorcentaje($total['completas'], $total['horas'])
              : '—') ?>
        </div>
      </div>
    </div>

    <?php if ($faltanCobro || $faltanPago): ?>
      <section class="border border-warning rounded p-4 mb-4 bg-white" aria-labelledby="titulo-faltan">
        <h2 id="titulo-faltan" class="h5"><?= texto('Faltan tarifas') ?></h2>
        <p class="text-secondary"><?= texto('Estas horas no tienen tarifa en su fecha y suman US$ 0 hasta que la cargues. Una tarifa con fecha anterior a todas las existentes se acepta siempre.') ?></p>
        <div class="table-responsive">
          <table class="table table-sm align-middle mb-0">
            <thead>
              <tr>
                <th scope="col"><?= texto('Tarifa') ?></th>
                <th scope="col"><?= texto('Cliente') ?> / <?= texto('Consultor') ?></th>
                <th scope="col" class="mejr-numero"><?= texto('Horas sin tarifa') ?></th>
                <th scope="col"><?= texto('Desde el') ?></th>
                <th scope="col" class="text-end"><?= texto('Acciones') ?></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($faltanCobro as $id => $falta): ?>
                <tr>
                  <td><?= texto('Tarifa de cobro') ?></td>
                  <td><?= escapar($clientes[$id] ?? '#' . $id) ?></td>
                  <td class="mejr-numero"><?= escapar(mejrHoras($falta['horas'])) ?></td>
                  <td><?= escapar(mejrFecha($falta['desde'])) ?></td>
                  <td class="mejr-col-acciones">
                    <div class="mejr-acciones">
                      <a class="btn btn-sm btn-dark" href="cliente.php?id=<?= (int) $id ?>#tarifa"><?= texto('Agregar tarifa') ?></a>
                      <a class="btn btn-sm btn-outline-secondary" href="<?= escapar($urlHoras(['cliente_id' => $id, 'sin_tarifa' => 'cliente'])) ?>"><?= texto('Ver horas') ?></a>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
              <?php foreach ($faltanPago as $id => $falta): ?>
                <tr>
                  <td><?= texto('Tarifa de pago') ?></td>
                  <td><?= escapar($nombreConsultor($id)) ?></td>
                  <td class="mejr-numero"><?= escapar(mejrHoras($falta['horas'])) ?></td>
                  <td><?= escapar(mejrFecha($falta['desde'])) ?></td>
                  <td class="mejr-col-acciones">
                    <div class="mejr-acciones">
                      <a class="btn btn-sm btn-dark" href="consultor.php?id=<?= (int) $id ?>#tarifa"><?= texto('Agregar tarifa') ?></a>
                      <a class="btn btn-sm btn-outline-secondary" href="<?= escapar($urlHoras(['consultor_id' => $id, 'sin_tarifa' => 'consultor'])) ?>"><?= texto('Ver horas') ?></a>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </section>
    <?php endif; ?>

    <?php if ($total['horas'] === 0): ?>
      <section class="bg-white border rounded p-4 mb-4">
        <p class="text-secondary mb-0"><?= texto('Sin horas registradas en el período.') ?></p>
      </section>
    <?php else: ?>
      <section class="bg-white border rounded p-4 mb-4" aria-labelledby="titulo-clientes">
        <h2 id="titulo-clientes" class="h5"><?= texto('Por cliente') ?></h2>
        <div class="table-responsive">
          <table class="table align-middle mb-0" id="tabla-clientes">
            <thead>
              <tr>
                <th scope="col" class="mejr-col-nombre"><?= texto('Cliente') ?></th>
                <th scope="col" class="mejr-numero"><?= texto('Horas') ?></th>
                <th scope="col" class="mejr-numero"><?= texto('Ingreso') ?></th>
                <th scope="col" class="mejr-numero"><?= texto('Costo') ?></th>
                <th scope="col" class="mejr-numero"><?= texto('Margen') ?></th>
                <th scope="col" class="mejr-numero"><?= texto('Margen %') ?></th>
                <th scope="col" class="text-end"><?= texto('Acciones') ?></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($porCliente as $id => $fila): ?>
                <tr>
                  <td><a href="cliente.php?id=<?= (int) $id ?>"><?= escapar($clientes[$id] ?? '#' . $id) ?></a><?= $marcaIncompleta($fila) ?></td>
                  <td class="mejr-numero"><?= escapar(mejrHoras($fila['horas'])) ?></td>
                  <td class="mejr-numero"><?= escapar(mejrUsdExacto($fila['ingreso'])) ?></td>
                  <td class="mejr-numero"><?= escapar(mejrUsdExacto($fila['costo'])) ?></td>
                  <td class="mejr-numero"><?= escapar(mejrUsdExacto($margen($fila))) ?></td>
                  <td class="mejr-numero"><?= escapar($porcentaje($fila)) ?></td>
                  <td class="mejr-col-acciones">
                    <div class="mejr-acciones">
                      <a class="btn btn-sm btn-outline-secondary" href="<?= escapar($urlHoras(['cliente_id' => $id])) ?>"><?= texto('Ver horas') ?></a>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </section>

      <section class="bg-white border rounded p-4 mb-4" aria-labelledby="titulo-proyectos">
        <h2 id="titulo-proyectos" class="h5"><?= texto('Por proyecto') ?></h2>
        <div class="table-responsive">
          <table class="table align-middle mb-0" id="tabla-proyectos">
            <thead>
              <tr>
                <th scope="col" class="mejr-col-nombre"><?= texto('Proyecto') ?></th>
                <th scope="col" class="mejr-numero"><?= texto('Horas') ?></th>
                <th scope="col" class="mejr-numero"><?= texto('Horas acumuladas') ?></th>
                <th scope="col" class="mejr-numero"><?= texto('Ingreso') ?></th>
                <th scope="col" class="mejr-numero"><?= texto('Costo') ?></th>
                <th scope="col" class="mejr-numero"><?= texto('Margen') ?></th>
                <th scope="col" class="mejr-numero"><?= texto('Margen %') ?></th>
                <th scope="col" class="text-end"><?= texto('Acciones') ?></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($porProyecto as $id => $fila): ?>
                <?php $proyecto = $proyectos[$id] ?? ['nombre' => '#' . $id, 'cliente_id' => 0]; ?>
                <tr>
                  <td>
                    <a href="proyecto.php?id=<?= (int) $id ?>"><?= escapar($proyecto['nombre']) ?></a><?= $marcaIncompleta($fila) ?>
                    <div class="small text-secondary"><?= escapar($clientes[(int) $proyecto['cliente_id']] ?? '') ?></div>
                  </td>
                  <td class="mejr-numero"><?= escapar(mejrHoras($fila['horas'])) ?></td>
                  <td class="mejr-numero"><?= escapar(mejrHoras($acumuladas[$id] ?? 0)) ?></td>
                  <td class="mejr-numero"><?= escapar(mejrUsdExacto($fila['ingreso'])) ?></td>
                  <td class="mejr-numero"><?= escapar(mejrUsdExacto($fila['costo'])) ?></td>
                  <td class="mejr-numero"><?= escapar(mejrUsdExacto($margen($fila))) ?></td>
                  <td class="mejr-numero"><?= escapar($porcentaje($fila)) ?></td>
                  <td class="mejr-col-acciones">
                    <div class="mejr-acciones">
                      <a class="btn btn-sm btn-outline-secondary" href="<?= escapar($urlHoras(['proyecto_id' => $id])) ?>"><?= texto('Ver horas') ?></a>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </section>
    <?php endif; ?>

    <section class="bg-white border rounded p-4 mb-4" aria-labelledby="titulo-consultores">
      <h2 id="titulo-consultores" class="h5"><?= texto('Por consultor') ?></h2>
      <div class="table-responsive">
        <table class="table align-middle mb-0" id="tabla-consultores">
          <thead>
            <tr>
              <th scope="col" class="mejr-col-nombre"><?= texto('Consultor') ?></th>
              <th scope="col" class="mejr-numero"><?= texto('Horas') ?></th>
              <th scope="col" class="mejr-numero"><?= texto('Costo') ?></th>
              <th scope="col" class="mejr-numero"><?= texto('Ingreso generado') ?></th>
              <th scope="col" class="mejr-numero"><?= texto('Margen') ?></th>
              <th scope="col" class="text-end"><?= texto('Acciones') ?></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($porConsultor as $id => $fila): ?>
              <tr>
                <td><?= escapar($nombreConsultor($id)) ?><?= $marcaIncompleta($fila) ?></td>
                <td class="mejr-numero"><?= escapar(mejrHoras($fila['horas'])) ?></td>
                <td class="mejr-numero"><?= escapar(mejrUsdExacto($fila['costo'])) ?></td>
                <td class="mejr-numero"><?= escapar(mejrUsdExacto($fila['ingreso'])) ?></td>
                <td class="mejr-numero"><?= escapar(mejrUsdExacto($margen($fila))) ?></td>
                <td class="mejr-col-acciones">
                  <div class="mejr-acciones">
                    <a class="btn btn-sm btn-outline-secondary" href="<?= escapar($urlHoras(['consultor_id' => $id])) ?>"><?= texto('Ver horas') ?></a>
                    <a class="btn btn-sm btn-outline-secondary" href="consultor.php?id=<?= (int) $id ?>"><?= texto('Ficha') ?></a>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>

    <section class="bg-white border rounded p-4" aria-labelledby="titulo-tendencia">
      <h2 id="titulo-tendencia" class="h5"><?= texto('Últimos 12 meses') ?></h2>
      <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
          <thead>
            <tr>
              <th scope="col"><?= texto('Mes') ?></th>
              <th scope="col"><?= texto('Horas') ?></th>
              <th scope="col" class="mejr-numero"><?= texto('Ingreso') ?></th>
              <th scope="col" class="mejr-numero"><?= texto('Costo') ?></th>
              <th scope="col" class="mejr-numero"><?= texto('Margen') ?></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($meses as $clave => $fila): ?>
              <tr>
                <td><a href="tablero.php?<?= escapar(http_build_query(['desde' => $clave, 'hasta' => $clave])) ?>"><?= escapar($fila['etiqueta']) ?></a></td>
                <td>
                  <meter class="mejr-barra-dato" min="0" max="<?= escapar($maximoHoras) ?>" value="<?= escapar($fila['horas']) ?>"></meter>
                  <span class="small"><?= escapar(mejrHoras($fila['horas'])) ?></span>
                </td>
                <td class="mejr-numero"><?= escapar(mejrUsdExacto($fila['ingreso'])) ?></td>
                <td class="mejr-numero"><?= escapar(mejrUsdExacto($fila['costo'])) ?></td>
                <td class="mejr-numero"><?= escapar(mejrUsdExacto($margen($fila))) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>
<?php require __DIR__ . '/config/plantilla-fin.php'; ?>
