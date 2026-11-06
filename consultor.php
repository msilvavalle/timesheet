<?php
// Ficha del consultor. Sin ?id= es el alta; con ?id= reúne datos, proyectos
// asignados, horas del mes, restablecer contraseña y estado.
// Reemplaza a usuarios-crear.php, usuarios-editar.php y al lado consultor de asignaciones.php.
$rolPagina = 'administrador';
require __DIR__ . '/config/arranque.php';
require_once __DIR__ . '/config/asignaciones.php';

$traducciones += [
    'Nuevo consultor' => 'New consultant',
    'Consultor no válido.' => 'Invalid consultant.',
    'Consultor no encontrado. Vuelve a Consultores.' => 'Consultant not found. Return to Consultants.',
    'Acción no válida. Vuelve a abrir la ficha.' => 'Invalid action. Open the profile again.',
    'Datos' => 'Details',
    'Proyectos asignados' => 'Assigned projects',
    'Contraseña' => 'Password',
    'Nombres' => 'First names',
    'Apellidos' => 'Last names',
    'Especialidad' => 'Specialty',
    'Ejemplo: SAP ABAP o SAP FI/CO.' => 'Example: SAP ABAP or SAP FI/CO.',
    'Campo opcional. Máximo 150 caracteres.' => 'Optional field. Maximum 150 characters.',
    'Correo electrónico' => 'Email address',
    'El consultor iniciará sesión con este correo electrónico.' => 'The consultant will sign in with this email address.',
    'Nombre de usuario' => 'Username',
    'Referencia interna de la cuenta: entre 3 y 60 caracteres, sin espacios ni tildes.' => 'Internal account reference: 3 to 60 characters, without spaces or accents.',
    'Repetir contraseña' => 'Confirm password',
    'Usa al menos 12 caracteres. Evita contraseñas fáciles de adivinar.' => 'Use at least 12 characters. Avoid passwords that are easy to guess.',
    'Crear consultor' => 'Create consultant',
    'Escribe un correo electrónico válido de hasta 254 caracteres.' => 'Enter a valid email address with up to 254 characters.',
    'Nombres y apellidos deben contener letras y un máximo de 100 caracteres por campo.' => 'First and last names must contain letters and have a maximum of 100 characters per field.',
    'La especialidad debe tener un máximo de 150 caracteres y no puede contener saltos de línea.' => 'The specialty must have a maximum of 150 characters and cannot contain line breaks.',
    'El usuario debe tener entre 3 y 60 caracteres: letras sin tilde, números, punto, guion o guion bajo.' => 'The username must contain 3 to 60 characters: unaccented letters, numbers, periods, hyphens or underscores.',
    'Usa una contraseña de al menos 12 caracteres. Si es muy larga, acórtala.' => 'Use a password with at least 12 characters. If it is too long, shorten it.',
    'Las contraseñas no coinciden.' => 'The passwords do not match.',
    'Ese correo o nombre de usuario ya está registrado. Revisa los datos.' => 'That email address or username is already registered. Check the details.',
    'No se pudo guardar la cuenta. Intenta nuevamente.' => 'The account could not be saved. Try again.',
    'Consultor creado. Ahora asígnale proyectos:' => 'Consultant created. Now assign projects to:',
    'Datos del consultor guardados.' => 'Consultant details saved.',
    'La cuenta cambió. Vuelve a Consultores y selecciónala nuevamente.' => 'The account changed. Return to Consultants and select it again.',
    'Proyectos asignados guardados.' => 'Assigned projects saved.',
    'Marca los proyectos en los que este consultor puede registrar horas. Desmarcar no borra sus horas.' => 'Select the projects this consultant can log hours on. Unselecting does not delete their hours.',
    'Buscar proyecto' => 'Find a project',
    'No hay proyectos activos. Créalos en Clientes y proyectos.' => 'There are no active projects. Create them in Clients and projects.',
    'Guardar proyectos' => 'Save projects',
    'Horas de' => 'Hours for',
    'Sin horas registradas este mes.' => 'No hours logged this month.',
    'Ver todas sus horas' => 'View all their hours',
    'La contraseña actual del consultor no se puede mostrar. Puedes asignarle una nueva si olvidó la anterior.' => 'The consultant’s current password cannot be displayed. You can set a new one if they forgot it.',
    'Tu contraseña de administrador' => 'Your administrator password',
    'Escribe la contraseña de tu propia cuenta para autorizar el cambio.' => 'Enter your own account password to authorize the change.',
    'Nueva contraseña del consultor' => 'Consultant’s new password',
    'Repetir nueva contraseña' => 'Repeat new password',
    'Usa al menos 12 caracteres.' => 'Use at least 12 characters.',
    'Restablecer contraseña' => 'Reset password',
    'Se alcanzó el límite de intentos. Espera dos minutos e intenta nuevamente.' => 'The attempt limit has been reached. Wait two minutes and try again.',
    'Tu contraseña de administrador no es correcta.' => 'Your administrator password is incorrect.',
    'Usa una contraseña nueva de al menos 12 caracteres. Si es muy larga, acórtala.' => 'Use a new password with at least 12 characters. If it is too long, shorten it.',
    'Las contraseñas nuevas no coinciden.' => 'The new passwords do not match.',
    'La contraseña nueva debe ser diferente de la actual del consultor.' => 'The new password must differ from the consultant’s current password.',
    'Tu cuenta de administrador cambió. Vuelve a iniciar sesión.' => 'Your administrator account changed. Sign in again.',
    'No se pudo restablecer la contraseña. Intenta nuevamente.' => 'The password could not be reset. Try again.',
    'Contraseña restablecida. El consultor debe usar la nueva para iniciar sesión.' => 'Password reset. The consultant must use the new one to sign in.',
    'Indica al consultor que cambie esta contraseña desde su propia cuenta después de recuperar el acceso.' => 'Ask the consultant to change this password from their own account after regaining access.',
    'Mostrar' => 'Show',
    'Ocultar' => 'Hide',
    'Desactivar una cuenta conserva sus horas y asignaciones, y cierra sus sesiones abiertas.' => 'Deactivating an account preserves its hours and assignments and signs out its open sessions.',
    'Consultor activado.' => 'Consultant activated.',
    'Consultor desactivado. Sus horas y asignaciones se conservan.' => 'Consultant deactivated. Their hours and assignments have been preserved.',
    'No se realizó el cambio. Revisa el estado actual.' => 'No change was made. Check the current status.',
    '¿Confirmas el cambio de acceso de este consultor? Sus horas se conservarán.' => 'Confirm this change to the consultant’s access? Their hours will be preserved.',
    'Secciones de la ficha' => 'Profile sections'
];

