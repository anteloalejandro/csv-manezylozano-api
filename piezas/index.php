<?php

ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(0);

require_once(__DIR__ . '/../../wp-load.php');
require_once(__DIR__ . '/../helpers/headers.php');
require_once(__DIR__ . '/../helpers/string.php');
require_once(__DIR__ . '/../helpers/login.php');
require_once(__DIR__ . '/../helpers/csv.php');

$testing = false; // Cambiar a false en el servidor

if ($testing || !isAllowed()) {
  CsvImportResponse::failure([], 'No se ha podido iniciar sesión');
} else if (!isset($_FILES['file'])) {
  CsvImportResponse::failure([], 'Archivo no subido correctamente');
} else if (stripos($_FILES['file']['name'], '.csv') === false) {
  CsvImportResponse::failure([], 'El archivo debe tener formato CSV');
}

$rows = readCSV($_FILES['file']['tmp_name']);

$idDespiece = 63;
$warnings = [];

for ($i = 1; $i < count($rows); $i++) {
  $row = $rows[$i];

  if (!is_array($row)) {
    array_push($warnings, new CsvWarning(
      'pieza',
      'none',
      "Error de formato en la fila " . $i + 1
    ));
    continue;
  }
  try {
    add_part($row, $i, $idDespiece, $warnings);
  } catch (Throwable $e) {
    CsvImportResponse::failure($warnings, $e->getMessage());
  }
}

CsvImportResponse::success($warnings);


function add_part(array $row, int $position, $idDespiece, &$warning_arr)
{
  /* TO-DO
      Visibilidad
  */
  [$ref, $name, $price, $estanteria] = $row;
  $price = str_replace(',', '.', $price);

  $post_id = wc_get_product_id_by_sku($ref); // No funciona
  if ($post_id) {
    $post = get_post($post_id);
    $post->post_title = $name;
    $post->post_name = sanitize_title($name);
    $post->menu_order = $position;
    $post_id = wp_update_post($post);
  } else {
    $post_id = wp_insert_post([
      'post_author' => 1,
      'post_content' => '',
      'post_status' => "publish",
      'post_title' => $name,
      'post_name' => sanitize_title($name),
      'post_parent' => '',
      'post_type' => "product",
      'menu_order' => $position
    ]);
  }

  $product = wc_get_product($post_id);
  $product->set_sku($ref);
  $product->save();

  try {
    add_part_to_bundle($idDespiece, $post_id, $ref, 1);
  } catch (Exception $e) {
    array_push($warning_arr, new CsvWarning(
      'pieza',
      $ref,
      'Error al añadir al conjunto: ' . $e->getMessage()
    ));
  }

  try {
    set_category($idDespiece, $post_id);
  } catch (Exception $e) {
    array_push($warning_arr, new CsvWarning(
      'pieza',
      $ref,
      'Error al establecer la categoría: ' . $e->getMessage()
    ));
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

}

function set_category($idDespiece, $post_id)
{
  $categorias_despiece = get_the_terms($idDespiece, 'product_cat');
  $nombre_categoria = 'Piezas ' . $categorias_despiece[0]->name;
  $all_categories = get_categories();
  $exists = false;
  foreach ($all_categories as $category) {
    if ($category->slug == $categorias_despiece[0]->slug) {
      $exists = true;
      break;
    }
  }

  if (!$exists) {
    wp_insert_term(
      $nombre_categoria,
      'product_cat',
      [
        'description' => '',
        'slug' => 'piezas-' . $categorias_despiece[0]->slug,
        'parent' => $categorias_despiece[0]->term_id
      ]
    );
  }

  wp_set_object_terms($post_id, $nombre_categoria, 'product_cat');
}

function add_part_to_bundle($idDespiece, $product_id, $ref, $quantity = '1')
{
  $despiece_bundle = get_post_meta($idDespiece, 'woosb_ids', true);
  if (is_serialized($despiece_bundle)) {
    unserialize($despiece_bundle);
  }
  if ($despiece_bundle == "") {
    $despiece_bundle = [];
  }
  $bundle_id = bin2hex(random_bytes(7));
  $bundle_id = substr_replace($bundle_id, '', 0, 1); // truncar 1 para tener 13
  $bundle_item = [
    $bundle_id => [
      'id' => "$product_id",
      'sku' => $ref,
      'qty' => "$quantity"
    ]
  ];
  $replaced = false;
  foreach ($despiece_bundle as &$despiece) {
    if ($despiece['sku'] == reset($bundle_item)['sku']) {
      $despiece = reset($bundle_item);
      $replaced = true;
      break;
    }
  }
  if (!$replaced) {
    $despiece_bundle += $bundle_item;
  }

  update_post_meta($idDespiece, 'woosb_ids', $despiece_bundle );
}

function set_attrs($post_id, $ref, $price)
{
  update_post_meta($post_id, '_sku', $ref);
  update_post_meta($post_id, '_regular_price', $price);
  update_post_meta($post_id, '_price', $price);
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
  update_post_meta($post_id, 'total_sales', '0');
  update_post_meta($post_id, '_tax_status', 'taxable');
  update_post_meta($post_id, '_tax_class', 'taxable');
  update_post_meta($post_id, '_manage_stock', 'no');
  update_post_meta($post_id, '_backorders', 'no');
  update_post_meta($post_id, '_sold_individually', 'no');
  update_post_meta($post_id, '_virtual', 'no');
  update_post_meta($post_id, '_downloadable', 'no');
  update_post_meta($post_id, '_download_limit', '-1');
  update_post_meta($post_id, '_download_expiry', '-1');
  update_post_meta($post_id, '_stock', '');
  update_post_meta($post_id, '_stock_status', 'instock');
  update_post_meta($post_id, '_wc_average_rating', '0');
  update_post_meta($post_id, '_wc_review_count', '0');
  update_post_meta($post_id, '_product_version', '7.6.0');
  update_post_meta($post_id, '_edit_lock', ''); # https://wordpress.stackexchange.com/questions/135480/why-are-simple-updates-to-wp-postmetas-edit-lock-so-slow
  update_post_meta($post_id, '_edit_last', '1');
  update_post_meta($post_id, '_woovr_active', 'default');
  update_post_meta($post_id, '_visibility', 'visible');
  update_post_meta($post_id, '_purchase_note', "");
  update_post_meta($post_id, '_featured', "no");
}
