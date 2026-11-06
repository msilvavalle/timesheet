<?php
session_name('mejr_timesheet');
session_start([
    'use_strict_mode' => true,
    'gc_maxlifetime' => 1800,
    'use_only_cookies' => true,
    'cookie_httponly' => true,
    'cookie_samesite' => 'Lax',
    'cookie_secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'
]);
header('Cache-Control: no-store');

require_once __DIR__ . '/config/idioma.php';

$traducciones += [
    'Correo electrónico' => 'Email address',
    'Escribe un correo electrónico válido de hasta 254 caracteres.' => 'Enter a valid email address with up to 254 characters.',
    'Ese correo o nombre de usuario ya está registrado. Revisa los datos.' => 'That email address or username is already registered. Check the details.',
    'El consultor iniciará sesión con este correo electrónico.' => 'The consultant will sign in with this email address.',
    'Referencia de la cuenta. El consultor iniciará sesión con su correo electrónico.' => 'Account reference. The consultant will sign in with their email address.',
    'Correo pendiente' => 'Email required',
    'Nombre, apellido, correo o usuario' => 'First name, last name, email or username',
    'Puedes escribir parte del nombre, apellido, correo o usuario.' => 'Enter all or part of a first name, last name, email address or username.',
    'La búsqueda no es válida. Usa un máximo de 254 caracteres.' => 'Invalid search. Use a maximum of 254 characters.',
    'Completa el correo de los consultores marcados como Correo pendiente para que puedan iniciar sesión.' => 'Enter an email address for consultants marked Email required so they can sign in.',
    'Idioma' => 'Language',
    'Usuarios' => 'Users',
    'Administración' => 'Administration',
    'Acceso reservado para administración.' => 'Access restricted to administrators.',
    'Solicitud no válida. Vuelve a abrir Usuarios.' => 'Invalid request. Open Users again.',
    'Datos no válidos. Vuelve a abrir Usuarios.' => 'Invalid data. Open Users again.',
    'Usuario no válido.' => 'Invalid user.',
    'Consultor activado correctamente.' => 'Consultant activated successfully.',
    'Datos del consultor guardados correctamente.' => 'Consultant details saved successfully.',
    'Consultor desactivado. Sus horas y asignaciones se conservan.' => 'Consultant deactivated. Their hours and assignments have been preserved.',
    'No se realizó el cambio. Revisa el estado actual: solo puedes activar o desactivar consultores.' => 'No change was made. Check the current status: you can only activate or deactivate consultants.',
    'No se pudo guardar el cambio. Inténtalo nuevamente.' => 'The change could not be saved. Try again.',
    'Volver al panel' => 'Back to dashboard',
    'Administra el acceso de los consultores.' => 'Manage consultant access.',
    'Desactivar una cuenta conserva sus horas y asignaciones.' => 'Deactivating an account preserves its hours and assignments.',
    'Crear consultor' => 'Create consultant',
    'Listado visible solo para administración.' => 'This list is visible to administrators only.',
    'Nombre y apellido' => 'Full name',
    'Usuario' => 'Username',
    'Rol' => 'Role',
    'Estado' => 'Status',
    'Acción' => 'Action',
    'Administrador' => 'Administrator',
    'Consultor' => 'Consultant',
    'Activo' => 'Active',
    'Inactivo' => 'Inactive',
    'Editar' => 'Edit',
    '¿Confirmas el cambio de acceso de este consultor? Sus horas se conservarán.' => 'Confirm this change to the consultant’s access? Their hours will be preserved.',
    'Desactivar' => 'Deactivate',
    'Activar' => 'Activate',
    'Cuenta de administrador' => 'Administrator account',
    'Buscar consultor' => 'Find a consultant',
    'Nombre, apellido o usuario' => 'First name, last name or username',
    'Puedes escribir parte del nombre o apellido, o el nombre de usuario.' => 'Enter all or part of a first name, last name or username.',
    'Buscar' => 'Search',
    'Limpiar' => 'Clear',
    'Resultados' => 'Results',
    'No se encontraron usuarios para esta búsqueda.' => 'No users match this search.',
    'No hay usuarios registrados.' => 'There are no registered users.',
    'La búsqueda no es válida. Usa un máximo de 120 caracteres.' => 'Invalid search. Use a maximum of 120 characters.',
    'Especialidad' => 'Specialty',
    'Sin especificar' => 'Not specified',
    'No aplica' => 'Not applicable'
];