$idTexto = $_GET['id'] ?? null;
$consultorId = $idTexto === null ? null : validarId($idTexto);

if ($consultorId === false) {
    http_response_code(400);
    exit(texto('Consultor no válido.'));
}

$consultor = null;

if ($consultorId !== null) {
    $buscar = $pdo->prepare(
        "SELECT id, nombres, apellidos, especialidad, usuario, correo, activo, password_hash
         FROM usuarios WHERE id = :id AND rol = 'consultor'"
    );
    $buscar->execute(['id' => $consultorId]);
    $consultor = $buscar->fetch();

    if (!$consultor) {
        http_response_code(404);
        exit(texto('Consultor no encontrado. Vuelve a Consultores.'));
    }
}

$urlFicha = static fn(string $ancla = ''): string =>
    'consultor.php?id=' . $consultorId . ($ancla !== '' ? '#' . $ancla : '');

$campos = ['nombres', 'apellidos', 'especialidad', 'correo', 'usuario'];
$datos = array_fill_keys($campos, '');

if ($consultor) {
    foreach ($campos as $campo) {
        $datos[$campo] = (string) ($consultor[$campo] ?? '');
    }
}

$errores = ['datos' => [], 'clave' => [], 'proyectos' => []];

function validarDatosConsultor(array $datos): array
{
    $errores = [];

    if (strlen($datos['correo']) > 254 || filter_var($datos['correo'], FILTER_VALIDATE_EMAIL) === false) {
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

    return $errores;
}

function claveNuevaValida(string $clave): bool
{
    return preg_match('/\A.{12,}\z/us', $clave) === 1 && strlen($clave) <= 72 && !str_contains($clave, "\0");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfValido()) {
        http_response_code(403);
        exit(texto('Solicitud no válida. Vuelve a abrir la página.'));
    }

    $accion = $_POST['accion'] ?? '';
    $permitidas = $consultor ? ['datos', 'proyectos', 'clave', 'estado'] : ['crear'];

    if (!is_string($accion) || !in_array($accion, $permitidas, true)) {
        http_response_code(400);
        exit(texto('Acción no válida. Vuelve a abrir la ficha.'));
    }

    $adminId = (int) $_SESSION['usuario_id'];

    if ($accion === 'crear' || $accion === 'datos') {
        foreach ($campos as $campo) {
            $datos[$campo] = is_string($_POST[$campo] ?? null) ? trim($_POST[$campo]) : '';
        }

        $datos['correo'] = strtolower($datos['correo']);
        $errores['datos'] = validarDatosConsultor($datos);

        if ($accion === 'crear') {
            $clave = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
            $confirmacion = is_string($_POST['confirmacion'] ?? null) ? $_POST['confirmacion'] : '';

            if (!claveNuevaValida($clave)) {
                $errores['datos'][] = 'Usa una contraseña de al menos 12 caracteres. Si es muy larga, acórtala.';
            } elseif ($clave !== $confirmacion) {
                $errores['datos'][] = 'Las contraseñas no coinciden.';
            }
        }

        if (!$errores['datos']) {
            try {
                if ($accion === 'crear') {
                    $pdo->prepare(
                        "INSERT INTO usuarios
                            (nombres, apellidos, especialidad, usuario, correo, password_hash, rol, activo)
                         VALUES
                            (:nombres, :apellidos, :especialidad, :usuario, :correo, :password_hash, 'consultor', 1)"
                    )->execute($datos + ['password_hash' => password_hash($clave, PASSWORD_DEFAULT)]);

                    $nuevoId = (int) $pdo->lastInsertId();
                    error_log('MEJR: administrador ' . $adminId . ' creó el consultor ' . $nuevoId . '.');
                    avisar('success', 'Consultor creado. Ahora asígnale proyectos:', $datos['nombres'] . ' ' . $datos['apellidos']);
                    redirigir('consultor.php?id=' . $nuevoId . '#proyectos');
                }

                $actualizar = $pdo->prepare(
                    "UPDATE usuarios
                     SET nombres = :nombres, apellidos = :apellidos, especialidad = :especialidad,
                         usuario = :usuario, correo = :correo
                     WHERE id = :id AND rol = 'consultor'"
                );
                $actualizar->execute($datos + ['id' => $consultorId]);

                error_log('MEJR: administrador ' . $adminId . ' actualizó los datos del consultor ' . $consultorId . '.');
                avisar('success', 'Datos del consultor guardados.');
                redirigir($urlFicha('datos'));
            } catch (PDOException $e) {
                if ((int) ($e->errorInfo[1] ?? 0) === 1062) {
                    $errores['datos'][] = 'Ese correo o nombre de usuario ya está registrado. Revisa los datos.';
                } else {
                    error_log('MEJR: no se pudo guardar un consultor.');
                    $errores['datos'][] = 'No se pudo guardar la cuenta. Intenta nuevamente.';
                }
            }
        }
    }

    if ($accion === 'proyectos') {
        $marcados = $_POST['proyectos'] ?? [];
        $marcados = is_array($marcados) ? array_filter(array_map('validarId', $marcados)) : [];

        try {
            [$activadas, $desactivadas] = mejrGuardarAsignaciones($pdo, 'consultor', $consultorId, $marcados);
            error_log(
                'MEJR: administrador ' . $adminId . ' cambió las asignaciones del consultor ' . $consultorId .
                ' (+' . $activadas . ' / -' . $desactivadas . ').'
            );
            avisar('success', 'Proyectos asignados guardados.');
            redirigir($urlFicha('proyectos'));
        } catch (Throwable $e) {
            error_log('MEJR: no se pudieron guardar asignaciones de un consultor.');
            $errores['proyectos'][] = 'No se pudo guardar el cambio. Inténtalo nuevamente.';
        }
    }

    if ($accion === 'clave') {
        $claveAdmin = is_string($_POST['clave_admin'] ?? null) ? $_POST['clave_admin'] : '';
        $nueva = is_string($_POST['nueva_clave'] ?? null) ? $_POST['nueva_clave'] : '';
        $confirmacion = is_string($_POST['confirmacion_clave'] ?? null) ? $_POST['confirmacion_clave'] : '';

        $intentos = $_SESSION['intentos_restablecer_clave'] ?? ['cantidad' => 0, 'desde' => time()];

        if (time() - $intentos['desde'] >= 120) {
            $intentos = ['cantidad' => 0, 'desde' => time()];
            $_SESSION['intentos_restablecer_clave'] = $intentos;
        }

        if ($intentos['cantidad'] >= 5) {
            $errores['clave'][] = 'Se alcanzó el límite de intentos. Espera dos minutos e intenta nuevamente.';
        } elseif ($claveAdmin === '' || strlen($claveAdmin) > 72 || str_contains($claveAdmin, "\0")
            || !password_verify($claveAdmin, $cuenta['password_hash'])) {
            $intentos['cantidad']++;
            $_SESSION['intentos_restablecer_clave'] = $intentos;
            $errores['clave'][] = 'Tu contraseña de administrador no es correcta.';
        } elseif (!claveNuevaValida($nueva)) {
            $errores['clave'][] = 'Usa una contraseña nueva de al menos 12 caracteres. Si es muy larga, acórtala.';
        } elseif ($nueva !== $confirmacion) {
            $errores['clave'][] = 'Las contraseñas nuevas no coinciden.';
        } else {
            try {
                $nuevoHash = password_hash($nueva, PASSWORD_DEFAULT);
                $pdo->beginTransaction();

                // Confirma nuevamente la cuenta que autoriza el restablecimiento.
                $bloquearAdmin = $pdo->prepare(
                    "SELECT password_hash FROM usuarios
                     WHERE id = :id AND rol = 'administrador' AND activo = 1 FOR UPDATE"
                );
                $bloquearAdmin->execute(['id' => $adminId]);
                $adminActual = $bloquearAdmin->fetch();

                if (!$adminActual || !hash_equals($cuenta['password_hash'], $adminActual['password_hash'])) {
                    throw new DomainException('Tu cuenta de administrador cambió. Vuelve a iniciar sesión.');
                }

                $bloquearConsultor = $pdo->prepare(
                    "SELECT password_hash FROM usuarios
                     WHERE id = :id AND rol = 'consultor' AND id <> :administrador FOR UPDATE"
                );
                $bloquearConsultor->execute(['id' => $consultorId, 'administrador' => $adminId]);
                $consultorActual = $bloquearConsultor->fetch();

                if (!$consultorActual) {
                    throw new DomainException('La cuenta cambió. Vuelve a Consultores y selecciónala nuevamente.');
                }

                if (password_verify($nueva, $consultorActual['password_hash'])) {
                    throw new DomainException('La contraseña nueva debe ser diferente de la actual del consultor.');
                }

                $pdo->prepare(
                    "UPDATE usuarios SET password_hash = :password_hash
                     WHERE id = :id AND rol = 'consultor' AND id <> :administrador"
                )->execute(['password_hash' => $nuevoHash, 'id' => $consultorId, 'administrador' => $adminId]);

                $pdo->commit();
                unset($_SESSION['intentos_restablecer_clave']);

                error_log('MEJR: administrador ' . $adminId . ' restableció la contraseña del consultor ' . $consultorId . '.');
                avisar('success', 'Contraseña restablecida. El consultor debe usar la nueva para iniciar sesión.');
                redirigir($urlFicha('clave'));
            } catch (DomainException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $errores['clave'][] = $e->getMessage();
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log('MEJR: no se pudo restablecer la contraseña de un consultor.');
                $errores['clave'][] = 'No se pudo restablecer la contraseña. Intenta nuevamente.';
            }
        }

        unset($claveAdmin, $nueva, $confirmacion, $nuevoHash);
    }

    if ($accion === 'estado') {
        $nuevoEstado = ($_POST['estado'] ?? '') === 'activar' ? 1 : 0;
        $volver = ($_POST['volver'] ?? '') === 'lista' ? 'consultores.php' : $urlFicha('estado');

        try {
            $cambio = $pdo->prepare(
                "UPDATE usuarios SET activo = :nuevo
                 WHERE id = :id AND rol = 'consultor' AND activo = :anterior AND id <> :administrador"
            );
            $cambio->execute([
                'nuevo' => $nuevoEstado,
                'id' => $consultorId,
                'anterior' => 1 - $nuevoEstado,
                'administrador' => $adminId
            ]);

            if ($cambio->rowCount() === 1) {
                error_log(
                    'MEJR: administrador ' . $adminId .
                    ($nuevoEstado === 1 ? ' activó' : ' desactivó') . ' al consultor ' . $consultorId . '.'
                );
                avisar('success', $nuevoEstado === 1 ? 'Consultor activado.' : 'Consultor desactivado. Sus horas y asignaciones se conservan.');
            } else {
                avisar('warning', 'No se realizó el cambio. Revisa el estado actual.');
            }
        } catch (PDOException $e) {
            error_log('MEJR: no se pudo actualizar el estado de un consultor.');
            avisar('danger', 'No se pudo guardar el cambio. Inténtalo nuevamente.');
        }

        redirigir($volver);
    }
}

