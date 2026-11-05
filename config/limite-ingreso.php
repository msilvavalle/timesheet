<?php
// Límite de intentos de ingreso para login.php.
// Requiere la tabla intentos_login (sql/003_intentos_login.sql).
// Si la tabla no existe o la consulta falla, el ingreso sigue funcionando
// sin límite y el problema queda en el log de PHP.

const MEJR_INGRESO_VENTANA_MINUTOS = 15;
const MEJR_INGRESO_MAXIMO_POR_CUENTA = 5;
const MEJR_INGRESO_MAXIMO_POR_IP = 30;

function mejrIpCliente(): string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';

    return filter_var($ip, FILTER_VALIDATE_IP) !== false ? $ip : '';
}

function mejrIngresoBloqueado(PDO $pdo, string $ip, string $identificador): bool
{
    $ventana = 'NOW() - INTERVAL ' . MEJR_INGRESO_VENTANA_MINUTOS . ' MINUTE';

    try {
        $consulta = $pdo->prepare(
            "SELECT
                (SELECT COUNT(*) FROM intentos_login
                 WHERE identificador = :identificador AND creado_en > $ventana) AS por_cuenta,
                (SELECT COUNT(*) FROM intentos_login
                 WHERE ip = :ip AND creado_en > $ventana) AS por_ip"
        );
        $consulta->execute(['identificador' => $identificador, 'ip' => $ip]);
        $conteo = $consulta->fetch();
    } catch (PDOException $e) {
        error_log('MEJR: no se pudo consultar el límite de ingreso.');

        return false;
    }

    return (int) $conteo['por_cuenta'] >= MEJR_INGRESO_MAXIMO_POR_CUENTA
        || (int) $conteo['por_ip'] >= MEJR_INGRESO_MAXIMO_POR_IP;
}

function mejrRegistrarIngresoFallido(PDO $pdo, string $ip, string $identificador): void
{
    try {
        $pdo->prepare(
            'INSERT INTO intentos_login (ip, identificador) VALUES (:ip, :identificador)'
        )->execute(['ip' => $ip, 'identificador' => $identificador]);

        // Limpieza ocasional: los intentos de más de un día ya no se usan.
        if (random_int(1, 100) === 1) {
            $pdo->exec('DELETE FROM intentos_login WHERE creado_en < NOW() - INTERVAL 1 DAY');
        }
    } catch (PDOException $e) {
        error_log('MEJR: no se pudo registrar un ingreso fallido.');
    }
}

function mejrLimpiarIngresosFallidos(PDO $pdo, string $identificador): void
{
    try {
        $pdo->prepare(
            'DELETE FROM intentos_login WHERE identificador = :identificador'
        )->execute(['identificador' => $identificador]);
    } catch (PDOException $e) {
        error_log('MEJR: no se pudieron limpiar los ingresos fallidos.');
    }
}
