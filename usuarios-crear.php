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
    'Idioma' => 'Language',
    'Crear consultor' => 'Create consultant',
    'Acceso reservado para administración.' => 'Access restricted to administrators.',
    'Solicitud no válida. Vuelve a abrir el formulario.' => 'Invalid request. Open the form again.',
    'Nombres y apellidos deben contener letras y un máximo de 100 caracteres por campo.' => 'First and last names must contain letters and have a maximum of 100 characters per field.',
    'El usuario debe tener entre 3 y 60 caracteres: letras sin tilde, números, punto, guion o guion bajo.' => 'The username must contain 3 to 60 characters: unaccented letters, numbers, periods, hyphens or underscores.',
    'Usa una contraseña de al menos 12 caracteres. Si es muy larga, acórtala.' => 'Use a password with at least 12 characters. If it is too long, shorten it.',
    'Las contraseñas no coinciden.' => 'The passwords do not match.',
    'Ese nombre de usuario ya existe. Elige otro.' => 'That username already exists. Choose another one.',
    'No se pudo guardar la cuenta. Intenta nuevamente.' => 'The account could not be saved. Try again.',
    'Volver a Usuarios' => 'Back to Users',
    'La cuenta tendrá acceso al panel del consultor.' => 'This account will have access to the consultant dashboard.',
    'Consultor creado:' => 'Consultant created:',
    'Ya puede iniciar sesión.' => 'They can now sign in.',
    'Nombres' => 'First names',
    'Apellidos' => 'Last names',
    'Nombre de usuario' => 'Username',
    'Ejemplo: ana.perez. Entre 3 y 60 caracteres, sin espacios ni tildes.' => 'Example: ana.perez. Use 3 to 60 characters without spaces or accents.',
    'Contraseña' => 'Password',
    'Usa al menos 12 caracteres. Evita contraseñas fáciles de adivinar.' => 'Use at least 12 characters. Avoid passwords that are easy to guess.',
    'Repetir contraseña' => 'Confirm password',
    'Guardar consultor' => 'Save consultant',
    'Especialidad' => 'Specialty',
    'Ejemplo: SAP ABAP o SAP FI/CO.' => 'Example: SAP ABAP or SAP FI/CO.',
    'Campo opcional. Máximo 150 caracteres.' => 'Optional field. Maximum 150 characters.',
    'La especialidad debe tener un máximo de 150 caracteres y no puede contener saltos de línea.' => 'The specialty must have a maximum of 150 characters and cannot contain line breaks.'
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
    exit(textoCrearUsuario('Acceso reservado para administración.'));
}

$_SESSION['ultima_actividad'] = time();