if (!isset($_SESSION['usuario_id']) || time() - ($_SESSION['ultima_actividad'] ?? 0) > 1800) {
    $_SESSION = [];
    header('Location: login.php');
    exit;
}

require __DIR__ . '/config/conexion.php';
require_once __DIR__ . '/config/validar-sesion.php';

// validar-sesion.php ya leyó y validó la cuenta de esta sesión.
$cuenta = $cuentaSesion;

if (!$cuenta) {
    $_SESSION = [];
    header('Location: login.php');
    exit;
}

if ($cuenta['rol'] !== 'administrador') {
    http_response_code(403);
    exit(textoUsuarios('Acceso reservado para administración.'));
}

$_SESSION['ultima_actividad'] = time();
$_SESSION['csrf'] ??= bin2hex(random_bytes(32));

function escapar($valor): string {
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

function textoUsuarios(string $texto): string {
    return escapar(mejrTraducir($texto));
}

$busqueda = $_GET['buscar'] ?? '';

if (!is_string($busqueda)) {
    http_response_code(400);
    exit(textoUsuarios('La búsqueda no es válida. Usa un máximo de 254 caracteres.'));
}

$busqueda = trim($busqueda);

if (preg_match('/\A[^\p{C}]{0,254}\z/u', $busqueda) !== 1) {
    http_response_code(400);
    exit(textoUsuarios('La búsqueda no es válida. Usa un máximo de 254 caracteres.'));
}

$urlListado = 'usuarios.php?' . http_build_query(
    ['idioma' => $idioma, 'buscar' => $busqueda],
    '', '&', PHP_QUERY_RFC3986
);

$urlEspanol = 'usuarios.php?' . http_build_query(
    ['idioma' => 'es', 'buscar' => $busqueda],
    '', '&', PHP_QUERY_RFC3986
);

$urlIngles = 'usuarios.php?' . http_build_query(
    ['idioma' => 'en', 'buscar' => $busqueda],
    '', '&', PHP_QUERY_RFC3986
);

$urlLimpiar = 'usuarios.php?' . http_build_query(
    ['idioma' => $idioma],
    '', '&', PHP_QUERY_RFC3986
);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf'] ?? '';

    if (!is_string($token) || !hash_equals($_SESSION['csrf'], $token)) {
        http_response_code(403);
        exit(textoUsuarios('Solicitud no válida. Vuelve a abrir Usuarios.'));
    }

    $accion = $_POST['accion'] ?? '';
    $idTexto = $_POST['usuario_id'] ?? '';

    if (!is_string($accion) || !in_array($accion, ['activar', 'desactivar'], true)
        || !is_string($idTexto) || !preg_match('/\A[1-9][0-9]{0,9}\z/', $idTexto)) {
        http_response_code(400);
        exit(textoUsuarios('Datos no válidos. Vuelve a abrir Usuarios.'));
    }

    $usuarioId = filter_var($idTexto, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1, 'max_range' => 4294967295]
    ]);

    if ($usuarioId === false) {
        http_response_code(400);
        exit(textoUsuarios('Usuario no válido.'));
    }

    $nuevoEstado = $accion === 'activar' ? 1 : 0;

    try {
        $cambio = $pdo->prepare(
            "UPDATE usuarios SET activo = :nuevo
             WHERE id = :id AND rol = 'consultor'
             AND activo = :anterior AND id <> :administrador"
        );

        $cambio->execute([
            'nuevo' => $nuevoEstado,
            'id' => $usuarioId,
            'anterior' => 1 - $nuevoEstado,
            'administrador' => $_SESSION['usuario_id']
        ]);

        if ($cambio->rowCount() === 1) {
            error_log(
                'MEJR: administrador ' . (int) $_SESSION['usuario_id'] .
                ($nuevoEstado === 1 ? ' activó' : ' desactivó') .
                ' al consultor ' . $usuarioId . '.'
            );

            $_SESSION['usuarios_aviso'] = [
                'tipo' => 'success',
                'texto' => $nuevoEstado === 1
                    ? 'Consultor activado correctamente.'
                    : 'Consultor desactivado. Sus horas y asignaciones se conservan.'
            ];
        } else {
            $_SESSION['usuarios_aviso'] = [
                'tipo' => 'warning',
                'texto' => 'No se realizó el cambio. Revisa el estado actual: solo puedes activar o desactivar consultores.'
            ];
        }
    } catch (PDOException $e) {
        error_log('MEJR: no se pudo actualizar el estado de un consultor.');

        $_SESSION['usuarios_aviso'] = [
            'tipo' => 'danger',
            'texto' => 'No se pudo guardar el cambio. Inténtalo nuevamente.'
        ];
    }

    header('Location: ' . $urlListado, true, 303);
    exit;
}

