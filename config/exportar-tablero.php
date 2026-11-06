<?php
require_once __DIR__ . '/idioma.php';

// Excel del dashboard: una hoja por cliente, otra por proyecto y otra por
// consultor, con horas e importes en US$. Los importes llegan exactos en
// centésimas de centavo (ver mejrMontoExacto en config/tarifas.php).
function descargarTableroExcel(
    string $periodo,
    array $porCliente,
    array $porProyecto,
    array $porConsultor,
    array $clientes,
    array $proyectos,
    array $consultores,
    bool $incompleto = false
): void {
    global $idioma;

    $en = $idioma === 'en';
    $t = static fn(string $es, string $ing): string => $en ? $ing : $es;

    $hojas = [
        [
            'titulo' => $t('Por cliente', 'By client'),
            'columnas' => [$t('Cliente', 'Client'), $t('Horas', 'Hours'), $t('Ingreso (US$)', 'Revenue (US$)'),
                $t('Costo (US$)', 'Cost (US$)'), $t('Margen (US$)', 'Margin (US$)')],
            'filas' => array_map(
                static fn($id, $fila) => [[$clientes[$id] ?? '#' . $id], $fila],
                array_keys($porCliente), $porCliente
            ),
        ],
        [
            'titulo' => $t('Por proyecto', 'By project'),
            'columnas' => [$t('Cliente', 'Client'), $t('Proyecto', 'Project'), $t('Horas', 'Hours'),
                $t('Ingreso (US$)', 'Revenue (US$)'), $t('Costo (US$)', 'Cost (US$)'), $t('Margen (US$)', 'Margin (US$)')],
            'filas' => array_map(
                static fn($id, $fila) => [[
                    $clientes[(int) ($proyectos[$id]['cliente_id'] ?? 0)] ?? '',
                    $proyectos[$id]['nombre'] ?? '#' . $id,
                ], $fila],
                array_keys($porProyecto), $porProyecto
            ),
        ],
        [
            'titulo' => $t('Por consultor', 'By consultant'),
            'columnas' => [$t('Consultor', 'Consultant'), $t('Horas', 'Hours'), $t('Ingreso generado (US$)', 'Revenue generated (US$)'),
                $t('Costo (US$)', 'Cost (US$)'), $t('Margen (US$)', 'Margin (US$)')],
            'filas' => array_map(
                static fn($id, $fila) => [[
                    isset($consultores[$id]) ? $consultores[$id]['nombres'] . ' ' . $consultores[$id]['apellidos'] : '#' . $id,
                ], $fila],
                array_keys($porConsultor), $porConsultor
            ),
        ],
    ];

    try {
        require_once __DIR__ . '/../vendor/autoload.php';

        $libro = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $libro->getProperties()->setCreator('MEJR Consulting')->setTitle('Dashboard');
        $libro->getDefaultStyle()->getFont()->setName('Calibri')->setSize(11);
        $libro->removeSheetByIndex(0);

        $texto = \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING;
        $numero = \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC;

        foreach ($hojas as $datosHoja) {
            $hoja = $libro->createSheet();
            $hoja->setTitle($datosHoja['titulo']);

            $cantidadTexto = count($datosHoja['columnas']) - 4;
            $ultima = chr(ord('A') + count($datosHoja['columnas']) - 1);

            $hoja->setCellValue('A1', 'MEJR CONSULTING | Dashboard | ' . $datosHoja['titulo']);
            $hoja->getStyle('A1')->getFont()->setBold(true)->setSize(14);
            $hoja->setCellValueExplicit('A2', $t('Período: ', 'Period: ') . $periodo, $texto);
            $hoja->setCellValue('A3', $t(
                'US$ con la tarifa vigente en la fecha de cada registro. Horas sin tarifa suman 0.',
                'US$ using the rate in force on each entry date. Hours without a rate count as 0.'
            ));
            if ($incompleto) {
                $hoja->setCellValue('A4', $t(
                    'ATENCIÓN: hay horas sin tarifa en el período; los montos están incompletos.',
                    'WARNING: some hours in the period have no rate; amounts are incomplete.'
                ));
                $hoja->getStyle('A4')->getFont()->setBold(true)->getColor()->setARGB('FFB45309');
            }

            $hoja->fromArray($datosHoja['columnas'], null, 'A5');

            $fila = 6;

            foreach ($datosHoja['filas'] as [$textos, $valores]) {
                $columna = 'A';

                foreach ($textos as $valorTexto) {
                    $hoja->setCellValueExplicit($columna++ . $fila, (string) $valorTexto, $texto);
                }

                $ingreso = $valores['ingreso'] / 10000;
                $costo = $valores['costo'] / 10000;
                foreach ([$valores['horas'] / 100, $ingreso, $costo, $ingreso - $costo] as $valorNumero) {
                    $hoja->setCellValueExplicit($columna++ . $fila, $valorNumero, $numero);
                }

                $fila++;
            }

            $primeraNumero = chr(ord('A') + $cantidadTexto);
            $hoja->setCellValue('A' . $fila, 'TOTAL');

            for ($columna = $primeraNumero; $columna <= $ultima; $columna++) {
                $hoja->setCellValue($columna . $fila, $fila > 6 ? "=SUM({$columna}6:{$columna}" . ($fila - 1) . ')' : 0);
                $hoja->getStyle("{$columna}6:{$columna}$fila")->getNumberFormat()
                    ->setFormatCode($columna === $primeraNumero ? '0.00' : '#,##0.00');
            }

            $hoja->getStyle("A5:{$ultima}5")->getFont()->setBold(true);
            $hoja->getStyle("A5:{$ultima}5")->getFill()->setFillType('solid')->getStartColor()->setARGB('FFE9ECEF');
            $hoja->getStyle("A$fila:{$ultima}$fila")->getFont()->setBold(true);
            $hoja->freezePane('A6');

            for ($columna = 'A'; $columna <= $ultima; $columna++) {
                $hoja->getColumnDimension($columna)->setWidth($columna < $primeraNumero ? 30 : 18);
            }
        }

        $libro->setActiveSheetIndex(0);
        $salida = fopen('php://memory', 'w+b');
        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($libro))->save($salida);
        rewind($salida);

        $nombre = preg_replace('/[^A-Za-z0-9-]+/', '-', 'MEJR-dashboard-' . strtr(explode(' · ', $periodo)[0], [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n', '–' => '-',
        ]));

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . trim($nombre, '-') . '.xlsx"');
        header('Cache-Control: no-store, private');
        header('X-Content-Type-Options: nosniff');

        fpassthru($salida);
        fclose($salida);
        $libro->disconnectWorksheets();
        exit;
    } catch (\Throwable $excepcion) {
        error_log($excepcion->getMessage());
        http_response_code(500);
        header('Content-Type: text/plain; charset=UTF-8');
        exit($t('No se pudo generar el Excel. Intenta nuevamente.', 'The Excel file could not be generated. Try again.'));
    }
}
