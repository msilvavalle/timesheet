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
    'Proyectos' => 'Projects',
    'Administración' => 'Administration',
    'Acceso reservado para administración.' => 'Access restricted to administrators.',
    'Solicitud no válida. Vuelve a abrir el formulario.' => 'Invalid request. Open the form again.',
    'Selecciona un cliente válido.' => 'Select a valid client.',
    'Escribe un nombre de proyecto de entre 1 y 150 caracteres, sin saltos de línea.' => 'Enter a project name between 1 and 150 characters, without line breaks.',
    'El cliente no existe o está inactivo. Selecciona otro.' => 'The client does not exist or is inactive. Select another one.',
    'Ese proyecto ya está registrado para este cliente.' => 'That project is already registered for this client.',
    'No se pudo guardar el proyecto. Intenta nuevamente.' => 'The project could not be saved. Try again.',
    'Volver al panel' => 'Back to dashboard',
    'Selecciona el cliente y registra su proyecto.' => 'Select the client and register their project.',
    'Proyecto registrado:' => 'Project registered:',
    'Primero debes' => 'First, you need to',
    'registrar un cliente activo' => 'register an active client',
    'Cliente' => 'Client',
    'Selecciona un cliente' => 'Select a client',
    'Nombre del proyecto' => 'Project name',
    'Guardar proyecto' => 'Save project',
    'Proyectos registrados' => 'Registered projects',
    'Todavía no hay proyectos registrados.' => 'No projects have been registered yet.',
    'Proyecto' => 'Project',
    'Estado' => 'Status',
    'Activo' => 'Active',
    'Inactivo' => 'Inactive'
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
    exit(textoProyectosAdmin('Acceso reservado para administración.'));
}

$_SESSION['ultima_actividad'] = time();

