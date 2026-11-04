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
    'Correo electrónico' => 'Email address',
    'Escribe un correo electrónico válido de hasta 254 caracteres.' => 'Enter a valid email address with up to 254 characters.',
    'Ese correo o nombre de usuario ya está registrado. Revisa los datos.' => 'That email address or username is already registered. Check the details.',
    'El consultor iniciará sesión con este correo electrónico.' => 'The consultant will sign in with this email address.',
    'Referencia de la cuenta. El consultor iniciará sesión con su correo electrónico.' => 'Account reference. The consultant will sign in with their email address.',
    'Correo pendiente' => 'Email required',
    'Correo electrónico del consultor' => 'Consultant email address',
    'Idioma' => 'Language',
    'Acción no válida. Vuelve a abrir el formulario.' => 'Invalid action. Open the form again.',
    'Restablecer contraseña' => 'Reset password',
    'La contraseña actual del consultor no se puede mostrar. Puedes asignarle una nueva si olvidó la anterior.' => 'The consultant’s current password cannot be displayed. You can set a new one if they forgot it.',
    'Nombre de usuario del consultor' => 'Consultant username',
    'Tu contraseña de administrador' => 'Your administrator password',
    'Escribe la contraseña de tu propia cuenta para autorizar el cambio.' => 'Enter your own account password to authorize the change.',
    'Nueva contraseña del consultor' => 'Consultant’s new password',
    'Repetir nueva contraseña' => 'Repeat new password',
    'Usa al menos 12 caracteres.' => 'Use at least 12 characters.',
    'Mostrar' => 'Show',
    'Ocultar' => 'Hide',
    'Se alcanzó el límite de intentos. Espera dos minutos e intenta nuevamente.' => 'The attempt limit has been reached. Wait two minutes and try again.',
    'Tu contraseña de administrador no es correcta.' => 'Your administrator password is incorrect.',
    'Usa una contraseña nueva de al menos 12 caracteres. Si es muy larga, acórtala.' => 'Use a new password with at least 12 characters. If it is too long, shorten it.',
    'Las contraseñas nuevas no coinciden.' => 'The new passwords do not match.',
    'La contraseña nueva debe ser diferente de la actual del consultor.' => 'The new password must differ from the consultant’s current password.',
    'Tu cuenta de administrador cambió. Vuelve a iniciar sesión.' => 'Your administrator account changed. Sign in again.',
    'No se pudo restablecer la contraseña. Intenta nuevamente.' => 'The password could not be reset. Try again.',
    'Contraseña restablecida correctamente.' => 'Password reset successfully.',
    'El consultor deberá usar la nueva contraseña para iniciar sesión. Sus datos, horas y asignaciones se conservan.' => 'The consultant must use the new password to sign in. Their details, hours and assignments are preserved.',
    'Indica al consultor que cambie esta contraseña desde su propia cuenta después de recuperar el acceso.' => 'Ask the consultant to change this password from their own account after regaining access.',
    'Editar consultor' => 'Edit consultant',
    'Acceso reservado para administración.' => 'Access restricted to administrators.',
    'Selecciona un consultor desde Usuarios.' => 'Select a consultant from Users.',
    'Consultor no válido.' => 'Invalid consultant.',
    'Consultor no encontrado. Vuelve a Usuarios.' => 'Consultant not found. Return to Users.',
    'Solicitud no válida. Vuelve a abrir el formulario.' => 'Invalid request. Open the form again.',
    'Nombres y apellidos deben contener letras y un máximo de 100 caracteres por campo.' => 'First and last names must contain letters and have a maximum of 100 characters per field.',
    'El usuario debe tener entre 3 y 60 caracteres: letras sin tilde, números, punto, guion o guion bajo.' => 'The username must contain 3 to 60 characters: unaccented letters, numbers, periods, hyphens or underscores.',
    'La cuenta cambió. Vuelve a Usuarios y selecciónala nuevamente.' => 'The account changed. Return to Users and select it again.',
    'Ese nombre de usuario ya existe. Elige otro.' => 'That username already exists. Choose another one.',
    'No se pudieron guardar los datos. Inténtalo nuevamente.' => 'The details could not be saved. Try again.',
    'Volver a Usuarios' => 'Back to Users',
    'Actualiza los datos y la especialidad del consultor.' => 'Update the consultant’s details and specialty.',
    'Nombres' => 'First names',
    'Apellidos' => 'Last names',
    'Nombre de usuario' => 'Username',
    'Si lo cambias, el consultor deberá usar el nuevo usuario para iniciar sesión. Su contraseña seguirá siendo la misma.' => 'If you change it, the consultant must use the new username to sign in. Their password will remain the same.',
    'Guardar cambios' => 'Save changes',
    'Cancelar' => 'Cancel',
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

