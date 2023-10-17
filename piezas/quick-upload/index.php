<?php

set_time_limit(300);

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(1);

require_once(__DIR__ . '/../../../wp-load.php');
require_once(__DIR__ . '/../../helpers/headers.php');
require_once(__DIR__ . '/../../helpers/login.php');
require_once(__DIR__ . '/../../helpers/csv.php');

$testing = true; // Cambiar a false en el servidor


if (!($testing || isAllowed())) {
  CsvImportResponse::failure([], 'No se ha podido iniciar sesión');
}

$json = file_get_contents('php://input');
if ($json == false) {
  CsvImportResponse::failure([], "Falta el argumento 'data'");
}
$data = json_decode($json, true);

/**
 * Formato del array asociativo
  $data = [
    [
      'ref' => '',
      'name' => '',
      'price' => '',
      'estanteria' => ''
    ]
  ];
*/

wp_insert_term(
  'Piezas',
  'product_cat',
  [ 'slug' => 'piezas' ]
);

foreach ($data as $i => $d) {
  if ($d['name'] == '') {
    continue;
  }
  add_part($d['ref'], $d['name'], $d['price'], $d['estanteria']);
}

CsvImportResponse::success([]);

function add_part($ref, $name, $price, $estanteria)
{
  $price = str_replace(',', '.', $price);

  $post_id = wc_get_product_id_by_sku($ref);
  if ($post_id) {
    $post = get_post($post_id);
    $update = false;
    if ($post->post_title != $name) {
      $post->post_title = $name;
      $update = true;
    }
    if (($sanitized_name = sanitize_title($name)) != $post->post_title) {
      $post->post_name = $sanitized_name;
      $update = true;
    }
    if ($update) {
      $post_id = wp_update_post($post);
    }
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

  wp_set_object_terms($post_id, $estanteria, 'pa_estanteria');
  wp_set_object_terms($post_id, 'Piezas', 'product_cat');
  set_attrs($post_id, $ref, $price, $estanteria);
}

function set_attrs($post_id, $ref, $price, $estanteria)
{
  wp_update_post([
    'ID' => $post_id,
    'meta_input' => [
      '_sku' => $ref,
      '_regular_price' => $price,
      '_price' => $price,
      'pa_estanteria' => [
        'name' => 'pa_estanteria',
        'value' => $estanteria,
        'is_visible' => '1',
        'is_taxonomy' => '1'
      ],

      // Common attrs
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
