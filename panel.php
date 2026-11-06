<?php
// Ya no es una pantalla: envía a cada rol a su tarea principal.
// Se mantiene porque login.php y los marcadores apuntan aquí, y el POST
// sigue cerrando la sesión para pestañas abiertas con la versión anterior.
$rolPagina = null;
require __DIR__ . '/config/arranque.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    mejrCerrarSesion();
}

redirigir($esAdmin ? 'tablero.php' : 'mis-horas.php');
