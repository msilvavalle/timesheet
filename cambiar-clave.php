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
    'Idioma' => 'Language',
    'Volver al panel' => 'Back to dashboard',
    'Cambiar contraseña' => 'Change password',
    'Actualiza la contraseña de tu cuenta.' => 'Update your account password.',
    'Contraseña actualizada correctamente.' => 'Your password has been updated successfully.',
    'La próxima vez que ingreses, usa la nueva contraseña.' => 'Use your new password the next time you sign in.',
    'Contraseña actual' => 'Current password',
    'Nueva contraseña' => 'New password',
    'Repetir nueva contraseña' => 'Confirm new password',
    'Usa al menos 12 caracteres.' => 'Use at least 12 characters.',
    'Guardar contraseña' => 'Save password',
    'Solicitud no válida. Vuelve a abrir Cambiar contraseña.' => 'Invalid request. Open Change password again.',
    'Se alcanzó el límite de intentos. Espera dos minutos e intenta nuevamente.' => 'Too many attempts. Wait two minutes and try again.',
    'La contraseña actual no es correcta.' => 'Your current password is incorrect.',
    'Usa una contraseña nueva de al menos 12 caracteres. Si es muy larga, acórtala.' => 'Use a new password with at least 12 characters. If it is too long, shorten it.',
    'Las contraseñas nuevas no coinciden.' => 'The new passwords do not match.',
    'La contraseña nueva debe ser diferente de la actual.' => 'Your new password must be different from your current password.',
    'La cuenta cambió durante la solicitud. Vuelve a iniciar sesión.' => 'The account changed during this request. Sign in again.',
    'No se pudo actualizar la contraseña. Intenta nuevamente.' => 'Your password could not be updated. Try again.'
];

if (
    !isset($_SESSION['usuario_id']) ||
    time() - ($_SESSION['ultima_actividad'] ?? 0) > 1800
) {
    $_SESSION = [];
    header('Location: login.php');
    exit;
}

require __DIR__ . '/config/conexion.php';
require_once __DIR__ . '/config/validar-sesion.php';

// validar-sesion.php ya leyó y validó la cuenta de esta sesión.
$cuenta = $cuentaSesion;

if (
    !$cuenta ||
    !in_array($cuenta['rol'], ['administrador', 'consultor'], true)
) {
    $_SESSION = [];
    header('Location: login.php');
    exit;
}

$_SESSION['ultima_actividad'] = time();
$_SESSION['csrf'] ??= bin2hex(random_bytes(32));

$guardado = $_SESSION['clave_cambiada'] ?? false;
unset($_SESSION['clave_cambiada']);

$error = '';

