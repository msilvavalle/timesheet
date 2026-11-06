<?php
// Cierre de sesión desde el menú de la cuenta. Solo POST con CSRF.
$rolPagina = null;
require __DIR__ . '/config/arranque.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirigir('panel.php');
}

mejrCerrarSesion();
