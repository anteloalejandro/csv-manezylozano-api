<?php

/* ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(0); */

require_once(__DIR__ . '/../../wp-load.php');
require_once(__DIR__ . '/../helpers/headers.php');
require_once(__DIR__ . '/../helpers/login.php');
require_once(__DIR__ . '/../helpers/csv.php');

$testing = true; // Cambiar a false en el servidor

if (!($testing || isAllowed())) {
  CsvImportResponse::failure([], 'No se ha podido iniciar sesión');
} else if (!isset($_FILES['despieces-csv'])) {
  CsvImportResponse::failure([], 'Archivo no subido correctamente');
} else if (pathinfo($_FILES['despieces-csv']['name'], PATHINFO_EXTENSION) != 'csv') {
  CsvImportResponse::failure([], 'El archivo debe tener formato CSV');
}

$rows = readCSV($_FILES['despieces-csv']['tmp_name']);
$unique_assemblies = [];
foreach ($rows as $i => $r) {
  if ($i == 0) continue;
  if (!is_array($r)) {
    array_push($warnings, new CsvWarning(
      'pieza',
      'none',
      "Error de formato en la fila " . ($i + 1)
    ));
    continue;
  }
  $arr = ['category' => $r[0], 'assembly' => $r[1]];
  if (in_array($arr, $r)) continue;
  array_push($unique_assemblies, $arr);
}

$warnings = [];

foreach ($unique_assemblies as $i => $row) {
  if ($i == 0) continue;
  try {
    $id = add_assembly($row['category'], $row['assembly'], $warnings);
    if ($id) {
      $name = $row['assembly'];
      $products = [];
      $filtered_rows = array_filter($rows, fn ($r) => $r[1] == $name);
      foreach ($filtered_rows as $r) {
        [$category_name, $assembly_name, $part_position, $part_ref, $part_qty] = $r;

        $part_id = wc_get_product_id_by_sku($part_ref);
        if ($part_id <= 0) continue;

        array_push($products, [
          'id' => "$part_id",
          'sku' => "$part_ref",
          'qty' => "$part_qty",
          'pos' => "$part_position"
        ]);
      }

      add_parts_to_bundle($id, $products);
    }
  } catch (Throwable $e) {
    CsvImportResponse::failure($warnings, $e->getMessage());
  }
}

CsvImportResponse::success($warnings);

function add_assembly($category_name, $assembly_name, &$warning_arr)
{
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
  return $assembly_id;
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
      'slug' => sanitize_title($category_name)
    ]
  );
}

function convert_to_bundle($assembly_id)
{
  wp_set_object_terms($assembly_id, 'woosb', 'product_type');

  wp_update_post([
    'ID' => $assembly_id,
    'meta_input' => [
      'woosb_optional_products' => 'on', // Editar nª de piezas
      'total_sales' => '0',
      '_tax_status' => 'taxable',
      '_tax_class' => '',
      '_manage_stock' => 'no',
      '_backorders' => 'no',
      '_sold_individually' => 'no',
      '_virtual' => 'no',
      '_downloadable' => 'no',
      '_download_limit' => '-1',
      '_download_expiry' => '-1',
      '_stock' => 'NULL',
      '_stock_status' => 'instock',
      '_wc_average_rating' => '0',
      '_wc_average_count' => '0',
      'woosb_disable_auto_price_off' => 'off',
      'woosb_discount' => '',
      'woosb_discount_amount' => '',
      'woosb_shipping_fee' => 'whole',
      'woosb_manage_stock' => 'off',
      'woosb_limit_each_min' => '',
      'woosb_limit_each_min_default' => 'off',
      'woosb_limit_whole_min' => '',
      'woosb_limit_whole_max' => '',
      'woosb_total_limits' => 'off',
      'woosb_total_limits_min' => '',
      'woosb_total_limits_max' => '',
      'woosb_layout' => 'unset',
      '_edit_lock' => NULL, // https://wordpress.stackexchange.com/questions/135480/why-are-simple-updates-to-wp-postmetas-edit-lock-so-slow
      '_edit_last' => '1'
    ]
  ]);

}

function add_parts_to_bundle($idDespiece, array $products)
{
  $despiece_bundle = get_post_meta($idDespiece, 'woosb_ids', true);
  if (is_serialized($despiece_bundle)) {
    unserialize($despiece_bundle);
  }
  if ($despiece_bundle == "") {
    $despiece_bundle = [];
  }

  $i = 0;
  foreach($despiece_bundle as &$despiece) {
    $despiece['pos'] ??= $i;
    $i++;
  }

  $bundles = [];
  for ($i = 0; $i < count($products); $i++) {
    $product = $products[$i];
    $bundle_id = sprintf("%'.013d", $i);

    $bundle_item = [
      $bundle_id => [
        'id' => $product['id'],
        'sku' => $product['sku'],
        'qty' => $product['qty'],
        'pos' => $product['pos']
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
  }

  usort($despiece_bundle, fn ($a, $b) => (int)$a['pos'] - (int)$b['pos']);
  update_post_meta($idDespiece, 'woosb_ids', $despiece_bundle);
}
