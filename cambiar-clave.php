<?php
$rolPagina = null;
require __DIR__ . '/config/arranque.php';

$traducciones += [
    'Actualiza la contraseña de tu cuenta.' => 'Update your account password.',
    'Contraseña actualizada. Las demás sesiones abiertas de tu cuenta se cerraron.' => 'Password updated. Other open sessions of your account have been signed out.',
    'Contraseña actual' => 'Current password',
    'Nueva contraseña' => 'New password',
    'Repetir nueva contraseña' => 'Confirm new password',
    'Usa al menos 12 caracteres.' => 'Use at least 12 characters.',
    'Guardar contraseña' => 'Save password',
    'Se alcanzó el límite de intentos. Espera dos minutos e intenta nuevamente.' => 'Too many attempts. Wait two minutes and try again.',
    'La contraseña actual no es correcta.' => 'Your current password is incorrect.',
    'Usa una contraseña nueva de al menos 12 caracteres. Si es muy larga, acórtala.' => 'Use a new password with at least 12 characters. If it is too long, shorten it.',
    'Las contraseñas nuevas no coinciden.' => 'The new passwords do not match.',
    'La contraseña nueva debe ser diferente de la actual.' => 'Your new password must be different from your current password.',
    'La cuenta cambió durante la solicitud. Vuelve a iniciar sesión.' => 'The account changed during this request. Sign in again.',
    'No se pudo actualizar la contraseña. Intenta nuevamente.' => 'Your password could not be updated. Try again.'
];

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfValido()) {
        http_response_code(403);
        exit(texto('Solicitud no válida. Vuelve a abrir la página.'));
    }

    $actual = is_string($_POST['actual'] ?? null) ? $_POST['actual'] : '';
    $nueva = is_string($_POST['nueva'] ?? null) ? $_POST['nueva'] : '';
    $confirmacion = is_string($_POST['confirmacion'] ?? null) ? $_POST['confirmacion'] : '';

    $intentos = $_SESSION['intentos_clave'] ?? ['cantidad' => 0, 'desde' => time()];

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

                avisar('success', 'Contraseña actualizada. Las demás sesiones abiertas de tu cuenta se cerraron.');
                redirigir('cambiar-clave.php');
            }
        } catch (PDOException $excepcion) {
            error_log('MEJR: no se pudo actualizar la contraseña del usuario autenticado.');
            $error = 'No se pudo actualizar la contraseña. Intenta nuevamente.';
        }
    }
}

$tituloPagina = 'Cambiar contraseña';
$seccion = null;
$scriptsPagina = ['mostrar-clave.js?v=2'];
require __DIR__ . '/config/plantilla-inicio.php';
?>
    <div class="row">
      <div class="col-lg-7">
        <div class="mejr-titulo">
          <div>
            <h1 class="h4 mb-1"><?= texto('Cambiar contraseña') ?></h1>
            <p class="text-secondary mb-0"><?= texto('Actualiza la contraseña de tu cuenta.') ?></p>
          </div>
        </div>

        <section class="bg-white border rounded p-4">
          <?php if ($error !== ''): ?>
            <div class="alert alert-danger" role="alert"><?= texto($error) ?></div>
          <?php endif; ?>

          <form method="post" action="cambiar-clave.php">
            <input type="hidden" name="csrf" value="<?= escapar($_SESSION['csrf']) ?>">

            <div class="mb-3">
              <label for="actual" class="form-label"><?= texto('Contraseña actual') ?></label>
              <input id="actual" name="actual" type="password" class="form-control"
                maxlength="72" autocomplete="current-password" required>
            </div>

            <div class="mb-3">
              <label for="nueva" class="form-label"><?= texto('Nueva contraseña') ?></label>
              <input id="nueva" name="nueva" type="password" class="form-control"
                minlength="12" maxlength="72" autocomplete="new-password"
                aria-describedby="ayuda-clave" required>
              <div id="ayuda-clave" class="form-text"><?= texto('Usa al menos 12 caracteres.') ?></div>
            </div>

            <div class="mb-4">
              <label for="confirmacion" class="form-label"><?= texto('Repetir nueva contraseña') ?></label>
              <input id="confirmacion" name="confirmacion" type="password" class="form-control"
                minlength="12" maxlength="72" autocomplete="new-password" required>
            </div>

            <button type="submit" class="btn btn-dark"><?= texto('Guardar contraseña') ?></button>
          </form>
        </section>
      </div>
    </div>
<?php require __DIR__ . '/config/plantilla-fin.php'; ?>