$consulta = $pdo->prepare(
    'SELECT nombres, apellidos, rol, password_hash FROM usuarios WHERE id = :id AND activo = 1'
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
    exit(textoEditarUsuario('Acceso reservado para administración.'));
}

$_SESSION['ultima_actividad'] = time();
$_SESSION['csrf'] ??= bin2hex(random_bytes(32));

function escapar($valor): string {
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

function textoEditarUsuario(string $texto): string {
    return escapar(mejrTraducir($texto));
}

$idTexto = $_GET['id'] ?? '';

if (!is_string($idTexto) || !preg_match('/\A[1-9][0-9]{0,9}\z/', $idTexto)) {
    http_response_code(400);
    exit(textoEditarUsuario('Selecciona un consultor desde Usuarios.'));
}

$consultorId = filter_var($idTexto, FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1, 'max_range' => 4294967295]
]);

if ($consultorId === false) {
    http_response_code(400);
    exit(textoEditarUsuario('Consultor no válido.'));
}

$buscar = $pdo->prepare(
    "SELECT nombres, apellidos, especialidad, usuario, correo FROM usuarios
     WHERE id = :id AND rol = 'consultor'"
);
$buscar->execute(['id' => $consultorId]);
$datos = $buscar->fetch();

if (!$datos) {
    http_response_code(404);
    exit(textoEditarUsuario('Consultor no encontrado. Vuelve a Usuarios.'));
}

$datos['correo'] = (string) ($datos['correo'] ?? '');
$datosConsultor = $datos;
$errores = [];
$erroresClave = [];
$claveRestablecida = ($_SESSION['consultor_clave_restablecida'] ?? null) === $consultorId;
unset($_SESSION['consultor_clave_restablecida']);

$accion = $_POST['accion'] ?? 'datos';

