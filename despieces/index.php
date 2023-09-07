<?php

ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(0);

require_once(__DIR__ . '/../../wp-load.php');
require_once(__DIR__ . '/../helpers/headers.php');
require_once(__DIR__ . '/../helpers/string.php');
require_once(__DIR__ . '/../helpers/login.php');
require_once(__DIR__ . '/../helpers/csv.php');

$testing = true; // Cambiar a false en el servidor

if ($testing || !isAllowed()) {
  CsvImportResponse::failure([], 'No se ha podido iniciar sesión');
} else if (!isset($_FILES['file'])) {
  CsvImportResponse::failure([], 'Archivo no subido correctamente');
} else if (stripos($_FILES['file']['name'], '.csv') === false) {
  CsvImportResponse::failure([], 'El archivo debe tener formato CSV');
}

$rows = readCSV($_FILES['file']['tmp_name']);
$warnings = [];