$aviso = $_SESSION['usuarios_aviso'] ?? null;
unset($_SESSION['usuarios_aviso']);

$sql = 'SELECT id, nombres, apellidos, especialidad, usuario, correo, rol, activo
        FROM usuarios';
$condiciones = [];
$parametros = [];

if ($busqueda !== '') {
    $palabras = preg_split('/\s+/u', $busqueda, -1, PREG_SPLIT_NO_EMPTY);

    foreach ($palabras as $indice => $palabra) {
        $patron = '%' . strtr($palabra, [
            '!' => '!!',
            '%' => '!%',
            '_' => '!_'
        ]) . '%';

        $condiciones[] = "(nombres LIKE :nombres_$indice ESCAPE '!'"
            . " OR apellidos LIKE :apellidos_$indice ESCAPE '!'"
            . " OR usuario LIKE :usuario_$indice ESCAPE '!'"
            . " OR correo LIKE :correo_$indice ESCAPE '!')";

        $parametros['nombres_' . $indice] = $patron;
        $parametros['apellidos_' . $indice] = $patron;
        $parametros['usuario_' . $indice] = $patron;
        $parametros['correo_' . $indice] = $patron;
    }

    if ($condiciones) {
        $sql .= ' WHERE ' . implode(' AND ', $condiciones);
    }
}

$sql .= ' ORDER BY apellidos, nombres, id';

