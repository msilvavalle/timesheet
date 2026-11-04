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
    'Clientes' => 'Clients',
    'Administración' => 'Administration',
    'Acceso reservado para administración.' => 'Access restricted to administrators.',
    'Solicitud no válida. Vuelve a abrir el formulario.' => 'Invalid request. Open the form again.',
    'Escribe un nombre de cliente de entre 1 y 150 caracteres, sin saltos de línea.' => 'Enter a client name between 1 and 150 characters, without line breaks.',
    'Ese cliente ya está registrado.' => 'That client is already registered.',
    'No se pudo guardar el cliente. Intenta nuevamente.' => 'The client could not be saved. Try again.',
    'Volver al panel' => 'Back to dashboard',
    'Registra las empresas a las que pertenecen los proyectos.' => 'Register the companies that the projects belong to.',
    'Cliente registrado:' => 'Client registered:',
    'Nombre del cliente' => 'Client name',
    'Guardar cliente' => 'Save client',
    'Clientes registrados' => 'Registered clients',
    'Todavía no hay clientes registrados.' => 'No clients have been registered yet.',
    'Cliente' => 'Client',
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
    exit(textoClientes('Acceso reservado para administración.'));
}

$_SESSION['ultima_actividad'] = time();

function escapar($valor): string {
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

function textoClientes(string $texto): string {
    return escapar(mejrTraducir($texto));
}

$_SESSION['csrf'] ??= bin2hex(random_bytes(32));

$nombre = '';
$error = '';
$creado = $_SESSION['cliente_creado'] ?? '';
unset($_SESSION['cliente_creado']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf'] ?? '';

    if (!is_string($token) || !hash_equals($_SESSION['csrf'], $token)) {
        http_response_code(403);
        exit(textoClientes('Solicitud no válida. Vuelve a abrir el formulario.'));
    }

    $nombre = is_string($_POST['nombre'] ?? null)
        ? trim($_POST['nombre'])
        : '';

    if (!preg_match('/\A[^\p{C}]{1,150}\z/u', $nombre)) {
        $error = 'Escribe un nombre de cliente de entre 1 y 150 caracteres, sin saltos de línea.';
    } else {
        try {
            $insertar = $pdo->prepare(
                'INSERT INTO clientes (nombre, activo) VALUES (:nombre, 1)'
            );
            $insertar->execute(['nombre' => $nombre]);

            $_SESSION['cliente_creado'] = $nombre;

            header('Location: clientes.php', true, 303);
            exit;
        } catch (PDOException $excepcion) {
            if (($excepcion->errorInfo[1] ?? 0) === 1062) {
                $error = 'Ese cliente ya está registrado.';
            } else {
                error_log($excepcion->getMessage());
                $error = 'No se pudo guardar el cliente. Intenta nuevamente.';
            }
        }
    }
}

$clientes = $pdo->query(
    'SELECT nombre, activo FROM clientes ORDER BY nombre, id'
)->fetchAll();
?>
<!DOCTYPE html>
<html lang="<?= escapar($idioma) ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <title><?= textoClientes('Clientes') ?> | MEJR Timesheet</title>

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
          <?= textoClientes('Administración') ?>
        </span>
      </div>
    </div>
  </header>

  <main class="container py-4">

    <div class="d-flex flex-wrap gap-3 justify-content-between align-items-center mb-3">
      <a href="panel.php" class="btn btn-outline-secondary">
        <?= textoClientes('Volver al panel') ?>
      </a>

      <nav aria-label="<?= textoClientes('Idioma') ?>">
        <div class="btn-group">
          <a
            href="clientes.php?idioma=es"
            lang="es"
            class="btn btn-sm <?= $idioma === 'es' ? 'btn-dark' : 'btn-outline-secondary' ?>"
            <?= $idioma === 'es' ? 'aria-current="true"' : '' ?>>
            Español
          </a>

          <a
            href="clientes.php?idioma=en"
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
        <?= textoClientes('Clientes') ?>
      </h1>

      <p class="text-secondary">
        <?= textoClientes('Registra las empresas a las que pertenecen los proyectos.') ?>
      </p>

      <?php if ($creado !== ''): ?>
        <div class="alert alert-success" role="status">
          <?= textoClientes('Cliente registrado:') ?>
          <?= escapar($creado) ?>.
        </div>
      <?php endif; ?>

      <?php if ($error !== ''): ?>
        <div class="alert alert-danger" role="alert">
          <?= textoClientes($error) ?>
        </div>
      <?php endif; ?>

      <form method="post" action="clientes.php">
        <input
          type="hidden"
          name="csrf"
          value="<?= escapar($_SESSION['csrf']) ?>">

        <label for="nombre" class="form-label">
          <?= textoClientes('Nombre del cliente') ?>
        </label>

        <div class="row g-2">
          <div class="col-md-8">
            <input
              id="nombre"
              name="nombre"
              class="form-control"
              maxlength="150"
              value="<?= escapar($nombre) ?>"
              required>
          </div>

          <div class="col-md-auto">
            <button type="submit" class="btn btn-dark">
              <?= textoClientes('Guardar cliente') ?>
            </button>
          </div>
        </div>
      </form>
    </section>

    <section class="bg-white border rounded p-4" aria-labelledby="titulo-listado">
      <h2 id="titulo-listado" class="h5">
        <?= textoClientes('Clientes registrados') ?>
      </h2>

      <?php if (!$clientes): ?>
        <p class="text-secondary mb-0">
          <?= textoClientes('Todavía no hay clientes registrados.') ?>
        </p>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table align-middle mb-0">
            <thead>
              <tr>
                <th scope="col"><?= textoClientes('Cliente') ?></th>
                <th scope="col"><?= textoClientes('Estado') ?></th>
              </tr>
            </thead>

            <tbody>
              <?php foreach ($clientes as $cliente): ?>
                <tr>
                  <td><?= escapar($cliente['nombre']) ?></td>

                  <td>
                    <?= textoClientes(
                        (int) $cliente['activo'] === 1 ? 'Activo' : 'Inactivo'
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