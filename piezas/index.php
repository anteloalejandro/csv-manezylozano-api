<?php

/* ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(0); */

// set_time_limit(300);

require_once(__DIR__ . '/../../wp-load.php');
require_once(__DIR__ . '/../helpers/headers.php');
require_once(__DIR__ . '/../helpers/login.php');
require_once(__DIR__ . '/../helpers/csv.php');

$testing = true; // Cambiar a false en el servidor

if (!($testing || isAllowed())) {
  CsvImportResponse::failure([], 'No se ha podido iniciar sesión');
} else if (!isset($_FILES['piezas-csv'])) {
  CsvImportResponse::failure([], 'Archivo no subido correctamente');
// } else if (stripos($_FILES['piezas-csv']['name'], '.csv') === false) {
} else if (pathinfo($_FILES['piezas-csv']['name'], PATHINFO_EXTENSION) != 'csv') {
  CsvImportResponse::failure([], 'El archivo debe tener formato CSV');
}

$rows = readCSV($_FILES['piezas-csv']['tmp_name']);

$warnings = [];

wp_insert_term(
  'Piezas',
  'product_cat',
  [ 'slug' => 'piezas' ]
);

CSVforEach($_FILES['piezas-csv']['tmp_name'], function ($i, $row, &$warnings) {
  if ($i == 0) return;
  set_time_limit(30);

  if (!is_array($row) || $row[1] == '') {
    array_push($warnings, new CsvWarning(
      'pieza',
      'none',
      "Ignorando la fila " . $i + 1 . " por un error de formato"
    ));
    return;
  }
  try {
    add_part($row, $i, $warnings);
  } catch (Throwable $e) {
    $error_str = json_encode([
      "stack_trace" => "$e",
      "row" => $i + 1,
      // "data" => $row
    ]);
    CsvImportResponse::failure($warnings, $error_str);
  }
}, $warnings);

CsvImportResponse::success($warnings);


function add_part(array $row, int $position, &$warning_arr)
{
  /* TO-DO
      Visibilidad
  */
  [$ref, $name, $price, $estanteria] = $row;
  $price = str_replace(',', '.', $price);

  $post_id = wc_get_product_id_by_sku($ref);
  if ($post_id) {
    $post = get_post($post_id);
    $post->post_title = $name;
    $post->post_name = sanitize_title($name);
    $post_id = wp_update_post($post);
  } else {
    $post_id = wp_insert_post([
      'post_author' => 1,
      'post_content' => '',
      'post_status' => "publish",
      'post_title' => $name,
      'post_name' => sanitize_title($name),
      'post_parent' => '',
      'post_type' => "product"
    ]);
    $product = wc_get_product($post_id);
    $product->set_sku($ref);
    $product->save();
  }


  try {
    set_attrs($post_id, $ref, $price);
    set_common_attrs($post_id);

    // wp_set_object_terms($post_id, 'simple', 'product_type');
    wp_set_object_terms($post_id, $estanteria, 'pa_estanteria');
    update_post_meta($post_id, '_product_attributes', [
      'pa_estanteria' => [
        'name' => 'pa_estanteria',
        'value' => $estanteria,
        'is_visible' => '1',
        'is_taxonomy' => '1'
      ]
    ]);
  } catch (Exception $e) {
    array_push($warning_arr, new CsvWarning(
      'pieza',
      $ref,
      'Error al establecer los atributos: ' . $e->getMessage()
    ));
  }

  wp_set_object_terms($post_id, 'Piezas', 'product_cat');

}

function set_attrs($post_id, $ref, $price)
{
  wp_update_post([
    'ID' => $post_id,
    'meta_input' => [
      '_sku' => $ref,
      '_regular_price' => $price,
      '_price' => $price
    ]
  ]);
}

function set_common_attrs($post_id)
{
  update_post_meta($post_id, '_product_attributes', [[
    'name' => 'estanteria',
    'value' => '',
    'position' => '0',
    'is_visible' => '1',
    'is_variation' => '1',
    'is_taxonomy' => '1'
  ]]);
  wp_update_post([
    'ID' => $post_id,
    'meta_input' => [
      'total_sales' => '0',
      '_tax_status' => 'taxable',
      '_tax_class' => 'taxable',
      '_manage_stock' => 'no',
      '_backorders' => 'no',
      '_sold_individually' => 'no',
      '_virtual' => 'no',
      '_downloadable' => 'no',
      '_download_limit' => '-1',
      '_download_expiry' => '-1',
      '_stock' => '',
      '_stock_status' => 'instock',
      '_wc_average_rating' => '0',
      '_wc_review_count' => '0',
      '_product_version' => '7.6.0',
      '_edit_lock' => '', # https://wordpress.stackexchange.com/questions/135480/why-are-simple-updates-to-wp-postmetas-edit-lock-so-slow
      '_edit_last' => '1',
      '_woovr_active' => 'default',
      '_visibility' => 'visible',
      '_purchase_note' => "",
      '_featured' => "no"
    ]
  ]);
}