$consultaUsuarios = $pdo->prepare($sql);
$consultaUsuarios->execute($parametros);
$usuarios = $consultaUsuarios->fetchAll();
?>
<!DOCTYPE html>
<html lang="<?= escapar($idioma) ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <title><?= textoUsuarios('Usuarios') ?> | MEJR Timesheet</title>

  <link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css"
    rel="stylesheet"
    integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB"
    crossorigin="anonymous">

  <link rel="stylesheet" href="assets/css/mejr-marca.css?v=20261105">
  <link rel="stylesheet" href="assets/css/styles.css?v=20261105">
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
          <?= textoUsuarios('Administración') ?>
        </span>
      </div>
    </div>
  </header>

  <main class="container py-4">

    <div class="d-flex flex-wrap gap-3 justify-content-between align-items-center mb-3">
      <a href="panel.php" class="btn btn-outline-secondary">
        <?= textoUsuarios('Volver al panel') ?>
      </a>

      <nav aria-label="<?= textoUsuarios('Idioma') ?>">
        <div class="btn-group">
          <a
            href="<?= escapar($urlEspanol) ?>"
            lang="es"
            class="btn btn-sm <?= $idioma === 'es' ? 'btn-dark' : 'btn-outline-secondary' ?>"
            <?= $idioma === 'es' ? 'aria-current="true"' : '' ?>>
            Español
          </a>

          <a
            href="<?= escapar($urlIngles) ?>"
            lang="en"
            class="btn btn-sm <?= $idioma === 'en' ? 'btn-dark' : 'btn-outline-secondary' ?>"
            <?= $idioma === 'en' ? 'aria-current="true"' : '' ?>>
            English
          </a>
        </div>
      </nav>
    </div>

    <?php if ($aviso): ?>
      <div class="alert alert-<?= escapar($aviso['tipo']) ?>" role="status">
        <?= textoUsuarios($aviso['texto']) ?>
      </div>
    <?php endif; ?>

    <section class="bg-white border rounded p-4" aria-labelledby="titulo-usuarios">
      <h1 id="titulo-usuarios" class="h4">
        <?= textoUsuarios('Usuarios') ?>
      </h1>

      <p class="text-secondary">
        <?= textoUsuarios('Administra el acceso de los consultores.') ?>
        <?= textoUsuarios('Desactivar una cuenta conserva sus horas y asignaciones.') ?>
      </p>

      <a href="usuarios-crear.php" class="btn btn-dark mb-3">
        <?= textoUsuarios('Crear consultor') ?>
      </a>

      <form method="get" action="usuarios.php" class="mb-4" role="search">
        <input type="hidden" name="idioma" value="<?= escapar($idioma) ?>">

        <label for="buscar" class="form-label">
          <?= textoUsuarios('Buscar consultor') ?>
        </label>

        <div class="row g-2 align-items-start">
          <div class="col-12 col-md-7 col-lg-6">
            <input
              id="buscar"
              name="buscar"
              type="search"
              class="form-control"
              maxlength="254"
              value="<?= escapar($busqueda) ?>"
              placeholder="<?= textoUsuarios('Nombre, apellido, correo o usuario') ?>"
              aria-describedby="ayuda-busqueda"
              autocomplete="off">
            <div id="ayuda-busqueda" class="form-text">
              <?= textoUsuarios('Puedes escribir parte del nombre, apellido, correo o usuario.') ?>
            </div>
          </div>

          <div class="col-auto">
            <button type="submit" class="btn btn-dark">
              <?= textoUsuarios('Buscar') ?>
            </button>
          </div>

          <div class="col-auto">
            <a href="<?= escapar($urlLimpiar) ?>" class="btn btn-outline-secondary">
              <?= textoUsuarios('Limpiar') ?>
            </a>
          </div>
        </div>
      </form>

      <p class="small text-secondary">
        <?= textoUsuarios('Resultados') ?>: <strong><?= count($usuarios) ?></strong>
      </p>

      <?php if (array_filter($usuarios, static fn($fila) =>
          $fila['rol'] === 'consultor' && empty($fila['correo']))): ?>
        <p class="alert alert-warning" role="status">
          <?= textoUsuarios('Completa el correo de los consultores marcados como Correo pendiente para que puedan iniciar sesión.') ?>
        </p>
      <?php endif; ?>

      <div class="table-responsive">
        <table class="table align-middle mb-0">
          <caption>
            <?= textoUsuarios('Listado visible solo para administración.') ?>
          </caption>

          <thead>
            <tr>
              <th scope="col"><?= textoUsuarios('Nombre y apellido') ?></th>
              <th scope="col"><?= textoUsuarios('Especialidad') ?></th>
              <th scope="col"><?= textoUsuarios('Correo electrónico') ?></th>
              <th scope="col"><?= textoUsuarios('Usuario') ?></th>
              <th scope="col"><?= textoUsuarios('Rol') ?></th>
              <th scope="col"><?= textoUsuarios('Estado') ?></th>
              <th scope="col"><?= textoUsuarios('Acción') ?></th>
            </tr>
          </thead>

          <tbody>
            <?php if (!$usuarios): ?>
              <tr>
                <td colspan="7" class="text-secondary py-4">
                  <?= textoUsuarios(
                      $busqueda !== ''
                          ? 'No se encontraron usuarios para esta búsqueda.'
                          : 'No hay usuarios registrados.'
                  ) ?>
                </td>
              </tr>
            <?php endif; ?>

            <?php foreach ($usuarios as $usuario): ?>
              <?php
              $activo = (int) $usuario['activo'] === 1;
              $especialidad = trim((string) ($usuario['especialidad'] ?? ''));
              ?>

              <tr>
                <td>
                  <?= escapar($usuario['nombres'] . ' ' . $usuario['apellidos']) ?>
                </td>

                <td class="text-break">
                  <?php if ($usuario['rol'] !== 'consultor'): ?>
                    <span class="small text-secondary">
                      <?= textoUsuarios('No aplica') ?>
                    </span>
                  <?php elseif ($especialidad === ''): ?>
                    <span class="small text-secondary">
                      <?= textoUsuarios('Sin especificar') ?>
                    </span>
                  <?php else: ?>
                    <?= escapar($especialidad) ?>
                  <?php endif; ?>
                </td>

                <td class="text-break">
                  <?php if ($usuario['rol'] === 'consultor' && empty($usuario['correo'])): ?>
                    <span class="badge text-bg-warning">
                      <?= textoUsuarios('Correo pendiente') ?>
                    </span>
                  <?php elseif (!empty($usuario['correo'])): ?>
                    <?= escapar($usuario['correo']) ?>
                  <?php else: ?>
                    <span class="small text-secondary"><?= textoUsuarios('No aplica') ?></span>
                  <?php endif; ?>
                </td>

                <td><?= escapar($usuario['usuario']) ?></td>

                <td>
                  <?= textoUsuarios(
                      $usuario['rol'] === 'administrador' ? 'Administrador' : 'Consultor'
                  ) ?>
                </td>

                <td>
                  <?= textoUsuarios($activo ? 'Activo' : 'Inactivo') ?>
                </td>

                <td>
                  <?php if ($usuario['rol'] === 'consultor'): ?>
                    <a
                      href="usuarios-editar.php?id=<?= escapar($usuario['id']) ?>"
                      class="btn btn-sm btn-outline-secondary mb-2">
                      <?= textoUsuarios('Editar') ?>
                    </a>

                    <form
                      method="post"
                      action="<?= escapar($urlListado) ?>"
                      data-confirmar="<?= textoUsuarios('¿Confirmas el cambio de acceso de este consultor? Sus horas se conservarán.') ?>">

                      <input
                        type="hidden"
                        name="csrf"
                        value="<?= escapar($_SESSION['csrf']) ?>">

                      <input
                        type="hidden"
                        name="usuario_id"
                        value="<?= escapar($usuario['id']) ?>">

                      <input
                        type="hidden"
                        name="accion"
                        value="<?= $activo ? 'desactivar' : 'activar' ?>">

                      <button
                        type="submit"
                        class="btn btn-sm <?= $activo ? 'btn-outline-danger' : 'btn-outline-dark' ?>"
                        aria-label="<?= escapar(
                            mejrTraducir($activo ? 'Desactivar' : 'Activar') . ': ' .
                            $usuario['nombres'] . ' ' . $usuario['apellidos']
                        ) ?>">
                        <?= textoUsuarios($activo ? 'Desactivar' : 'Activar') ?>
                      </button>
                    </form>
                  <?php else: ?>
                    <span class="small text-secondary">
                      <?= textoUsuarios('Cuenta de administrador') ?>
                    </span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>
  </main>

  <script src="js/confirmar.js?v=1" defer></script>
</body>
</html>
