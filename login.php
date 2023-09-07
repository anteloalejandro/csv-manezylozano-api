<?php

// Llamada de API para iniciar sesión
// Devolverá la URL para iniciar sesión y volver a la APP = wp_login_url('/app', true)
// La APP llamará a este archivo antes de dejar pasar al usuario

include_once(__DIR__ . '/../wp-load.php');
require_once(__DIR__ . '/helpers/login.php');
require_once(__DIR__ . '/helpers/headers.php');

$protocol = stripos($_SERVER['SERVER_PROTOCOL'], 'https') === 0 ? 'https' : 'http';
$base_url = "$protocol://" . $_SERVER['HTTP_HOST'] . "/importar/csv";

if (!isAllowed()) {
  LoginResponse::failure(
    'No se ha podido iniciar sesión',
    wp_login_url($base_url, true)
  );
}

LoginResponse::success("$base_url");