function escapar($valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

function textoClave(string $texto): string
{
    return escapar(mejrTraducir($texto));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf'] ?? '';

    if (!is_string($token) || !hash_equals($_SESSION['csrf'], $token)) {
        http_response_code(403);
        exit(textoClave('Solicitud no válida. Vuelve a abrir Cambiar contraseña.'));
    }

    $actual = is_string($_POST['actual'] ?? null)
        ? $_POST['actual']
        : '';

    $nueva = is_string($_POST['nueva'] ?? null)
        ? $_POST['nueva']
        : '';

    $confirmacion = is_string($_POST['confirmacion'] ?? null)
        ? $_POST['confirmacion']
        : '';

    $intentos = $_SESSION['intentos_clave']
        ?? ['cantidad' => 0, 'desde' => time()];

    if (time() - $intentos['desde'] >= 120) {
        $intentos = ['cantidad' => 0, 'desde' => time()];
    }

    if ($intentos['cantidad'] >= 5) {
        $error = 'Se alcanzó el límite de intentos. Espera dos minutos e intenta nuevamente.';
    } elseif (
        $actual === '' ||
        strlen($actual) > 72 ||
        !password_verify($actual, $cuenta['password_hash'])
    ) {
        $intentos['cantidad']++;
        $_SESSION['intentos_clave'] = $intentos;
        $error = 'La contraseña actual no es correcta.';
    } elseif (
        preg_match('/\A.{12,}\z/us', $nueva) !== 1 ||
        strlen($nueva) > 72 ||
        str_contains($nueva, "\0")
    ) {
        $error = 'Usa una contraseña nueva de al menos 12 caracteres. Si es muy larga, acórtala.';
    } elseif ($nueva !== $confirmacion) {
        $error = 'Las contraseñas nuevas no coinciden.';
    } elseif (password_verify($nueva, $cuenta['password_hash'])) {
        $error = 'La contraseña nueva debe ser diferente de la actual.';
    } else {
        try {
            $nuevoHash = password_hash($nueva, PASSWORD_DEFAULT);

            $actualizar = $pdo->prepare(
                'UPDATE usuarios
                 SET password_hash = :nueva
                 WHERE id = :id
                   AND activo = 1
                   AND password_hash = :anterior'
            );

            $actualizar->execute([
                'nueva' => $nuevoHash,
                'id' => $_SESSION['usuario_id'],
                'anterior' => $cuenta['password_hash']
            ]);

            if ($actualizar->rowCount() !== 1) {
                $error = 'La cuenta cambió durante la solicitud. Vuelve a iniciar sesión.';
            } else {
                session_regenerate_id(true);

                $_SESSION['csrf'] = bin2hex(random_bytes(32));
                // Esta sesión sigue activa; las demás de la cuenta se cierran
                // porque conservan la versión anterior de la credencial.
                $_SESSION['credencial_version'] = hash('sha256', $nuevoHash);
                unset($_SESSION['intentos_clave']);

                $_SESSION['clave_cambiada'] = true;

                header('Location: cambiar-clave.php', true, 303);
                exit;
            }
        } catch (PDOException $excepcion) {
            error_log(
                'No se pudo actualizar la contraseña del usuario autenticado.'
            );
            $error = 'No se pudo actualizar la contraseña. Intenta nuevamente.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="<?= escapar($idioma) ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <title><?= textoClave('Cambiar contraseña') ?> | MEJR Timesheet</title>

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

      <p class="fw-semibold mb-0">
        <?= escapar($cuenta['nombres'] . ' ' . $cuenta['apellidos']) ?>
      </p>
    </div>
  </header>

  <main class="container py-4">

    <div class="d-flex flex-wrap gap-3 justify-content-between align-items-center mb-3">
      <a href="panel.php" class="btn btn-outline-secondary">
        <?= textoClave('Volver al panel') ?>
      </a>

      <nav aria-label="<?= textoClave('Idioma') ?>">
        <div class="btn-group">
          <a
            href="cambiar-clave.php?idioma=es"
            lang="es"
            class="btn btn-sm <?= $idioma === 'es' ? 'btn-dark' : 'btn-outline-secondary' ?>"
            <?= $idioma === 'es' ? 'aria-current="true"' : '' ?>>
            Español
          </a>

          <a
            href="cambiar-clave.php?idioma=en"
            lang="en"
            class="btn btn-sm <?= $idioma === 'en' ? 'btn-dark' : 'btn-outline-secondary' ?>"
            <?= $idioma === 'en' ? 'aria-current="true"' : '' ?>>
            English
          </a>
        </div>
      </nav>
    </div>

    <div class="row">
      <div class="col-lg-7">
        <section class="bg-white border rounded p-4">

          <h1 class="h4">
            <?= textoClave('Cambiar contraseña') ?>
          </h1>

          <p class="text-secondary">
            <?= textoClave('Actualiza la contraseña de tu cuenta.') ?>
          </p>

          <?php if ($guardado): ?>
            <div class="alert alert-success" role="status">
              <?= textoClave('Contraseña actualizada correctamente.') ?>
              <?= textoClave('La próxima vez que ingreses, usa la nueva contraseña.') ?>
            </div>
          <?php endif; ?>

          <?php if ($error !== ''): ?>
            <div class="alert alert-danger" role="alert">
              <?= textoClave($error) ?>
            </div>
          <?php endif; ?>

          <form method="post" action="cambiar-clave.php">
            <input
              type="hidden"
              name="csrf"
              value="<?= escapar($_SESSION['csrf']) ?>">

            <div class="mb-3">
              <label for="actual" class="form-label">
                <?= textoClave('Contraseña actual') ?>
              </label>

              <input
                id="actual"
                name="actual"
                type="password"
                class="form-control"
                maxlength="72"
                autocomplete="current-password"
                required>
            </div>

            <div class="mb-3">
              <label for="nueva" class="form-label">
                <?= textoClave('Nueva contraseña') ?>
              </label>

              <input
                id="nueva"
                name="nueva"
                type="password"
                class="form-control"
                minlength="12"
                maxlength="72"
                autocomplete="new-password"
                aria-describedby="ayuda-clave"
                required>

              <div id="ayuda-clave" class="form-text">
                <?= textoClave('Usa al menos 12 caracteres.') ?>
              </div>
            </div>

            <div class="mb-4">
              <label for="confirmacion" class="form-label">
                <?= textoClave('Repetir nueva contraseña') ?>
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

            <button type="submit" class="btn btn-dark">
              <?= textoClave('Guardar contraseña') ?>
            </button>
          </form>

        </section>
      </div>
    </div>
  </main>

  <script src="js/mostrar-clave.js?v=2" defer></script>
</body>
</html>