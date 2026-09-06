<?php
// Permite ao servidor PHP local entregar assets directamente e encaminha
// todas as restantes rotas para o front controller da aplicação.
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$file = dirname(__DIR__) . '/public' . $path;
if ($path !== '/' && is_file($file)) {
    return false;
}
require dirname(__DIR__) . '/public/index.php';