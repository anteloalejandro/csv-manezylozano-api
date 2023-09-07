<?php

$protocol = stripos($_SERVER['SERVER_PROTOCOL'], 'https') === 0 ? 'https' : 'http';
$base_url = "$protocol://" . $_SERVER['HTTP_HOST'];

header("Location: $base_url/importar/csv/");
