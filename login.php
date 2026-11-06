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
    'Correo electrónico o usuario de administración' => 'Email address or administrator username',
    'Consultores: usa tu correo electrónico. Administración: usa tu usuario habitual.' => 'Consultants: use your email address. Administrators: use your usual username.',
    'Correo, usuario de administración o contraseña incorrectos.' => 'Incorrect email address, administrator username or password.',
    'No se pudo comprobar el acceso. Inténtalo nuevamente.' => 'Access could not be checked. Please try again.',
    'Actualiza la página e intenta nuevamente.' => 'Refresh the page and try again.',
    'Demasiados intentos de ingreso. Espera 15 minutos e intenta nuevamente.' => 'Too many sign-in attempts. Wait 15 minutes and try again.'
];

require __DIR__ . '/config/conexion.php';
require_once __DIR__ . '/config/validar-sesion.php';
require_once __DIR__ . '/config/limite-ingreso.php';

if (isset($_SESSION['usuario_id'])) {
  header('Location: panel.php');
  exit;
}

$_SESSION['csrf'] ??= bin2hex(random_bytes(32));
$error = $_SESSION['aviso_acceso'] ?? '';
unset($_SESSION['aviso_acceso']);

function textoLogin(string $texto): string {
    return htmlspecialchars(mejrTraducir($texto), ENT_QUOTES, 'UTF-8');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf'] ?? '';

    $usuario = is_string($_POST['usuario'] ?? null)
        ? trim($_POST['usuario']) : '';

    $clave = is_string($_POST['password'] ?? null)
        ? $_POST['password'] : '';

    if (!is_string($token) || !hash_equals($_SESSION['csrf'], $token)) {
        $error = 'Actualiza la página e intenta nuevamente.';
    } elseif (
        $usuario === '' || strlen($usuario) > 254 ||
        $clave === '' || strlen($clave) > 72 || str_contains($clave, "\0")
    ) {
        $error = 'Correo, usuario de administración o contraseña incorrectos.';
    } else {
        $cuenta = false;
        $falloConsulta = false;

        try {
            if (filter_var($usuario, FILTER_VALIDATE_EMAIL) !== false) {
                $consulta = $pdo->prepare(
                    "SELECT id, password_hash
                     FROM usuarios
                     WHERE correo = :correo AND rol = 'consultor' AND activo = 1
                     LIMIT 1"
                );
                $consulta->execute(['correo' => strtolower($usuario)]);
                $cuenta = $consulta->fetch();
            } elseif (strlen($usuario) <= 60 && !str_contains($usuario, '@')) {
                $consulta = $pdo->prepare(
                    "SELECT id, password_hash
                     FROM usuarios
                     WHERE usuario = :usuario AND rol = 'administrador' AND activo = 1
                     LIMIT 1"
                );
                $consulta->execute(['usuario' => $usuario]);
                $cuenta = $consulta->fetch();
            }
        } catch (PDOException $e) {
            $falloConsulta = true;
            error_log('MEJR: no se pudo comprobar el acceso.');
            $error = 'No se pudo comprobar el acceso. Inténtalo nuevamente.';
        }

        if (!$falloConsulta) {
            // El límite cuenta por cuenta encontrada, no por el texto escrito.
            $claveLimite = $cuenta
                ? 'cuenta:' . (int) $cuenta['id']
                : 'texto:' . strtolower($usuario);
            $ip = mejrIpCliente();
            $intento = mejrRegistrarIntento($pdo, $ip, $claveLimite);

            if (mejrIngresoBloqueado($pdo, $intento, $ip, $claveLimite)) {
                mejrDescartarIntento($pdo, $intento);
                $error = 'Demasiados intentos de ingreso. Espera 15 minutos e intenta nuevamente.';
            } else {
                if ($cuenta) {
                    $claveCorrecta = password_verify($clave, $cuenta['password_hash']);
                } else {
                    // Mismo costo que verificar una cuenta real: así el tiempo
                    // de respuesta no revela si el correo o el usuario existe.
                    password_hash($clave, PASSWORD_DEFAULT);
                    $claveCorrecta = false;
                }

                if ($claveCorrecta) {
                    mejrLimpiarIntentos($pdo, $claveLimite);
                    $hashVigente = $cuenta['password_hash'];

                    // Lleva el hash al costo actual de PHP. Así todas las cuentas
                    // tardan lo mismo que el cálculo de una cuenta inexistente.
                    if (password_needs_rehash($hashVigente, PASSWORD_DEFAULT)) {
                        $hashNuevo = password_hash($clave, PASSWORD_DEFAULT);

                        try {
                            $rehash = $pdo->prepare(
                                'UPDATE usuarios SET password_hash = :nuevo
                                 WHERE id = :id AND password_hash = :anterior'
                            );
                            $rehash->execute([
                                'nuevo' => $hashNuevo,
                                'id' => $cuenta['id'],
                                'anterior' => $hashVigente
                            ]);

                            if ($rehash->rowCount() === 1) {
                                $hashVigente = $hashNuevo;
                            }
                        } catch (PDOException $e) {
                            error_log('MEJR: no se pudo actualizar el costo de una contraseña.');
                        }
                    }

                    session_regenerate_id(true);

                    $_SESSION = [
                        'usuario_id' => (int) $cuenta['id'],
                        'ultima_actividad' => time(),
                        'csrf' => bin2hex(random_bytes(32)),
                        'credencial_version' => hash('sha256', $hashVigente),
                        'idioma' => $idioma
                    ];

                    header('Location: panel.php', true, 303);
                    exit;
                }

                $error = 'Correo, usuario de administración o contraseña incorrectos.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($idioma, ENT_QUOTES, 'UTF-8') ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <title><?= textoLogin('Ingresar') ?> | MEJR Timesheet</title>

  <link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css"
    rel="stylesheet"
    integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB"
    crossorigin="anonymous">

  <link rel="stylesheet" href="assets/css/mejr-marca.css?v=20261108">
  <link rel="stylesheet" href="assets/css/styles.css?v=20261108">

</head>
<body class="bg-light mejr-login">
  <main class="container">
    <div class="row justify-content-center align-items-center min-vh-100 py-5">
      <div class="col-12 col-sm-9 col-md-7 col-lg-5 col-xl-4">

        <nav
          class="d-flex justify-content-end mb-4"
          aria-label="<?= $idioma === 'en' ? 'Language' : 'Idioma' ?>">

          <div
            class="btn-group"
            role="group"
            aria-label="<?= $idioma === 'en' ? 'Language' : 'Idioma' ?>">

            <a
              href="login.php?idioma=es"
              lang="es"
              class="btn btn-sm <?= $idioma === 'es' ? 'btn-dark' : 'btn-outline-secondary' ?>"
              <?= $idioma === 'es' ? 'aria-current="true"' : '' ?>>
              Español
            </a>

            <a
              href="login.php?idioma=en"
              lang="en"
              class="btn btn-sm <?= $idioma === 'en' ? 'btn-dark' : 'btn-outline-secondary' ?>"
              <?= $idioma === 'en' ? 'aria-current="true"' : '' ?>>
              English
            </a>
          </div>
        </nav>

        <header class="mb-4">
          <p class="fw-bold mb-1">MEJR CONSULTING</p>
          <p class="text-secondary small mb-0">
            <?= textoLogin('Registro de horas') ?>
          </p>
        </header>

        <section class="bg-white border rounded p-4">
          <h1 class="h4 mb-2">
            <?= textoLogin('Iniciar sesión') ?>
          </h1>

          <p class="text-secondary small mb-4">
            <?= textoLogin('Ingresa con tu cuenta asignada.') ?>
          </p>

          <?php if ($error !== ''): ?>
            <p class="text-danger small" role="alert">
              <?= textoLogin($error) ?>
            </p>
          <?php endif; ?>

          <form method="post" action="login.php">
            <input
              type="hidden"
              name="csrf"
              value="<?= htmlspecialchars($_SESSION['csrf'], ENT_QUOTES, 'UTF-8') ?>">

            <div class="mb-3">
              <label for="usuario" class="form-label">
                <?= textoLogin('Correo electrónico o usuario de administración') ?>
              </label>

              <input
                id="usuario"
                name="usuario"
                class="form-control"
                type="text"
                maxlength="254"
                autocomplete="username"
                autocapitalize="none"
                spellcheck="false"
                aria-describedby="ayuda-acceso"
                required>
              <div id="ayuda-acceso" class="form-text">
                <?= textoLogin('Consultores: usa tu correo electrónico. Administración: usa tu usuario habitual.') ?>
              </div>
            </div>

            <div class="mb-4">
              <label for="password" class="form-label">
                <?= textoLogin('Contraseña') ?>
              </label>

              <input
                type="password"
                id="password"
                name="password"
                class="form-control"
                autocomplete="current-password"
                required>
            </div>

            <button type="submit" class="btn btn-dark w-100">
              <?= textoLogin('Ingresar') ?>
            </button>

            <details class="small text-secondary mt-3">
              <summary>
                <?= textoLogin('Olvidé mi contraseña') ?>
              </summary>

              <p class="mt-2 mb-0">
                <?= textoLogin('Solicita el restablecimiento de tu contraseña a administración.') ?>
              </p>
            </details>
          </form>
        </section>

        <footer class="text-center text-secondary small mt-4">
          MEJR Consulting · Timesheet
        </footer>

      </div>
    </div>
  </main>

  <script src="js/mostrar-clave.js?v=2" defer></script>
</body>
</html>
