<?php
// Límite de intentos de ingreso para login.php (tabla sql/003_intentos_login.sql).
//
// Cada intento se registra ANTES de verificar la contraseña y se cuenta junto
// con los anteriores: así, pedidos simultáneos no se saltan el límite.
// El contador va por cuenta encontrada ('cuenta:<id>') y no por el texto
// escrito, para que las variantes de un mismo usuario lo compartan. En la
// tabla solo se guarda el SHA-256 de esa clave, nunca el correo.
//
// Si la tabla no existe o la consulta falla, el ingreso sigue funcionando
// sin límite y el problema queda en el log de PHP.

const MEJR_INGRESO_VENTANA_MINUTOS = 15;
const MEJR_INGRESO_MAXIMO_CUENTA_IP = 5;  // misma cuenta desde la misma IP
const MEJR_INGRESO_MAXIMO_CUENTA = 50;    // misma cuenta desde cualquier IP
const MEJR_INGRESO_MAXIMO_IP = 30;        // cualquier cuenta desde la misma IP

function mejrIpCliente(): string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';

    // IPv4 escrita como IPv6 (::ffff:1.2.3.4): se usa la IPv4.
    if (stripos($ip, '::ffff:') === 0
        && filter_var(substr($ip, 7), FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
        return substr($ip, 7);
    }

    // IPv6: se agrupa por /64, que normalmente corresponde a una sola conexión.
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false) {
        return inet_ntop(substr(inet_pton($ip), 0, 8) . str_repeat("\0", 8)) . '/64';
    }

    return filter_var($ip, FILTER_VALIDATE_IP) !== false ? $ip : '';
}

function mejrRegistrarIntento(PDO $pdo, string $ip, string $claveLimite): ?int
{
    try {
        $pdo->prepare(
            'INSERT INTO intentos_login (clave, ip, creado_en)
             VALUES (:clave, :ip, UTC_TIMESTAMP())'
        )->execute(['clave' => hash('sha256', $claveLimite), 'ip' => $ip]);

        $intento = (int) $pdo->lastInsertId();

        // Limpieza ocasional: los intentos de más de un día ya no se usan.
        if (random_int(1, 100) === 1) {
            $pdo->exec(
                'DELETE FROM intentos_login WHERE creado_en < UTC_TIMESTAMP() - INTERVAL 1 DAY'
            );
        }

        return $intento;
    } catch (PDOException $e) {
        error_log('MEJR: no se pudo registrar un intento de ingreso.');

        return null;
    }
}

function mejrIngresoBloqueado(PDO $pdo, ?int $intento, string $ip, string $claveLimite): bool
{
    if ($intento === null) {
        return false;
    }

    $ventana = 'UTC_TIMESTAMP() - INTERVAL ' . MEJR_INGRESO_VENTANA_MINUTOS . ' MINUTE';
    $clave = hash('sha256', $claveLimite);

    try {
        $consulta = $pdo->prepare(
            "SELECT
                (SELECT COUNT(*) FROM intentos_login
                 WHERE clave = :clave_ip AND ip = :ip_cuenta AND creado_en > $ventana) AS cuenta_ip,
                (SELECT COUNT(*) FROM intentos_login
                 WHERE clave = :clave AND creado_en > $ventana) AS cuenta,
                (SELECT COUNT(*) FROM intentos_login
                 WHERE ip = :ip AND creado_en > $ventana) AS ip"
        );
        $consulta->execute([
            'clave_ip' => $clave,
            'ip_cuenta' => $ip,
            'clave' => $clave,
            'ip' => $ip
        ]);
        $conteo = $consulta->fetch();
    } catch (PDOException $e) {
        error_log('MEJR: no se pudo consultar el límite de ingreso.');

        return false;
    }

    // Los conteos incluyen el intento actual.
    return (int) $conteo['cuenta_ip'] > MEJR_INGRESO_MAXIMO_CUENTA_IP
        || (int) $conteo['cuenta'] > MEJR_INGRESO_MAXIMO_CUENTA
        || (int) $conteo['ip'] > MEJR_INGRESO_MAXIMO_IP;
}

// Un intento rechazado por el límite no se cuenta: el bloqueo vence 15 minutos
// después del último intento real, aunque la persona siga probando.
function mejrDescartarIntento(PDO $pdo, ?int $intento): void
{
    if ($intento === null) {
        return;
    }

    try {
        $pdo->prepare('DELETE FROM intentos_login WHERE id = :id')
            ->execute(['id' => $intento]);
    } catch (PDOException $e) {
        error_log('MEJR: no se pudo descartar un intento de ingreso.');
    }
}

function mejrLimpiarIntentos(PDO $pdo, string $claveLimite): void
{
    try {
        $pdo->prepare('DELETE FROM intentos_login WHERE clave = :clave')
            ->execute(['clave' => hash('sha256', $claveLimite)]);
    } catch (PDOException $e) {
        error_log('MEJR: no se pudieron limpiar los intentos de ingreso.');
    }
}