if ($_SERVER['REQUEST_METHOD'] === 'POST' &&
    (!is_string($accion) || !in_array($accion, ['datos', 'restablecer_clave'], true))) {
    http_response_code(400);
    exit(textoEditarUsuario('Acción no válida. Vuelve a abrir el formulario.'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $accion === 'restablecer_clave') {
    $token = $_POST['csrf'] ?? '';

    if (!is_string($token) || !hash_equals($_SESSION['csrf'], $token)) {
        http_response_code(403);
        exit(textoEditarUsuario('Solicitud no válida. Vuelve a abrir el formulario.'));
    }

    $claveAdmin = is_string($_POST['clave_admin'] ?? null) ? $_POST['clave_admin'] : '';
    $nueva = is_string($_POST['nueva_clave'] ?? null) ? $_POST['nueva_clave'] : '';
    $confirmacion = is_string($_POST['confirmacion_clave'] ?? null) ? $_POST['confirmacion_clave'] : '';

    $intentos = $_SESSION['intentos_restablecer_clave']
        ?? ['cantidad' => 0, 'desde' => time()];

    if (time() - $intentos['desde'] >= 120) {
        $intentos = ['cantidad' => 0, 'desde' => time()];
        $_SESSION['intentos_restablecer_clave'] = $intentos;
    }

    if ($intentos['cantidad'] >= 5) {
        $erroresClave[] = 'Se alcanzó el límite de intentos. Espera dos minutos e intenta nuevamente.';
    } elseif (
        $claveAdmin === '' || strlen($claveAdmin) > 72 || str_contains($claveAdmin, "\0") ||
        !password_verify($claveAdmin, $cuenta['password_hash'])
    ) {
        $intentos['cantidad']++;
        $_SESSION['intentos_restablecer_clave'] = $intentos;
        $erroresClave[] = 'Tu contraseña de administrador no es correcta.';
    } elseif (
        preg_match('/\A.{12,}\z/us', $nueva) !== 1 ||
        strlen($nueva) > 72 || str_contains($nueva, "\0")
    ) {
        $erroresClave[] = 'Usa una contraseña nueva de al menos 12 caracteres. Si es muy larga, acórtala.';
    } elseif ($nueva !== $confirmacion) {
        $erroresClave[] = 'Las contraseñas nuevas no coinciden.';
    } else {
        try {
            $nuevoHash = password_hash($nueva, PASSWORD_DEFAULT);
            $pdo->beginTransaction();

            // Confirma nuevamente la cuenta que autoriza el restablecimiento.
            $bloquearAdmin = $pdo->prepare(
                "SELECT password_hash FROM usuarios
                 WHERE id = :id AND rol = 'administrador' AND activo = 1 FOR UPDATE"
            );
            $bloquearAdmin->execute(['id' => $_SESSION['usuario_id']]);
            $adminActual = $bloquearAdmin->fetch();

            if (!$adminActual || !hash_equals($cuenta['password_hash'], $adminActual['password_hash'])) {
                throw new DomainException('Tu cuenta de administrador cambió. Vuelve a iniciar sesión.');
            }

            // Se limita el cambio a un consultor distinto del administrador.
            $bloquearConsultor = $pdo->prepare(
                "SELECT password_hash FROM usuarios
                 WHERE id = :id AND rol = 'consultor' AND id <> :administrador FOR UPDATE"
            );
            $bloquearConsultor->execute([
                'id' => $consultorId,
                'administrador' => $_SESSION['usuario_id']
            ]);
            $consultorActual = $bloquearConsultor->fetch();

            if (!$consultorActual) {
                throw new DomainException('La cuenta cambió. Vuelve a Usuarios y selecciónala nuevamente.');
            }

            if (password_verify($nueva, $consultorActual['password_hash'])) {
                throw new DomainException('La contraseña nueva debe ser diferente de la actual del consultor.');
            }

            $restablecer = $pdo->prepare(
                "UPDATE usuarios SET password_hash = :password_hash
                 WHERE id = :id AND rol = 'consultor' AND id <> :administrador"
            );
            $restablecer->execute([
                'password_hash' => $nuevoHash,
                'id' => $consultorId,
                'administrador' => $_SESSION['usuario_id']
            ]);

            if ($restablecer->rowCount() !== 1) {
                throw new DomainException('La cuenta cambió. Vuelve a Usuarios y selecciónala nuevamente.');
            }

            $pdo->commit();

            // Solo se conserva el identificador del consultor, nunca la clave.
            unset($_SESSION['intentos_restablecer_clave']);
            $_SESSION['consultor_clave_restablecida'] = $consultorId;

            error_log(
                'MEJR: administrador ' . (int) $_SESSION['usuario_id'] .
                ' restableció la contraseña del consultor ' . $consultorId . '.'
            );

            header(
                'Location: usuarios-editar.php?id=' . $consultorId .
                '&idioma=' . rawurlencode($idioma) . '#restablecer-clave',
                true,
                303
            );
            exit;
        } catch (DomainException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $erroresClave[] = $e->getMessage();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('MEJR: no se pudo restablecer la contraseña de un consultor.');
            $erroresClave[] = 'No se pudo restablecer la contraseña. Intenta nuevamente.';
        }
    }

    // Las claves no se vuelven a mostrar ni se guardan en la sesión.
    unset($claveAdmin, $nueva, $confirmacion, $nuevoHash);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $accion === 'datos') {
    $token = $_POST['csrf'] ?? '';

    if (!is_string($token) || !hash_equals($_SESSION['csrf'], $token)) {
        http_response_code(403);
        exit(textoEditarUsuario('Solicitud no válida. Vuelve a abrir el formulario.'));
    }

    foreach (['nombres', 'apellidos', 'especialidad', 'usuario', 'correo'] as $campo) {
        $datos[$campo] = is_string($_POST[$campo] ?? null)
            ? trim($_POST[$campo]) : '';
    }

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

    if (!$errores) {
        try {
            $pdo->beginTransaction();

            $bloqueo = $pdo->prepare(
                "SELECT id FROM usuarios
                 WHERE id = :id AND rol = 'consultor' FOR UPDATE"
            );
            $bloqueo->execute(['id' => $consultorId]);

            if (!$bloqueo->fetch()) {
                throw new DomainException('La cuenta ya no corresponde a un consultor.');
            }

            $actualizar = $pdo->prepare(
                "UPDATE usuarios
                 SET nombres = :nombres,
                     apellidos = :apellidos,
                     especialidad = :especialidad,
                     usuario = :usuario,
                     correo = :correo
                 WHERE id = :id AND rol = 'consultor'"
            );
            $actualizar->execute([
                'nombres' => $datos['nombres'],
                'apellidos' => $datos['apellidos'],
                'especialidad' => $datos['especialidad'],
                'usuario' => $datos['usuario'],
                'correo' => $datos['correo'],
                'id' => $consultorId
            ]);

            $pdo->commit();

            $_SESSION['usuarios_aviso'] = [
                'tipo' => 'success',
                'texto' => 'Datos del consultor guardados correctamente.'
            ];

            header('Location: usuarios.php', true, 303);
            exit;
        } catch (DomainException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $errores[] = 'La cuenta cambió. Vuelve a Usuarios y selecciónala nuevamente.';
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            if ((int) ($e->errorInfo[1] ?? 0) === 1062) {
                $errores[] = 'Ese correo o nombre de usuario ya está registrado. Revisa los datos.';
            } else {
                error_log('MEJR: no se pudieron actualizar los datos de un consultor.');
                $errores[] = 'No se pudieron guardar los datos. Inténtalo nuevamente.';
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

  <title><?= textoEditarUsuario('Editar consultor') ?> | MEJR Timesheet</title>

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

      <span>
        <?= escapar($cuenta['nombres'] . ' ' . $cuenta['apellidos']) ?>
      </span>
    </div>
  </header>

  <main class="container py-4">

    <div class="d-flex flex-wrap gap-3 justify-content-between align-items-center mb-3">
      <a href="usuarios.php" class="btn btn-outline-secondary">
        <?= textoEditarUsuario('Volver a Usuarios') ?>
      </a>

      <nav aria-label="<?= textoEditarUsuario('Idioma') ?>">
        <div class="btn-group">
          <a
            href="usuarios-editar.php?id=<?= escapar($consultorId) ?>&amp;idioma=es"
            lang="es"
            class="btn btn-sm <?= $idioma === 'es' ? 'btn-dark' : 'btn-outline-secondary' ?>"
            <?= $idioma === 'es' ? 'aria-current="true"' : '' ?>>
            Español
          </a>

          <a
            href="usuarios-editar.php?id=<?= escapar($consultorId) ?>&amp;idioma=en"
            lang="en"
            class="btn btn-sm <?= $idioma === 'en' ? 'btn-dark' : 'btn-outline-secondary' ?>"
            <?= $idioma === 'en' ? 'aria-current="true"' : '' ?>>
            English
          </a>
        </div>
      </nav>
    </div>

    <section class="bg-white border rounded p-4 mb-4" aria-labelledby="titulo-editar">
      <h1 id="titulo-editar" class="h4">
        <?= textoEditarUsuario('Editar consultor') ?>
      </h1>

      <p class="text-secondary">
        <?= textoEditarUsuario('Actualiza los datos y la especialidad del consultor.') ?>
      </p>

      <?php if ($errores): ?>
        <div class="alert alert-danger" role="alert">
          <ul class="mb-0">
            <?php foreach ($errores as $error): ?>
              <li><?= textoEditarUsuario($error) ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <form
        method="post"
        action="usuarios-editar.php?id=<?= escapar($consultorId) ?>&amp;idioma=<?= escapar($idioma) ?>">

        <input type="hidden" name="accion" value="datos">

        <input
          type="hidden"
          name="csrf"
          value="<?= escapar($_SESSION['csrf']) ?>">

        <div class="row g-3">
          <div class="col-md-6">
            <label for="nombres" class="form-label">
              <?= textoEditarUsuario('Nombres') ?>
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
              <?= textoEditarUsuario('Apellidos') ?>
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
              <?= textoEditarUsuario('Especialidad') ?>
            </label>

            <input
              id="especialidad"
              name="especialidad"
              type="text"
              class="form-control"
              maxlength="150"
              autocomplete="off"
              value="<?= escapar($datos['especialidad']) ?>"
              placeholder="<?= textoEditarUsuario('Ejemplo: SAP ABAP o SAP FI/CO.') ?>"
              aria-describedby="ayuda-especialidad">

            <div id="ayuda-especialidad" class="form-text">
              <?= textoEditarUsuario('Campo opcional. Máximo 150 caracteres.') ?>
            </div>
          </div>

          <div class="col-12">
            <label for="correo" class="form-label">
              <?= textoEditarUsuario('Correo electrónico') ?>
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
              <?= textoEditarUsuario('El consultor iniciará sesión con este correo electrónico.') ?>
            </div>
          </div>

          <div class="col-12">
            <label for="usuario" class="form-label">
              <?= textoEditarUsuario('Nombre de usuario') ?>
            </label>

            <input
              id="usuario"
              name="usuario"
              class="form-control"
              minlength="3"
              maxlength="60"
              autocomplete="off"
              aria-describedby="ayuda-usuario"
              value="<?= escapar($datos['usuario']) ?>"
              required>

            <div id="ayuda-usuario" class="form-text">
              <?= textoEditarUsuario('Referencia de la cuenta. El consultor iniciará sesión con su correo electrónico.') ?>
            </div>
          </div>
        </div>

        <div class="d-flex flex-wrap gap-2 mt-4">
          <button type="submit" class="btn btn-dark">
            <?= textoEditarUsuario('Guardar cambios') ?>
          </button>

          <a href="usuarios.php" class="btn btn-outline-secondary">
            <?= textoEditarUsuario('Cancelar') ?>
          </a>
        </div>
      </form>
    </section>

    <section id="restablecer-clave" class="bg-white border rounded p-4" aria-labelledby="titulo-clave">
      <h2 id="titulo-clave" class="h5">
        <?= textoEditarUsuario('Restablecer contraseña') ?>
      </h2>

      <p class="text-secondary">
        <?= textoEditarUsuario('La contraseña actual del consultor no se puede mostrar. Puedes asignarle una nueva si olvidó la anterior.') ?>
      </p>

      <p class="mb-1 fw-semibold">
        <?= escapar($datosConsultor['nombres'] . ' ' . $datosConsultor['apellidos']) ?>
      </p>
      <p class="small text-secondary mb-4">
        <?= textoEditarUsuario('Correo electrónico del consultor') ?>:
        <strong><?= $datosConsultor['correo'] !== ''
            ? escapar($datosConsultor['correo'])
            : textoEditarUsuario('Correo pendiente') ?></strong>
      </p>

      <?php if ($claveRestablecida): ?>
        <div class="alert alert-success" role="status">
          <p class="fw-semibold mb-1">
            <?= textoEditarUsuario('Contraseña restablecida correctamente.') ?>
          </p>
          <p class="mb-0">
            <?= textoEditarUsuario('El consultor deberá usar la nueva contraseña para iniciar sesión. Sus datos, horas y asignaciones se conservan.') ?>
          </p>
        </div>
      <?php endif; ?>

      <?php if ($erroresClave): ?>
        <div class="alert alert-danger" role="alert">
          <ul class="mb-0">
            <?php foreach ($erroresClave as $errorClave): ?>
              <li><?= textoEditarUsuario($errorClave) ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <form method="post" action="usuarios-editar.php?id=<?= escapar($consultorId) ?>&amp;idioma=<?= escapar($idioma) ?>#restablecer-clave">
        <input type="hidden" name="accion" value="restablecer_clave">
        <input type="hidden" name="csrf" value="<?= escapar($_SESSION['csrf']) ?>">

        <div class="row g-3">
          <div class="col-12 col-lg-6">
            <label id="etiqueta-admin" for="clave_admin" class="form-label">
              <?= textoEditarUsuario('Tu contraseña de administrador') ?>
            </label>
            <div class="input-group">
              <input id="clave_admin" name="clave_admin" type="password" class="form-control"
                maxlength="72" autocomplete="current-password" aria-describedby="ayuda-admin" required>
              <button id="ver-admin" type="button" class="btn btn-outline-secondary"
                data-ver-clave="clave_admin" data-mostrar="<?= textoEditarUsuario('Mostrar') ?>"
                data-ocultar="<?= textoEditarUsuario('Ocultar') ?>"
                aria-controls="clave_admin" aria-pressed="false" aria-labelledby="ver-admin etiqueta-admin">
                <?= textoEditarUsuario('Mostrar') ?>
              </button>
            </div>
            <div id="ayuda-admin" class="form-text">
              <?= textoEditarUsuario('Escribe la contraseña de tu propia cuenta para autorizar el cambio.') ?>
            </div>
          </div>
          <div class="col-lg-6 d-none d-lg-block" aria-hidden="true"></div>
          <div class="col-md-6">
            <label id="etiqueta-nueva" for="nueva_clave" class="form-label">
              <?= textoEditarUsuario('Nueva contraseña del consultor') ?>
            </label>
            <div class="input-group">
              <input id="nueva_clave" name="nueva_clave" type="password" class="form-control"
                minlength="12" maxlength="72" autocomplete="new-password" aria-describedby="ayuda-nueva" required>
              <button id="ver-nueva" type="button" class="btn btn-outline-secondary"
                data-ver-clave="nueva_clave" data-mostrar="<?= textoEditarUsuario('Mostrar') ?>"
                data-ocultar="<?= textoEditarUsuario('Ocultar') ?>"
                aria-controls="nueva_clave" aria-pressed="false" aria-labelledby="ver-nueva etiqueta-nueva">
                <?= textoEditarUsuario('Mostrar') ?>
              </button>
            </div>
            <div id="ayuda-nueva" class="form-text">
              <?= textoEditarUsuario('Usa al menos 12 caracteres.') ?>
            </div>
          </div>
          <div class="col-md-6">
            <label id="etiqueta-confirmacion" for="confirmacion_clave" class="form-label">
              <?= textoEditarUsuario('Repetir nueva contraseña') ?>
            </label>
            <div class="input-group">
              <input id="confirmacion_clave" name="confirmacion_clave" type="password" class="form-control"
                minlength="12" maxlength="72" autocomplete="new-password" required>
              <button id="ver-confirmacion" type="button" class="btn btn-outline-secondary"
                data-ver-clave="confirmacion_clave" data-mostrar="<?= textoEditarUsuario('Mostrar') ?>"
                data-ocultar="<?= textoEditarUsuario('Ocultar') ?>"
                aria-controls="confirmacion_clave" aria-pressed="false" aria-labelledby="ver-confirmacion etiqueta-confirmacion">
                <?= textoEditarUsuario('Mostrar') ?>
              </button>
            </div>
          </div>
        </div>

        <p class="small text-secondary mt-3">
          <?= textoEditarUsuario('Indica al consultor que cambie esta contraseña desde su propia cuenta después de recuperar el acceso.') ?>
        </p>

        <button type="submit" class="btn btn-dark">
          <?= textoEditarUsuario('Restablecer contraseña') ?>
        </button>
      </form>
    </section>
  </main>

  <script>
    document.querySelectorAll('[data-ver-clave]').forEach((boton) => {
      const campo = document.getElementById(boton.dataset.verClave);
      if (!campo) return;

      boton.addEventListener('click', () => {
        const mostrar = campo.type === 'password';
        campo.type = mostrar ? 'text' : 'password';
        boton.textContent = mostrar ? boton.dataset.ocultar : boton.dataset.mostrar;
        boton.setAttribute('aria-pressed', String(mostrar));
      });
    });
  </script>
</body>
</html>