// Datos para las secciones de la ficha.
$opciones = [];
$asignados = [];
$horasMes = [];
$totalMes = 0;
$ahora = mejrAhora();

if ($consultor) {
    $opciones = mejrOpcionesAsignables($pdo, 'consultor');
    $asignados = $_SERVER['REQUEST_METHOD'] === 'POST' && ($accion ?? '') === 'proyectos'
        ? array_map('intval', $marcados)
        : mejrAsignacionesActivas($pdo, 'consultor', $consultorId);

    $consultaHoras = $pdo->prepare(
        'SELECT r.proyecto_id, c.nombre AS cliente, p.nombre AS proyecto, SUM(r.horas) AS horas
         FROM registros_horas r
         INNER JOIN proyectos p ON p.id = r.proyecto_id
         INNER JOIN clientes c ON c.id = p.cliente_id
         WHERE r.consultor_id = :id AND r.fecha >= :inicio AND r.fecha < :fin
         GROUP BY r.proyecto_id, c.nombre, p.nombre
         ORDER BY c.nombre, p.nombre'
    );
    $consultaHoras->execute([
        'id' => $consultorId,
        'inicio' => $ahora->format('Y-m-01'),
        'fin' => $ahora->modify('first day of next month')->format('Y-m-d')
    ]);
    $horasMes = $consultaHoras->fetchAll();

    foreach ($horasMes as $fila) {
        $totalMes += mejrEscalar($fila['horas'], 2);
    }
}

