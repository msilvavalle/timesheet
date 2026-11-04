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
    'Nombre, apellido, correo o usuario' => 'First name, last name, email or username',
    'Idioma' => 'Language',
    'Asignaciones' => 'Assignments',
    'Administración' => 'Administration',
    'Acceso reservado para administración.' => 'Access restricted to administrators.',
    'Solicitud no válida. Vuelve a abrir el formulario.' => 'Invalid request. Open the form again.',
    'Selecciona un proyecto y un consultor válidos.' => 'Select a valid project and consultant.',
    'Comprueba que el cliente, el proyecto y el consultor estén activos y existan.' => 'Check that the client, project and consultant exist and are active.',
    'Ese consultor ya tiene una asignación registrada para este proyecto.' => 'That consultant already has an assignment registered for this project.',
    'No se pudo guardar la asignación. Intenta nuevamente.' => 'The assignment could not be saved. Try again.',
    'Volver al panel' => 'Back to dashboard',
    'Asigna un proyecto a cada consultor que trabajará en él.' => 'Assign a project to each consultant who will work on it.',
    'Asignación guardada correctamente.' => 'Assignment saved successfully.',
    'Para asignar, necesitas un proyecto con cliente activo y un consultor activo.' => 'To create an assignment, you need an active project with an active client and an active consultant.',
    'Cliente / Proyecto' => 'Client / Project',
    'Selecciona un proyecto' => 'Select a project',
    'Consultor' => 'Consultant',
    'Selecciona un consultor' => 'Select a consultant',
    'Guardar asignación' => 'Save assignment',
    'Asignaciones registradas' => 'Registered assignments',
    'Todavía no hay asignaciones registradas.' => 'No assignments have been registered yet.',
    'Cliente' => 'Client',
    'Proyecto' => 'Project',
    'Estado' => 'Status',
    'Activa' => 'Active',
    'Inactiva' => 'Inactive',
    'Buscar consultor' => 'Find a consultant',
    'Nombre, apellido o usuario' => 'First name, last name or username',
    'Escribe para filtrar la lista y luego selecciona al consultor.' => 'Type to filter the list, then select a consultant.',
    'Ordenados por apellido y nombre.' => 'Sorted by last name and first name.',
    'Limpiar' => 'Clear',
    'Consultores disponibles' => 'Available consultants',
    'No se encontraron consultores. Prueba otra búsqueda o pulsa Limpiar.' => 'No consultants match. Try another search or select Clear.'
];

if (!isset($_SESSION['usuario_id']) || time() - ($_SESSION['ultima_actividad'] ?? 0) > 1800) {
    $_SESSION = [];
    header('Location: login.php');
    exit;
}

require __DIR__ . '/config/conexion.php';
require_once __DIR__ . '/config/validar-sesion.php';

$consulta = $pdo->prepare(
    'SELECT nombres, apellidos, rol
     FROM usuarios WHERE id = :id AND activo = 1'
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
    exit(textoAsignaciones('Acceso reservado para administración.'));
}

$_SESSION['ultima_actividad'] = time();

