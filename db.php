<?php
require_once __DIR__ . '/vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class DataBase
{
  static public function getConnection(?string $dbName = null): mysqli
  {
    $originalDbName = $dbName;
    // Si no se pasa parámetro, PHP usará null y aquí lo detectamos.

    if ($dbName === null) {
      $dbName = self::getTenancyDbName() ?? 'nimbus';
    }

    if ($dbName !== 'nimbus') {
      $dbNameHeader = self::getTenancyDbName();
      if ($dbNameHeader !== null) {
        $dbName = $dbNameHeader;
      }
    } else {
      $dbName = "db2h4yv7vfqywh";
    }

    $host = '127.0.0.1'; // Usamos la IP para evitar el caché de localhost
    $user = 'uxg55gpaccltb'; // CRÍTICO: Esta variable faltaba en tu código

    // IMPORTANTE: Deja solo UNA contraseña aquí, la que esté activa en SiteGround
    $password = 'NimbusSanti2026DB';

    // Intentar conectar
    $db = new mysqli($host, $user, $password, $dbName);
    $db->set_charset('utf8');

    if ($db->connect_error) {
      $originalDbNameText = $originalDbName === null ? 'null' : $originalDbName;
      header('Content-Type: application/json');
      echo json_encode([
        'status' => 500,
        'statusText' => "Database Connection Error: " . $db->connect_error,
        'dbNameUsed' => $dbName,
        'originalDbName' => $originalDbNameText
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
        if (!defined('AUTHORIZATION_TOKEN')) {
          require_once __DIR__ . '/const.php';
        }

        $key = AUTHORIZATION_TOKEN;
        $decoded = JWT::decode($token, new Key($key, 'HS512'));
        $data = (array) $decoded->data;

        if (isset($data['db_name'])) {
          // Devuelve el nombre dinámico del token, no lo fuerces a la BD principal aquí
          return $data['db_name'];
        }
      } catch (Exception $e) {
        // Fallback silently if token decoding fails
      }
    }

    // Base de datos principal por defecto de SiteGround
    return null;
  }
}
