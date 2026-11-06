<?php
// Tarifas por hora en dólares, con vigencia. Solo para páginas de administración.
// Tablas: tarifas_cliente (cobro) y tarifas_consultor (pago), migración sql/004_tarifas.sql.
//
// Reglas, para que un mes ya valorizado no cambie nunca:
// - Una tarifa que rige desde el 1 del mes en curso o después se acepta siempre
//   (si ya hay una con esa misma fecha, se reemplaza).
// - Una tarifa con fecha anterior solo se acepta si es más antigua que todas las
//   existentes: sirve para cargar el historial al empezar a usar las tarifas.
// - Solo se borran tarifas del mes en curso o futuras.

$traducciones += [
    'Tarifa de cobro' => 'Billing rate',
    'Tarifa de pago' => 'Pay rate',
    'Lo que se cobra al cliente por cada hora registrada en sus proyectos.' => 'What the client is billed for each hour logged on its projects.',
    'Lo que se paga al consultor por cada hora que registra.' => 'What the consultant is paid for each hour they log.',
    'Vigente hoy' => 'Current rate',
    'Historial' => 'History',
    'Desde' => 'From',
    'US$ por hora' => 'US$ per hour',
    'Cargada por' => 'Added by',
    'Nueva tarifa' => 'New rate',
    'Rige desde' => 'Effective from',
    'Guardar tarifa' => 'Save rate',
    'Sin tarifas cargadas. Las horas de este período quedan sin valorizar hasta que agregues una.' => 'No rates yet. Hours stay unvalued until you add one.',
    'Escribe un monto en dólares mayor que cero, con hasta dos decimales. Ejemplo: 120 o 120,50.' => 'Enter a US dollar amount greater than zero, with up to two decimals. Example: 120 or 120.50.',
    'Selecciona una fecha válida.' => 'Select a valid date.',
    'Esa fecha cae en un mes cerrado y ya hay tarifas posteriores: cambiarla alteraría montos ya calculados. Usa una fecha desde el 1 de este mes, o una anterior a la tarifa más antigua.' => 'That date falls in a closed month and later rates already exist: changing it would alter amounts already calculated. Use a date from the 1st of this month, or one earlier than the oldest rate.',
    'Solo se pueden borrar tarifas del mes en curso o futuras.' => 'Only current-month or future rates can be deleted.',
    'Tarifa guardada.' => 'Rate saved.',
    'Tarifa borrada.' => 'Rate deleted.',
    'No se pudo guardar la tarifa. Intenta nuevamente.' => 'The rate could not be saved. Try again.',
    'Las tarifas se aplican a cada hora según su fecha. Para corregir una tarifa de este mes o futura, bórrala y vuelve a cargarla.' => 'Rates apply to each hour by its date. To fix a current or future rate, delete it and add it again.',
    '¿Borrar esta tarifa?' => 'Delete this rate?'
];

const MEJR_TABLAS_TARIFA = [
    'cliente' => ['tabla' => 'tarifas_cliente', 'columna' => 'cliente_id', 'bloqueo' => 'SELECT id FROM clientes WHERE id = :id FOR UPDATE'],
    'consultor' => ['tabla' => 'tarifas_consultor', 'columna' => 'consultor_id', 'bloqueo' => "SELECT id FROM usuarios WHERE id = :id AND rol = 'consultor' FOR UPDATE"],
];

// Tarifa vigente en la fecha de cada registro. Fragmentos para una consulta
// sobre registros_horas r INNER JOIN proyectos p. Subconsultas escalares sobre
// la clave primaria: funcionan en MySQL 5.7/8 y MariaDB 10.x.
const MEJR_SQL_TARIFA_CLIENTE =
    '(SELECT tc.centavos_hora FROM tarifas_cliente tc
      WHERE tc.cliente_id = p.cliente_id AND tc.vigente_desde <= r.fecha
      ORDER BY tc.vigente_desde DESC LIMIT 1)';
const MEJR_SQL_TARIFA_CONSULTOR =
    '(SELECT tu.centavos_hora FROM tarifas_consultor tu
      WHERE tu.consultor_id = r.consultor_id AND tu.vigente_desde <= r.fecha
      ORDER BY tu.vigente_desde DESC LIMIT 1)';

// Dólares escritos por una persona ("120", "120,5", "120.50") a centavos, o null.
function mejrLeerUsd(string $texto): ?int
{
    if (!preg_match('/\A([0-9]{1,6})(?:[.,]([0-9]{1,2}))?\z/', trim($texto), $partes)) {
        return null;
    }

    $centavos = (int) $partes[1] * 100 + (int) str_pad($partes[2] ?? '', 2, '0');

    return $centavos > 0 ? $centavos : null;
}

function mejrHistorialTarifas(PDO $pdo, string $tipo, int $id): array
{
    $config = MEJR_TABLAS_TARIFA[$tipo];
    $consulta = $pdo->prepare(
        "SELECT t.vigente_desde, t.centavos_hora, t.creado_en,
                CONCAT(u.nombres, ' ', u.apellidos) AS autor
         FROM {$config['tabla']} t
         LEFT JOIN usuarios u ON u.id = t.creado_por
         WHERE t.{$config['columna']} = :id
         ORDER BY t.vigente_desde DESC"
    );
    $consulta->execute(['id' => $id]);

    return $consulta->fetchAll();
}