function escapar($valor): string {
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

function textoAsignaciones(string $texto): string {
    return escapar(mejrTraducir($texto));
}

function validarId($valor) {
    return filter_var($valor, FILTER_VALIDATE_INT, [
        'options' => [
            'min_range' => 1,
            'max_range' => 4294967295
        ]
    ]);
}

$_SESSION['csrf'] ??= bin2hex(random_bytes(32));

$proyectoId = '';
$consultorId = '';
$error = '';
$guardado = $_SESSION['asignacion_guardada'] ?? false;
unset($_SESSION['asignacion_guardada']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf'] ?? '';

    if (!is_string($token) || !hash_equals($_SESSION['csrf'], $token)) {
        http_response_code(403);
        exit(textoAsignaciones('Solicitud no válida. Vuelve a abrir el formulario.'));
    }

    $proyectoId = is_string($_POST['proyecto_id'] ?? null)
        ? $_POST['proyecto_id']
        : '';

    $consultorId = is_string($_POST['consultor_id'] ?? null)
        ? $_POST['consultor_id']
        : '';

    $proyectoValido = validarId($proyectoId);
    $consultorValido = validarId($consultorId);

    if ($proyectoValido === false || $consultorValido === false) {
        $error = 'Selecciona un proyecto y un consultor válidos.';
    } else {
        try {
            $insertar = $pdo->prepare(
                "INSERT INTO proyecto_consultor
                    (proyecto_id, consultor_id, activo)
                 SELECT p.id, u.id, 1
                 FROM proyectos p
                 INNER JOIN clientes c ON c.id = p.cliente_id
                 CROSS JOIN usuarios u
                 WHERE p.id = :proyecto_id
                   AND p.activo = 1
                   AND c.activo = 1
                   AND u.id = :consultor_id
                   AND u.rol = 'consultor'
                   AND u.activo = 1"
            );

            $insertar->execute([
                'proyecto_id' => $proyectoValido,
                'consultor_id' => $consultorValido
            ]);

            if ($insertar->rowCount() === 1) {
                $_SESSION['asignacion_guardada'] = true;

                header('Location: asignaciones.php', true, 303);
                exit;
            }

            $error = 'Comprueba que el cliente, el proyecto y el consultor estén activos y existan.';
        } catch (PDOException $excepcion) {
            if (($excepcion->errorInfo[1] ?? 0) === 1062) {
                $error = 'Ese consultor ya tiene una asignación registrada para este proyecto.';
            } else {
                error_log($excepcion->getMessage());
                $error = 'No se pudo guardar la asignación. Intenta nuevamente.';
            }
        }
    }
}

$proyectos = $pdo->query(
    'SELECT p.id, p.nombre AS proyecto, c.nombre AS cliente
     FROM proyectos p
     INNER JOIN clientes c ON c.id = p.cliente_id
     WHERE p.activo = 1 AND c.activo = 1
     ORDER BY c.nombre, p.nombre, p.id'
)->fetchAll();

$consultores = $pdo->query(
    "SELECT id, nombres, apellidos, usuario, correo
     FROM usuarios
     WHERE rol = 'consultor' AND activo = 1
     ORDER BY apellidos, nombres, id"
)->fetchAll();

$asignaciones = $pdo->query(
    'SELECT c.nombre AS cliente, p.nombre AS proyecto,
            u.nombres, u.apellidos, u.usuario, u.correo, pc.activo
     FROM proyecto_consultor pc
     INNER JOIN proyectos p ON p.id = pc.proyecto_id
     INNER JOIN clientes c ON c.id = p.cliente_id
     INNER JOIN usuarios u ON u.id = pc.consultor_id
     ORDER BY u.apellidos, u.nombres, u.id, c.nombre, p.nombre, p.id'
)->fetchAll();
?>
<!DOCTYPE html>
<html lang="<?= escapar($idioma) ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <title><?= textoAsignaciones('Asignaciones') ?> | MEJR Timesheet</title>

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
          <?= textoAsignaciones('Administración') ?>
        </span>
      </div>
    </div>
  </header>

  <main class="container py-4">

    <div class="d-flex flex-wrap gap-3 justify-content-between align-items-center mb-3">
      <a href="panel.php" class="btn btn-outline-secondary">
        <?= textoAsignaciones('Volver al panel') ?>
      </a>

      <nav aria-label="<?= textoAsignaciones('Idioma') ?>">
        <div class="btn-group">
          <a
            href="asignaciones.php?idioma=es"
            lang="es"
            class="btn btn-sm <?= $idioma === 'es' ? 'btn-dark' : 'btn-outline-secondary' ?>"
            <?= $idioma === 'es' ? 'aria-current="true"' : '' ?>>
            Español
          </a>

          <a
            href="asignaciones.php?idioma=en"
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
        <?= textoAsignaciones('Asignaciones') ?>
      </h1>

      <p class="text-secondary">
        <?= textoAsignaciones('Asigna un proyecto a cada consultor que trabajará en él.') ?>
      </p>

      <?php if ($guardado): ?>
        <div class="alert alert-success" role="status">
          <?= textoAsignaciones('Asignación guardada correctamente.') ?>
        </div>
      <?php endif; ?>

      <?php if ($error !== ''): ?>
        <div class="alert alert-danger" role="alert">
          <?= textoAsignaciones($error) ?>
        </div>
      <?php endif; ?>

      <?php if (!$proyectos || !$consultores): ?>
        <p>
          <?= textoAsignaciones('Para asignar, necesitas un proyecto con cliente activo y un consultor activo.') ?>
        </p>
      <?php endif; ?>

      <form method="post" action="asignaciones.php">
        <input
          type="hidden"
          name="csrf"
          value="<?= escapar($_SESSION['csrf']) ?>">

        <div class="row g-3">
          <div class="col-md-6">
            <label for="proyecto_id" class="form-label">
              <?= textoAsignaciones('Cliente / Proyecto') ?>
            </label>

            <select
              id="proyecto_id"
              name="proyecto_id"
              class="form-select"
              required>

              <option value="">
                <?= textoAsignaciones('Selecciona un proyecto') ?>
              </option>

              <?php foreach ($proyectos as $proyecto): ?>
                <option
                  value="<?= escapar($proyecto['id']) ?>"
                  <?= (string) $proyecto['id'] === $proyectoId ? 'selected' : '' ?>>
                  <?= escapar($proyecto['cliente'] . ' / ' . $proyecto['proyecto']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="col-md-6">
            <div id="buscador-consultores" class="mb-3" hidden>
              <label for="buscar-consultor" class="form-label">
                <?= textoAsignaciones('Buscar consultor') ?>
              </label>

              <div class="input-group">
                <input
                  id="buscar-consultor"
                  type="search"
                  class="form-control"
                  maxlength="254"
                  placeholder="<?= textoAsignaciones('Nombre, apellido, correo o usuario') ?>"
                  autocomplete="off"
                  aria-controls="consultor_id"
                  aria-describedby="ayuda-busqueda-consultor">

                <button
                  type="button"
                  id="limpiar-consultor"
                  class="btn btn-outline-secondary">
                  <?= textoAsignaciones('Limpiar') ?>
                </button>
              </div>

              <div id="ayuda-busqueda-consultor" class="form-text">
                <?= textoAsignaciones('Escribe para filtrar la lista y luego selecciona al consultor.') ?>
              </div>
            </div>

            <label for="consultor_id" class="form-label">
              <?= textoAsignaciones('Consultor') ?>
            </label>

            <select
              id="consultor_id"
              name="consultor_id"
              class="form-select"
              aria-describedby="orden-consultores"
              required>

              <option value="">
                <?= textoAsignaciones('Selecciona un consultor') ?>
              </option>

              <?php foreach ($consultores as $consultor): ?>
                <option
                  value="<?= escapar($consultor['id']) ?>"
                  <?= (string) $consultor['id'] === $consultorId ? 'selected' : '' ?>>
                  <?= escapar(
                      $consultor['apellidos'] . ', ' .
                      $consultor['nombres'] . ' (' .
                      ($consultor['correo'] ?: $consultor['usuario']) . ')'
                  ) ?>
                </option>
              <?php endforeach; ?>
            </select>

            <div id="orden-consultores" class="form-text">
              <?= textoAsignaciones('Ordenados por apellido y nombre.') ?>
            </div>

            <p
              id="resultados-consultores"
              class="form-text mb-0"
              role="status"
              aria-live="polite"
              aria-atomic="true"
              data-disponibles="<?= textoAsignaciones('Consultores disponibles') ?>"
              data-sin-resultados="<?= textoAsignaciones('No se encontraron consultores. Prueba otra búsqueda o pulsa Limpiar.') ?>"
              hidden></p>
          </div>
        </div>

        <button
          id="guardar-asignacion"
          type="submit"
          class="btn btn-dark mt-3"
          <?= !$proyectos || !$consultores ? 'disabled' : '' ?>>
          <?= textoAsignaciones('Guardar asignación') ?>
        </button>
      </form>
    </section>

    <section class="bg-white border rounded p-4" aria-labelledby="titulo-listado">
      <h2 id="titulo-listado" class="h5">
        <?= textoAsignaciones('Asignaciones registradas') ?>
      </h2>

      <?php if (!$asignaciones): ?>
        <p class="text-secondary mb-0">
          <?= textoAsignaciones('Todavía no hay asignaciones registradas.') ?>
        </p>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table align-middle mb-0">
            <thead>
              <tr>
                <th scope="col"><?= textoAsignaciones('Consultor') ?></th>
                <th scope="col"><?= textoAsignaciones('Cliente') ?></th>
                <th scope="col"><?= textoAsignaciones('Proyecto') ?></th>
                <th scope="col"><?= textoAsignaciones('Estado') ?></th>
              </tr>
            </thead>

            <tbody>
              <?php foreach ($asignaciones as $asignacion): ?>
                <tr>
                  <td>
                    <?= escapar(
                        $asignacion['apellidos'] . ', ' .
                        $asignacion['nombres']
                    ) ?>

                    <span class="d-block small text-secondary">
                      <?= escapar($asignacion['correo'] ?: $asignacion['usuario']) ?>
                    </span>
                  </td>

                  <td><?= escapar($asignacion['cliente']) ?></td>
                  <td><?= escapar($asignacion['proyecto']) ?></td>

                  <td>
                    <?= textoAsignaciones(
                        (int) $asignacion['activo'] === 1 ? 'Activa' : 'Inactiva'
                    ) ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </section>
  </main>

  <script>
    (() => {
      'use strict';

      const buscador = document.getElementById('buscador-consultores');
      const entrada = document.getElementById('buscar-consultor');
      const limpiar = document.getElementById('limpiar-consultor');
      const selector = document.getElementById('consultor_id');
      const resultados = document.getElementById('resultados-consultores');
      const guardar = document.getElementById('guardar-asignacion');

      if (!buscador || !entrada || !limpiar || !selector || !resultados || !guardar) {
        return;
      }

      function normalizar(texto) {
        return texto
          .normalize('NFD')
          .replace(/[\u0300-\u036f]/g, '')
          .toLocaleLowerCase('es')
          .trim();
      }

      const opciones = Array.from(selector.options)
        .filter(opcion => opcion.value !== '')
        .map(opcion => ({
          valor: opcion.value,
          texto: opcion.textContent.trim(),
          busqueda: normalizar(opcion.textContent)
        }));

      if (opciones.length === 0) {
        return;
      }

      const textoInicial = selector.options[0].textContent.trim();
      const guardadoBloqueado = guardar.disabled;

      function filtrarConsultores() {
        const palabras = normalizar(entrada.value).split(/\s+/).filter(Boolean);
        const seleccionAnterior = selector.value;

        const visibles = opciones.filter(opcion =>
          palabras.every(palabra => opcion.busqueda.includes(palabra))
        );

        const fragmento = document.createDocumentFragment();
        fragmento.appendChild(new Option(textoInicial, ''));

        visibles.forEach(opcion => {
          fragmento.appendChild(new Option(opcion.texto, opcion.valor));
        });

        selector.replaceChildren(fragmento);

        selector.value = visibles.some(opcion => opcion.valor === seleccionAnterior)
          ? seleccionAnterior
          : '';

        guardar.disabled = guardadoBloqueado || visibles.length === 0;

        resultados.textContent = visibles.length > 0
          ? `${resultados.dataset.disponibles}: ${visibles.length}`
          : resultados.dataset.sinResultados;
      }

      entrada.addEventListener('input', filtrarConsultores);
      entrada.addEventListener('search', filtrarConsultores);

      entrada.addEventListener('keydown', evento => {
        if (evento.key === 'Enter') {
          evento.preventDefault();
          selector.focus();
        }
      });

      limpiar.addEventListener('click', () => {
        entrada.value = '';
        filtrarConsultores();
        entrada.focus();
      });

      filtrarConsultores();
      buscador.hidden = false;
      resultados.hidden = false;
    })();
  </script>
</body>
</html>