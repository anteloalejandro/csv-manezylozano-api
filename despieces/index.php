<?php

ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(0);

require_once(__DIR__ . '/../../wp-load.php');
require_once(__DIR__ . '/../helpers/headers.php');
require_once(__DIR__ . '/../helpers/login.php');
require_once(__DIR__ . '/../helpers/csv.php');

$testing = false; // Cambiar a false en el servidor

if (!($testing || isAllowed())) {
  CsvImportResponse::failure([], 'No se ha podido iniciar sesión');
} else if (!isset($_FILES['despieces-csv'])) {
  CsvImportResponse::failure([], 'Archivo no subido correctamente');
} else if (pathinfo($_FILES['despieces-csv']['name'], PATHINFO_EXTENSION) != 'csv') {
  CsvImportResponse::failure([], 'El archivo debe tener formato CSV');
}

$rows = readCSV($_FILES['despieces-csv']['tmp_name']);

usort($rows, function ($a, $b) {
  $a_pos = $a[2];
  $b_pos = $b[2];

  return (int)$a_pos - (int)$b_pos;
});
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
    add_assembly($row, $i, $warnings);
  } catch (Throwable $e) {
    CsvImportResponse::failure($warnings, $e->getMessage());
  }
}

CsvImportResponse::success($warnings);

function add_assembly(array $row, $idx, &$warning_arr)
{
  [$category_name, $assembly_name, $part_position, $part_ref, $part_qty] = $row;

  $part_id = wc_get_product_id_by_sku($part_ref);
  if ($part_id <= 0) {
    array_push($warning_arr, new CsvWarning('part', $part_ref, "Pieza $part_ref no encontrada"));
    return;
  }
  $assemblies = get_posts([
    'name' => sanitize_title($assembly_name),
    'post_type' => 'product'
  ]);

  $assembly_id = null;
  if (count($assemblies) > 0) {
    $assembly_id = is_int($assemblies[0])
      ? $assemblies[0]
      : $assemblies[0]->ID;
  } else {
    $assembly_id = wp_insert_post([
      'post_author' => 1,
      'post_content' => '',
      'post_status' => "publish",
      'post_title' => $assembly_name,
      'post_name' => sanitize_title($assembly_name),
      'post_parent' => '',
      'post_type' => "product"
    ]);
  }

  create_category_if_not_exists($category_name);
  wp_set_object_terms($assembly_id, $category_name, 'product_cat');
  convert_to_bundle($assembly_id);
  add_part_to_bundle($assembly_id, $part_id, $part_ref, $part_qty, $idx);
}

function create_category_if_not_exists($category_name)
{
  $categories = get_terms(['taxonomy' => 'product_cat']);
  foreach ($categories as $cat) {
    if ($cat->name == $cat || $cat->slug == sanitize_title($category_name)) {
      return;
    }
  }

  wp_insert_term(
    $category_name,
    'product_cat',
    [
      'description' => '',
      'slug' => sanitize_title($category_name)
    ]
  );
}

function convert_to_bundle($assembly_id)
{
  wp_set_object_terms($assembly_id, 'woosb', 'product_type');
  update_post_meta($assembly_id, 'woosb_optional_products', 'on'); // Editar nª de piezas

  update_post_meta($assembly_id, 'total_sales', '0');
  update_post_meta($assembly_id, '_tax_status', 'taxable');
  update_post_meta($assembly_id, '_tax_class', '');
  update_post_meta($assembly_id, '_manage_stock', 'no');
  update_post_meta($assembly_id, '_backorders', 'no');
  update_post_meta($assembly_id, '_sold_individually', 'no');
  update_post_meta($assembly_id, '_virtual', 'no');
  update_post_meta($assembly_id, '_downloadable', 'no');
  update_post_meta($assembly_id, '_download_limit', '-1');
  update_post_meta($assembly_id, '_download_expiry', '-1');
  update_post_meta($assembly_id, '_stock', 'NULL');
  update_post_meta($assembly_id, '_stock_status', 'instock');
  update_post_meta($assembly_id, '_wc_average_rating', '0');
  update_post_meta($assembly_id, '_wc_average_count', '0');
  update_post_meta($assembly_id, 'woosb_disable_auto_price_off', 'off');
  update_post_meta($assembly_id, 'woosb_discount', '');
  update_post_meta($assembly_id, 'woosb_discount_amount', '');
  update_post_meta($assembly_id, 'woosb_shipping_fee', 'whole');
  update_post_meta($assembly_id, 'woosb_manage_stock', 'off');
  update_post_meta($assembly_id, 'woosb_limit_each_min', '');
  update_post_meta($assembly_id, 'woosb_limit_each_min_default', 'off');
  update_post_meta($assembly_id, 'woosb_limit_whole_min', '');
  update_post_meta($assembly_id, 'woosb_limit_whole_max', '');
  update_post_meta($assembly_id, 'woosb_total_limits', 'off');
  update_post_meta($assembly_id, 'woosb_total_limits_min', '');
  update_post_meta($assembly_id, 'woosb_total_limits_max', '');
  update_post_meta($assembly_id, 'woosb_layout', 'unset');
  update_post_meta($assembly_id, '_edit_lock', NULL); // https://wordpress.stackexchange.com/questions/135480/why-are-simple-updates-to-wp-postmetas-edit-lock-so-slow
  update_post_meta($assembly_id, '_edit_last', '1');

}

function add_part_to_bundle($idDespiece, $product_id, $ref, $quantity = '1', $idx = 0)
{
  $despiece_bundle = get_post_meta($idDespiece, 'woosb_ids', true);
  if (is_serialized($despiece_bundle)) {
    unserialize($despiece_bundle);
  }
  if ($despiece_bundle == "") {
    $despiece_bundle = [];
  }


  $bundle_id = '';
  if ($idx <= 0) {
    $bundle_id = bin2hex(random_bytes(7));
    $bundle_id = substr_replace($bundle_id, '', 0, 1); // truncar 1 para tener 13
  } else {
    $bundle_id = sprintf("%'.013d", $idx);
  }

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

  update_post_meta($idDespiece, 'woosb_ids', $despiece_bundle);
}
