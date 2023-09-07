<?php

function db_connect(
  $host, $database, $user, $password, $port = 3306
) {
  $dbHost = "$host:$port";
  $dbName = $database;
  $dbUser = $user;
  $dbPass = $password;
  $charset = "utf8";
  $dsn = "mysql:host={$dbHost}; dbname={$dbName}; charset={$charset}";

  try {
    $dbConn = new PDO($dsn, $dbUser, $dbPass);
    $dbConn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  } catch (\Throwable $e) {
    return null;
  }

  return $dbConn;
}
