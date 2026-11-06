<?php
// Formatos compartidos. Las horas se manejan en centésimas y el dinero en
// centavos de dólar, siempre como enteros; solo se convierten al mostrarlos.

const MEJR_ZONA = 'America/Santiago';

function mejrAhora(): DateTimeImmutable
{
    return new DateTimeImmutable('now', new DateTimeZone(MEJR_ZONA));
}

// Convierte un DECIMAL de MySQL ("2.50", "64000.0050") a entero, corriendo la
// coma $escala lugares y redondeando hacia arriba desde ,5 (sin pasar por float).
function mejrEscalar(string|int|float|null $valor, int $escala = 0): int
{
    $texto = trim((string) ($valor ?? '0'));

    if (!preg_match('/\A(-?)(\d*)(?:\.(\d*))?\z/', $texto, $partes)) {
        return 0;
    }

    $fraccion = str_pad($partes[3] ?? '', $escala + 1, '0');
    $numero = (int) (($partes[2] === '' ? '0' : $partes[2]) . substr($fraccion, 0, $escala));

    if ((int) $fraccion[$escala] >= 5) {
        $numero++;
    }

    return $partes[1] === '-' ? -$numero : $numero;
}

function mejrNumero(int $valor, int $decimales): string
{
    global $idioma;

    return number_format(
        $valor / (10 ** $decimales),
        $decimales,
        $idioma === 'en' ? '.' : ',',
        $idioma === 'en' ? ',' : '.'
    );
}

function mejrHoras(int $centesimas): string
{
    return mejrNumero($centesimas, 2);
}

function mejrUsd(?int $centavos): string
{
    return $centavos === null ? '—' : 'US$ ' . mejrNumero($centavos, 2);
}

function mejrPorcentaje(?int $parte, ?int $total): string
{
    if ($parte === null || !$total) {
        return '—';
    }

    return mejrNumero((int) round($parte * 1000 / $total), 1) . ' %';
}

function mejrFecha(string $ymd): string
{
    global $idioma;

    $fecha = DateTimeImmutable::createFromFormat('!Y-m-d', $ymd);

    return $fecha ? $fecha->format($idioma === 'en' ? 'm/d/Y' : 'd/m/Y') : $ymd;
}

function mejrNombreMes(DateTimeImmutable $mes): string
{
    global $idioma;

    $nombres = $idioma === 'en'
        ? ['January', 'February', 'March', 'April', 'May', 'June', 'July',
           'August', 'September', 'October', 'November', 'December']
        : ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio',
           'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];

    return $nombres[(int) $mes->format('n') - 1] . ' ' . $mes->format('Y');
}

// Mes "AAAA-MM" válido o null.
function mejrMesValido($valor): ?DateTimeImmutable
{
    if (!is_string($valor)
        || !preg_match('/\A[0-9]{4}-(0[1-9]|1[0-2])\z/', $valor)
        || $valor < '1000-01' || $valor > '9998-12') {
        return null;
    }

    return new DateTimeImmutable($valor . '-01', new DateTimeZone(MEJR_ZONA));
}

// Rango de meses de un GET: desde/hasta "AAAA-MM", o mes= que fija ambos.
// Por defecto el mes en curso. Devuelve null si es inválido o muy largo.
// 'fin' es el primer día del mes siguiente a 'hasta' (límite exclusivo).
function mejrPeriodoMeses(array $get, int $maximoMeses = 36): ?array
{
    $mes = $get['mes'] ?? null;
    $desde = mejrMesValido($get['desde'] ?? $mes ?? mejrAhora()->format('Y-m'));
    $hasta = $desde ? mejrMesValido($get['hasta'] ?? $mes ?? $desde->format('Y-m')) : null;

    if (!$desde || !$hasta || $hasta < $desde) {
        return null;
    }

    $meses = ((int) $hasta->format('Y') - (int) $desde->format('Y')) * 12
        + (int) $hasta->format('n') - (int) $desde->format('n') + 1;

    if ($meses > $maximoMeses) {
        return null;
    }

    return [
        'desde' => $desde,
        'hasta' => $hasta,
        'fin' => $hasta->modify('+1 month'),
        'meses' => $meses,
        'desdeMes' => $desde->format('Y-m'),
        'hastaMes' => $hasta->format('Y-m'),
        'etiqueta' => $meses === 1
            ? mejrNombreMes($desde)
            : mejrNombreMes($desde) . ' – ' . mejrNombreMes($hasta),
    ];
}

// Horas escritas por una persona ("2,5", "8", "0.25") a centésimas, o null.
function mejrLeerHoras(string $texto): ?int
{
    if (!preg_match('/\A([0-9]{1,2})(?:[.,]([0-9]{1,2}))?\z/', $texto, $partes)) {
        return null;
    }

    return (int) $partes[1] * 100 + (int) str_pad($partes[2] ?? '', 2, '0');
}

// Centésimas a DECIMAL para MySQL: 250 -> "2.50".
function mejrHorasBd(int $centesimas): string
{
    return intdiv($centesimas, 100) . '.' . str_pad((string) ($centesimas % 100), 2, '0', STR_PAD_LEFT);
}
