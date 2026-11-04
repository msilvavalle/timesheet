<?php
require_once __DIR__ . '/idioma.php';

function descargarHorasExcel(
    array $registros,
    int $totalCentesimas,
    string $periodo
): void {
    global $idioma;

    $ingles = $idioma === 'en';

    $etiquetas = [
        'titulo' => $ingles ? 'Time entries' : 'Horas registradas',

        'periodo' => $ingles ? 'Period: ' : 'Periodo: ',

        'nota' => $ingles
            ? 'Entries matching the selected filters. Hours are shown in decimal format.'
            : 'Registros según los filtros seleccionados. Horas en formato decimal.',

        'columnas' => $ingles
            ? ['Date', 'Consultant', 'Client', 'Project', 'Hours', 'Description']
            : ['Fecha', 'Consultor', 'Cliente', 'Proyecto', 'Horas', 'Descripción'],

        'total' => $ingles ? 'TOTAL HOURS' : 'TOTAL DE HORAS',

        'archivo' => $ingles ? 'MEJR-hours-' : 'MEJR-horas-',

        'error' => $ingles
            ? 'The Excel report could not be generated. Return to your report and try again.'
            : 'No se pudo generar el Excel. Vuelve al reporte e intenta nuevamente.'
    ];

    $salida = null;

    try {
        require_once __DIR__ . '/../vendor/autoload.php';

        $libro = new \PhpOffice\PhpSpreadsheet\Spreadsheet();

        $libro->getProperties()
            ->setCreator('MEJR Consulting')
            ->setTitle($etiquetas['titulo']);

        $hoja = $libro->getActiveSheet();
        $hoja->setTitle($etiquetas['titulo']);

        $libro->getDefaultStyle()
            ->getFont()
            ->setName('Calibri')
            ->setSize(11);

        $hoja->mergeCells('A1:F1');
        $hoja->setCellValue(
            'A1',
            'MEJR CONSULTING | ' . $etiquetas['titulo']
        );

        $hoja->getStyle('A1')
            ->getFont()
            ->setBold(true)
            ->setSize(14);

        $hoja->mergeCells('A2:F2');
        $hoja->setCellValue(
            'A2',
            $etiquetas['periodo'] . $periodo
        );

        $hoja->mergeCells('A3:F3');
        $hoja->setCellValue('A3', $etiquetas['nota']);

        $hoja->fromArray(
            $etiquetas['columnas'],
            null,
            'A5'
        );

        $fila = 6;

        foreach ($registros as $registro) {
            $fecha = \DateTimeImmutable::createFromFormat(
                '!Y-m-d',
                $registro['fecha']
            );

            $hoja->setCellValue(
                'A' . $fila,
                \PhpOffice\PhpSpreadsheet\Shared\Date::PHPToExcel($fecha)
            );

            $textos = [
                'B' => $registro['nombres'] . ' ' . $registro['apellidos'],
                'C' => $registro['cliente'],
                'D' => $registro['proyecto'],
                'F' => $registro['descripcion']
            ];

            foreach ($textos as $columna => $texto) {
                $hoja->setCellValueExplicit(
                    $columna . $fila,
                    (string) $texto,
                    \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING
                );
            }

            $hoja->setCellValueExplicit(
                'E' . $fila,
                (float) $registro['horas'],
                \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC
            );

            $fila++;
        }

        if ($fila > 6) {
            $hoja->getStyle('A6:A' . ($fila - 1))
                ->getNumberFormat()
                ->setFormatCode(
                    $ingles ? 'mm/dd/yyyy' : 'dd/mm/yyyy'
                );

            $hoja->getStyle('B6:F' . ($fila - 1))
                ->getAlignment()
                ->setWrapText(true)
                ->setVertical('top');
        }

        $hoja->setCellValue(
            'D' . $fila,
            $etiquetas['total']
        );

        $hoja->setCellValue(
            'E' . $fila,
            $fila > 6 ? '=SUM(E6:E' . ($fila - 1) . ')' : 0
        );

        $hoja->getCell('E' . $fila)
            ->setCalculatedValue($totalCentesimas / 100);

        $hoja->getStyle('D' . $fila . ':E' . $fila)
            ->getFont()
            ->setBold(true);

        $hoja->getStyle('E6:E' . $fila)
            ->getNumberFormat()
            ->setFormatCode('0.00');

        $hoja->getStyle('A5:F5')
            ->getFont()
            ->setBold(true);

        $hoja->getStyle('A5:F5')
            ->getFill()
            ->setFillType('solid')
            ->getStartColor()
            ->setARGB('FFE9ECEF');

        $hoja->freezePane('A6');
        $hoja->setAutoFilter('A5:F' . max(5, $fila - 1));

        $anchos = [
            'A' => 15,
            'B' => 26,
            'C' => 28,
            'D' => 32,
            'E' => 18,
            'F' => 60
        ];

        foreach ($anchos as $columna => $ancho) {
            $hoja->getColumnDimension($columna)
                ->setWidth($ancho);
        }

        $salida = fopen('php://memory', 'w+b');

        if ($salida === false) {
            throw new \RuntimeException(
                'No se pudo preparar la descarga.'
            );
        }

        $escritor = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($libro);
        $escritor->save($salida);
        rewind($salida);

        header(
            'Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        );

        header(
            'Content-Disposition: attachment; filename="' .
            $etiquetas['archivo'] .
            str_replace('/', '-', $periodo) . '.xlsx"'
        );

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