// Tarifa vigente hoy para todos los clientes o consultores: [id => centavos].
function mejrTarifasVigentes(PDO $pdo, string $tipo): array
{
    $config = MEJR_TABLAS_TARIFA[$tipo];
    $consulta = $pdo->prepare(
        "SELECT t.{$config['columna']} AS id, t.centavos_hora
         FROM {$config['tabla']} t
         WHERE t.vigente_desde = (
             SELECT MAX(x.vigente_desde) FROM {$config['tabla']} x
             WHERE x.{$config['columna']} = t.{$config['columna']} AND x.vigente_desde <= :hoy
         )"
    );
    $consulta->execute(['hoy' => mejrAhora()->format('Y-m-d')]);
    $vigentes = [];

    foreach ($consulta as $fila) {
        $vigentes[(int) $fila['id']] = (int) $fila['centavos_hora'];
    }

    return $vigentes;
}

function mejrGuardarTarifa(PDO $pdo, string $tipo, int $id, string $desde, int $centavos, int $adminId): void
{
    $config = MEJR_TABLAS_TARIFA[$tipo];
    $inicioMes = mejrAhora()->format('Y-m-01');
    $pdo->beginTransaction();

    try {
        $bloqueo = $pdo->prepare($config['bloqueo']);
        $bloqueo->execute(['id' => $id]);

        if (!$bloqueo->fetch()) {
            throw new DomainException('No se pudo guardar la tarifa. Intenta nuevamente.');
        }

        if ($desde < $inicioMes) {
            $minima = $pdo->prepare(
                "SELECT MIN(vigente_desde) FROM {$config['tabla']} WHERE {$config['columna']} = :id FOR UPDATE"
            );
            $minima->execute(['id' => $id]);
            $masAntigua = $minima->fetchColumn();

            if ($masAntigua !== null && $masAntigua !== false && $desde >= $masAntigua) {
                throw new DomainException('Esa fecha cae en un mes cerrado y ya hay tarifas posteriores: cambiarla alteraría montos ya calculados. Usa una fecha desde el 1 de este mes, o una anterior a la tarifa más antigua.');
            }
        }

        $pdo->prepare(
            "INSERT INTO {$config['tabla']} ({$config['columna']}, vigente_desde, centavos_hora, creado_por, creado_en)
             VALUES (:id, :desde, :centavos, :autor, :creado)
             ON DUPLICATE KEY UPDATE centavos_hora = VALUES(centavos_hora),
                                     creado_por = VALUES(creado_por), creado_en = VALUES(creado_en)"
        )->execute([
            'id' => $id,
            'desde' => $desde,
            'centavos' => $centavos,
            'autor' => $adminId,
            'creado' => gmdate('Y-m-d H:i:s')
        ]);

        $pdo->commit();
    } catch (Throwable $excepcion) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $excepcion;
    }

    error_log(
        'MEJR: administrador ' . $adminId . ' cargó la tarifa de ' . $tipo . ' ' . $id .
        ' desde ' . $desde . ' (' . $centavos . ' centavos/h).'
    );
}

function mejrBorrarTarifa(PDO $pdo, string $tipo, int $id, string $desde, int $adminId): void
{
    if ($desde < mejrAhora()->format('Y-m-01')) {
        throw new DomainException('Solo se pueden borrar tarifas del mes en curso o futuras.');
    }

    $config = MEJR_TABLAS_TARIFA[$tipo];
    $pdo->prepare(
        "DELETE FROM {$config['tabla']} WHERE {$config['columna']} = :id AND vigente_desde = :desde"
    )->execute(['id' => $id, 'desde' => $desde]);

    error_log('MEJR: administrador ' . $adminId . ' borró la tarifa de ' . $tipo . ' ' . $id . ' desde ' . $desde . '.');
}

// Procesa los POST 'tarifa' y 'tarifa_borrar' de una ficha. Redirige si todo
// sale bien; si no, devuelve el mensaje de error para mostrarlo en la sección.
function mejrProcesarTarifa(PDO $pdo, string $tipo, int $id, string $accion, string $urlVolver): string
{
    $adminId = (int) $_SESSION['usuario_id'];
    $desde = is_string($_POST['desde'] ?? null) ? trim($_POST['desde']) : '';
    $fecha = DateTimeImmutable::createFromFormat('!Y-m-d', $desde);

    if (!$fecha || $fecha->format('Y-m-d') !== $desde) {
        return 'Selecciona una fecha válida.';
    }

    try {
        if ($accion === 'tarifa_borrar') {
            mejrBorrarTarifa($pdo, $tipo, $id, $desde, $adminId);
            avisar('success', 'Tarifa borrada.');
            redirigir($urlVolver);
        }

        $centavos = mejrLeerUsd(is_string($_POST['valor'] ?? null) ? $_POST['valor'] : '');

        if ($centavos === null) {
            return 'Escribe un monto en dólares mayor que cero, con hasta dos decimales. Ejemplo: 120 o 120,50.';
        }

        mejrGuardarTarifa($pdo, $tipo, $id, $desde, $centavos, $adminId);
        avisar('success', 'Tarifa guardada.');
        redirigir($urlVolver);
    } catch (DomainException $excepcion) {
        return $excepcion->getMessage();
    } catch (PDOException $excepcion) {
        error_log($excepcion->getMessage());

        return 'No se pudo guardar la tarifa. Intenta nuevamente.';
    }

    return '';
}
