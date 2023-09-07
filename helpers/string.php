<?php

function random_string($used = [], $length = 13) {
  $chars = '0123456789abcdef';
  $charsLen = strlen($chars);
  $random = '';
  $repeat = false;
  do {
    for ($i = 0; $i < $length; $i++) {
      $random .= $chars[random_int(0, $charsLen - 1)];
    }

    $repeat = false;
    foreach ($used as $u) {
      if ($u == $random) {
        $repeat = true;
        $random = '';
      }
    }
  } while ($repeat);
  return $random;
}
