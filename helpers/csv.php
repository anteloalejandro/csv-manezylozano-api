<?php

function readCSV(string $filename)
{
  $file = fopen($filename, 'r+');

  if ($file === false) return false;

  $rows = [];
  while (!feof($file)) {
    $row = fgetcsv($file, null, ';', '"');
    if ($row !== false) {
      array_push($rows, $row);
    }
  }
  fclose($file);

  return $rows;
}

function CSVforEach(string $filename, $callback, &$warnings)
{
  $file = fopen($filename, 'r+');

  if ($file === false) return false;

  $i = 0;
  while (!feof($file)) {
    $row = fgetcsv($file, null, ';', '"');
    if ($row !== false) {
      $callback($i, $row, $warnings);
    }
    $i++;
  }
  fclose($file);
}

class CsvWarning {
  public $dataType;
  public $ref;
  public $message;

  public function __construct(string $dataType, string $ref, string $message)
  {
    $this->dataType = $dataType;
    $this->ref = $ref;
    $this->message = $message;
  }
}

class CsvImportResponse {
  public $error;
  public $error_msg;
  public $warnings;

  /**
   * @param $error bool
   * @param CsvWarning[] $warnings
   */
  public function __construct(bool $error, array $warnings, string $error_msg)
  {
    $this->error = $error;
    $this->error_msg = $error_msg;
    $this->warnings = $warnings;
  }

  public static function success(array $warnings)
  {
    echo json_encode(new CsvImportResponse(false, $warnings, ''));
    die();
  }

  public static function failure(array $warnings, string $error_msg)
  {
    echo json_encode(new CsvImportResponse(true, $warnings, $error_msg));
    die();
  }
}
