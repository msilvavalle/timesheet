<?php
require_once __DIR__ . '/idioma.php';
require_once __DIR__ . '/formato.php';

// Descarga en Excel los registros de horas. Con $conMontos (solo administración)
// agrega tarifas, ingreso y costo en US$; cada registro debe traer entonces
// 'tarifa_cliente' y 'tarifa_consultor' en centavos por hora (o null).
function descargarHorasExcel(
    array $registros,
    int $totalCentesimas,
    string $periodo,
    bool $conMontos = false
): void {
    global $idioma;

    $ingles = $idioma === 'en';

    $etiquetas = [
        'titulo' => $ingles ? 'Time entries' : 'Horas registradas',
        'periodo' => $ingles ? 'Period: ' : 'Periodo: ',
        'nota' => $ingles
            ? 'Entries matching the selected filters. Hours are shown in decimal format.'
            : 'Registros según los filtros seleccionados. Horas en formato decimal.',
        'notaMontos' => $ingles
            ? ' Amounts in US$, using the rate in force on each date. Blank = no rate.'
            : ' Montos en US$ con la tarifa vigente en cada fecha. En blanco = sin tarifa.',
        'total' => $ingles ? 'TOTAL' : 'TOTAL',
        'archivo' => $ingles ? 'MEJR-hours-' : 'MEJR-horas-',
        'error' => $ingles
            ? 'The Excel report could not be generated. Return to your report and try again.'
            : 'No se pudo generar el Excel. Vuelve al reporte e intenta nuevamente.'
    ];

    // Columnas: clave => [título ES, título EN, ancho].
    $columnas = [
        'fecha' => ['Fecha', 'Date', 13],
        'consultor' => ['Consultor', 'Consultant', 26],
        'cliente' => ['Cliente', 'Client', 26],
        'proyecto' => ['Proyecto', 'Project', 30],
        'horas' => ['Horas', 'Hours', 10],
    ];

    if ($conMontos) {
        $columnas += [
            'tarifa_cliente' => ['Tarifa cliente (US$/h)', 'Client rate (US$/h)', 18],
            'ingreso' => ['Ingreso (US$)', 'Revenue (US$)', 15],
            'tarifa_consultor' => ['Tarifa consultor (US$/h)', 'Consultant rate (US$/h)', 20],
            'costo' => ['Costo (US$)', 'Cost (US$)', 14],
        ];
    }

    $columnas['descripcion'] = ['Descripción', 'Description', 60];

    $letras = [];
    $letra = 'A';

    foreach (array_keys($columnas) as $clave) {
        $letras[$clave] = $letra++;
    }

    $ultima = end($letras);
    $salida = null;

    try {
        require_once __DIR__ . '/../vendor/autoload.php';

        $libro = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $libro->getProperties()->setCreator('MEJR Consulting')->setTitle($etiquetas['titulo']);
        $libro->getDefaultStyle()->getFont()->setName('Calibri')->setSize(11);

        $hoja = $libro->getActiveSheet();
        $hoja->setTitle($etiquetas['titulo']);

        $hoja->mergeCells("A1:{$ultima}1");
        $hoja->setCellValue('A1', 'MEJR CONSULTING | ' . $etiquetas['titulo']);
        $hoja->getStyle('A1')->getFont()->setBold(true)->setSize(14);

        $hoja->mergeCells("A2:{$ultima}2");
        $hoja->setCellValueExplicit('A2', $etiquetas['periodo'] . $periodo, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);

        $hoja->mergeCells("A3:{$ultima}3");
        $hoja->setCellValue('A3', $etiquetas['nota'] . ($conMontos ? $etiquetas['notaMontos'] : ''));

        $hoja->fromArray(
            array_map(static fn($columna) => $ingles ? $columna[1] : $columna[0], array_values($columnas)),
            null,
            'A5'
        );

        $fila = 6;
        $totales = ['ingreso' => 0, 'costo' => 0];
        $texto = \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING;
        $numero = \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC;

        foreach ($registros as $registro) {
            $fecha = \DateTimeImmutable::createFromFormat('!Y-m-d', $registro['fecha']);
            $centesimas = mejrEscalar($registro['horas'], 2);

            $hoja->setCellValue($letras['fecha'] . $fila, \PhpOffice\PhpSpreadsheet\Shared\Date::PHPToExcel($fecha));

            // Texto explícito: una descripción que empiece con "=" no se evalúa como fórmula.
            foreach ([
                'consultor' => $registro['nombres'] . ' ' . $registro['apellidos'],
                'cliente' => $registro['cliente'],
                'proyecto' => $registro['proyecto'],
                'descripcion' => $registro['descripcion'],
            ] as $clave => $valor) {
                $hoja->setCellValueExplicit($letras[$clave] . $fila, (string) $valor, $texto);
            }

            $hoja->setCellValueExplicit($letras['horas'] . $fila, $centesimas / 100, $numero);

            if ($conMontos) {
                foreach (['cliente' => 'ingreso', 'consultor' => 'costo'] as $quien => $monto) {
                    $tarifa = $registro['tarifa_' . $quien];

                    if ($tarifa !== null) {
                        $exacto = $centesimas * (int) $tarifa;
                        $totales[$monto] += $exacto;
                        $hoja->setCellValueExplicit($letras['tarifa_' . $quien] . $fila, (int) $tarifa / 100, $numero);
                        $hoja->setCellValueExplicit($letras[$monto] . $fila, $exacto / 10000, $numero);
                    }
                }
            }

            $fila++;
        }

        $ultimaFila = max(6, $fila - 1);

        if ($fila > 6) {
            $hoja->getStyle("A6:A$ultimaFila")->getNumberFormat()->setFormatCode($ingles ? 'mm/dd/yyyy' : 'dd/mm/yyyy');
            $hoja->getStyle("B6:{$ultima}$ultimaFila")->getAlignment()->setWrapText(true)->setVertical('top');
        }

        $etiquetaTotal = $letras['proyecto'];
        $hoja->setCellValue($etiquetaTotal . $fila, $etiquetas['total']);

        $sumas = ['horas' => $totalCentesimas / 100];

        if ($conMontos) {
            $sumas['ingreso'] = $totales['ingreso'] / 10000;
            $sumas['costo'] = $totales['costo'] / 10000;
        }

        foreach ($sumas as $clave => $valorCalculado) {
            $columna = $letras[$clave];
            $hoja->setCellValue($columna . $fila, $fila > 6 ? "=SUM({$columna}6:{$columna}" . ($fila - 1) . ')' : 0);
            $hoja->getCell($columna . $fila)->setCalculatedValue($valorCalculado);
        }

        $hoja->getStyle("A$fila:{$ultima}$fila")->getFont()->setBold(true);
        $hoja->getStyle("{$letras['horas']}6:{$letras['horas']}$fila")->getNumberFormat()->setFormatCode('0.00');

        if ($conMontos) {
            foreach (['tarifa_cliente', 'ingreso', 'tarifa_consultor', 'costo'] as $clave) {
                $hoja->getStyle("{$letras[$clave]}6:{$letras[$clave]}$fila")->getNumberFormat()->setFormatCode('#,##0.00');
            }
        }

        $hoja->getStyle("A5:{$ultima}5")->getFont()->setBold(true);
        $hoja->getStyle("A5:{$ultima}5")->getFill()->setFillType('solid')->getStartColor()->setARGB('FFE9ECEF');
        $hoja->freezePane('A6');
        $hoja->setAutoFilter("A5:{$ultima}" . max(5, $fila - 1));

        foreach ($columnas as $clave => $columna) {
            $hoja->getColumnDimension($letras[$clave])->setWidth($columna[2]);
        }

        $salida = fopen('php://memory', 'w+b');

        if ($salida === false) {
            throw new \RuntimeException('No se pudo preparar la descarga.');
        }

        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($libro))->save($salida);
        rewind($salida);

        $nombreArchivo = preg_replace('/[^A-Za-z0-9-]+/', '-', $etiquetas['archivo'] . mejrQuitarTildes(explode(' · ', $periodo)[0]));

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . trim($nombreArchivo, '-') . '.xlsx"');
        header('Cache-Control: no-store, private');
        header('X-Content-Type-Options: nosniff');

        fpassthru($salida);
        fclose($salida);

        $libro->disconnectWorksheets();
        exit;
    } catch (\Throwable $excepcion) {
        if (is_resource($salida)) {
            fclose($salida);
        }

        error_log($excepcion->getMessage());

        http_response_code(500);
        header('Content-Type: text/plain; charset=UTF-8');

        exit($etiquetas['error']);
    }
}

function mejrQuitarTildes(string $texto): string
{
    return strtr($texto, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n', '–' => '-', ' ' => '-']);
}
