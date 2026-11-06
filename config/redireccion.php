<?php
// Redirección de pantallas antiguas a las nuevas, para no romper marcadores.
// Reenvía solo los parámetros conocidos; la pantalla de destino valida todo.
// Borrar estos archivos de redirección después de una entrega.

function mejrRedirigirPantalla(string $destino, array $permitidos = [], array $renombrar = []): void
{
    $parametros = [];

    foreach (array_merge($permitidos, ['idioma']) as $nombre) {
        if (isset($_GET[$nombre]) && is_string($_GET[$nombre])) {
            $parametros[$renombrar[$nombre] ?? $nombre] = $_GET[$nombre];
        }
    }

    header(
        'Location: ' . $destino . ($parametros ? '?' . http_build_query($parametros) : ''),
        true,
        302
    );
    exit;
}