function escapar($valor): string {
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

function textoProyectosAdmin(string $texto): string {
    return escapar(mejrTraducir($texto));
}

$_SESSION['csrf'] ??= bin2hex(random_bytes(32));

$nombre = '';
$clienteId = '';
$error = '';
$creado = $_SESSION['proyecto_creado'] ?? '';
unset($_SESSION['proyecto_creado']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf'] ?? '';

    if (!is_string($token) || !hash_equals($_SESSION['csrf'], $token)) {
        http_response_code(403);
        exit(textoProyectosAdmin('Solicitud no válida. Vuelve a abrir el formulario.'));
    }

    $nombre = is_string($_POST['nombre'] ?? null)
        ? trim($_POST['nombre'])
        : '';

    $clienteId = is_string($_POST['cliente_id'] ?? null)
        ? $_POST['cliente_id']
        : '';

    $idValido = filter_var($clienteId, FILTER_VALIDATE_INT, [
        'options' => [
            'min_range' => 1,
            'max_range' => 4294967295
        ]
    ]);

    if ($idValido === false) {
        $error = 'Selecciona un cliente válido.';
    } elseif (!preg_match('/\A[^\p{C}]{1,150}\z/u', $nombre)) {
        $error = 'Escribe un nombre de proyecto de entre 1 y 150 caracteres, sin saltos de línea.';
    } else {
        try {
            $insertar = $pdo->prepare(
                'INSERT INTO proyectos (cliente_id, nombre, activo)
                 SELECT id, :nombre, 1
                 FROM clientes
                 WHERE id = :cliente_id AND activo = 1'
            );

            $insertar->execute([
                'nombre' => $nombre,
                'cliente_id' => $idValido
            ]);

            if ($insertar->rowCount() === 1) {
                $_SESSION['proyecto_creado'] = $nombre;

                header('Location: proyectos.php', true, 303);
                exit;
            }

            $error = 'El cliente no existe o está inactivo. Selecciona otro.';
        } catch (PDOException $excepcion) {
            if (($excepcion->errorInfo[1] ?? 0) === 1062) {
                $error = 'Ese proyecto ya está registrado para este cliente.';
            } else {
                error_log($excepcion->getMessage());
                $error = 'No se pudo guardar el proyecto. Intenta nuevamente.';
            }
        }
    }
}

$clientes = $pdo->query(
    'SELECT id, nombre FROM clientes
     WHERE activo = 1 ORDER BY nombre, id'
)->fetchAll();

$proyectos = $pdo->query(
    'SELECT p.nombre AS proyecto, c.nombre AS cliente, p.activo
     FROM proyectos p
     INNER JOIN clientes c ON c.id = p.cliente_id
     ORDER BY c.nombre, p.nombre, p.id'
)->fetchAll();
?>
<!DOCTYPE html>
<html lang="<?= escapar($idioma) ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <title><?= textoProyectosAdmin('Proyectos') ?> | MEJR Timesheet</title>

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
          <?= textoProyectosAdmin('Administración') ?>
        </span>
      </div>
    </div>
  </header>

  <main class="container py-4">

    <div class="d-flex flex-wrap gap-3 justify-content-between align-items-center mb-3">
      <a href="panel.php" class="btn btn-outline-secondary">
        <?= textoProyectosAdmin('Volver al panel') ?>
      </a>

      <nav aria-label="<?= textoProyectosAdmin('Idioma') ?>">
        <div class="btn-group">
          <a
            href="proyectos.php?idioma=es"
            lang="es"
            class="btn btn-sm <?= $idioma === 'es' ? 'btn-dark' : 'btn-outline-secondary' ?>"
            <?= $idioma === 'es' ? 'aria-current="true"' : '' ?>>
            Español
          </a>

          <a
            href="proyectos.php?idioma=en"
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
        <?= textoProyectosAdmin('Proyectos') ?>
      </h1>

      <p class="text-secondary">
        <?= textoProyectosAdmin('Selecciona el cliente y registra su proyecto.') ?>
      </p>

      <?php if ($creado !== ''): ?>
        <div class="alert alert-success" role="status">
          <?= textoProyectosAdmin('Proyecto registrado:') ?>
          <?= escapar($creado) ?>.
        </div>
      <?php endif; ?>

      <?php if ($error !== ''): ?>
        <div class="alert alert-danger" role="alert">
          <?= textoProyectosAdmin($error) ?>
        </div>
      <?php endif; ?>

      <?php if (!$clientes): ?>
        <p>
          <?= textoProyectosAdmin('Primero debes') ?>
          <a href="clientes.php">
            <?= textoProyectosAdmin('registrar un cliente activo') ?>
          </a>.
        </p>
      <?php endif; ?>

      <form method="post" action="proyectos.php">
        <input
          type="hidden"
          name="csrf"
          value="<?= escapar($_SESSION['csrf']) ?>">

        <div class="row g-3">
          <div class="col-md-6">
            <label for="cliente_id" class="form-label">
              <?= textoProyectosAdmin('Cliente') ?>
            </label>

            <select
              id="cliente_id"
              name="cliente_id"
              class="form-select"
              required>

              <option value="">
                <?= textoProyectosAdmin('Selecciona un cliente') ?>
              </option>

              <?php foreach ($clientes as $cliente): ?>
                <option
                  value="<?= escapar($cliente['id']) ?>"
                  <?= (string) $cliente['id'] === $clienteId ? 'selected' : '' ?>>
                  <?= escapar($cliente['nombre']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="col-md-6">
            <label for="nombre" class="form-label">
              <?= textoProyectosAdmin('Nombre del proyecto') ?>
            </label>

            <input
              id="nombre"
              name="nombre"
              class="form-control"
              maxlength="150"
              value="<?= escapar($nombre) ?>"
              required>
          </div>
        </div>

        <button
          type="submit"
          class="btn btn-dark mt-3"
          <?= !$clientes ? 'disabled' : '' ?>>
          <?= textoProyectosAdmin('Guardar proyecto') ?>
        </button>
      </form>
    </section>

    <section class="bg-white border rounded p-4" aria-labelledby="titulo-listado">
      <h2 id="titulo-listado" class="h5">
        <?= textoProyectosAdmin('Proyectos registrados') ?>
      </h2>

      <?php if (!$proyectos): ?>
        <p class="text-secondary mb-0">
          <?= textoProyectosAdmin('Todavía no hay proyectos registrados.') ?>
        </p>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table align-middle mb-0">
            <thead>
              <tr>
                <th scope="col"><?= textoProyectosAdmin('Cliente') ?></th>
                <th scope="col"><?= textoProyectosAdmin('Proyecto') ?></th>
                <th scope="col"><?= textoProyectosAdmin('Estado') ?></th>
              </tr>
            </thead>

            <tbody>
              <?php foreach ($proyectos as $proyecto): ?>
                <tr>
                  <td><?= escapar($proyecto['cliente']) ?></td>
                  <td><?= escapar($proyecto['proyecto']) ?></td>

                  <td>
                    <?= textoProyectosAdmin(
                        (int) $proyecto['activo'] === 1 ? 'Activo' : 'Inactivo'
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

</body>
</html>