$nombreConsultor = $consultor ? $consultor['nombres'] . ' ' . $consultor['apellidos'] : '';
$activo = $consultor && (int) $consultor['activo'] === 1;

$tituloPagina = $consultor ? 'Consultores' : 'Nuevo consultor';
$seccion = 'consultores';
$parametrosIdioma = $consultor ? ['id' => $consultorId] : [];
$scriptsPagina = $consultor ? ['filtro.js?v=1', 'ver-clave.js?v=1', 'confirmar.js?v=1'] : ['mostrar-clave.js?v=2'];
require __DIR__ . '/config/plantilla-inicio.php';
?>
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="consultores.php"><?= texto('Consultores') ?></a></li>
        <li class="breadcrumb-item active" aria-current="page">
          <?= $consultor ? escapar($nombreConsultor) : texto('Nuevo consultor') ?>
        </li>
      </ol>
    </nav>

    <div class="mejr-titulo">
      <div>
        <h1 class="h4">
          <?= $consultor ? escapar($nombreConsultor) : texto('Nuevo consultor') ?>
          <?php if ($consultor): ?>
            <span class="badge <?= $activo ? 'text-bg-success' : 'text-bg-secondary' ?> align-middle">
              <?= texto($activo ? 'Activo' : 'Inactivo') ?>
            </span>
          <?php endif; ?>
        </h1>
        <?php if ($consultor && trim((string) $consultor['especialidad']) !== ''): ?>
          <p class="text-secondary mb-0"><?= escapar($consultor['especialidad']) ?></p>
        <?php endif; ?>
      </div>

      <?php if ($consultor): ?>
        <a class="btn btn-outline-dark"
          href="horas-admin.php?consultor_id=<?= escapar($consultorId) ?>"><?= texto('Ver todas sus horas') ?></a>
      <?php endif; ?>
    </div>

    <?php if ($consultor): ?>
      <nav class="mejr-ficha-indice" aria-label="<?= texto('Secciones de la ficha') ?>">
        <a class="btn btn-sm btn-outline-secondary" href="#datos"><?= texto('Datos') ?></a>
        <a class="btn btn-sm btn-outline-secondary" href="#proyectos"><?= texto('Proyectos asignados') ?></a>
        <a class="btn btn-sm btn-outline-secondary" href="#horas"><?= texto('Horas del mes') ?></a>
        <a class="btn btn-sm btn-outline-secondary" href="#clave"><?= texto('Contraseña') ?></a>
        <a class="btn btn-sm btn-outline-secondary" href="#estado"><?= texto('Estado') ?></a>
      </nav>
    <?php endif; ?>

    <div class="mejr-ficha">
      <section id="datos" class="bg-white border rounded p-4" aria-labelledby="titulo-datos">
        <h2 id="titulo-datos" class="h5"><?= texto('Datos') ?></h2>

        <?php if ($errores['datos']): ?>
          <div class="alert alert-danger" role="alert">
            <ul class="mb-0">
              <?php foreach ($errores['datos'] as $error): ?>
                <li><?= texto($error) ?></li>
              <?php endforeach; ?>
            </ul>
          </div>
        <?php endif; ?>

        <form method="post" action="<?= escapar($consultor ? $urlFicha('datos') : 'consultor.php') ?>">
          <input type="hidden" name="csrf" value="<?= escapar($_SESSION['csrf']) ?>">
          <input type="hidden" name="accion" value="<?= $consultor ? 'datos' : 'crear' ?>">

          <div class="row g-3">
            <div class="col-md-6">
              <label for="nombres" class="form-label"><?= texto('Nombres') ?></label>
              <input id="nombres" name="nombres" class="form-control" maxlength="100"
                value="<?= escapar($datos['nombres']) ?>" required>
            </div>
            <div class="col-md-6">
              <label for="apellidos" class="form-label"><?= texto('Apellidos') ?></label>
              <input id="apellidos" name="apellidos" class="form-control" maxlength="100"
                value="<?= escapar($datos['apellidos']) ?>" required>
            </div>
            <div class="col-md-6">
              <label for="especialidad" class="form-label"><?= texto('Especialidad') ?></label>
              <input id="especialidad" name="especialidad" class="form-control" maxlength="150"
                autocomplete="off" value="<?= escapar($datos['especialidad']) ?>"
                placeholder="<?= texto('Ejemplo: SAP ABAP o SAP FI/CO.') ?>" aria-describedby="ayuda-especialidad">
              <div id="ayuda-especialidad" class="form-text"><?= texto('Campo opcional. Máximo 150 caracteres.') ?></div>
            </div>
            <div class="col-md-6">
              <label for="correo" class="form-label"><?= texto('Correo electrónico') ?></label>
              <input id="correo" name="correo" type="email" class="form-control" maxlength="254"
                autocomplete="off" autocapitalize="none" spellcheck="false"
                value="<?= escapar($datos['correo']) ?>" aria-describedby="ayuda-correo" required>
              <div id="ayuda-correo" class="form-text"><?= texto('El consultor iniciará sesión con este correo electrónico.') ?></div>
            </div>
            <div class="col-md-6">
              <label for="usuario" class="form-label"><?= texto('Nombre de usuario') ?></label>
              <input id="usuario" name="usuario" class="form-control" minlength="3" maxlength="60"
                autocomplete="off" value="<?= escapar($datos['usuario']) ?>" aria-describedby="ayuda-usuario" required>
              <div id="ayuda-usuario" class="form-text"><?= texto('Referencia interna de la cuenta: entre 3 y 60 caracteres, sin espacios ni tildes.') ?></div>
            </div>

            <?php if (!$consultor): ?>
              <div class="col-md-6"></div>
              <div class="col-md-6">
                <label for="password" class="form-label"><?= texto('Contraseña') ?></label>
                <input id="password" name="password" type="password" class="form-control"
                  minlength="12" maxlength="72" autocomplete="new-password" aria-describedby="ayuda-password" required>
                <div id="ayuda-password" class="form-text"><?= texto('Usa al menos 12 caracteres. Evita contraseñas fáciles de adivinar.') ?></div>
              </div>
              <div class="col-md-6">
                <label for="confirmacion" class="form-label"><?= texto('Repetir contraseña') ?></label>
                <input id="confirmacion" name="confirmacion" type="password" class="form-control"
                  minlength="12" maxlength="72" autocomplete="new-password" required>
              </div>
            <?php endif; ?>
          </div>

          <div class="d-flex flex-wrap gap-2 mt-4">
            <button type="submit" class="btn btn-dark">
              <?= texto($consultor ? 'Guardar cambios' : 'Crear consultor') ?>
            </button>
            <a href="consultores.php" class="btn btn-outline-secondary"><?= texto('Cancelar') ?></a>
          </div>
        </form>
      </section>

      <?php if ($consultor): ?>
        <section id="proyectos" class="bg-white border rounded p-4" aria-labelledby="titulo-proyectos">
          <h2 id="titulo-proyectos" class="h5"><?= texto('Proyectos asignados') ?></h2>
          <p class="text-secondary"><?= texto('Marca los proyectos en los que este consultor puede registrar horas. Desmarcar no borra sus horas.') ?></p>

          <?php if ($errores['proyectos']): ?>
            <div class="alert alert-danger" role="alert"><?= texto($errores['proyectos'][0]) ?></div>
          <?php endif; ?>

          <?php if (!$opciones): ?>
            <p class="mb-0"><?= texto('No hay proyectos activos. Créalos en Clientes y proyectos.') ?></p>
          <?php else: ?>
            <form method="post" action="<?= escapar($urlFicha('proyectos')) ?>">
              <input type="hidden" name="csrf" value="<?= escapar($_SESSION['csrf']) ?>">
              <input type="hidden" name="accion" value="proyectos">

              <div class="mb-2" data-filtro-caja hidden>
                <label for="buscar-proyecto" class="form-label"><?= texto('Buscar proyecto') ?></label>
                <input id="buscar-proyecto" type="search" class="form-control" data-filtro="lista-proyectos" autocomplete="off">
              </div>

              <div id="lista-proyectos" class="mejr-casillas">
                <?php $grupoActual = null; ?>
                <?php foreach ($opciones as $indice => $opcion): ?>
                  <?php if ($opcion['grupo'] !== $grupoActual): ?>
                    <?php if ($grupoActual !== null): ?></div><?php endif; ?>
                    <?php $grupoActual = $opcion['grupo']; ?>
                    <div data-filtro-grupo class="mb-2">
                      <p class="fw-semibold mb-1 mt-2"><?= escapar($grupoActual) ?></p>
                  <?php endif; ?>
                  <div class="form-check" data-filtro-item>
                    <input class="form-check-input" type="checkbox" name="proyectos[]"
                      id="proyecto-<?= escapar($opcion['id']) ?>" value="<?= escapar($opcion['id']) ?>"
                      <?= in_array((int) $opcion['id'], $asignados, true) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="proyecto-<?= escapar($opcion['id']) ?>">
                      <?= escapar($opcion['nombre']) ?>
                      <span class="visually-hidden"><?= escapar($opcion['grupo']) ?></span>
                    </label>
                  </div>
                <?php endforeach; ?>
                <?php if ($grupoActual !== null): ?></div><?php endif; ?>
              </div>

              <button type="submit" class="btn btn-dark mt-3"><?= texto('Guardar proyectos') ?></button>
            </form>
          <?php endif; ?>
        </section>

        <section id="horas" class="bg-white border rounded p-4" aria-labelledby="titulo-horas">
          <div class="mejr-titulo">
            <h2 id="titulo-horas" class="h5 mb-0">
              <?= texto('Horas de') ?> <?= escapar(mejrNombreMes($ahora)) ?>:
              <?= escapar(mejrHoras($totalMes)) ?>
            </h2>
            <a class="btn btn-sm btn-outline-secondary"
              href="horas-admin.php?consultor_id=<?= escapar($consultorId) ?>"><?= texto('Ver todas sus horas') ?></a>
          </div>

          <?php if (!$horasMes): ?>
            <p class="text-secondary mb-0"><?= texto('Sin horas registradas este mes.') ?></p>
          <?php else: ?>
            <table class="table table-sm align-middle mb-0">
              <thead>
                <tr>
                  <th scope="col"><?= texto('Cliente') ?></th>
                  <th scope="col"><?= texto('Proyecto') ?></th>
                  <th scope="col" class="mejr-numero"><?= texto('Horas') ?></th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($horasMes as $fila): ?>
                  <tr>
                    <td><?= escapar($fila['cliente']) ?></td>
                    <td><?= escapar($fila['proyecto']) ?></td>
                    <td class="mejr-numero"><?= escapar(mejrHoras(mejrEscalar($fila['horas'], 2))) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          <?php endif; ?>
        </section>

        <section id="clave" class="bg-white border rounded p-4" aria-labelledby="titulo-clave">
          <h2 id="titulo-clave" class="h5"><?= texto('Restablecer contraseña') ?></h2>
          <p class="text-secondary">
            <?= texto('La contraseña actual del consultor no se puede mostrar. Puedes asignarle una nueva si olvidó la anterior.') ?>
          </p>

          <?php if ($errores['clave']): ?>
            <div class="alert alert-danger" role="alert"><?= texto($errores['clave'][0]) ?></div>
          <?php endif; ?>

          <form method="post" action="<?= escapar($urlFicha('clave')) ?>">
            <input type="hidden" name="csrf" value="<?= escapar($_SESSION['csrf']) ?>">
            <input type="hidden" name="accion" value="clave">

            <div class="row g-3">
              <div class="col-12 col-lg-6">
                <label id="etiqueta-admin" for="clave_admin" class="form-label"><?= texto('Tu contraseña de administrador') ?></label>
                <div class="input-group">
                  <input id="clave_admin" name="clave_admin" type="password" class="form-control"
                    maxlength="72" autocomplete="current-password" aria-describedby="ayuda-admin" required>
                  <button id="ver-admin" type="button" class="btn btn-outline-secondary"
                    data-ver-clave="clave_admin" data-mostrar="<?= texto('Mostrar') ?>" data-ocultar="<?= texto('Ocultar') ?>"
                    aria-controls="clave_admin" aria-pressed="false" aria-labelledby="ver-admin etiqueta-admin"><?= texto('Mostrar') ?></button>
                </div>
                <div id="ayuda-admin" class="form-text"><?= texto('Escribe la contraseña de tu propia cuenta para autorizar el cambio.') ?></div>
              </div>
              <div class="col-lg-6 d-none d-lg-block" aria-hidden="true"></div>
              <div class="col-md-6">
                <label id="etiqueta-nueva" for="nueva_clave" class="form-label"><?= texto('Nueva contraseña del consultor') ?></label>
                <div class="input-group">
                  <input id="nueva_clave" name="nueva_clave" type="password" class="form-control"
                    minlength="12" maxlength="72" autocomplete="new-password" aria-describedby="ayuda-nueva" required>
                  <button id="ver-nueva" type="button" class="btn btn-outline-secondary"
                    data-ver-clave="nueva_clave" data-mostrar="<?= texto('Mostrar') ?>" data-ocultar="<?= texto('Ocultar') ?>"
                    aria-controls="nueva_clave" aria-pressed="false" aria-labelledby="ver-nueva etiqueta-nueva"><?= texto('Mostrar') ?></button>
                </div>
                <div id="ayuda-nueva" class="form-text"><?= texto('Usa al menos 12 caracteres.') ?></div>
              </div>
              <div class="col-md-6">
                <label id="etiqueta-confirmacion" for="confirmacion_clave" class="form-label"><?= texto('Repetir nueva contraseña') ?></label>
                <div class="input-group">
                  <input id="confirmacion_clave" name="confirmacion_clave" type="password" class="form-control"
                    minlength="12" maxlength="72" autocomplete="new-password" required>
                  <button id="ver-confirmacion" type="button" class="btn btn-outline-secondary"
                    data-ver-clave="confirmacion_clave" data-mostrar="<?= texto('Mostrar') ?>" data-ocultar="<?= texto('Ocultar') ?>"
                    aria-controls="confirmacion_clave" aria-pressed="false" aria-labelledby="ver-confirmacion etiqueta-confirmacion"><?= texto('Mostrar') ?></button>
                </div>
              </div>
            </div>

            <p class="small text-secondary mt-3">
              <?= texto('Indica al consultor que cambie esta contraseña desde su propia cuenta después de recuperar el acceso.') ?>
            </p>
            <button type="submit" class="btn btn-dark"><?= texto('Restablecer contraseña') ?></button>
          </form>
        </section>

        <section id="estado" class="bg-white border rounded p-4" aria-labelledby="titulo-estado">
          <h2 id="titulo-estado" class="h5"><?= texto('Estado') ?></h2>
          <p class="text-secondary"><?= texto('Desactivar una cuenta conserva sus horas y asignaciones, y cierra sus sesiones abiertas.') ?></p>

          <form method="post" action="<?= escapar($urlFicha('estado')) ?>"
            data-confirmar="<?= texto('¿Confirmas el cambio de acceso de este consultor? Sus horas se conservarán.') ?>">
            <input type="hidden" name="csrf" value="<?= escapar($_SESSION['csrf']) ?>">
            <input type="hidden" name="accion" value="estado">
            <input type="hidden" name="estado" value="<?= $activo ? 'desactivar' : 'activar' ?>">
            <button type="submit" class="btn <?= $activo ? 'btn-outline-danger' : 'btn-dark' ?>">
              <?= texto($activo ? 'Desactivar' : 'Activar') ?>
            </button>
          </form>
        </section>
      <?php endif; ?>
    </div>
<?php require __DIR__ . '/config/plantilla-fin.php'; ?>
