<?php

include_once(__DIR__ . '/../../wp-load.php');

class LoginResponse
{
  public $error;
  public $message;
  public $redirect_link;

  public function __construct(bool $error, string $message, string $redirect_link)
  {
    $this->error = $error;
    $this->message = $message;
    $this->redirect_link = $redirect_link;
  }

  public static function success(string $redirect_link)
  {
    echo json_encode(new LoginResponse(false, 'OK', $redirect_link), JSON_UNESCAPED_SLASHES);
    die();
  }

  public static function failure(string $message, string $redirect_link)
  {
    echo json_encode(new LoginResponse(true, $message, $redirect_link), JSON_UNESCAPED_SLASHES);
    die();
  }
}

function isAllowed()
{
  $current_user = wp_get_current_user();
  if (0 == $current_user->ID) {
    return false;
  }

  if (!in_array('administrator',  $current_user->roles)) {
    return false;
  }

  return true;
}
