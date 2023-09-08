<?php

include_once(__DIR__ . '/../wp-load.php');

$posts = get_posts([
  'numberposts' => -1,
  'post_type' => 'product'
]);

$deleted = [];

foreach ($posts as $p) {
  array_push($deleted, wp_delete_post(is_int($p) ? $p : $p->ID, true));
}


echo json_encode($deleted);