function escapar($valor): string {
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

function textoCrearUsuario(string $texto): string {
    return escapar(mejrTraducir($texto));
}

$_SESSION['csrf'] ??= bin2hex(random_bytes(32));

$errores = [];
$datos = [
    'nombres' => '',
    'apellidos' => '',
    'especialidad' => '',
    'correo' => '',
    'usuario' => ''
];
$creado = $_SESSION['consultor_creado'] ?? '';
unset($_SESSION['consultor_creado']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf'] ?? '';

    if (!is_string($token) || !hash_equals($_SESSION['csrf'], $token)) {
        http_response_code(403);
        exit(textoCrearUsuario('Solicitud no válida. Vuelve a abrir el formulario.'));
    }

    foreach ($datos as $campo => $valor) {
        $datos[$campo] = is_string($_POST[$campo] ?? null)
            ? trim($_POST[$campo])
            : '';
    }

    $password = is_string($_POST['password'] ?? null)
        ? $_POST['password']
        : '';

    $confirmacion = is_string($_POST['confirmacion'] ?? null)
        ? $_POST['confirmacion']
        : '';

    $datos['correo'] = strtolower($datos['correo']);

    if (strlen($datos['correo']) > 254 ||
        filter_var($datos['correo'], FILTER_VALIDATE_EMAIL) === false) {
        $errores[] = 'Escribe un correo electrónico válido de hasta 254 caracteres.';
    }

    foreach (['nombres', 'apellidos'] as $campo) {
        if (!preg_match("/\A[\p{L}\p{M} .'\x{2019}-]{1,100}\z/u", $datos[$campo])
            || !preg_match('/\p{L}/u', $datos[$campo])) {
            $errores[] = 'Nombres y apellidos deben contener letras y un máximo de 100 caracteres por campo.';
            break;
        }
    }

    if (preg_match('/\A[^\p{C}]{0,150}\z/u', $datos['especialidad']) !== 1) {
        $errores[] = 'La especialidad debe tener un máximo de 150 caracteres y no puede contener saltos de línea.';
    }

    if (!preg_match('/\A[a-zA-Z0-9._-]{3,60}\z/', $datos['usuario'])) {
        $errores[] = 'El usuario debe tener entre 3 y 60 caracteres: letras sin tilde, números, punto, guion o guion bajo.';
    }

    if (
        preg_match('/\A.{12,}\z/us', $password) !== 1
        || strlen($password) > 72
        || str_contains($password, "\0")
    ) {
        $errores[] = 'Usa una contraseña de al menos 12 caracteres. Si es muy larga, acórtala.';
    }

    if ($password !== $confirmacion) {
        $errores[] = 'Las contraseñas no coinciden.';
    }

    if (!$errores) {
        try {
            $insertar = $pdo->prepare(
                "INSERT INTO usuarios
                    (nombres, apellidos, especialidad, usuario, correo, password_hash, rol, activo)
                 VALUES
                    (:nombres, :apellidos, :especialidad, :usuario, :correo, :password_hash, 'consultor', 1)"
            );

            $insertar->execute([
                'nombres' => $datos['nombres'],
                'apellidos' => $datos['apellidos'],
                'especialidad' => $datos['especialidad'],
                'usuario' => $datos['usuario'],
                'correo' => $datos['correo'],
                'password_hash' => password_hash($password, PASSWORD_DEFAULT)
            ]);

            error_log(
                'MEJR: administrador ' . (int) $_SESSION['usuario_id'] .
                ' creó el consultor ' . (int) $pdo->lastInsertId() . '.'
            );

            $_SESSION['consultor_creado'] = $datos['correo'];

            header('Location: usuarios-crear.php', true, 303);
            exit;
        } catch (PDOException $error) {
            if ((int) ($error->errorInfo[1] ?? 0) === 1062) {
                $errores[] = 'Ese correo o nombre de usuario ya está registrado. Revisa los datos.';
            } else {
                error_log($error->getMessage());
                $errores[] = 'No se pudo guardar la cuenta. Intenta nuevamente.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="<?= escapar($idioma) ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <title><?= textoCrearUsuario('Crear consultor') ?> | MEJR Timesheet</title>

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

      <span>
        <?= escapar($cuenta['nombres'] . ' ' . $cuenta['apellidos']) ?>
      </span>
    </div>
  </header>

  <main class="container py-4">

    <div class="d-flex flex-wrap gap-3 justify-content-between align-items-center mb-3">
      <a href="usuarios.php" class="btn btn-outline-secondary">
        <?= textoCrearUsuario('Volver a Usuarios') ?>
      </a>

      <nav aria-label="<?= textoCrearUsuario('Idioma') ?>">
        <div class="btn-group">
          <a
            href="usuarios-crear.php?idioma=es"
            lang="es"
            class="btn btn-sm <?= $idioma === 'es' ? 'btn-dark' : 'btn-outline-secondary' ?>"
            <?= $idioma === 'es' ? 'aria-current="true"' : '' ?>>
            Español
          </a>

          <a
            href="usuarios-crear.php?idioma=en"
            lang="en"
            class="btn btn-sm <?= $idioma === 'en' ? 'btn-dark' : 'btn-outline-secondary' ?>"
            <?= $idioma === 'en' ? 'aria-current="true"' : '' ?>>
            English
          </a>
        </div>
      </nav>
    </div>

    <section class="bg-white border rounded p-4">
      <h1 class="h4">
        <?= textoCrearUsuario('Crear consultor') ?>
      </h1>

      <p class="text-secondary">
        <?= textoCrearUsuario('La cuenta tendrá acceso al panel del consultor.') ?>
      </p>

      <?php if ($creado !== ''): ?>
        <div class="alert alert-success" role="status">
          <?= textoCrearUsuario('Consultor creado:') ?>
          <?= escapar($creado) ?>.
          <?= textoCrearUsuario('Ya puede iniciar sesión.') ?>
        </div>
      <?php endif; ?>

      <?php if ($errores): ?>
        <div class="alert alert-danger" role="alert">
          <ul class="mb-0">
            <?php foreach ($errores as $error): ?>
              <li><?= textoCrearUsuario($error) ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <form method="post" action="usuarios-crear.php">
        <input
          type="hidden"
          name="csrf"
          value="<?= escapar($_SESSION['csrf']) ?>">

        <div class="row g-3">
          <div class="col-md-6">
            <label for="nombres" class="form-label">
              <?= textoCrearUsuario('Nombres') ?>
            </label>

            <input
              id="nombres"
              name="nombres"
              class="form-control"
              maxlength="100"
              value="<?= escapar($datos['nombres']) ?>"
              required>
          </div>

          <div class="col-md-6">
            <label for="apellidos" class="form-label">
              <?= textoCrearUsuario('Apellidos') ?>
            </label>

            <input
              id="apellidos"
              name="apellidos"
              class="form-control"
              maxlength="100"
              value="<?= escapar($datos['apellidos']) ?>"
              required>
          </div>

          <div class="col-12">
            <label for="especialidad" class="form-label">
              <?= textoCrearUsuario('Especialidad') ?>
            </label>

            <input
              id="especialidad"
              name="especialidad"
              type="text"
              class="form-control"
              maxlength="150"
              autocomplete="off"
              value="<?= escapar($datos['especialidad']) ?>"
              placeholder="<?= textoCrearUsuario('Ejemplo: SAP ABAP o SAP FI/CO.') ?>"
              aria-describedby="ayuda-especialidad">

            <div id="ayuda-especialidad" class="form-text">
              <?= textoCrearUsuario('Campo opcional. Máximo 150 caracteres.') ?>
            </div>
          </div>

          <div class="col-12">
            <label for="correo" class="form-label">
              <?= textoCrearUsuario('Correo electrónico') ?>
            </label>
            <input
              id="correo"
              name="correo"
              type="email"
              class="form-control"
              maxlength="254"
              autocomplete="off"
              autocapitalize="none"
              spellcheck="false"
              value="<?= escapar($datos['correo']) ?>"
              aria-describedby="ayuda-correo"
              required>
            <div id="ayuda-correo" class="form-text">
              <?= textoCrearUsuario('El consultor iniciará sesión con este correo electrónico.') ?>
            </div>
          </div>

          <div class="col-12">
            <label for="usuario" class="form-label">
              <?= textoCrearUsuario('Nombre de usuario') ?>
            </label>

            <input
              id="usuario"
              name="usuario"
              class="form-control"
              minlength="3"
              maxlength="60"
              autocomplete="off"
              value="<?= escapar($datos['usuario']) ?>"
              aria-describedby="ayuda-usuario"
              required>

            <div id="ayuda-usuario" class="form-text">
              <?= textoCrearUsuario('Referencia de la cuenta. El consultor iniciará sesión con su correo electrónico.') ?>
            </div>
          </div>

          <div class="col-md-6">
            <label for="password" class="form-label">
              <?= textoCrearUsuario('Contraseña') ?>
            </label>

            <input
              id="password"
              name="password"
              type="password"
              class="form-control"
              minlength="12"
              maxlength="72"
              autocomplete="new-password"
              aria-describedby="ayuda-password"
              required>

            <div id="ayuda-password" class="form-text">
              <?= textoCrearUsuario('Usa al menos 12 caracteres. Evita contraseñas fáciles de adivinar.') ?>
            </div>
          </div>

          <div class="col-md-6">
            <label for="confirmacion" class="form-label">
              <?= textoCrearUsuario('Repetir contraseña') ?>
            </label>

            <input
              id="confirmacion"
              name="confirmacion"
              type="password"
              class="form-control"
              minlength="12"
              maxlength="72"
              autocomplete="new-password"
              required>
          </div>
        </div>

        <button type="submit" class="btn btn-dark mt-4">
          <?= textoCrearUsuario('Guardar consultor') ?>
        </button>
      </form>
    </section>
  </main>

  <script src="js/mostrar-clave.js?v=2" defer></script>
</body>
</html>