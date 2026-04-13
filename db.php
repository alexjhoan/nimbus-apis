<?php
require_once __DIR__ . '/vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class DataBase
{
  static public function getConnection($dbName = NULL)
  {

    if ($dbName === NULL) {
      $dbName = self::getTenancyDbName();
    } else {
      $dbName = "casamedi_" . $dbName;
    }

    $host = 'localhost';
    $user = 'casamedi';
    $password = '4h!2[A7C9vSVoh';

    // Intentar conectar
    $db = new mysqli($host, $user, $password, $dbName);
    $db->set_charset('utf8');

    if ($db->connect_error) {
      header('Content-Type: application/json');
      echo json_encode([
        'status' => 500,
        'statusText' => "Database Connection Error: " . $db->connect_error
      ], http_response_code(500));
      die();
    }

    return $db;
  }

  static private function getTenancyDbName()
  {
    $headers = array_change_key_case(getallheaders(), CASE_LOWER);
    $token = $headers['x-tenancy-token'] ?? null;

    if ($token) {
      try {
        // We use the same key as AUTHORIZATION_TOKEN for the tenancy token
        // Need to make sure const.php is loaded or define it here if needed
        if (!defined('AUTHORIZATION_TOKEN')) {
          require_once __DIR__ . '/const.php';
        }

        $key = AUTHORIZATION_TOKEN;
        $decoded = JWT::decode($token, new Key($key, 'HS512'));
        $data = (array) $decoded->data;

        if (isset($data['db_name'])) {
          return "casamedi_" . $data['db_name'];
        }
      } catch (Exception $e) {
        // Fallback or handle error
      }
    }

    return "casamedi_nimbus";
  }
}
