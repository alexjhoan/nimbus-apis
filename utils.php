<?php

require_once "db.php";
require_once "vendor/autoload.php";

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class Utils
{
  static function diverseArray($vector)
  {
    $result = [];
    foreach ($vector as $key1 => $value1) {
      foreach ($value1 as $key2 => $value2) {
        $result[$key2][$key1] = $value2;
      }
    }
    return $result;
  }
  static function isRecordExists($table, $column, $record, $db = null)
  {
    $sql = "SELECT * FROM $table where `$column` = ?";
    try {

      $db = DataBase::getConnection($db);
      $stmt = $db->prepare($sql);

      // Determinar el tipo de variable para bind_param
      $type = is_numeric($record) ? 'i' : 's';

      $stmt->bind_param($type, $record); // Vincular el tipo de variable adecuado
      $stmt->execute();
      $result = $stmt->get_result();

      if ($result->num_rows == 0) {
        return ["boolean" => false, "data" => null, "total" => 0];
      } else if ($result->num_rows == 1) {
        $myArray = [];
        foreach ($result->fetch_assoc() as $key => $value) {
          ($value !== NULL && substr($value, 0, 3) === '[{"') ? $row[$key] = json_decode($value, true) : $row[$key] = $value;
        }
        return ["boolean" => true, "data" => $row, "total" => $result->num_rows];
      } else {
        $myArray = [];
        while ($row = $result->fetch_assoc()) {
          foreach ($row as $key => $value) {
            ($value !== NULL && substr($value, 0, 3) === '[{"') ? $row[$key] = json_decode($value, true) : $row[$key] = $value;
          }
          array_push($myArray, $row);
        }
        return ["boolean" => true, "data" => $myArray, "total" => $result->num_rows];
      }
    } catch (mysqli_sql_exception $error) {
      $json = [
        'status' => 500,
        'body' => [],
        'statusText' => $error->getMessage()
      ];
      echo json_encode($json, http_response_code($json["status"]));
      die();
    } finally {
      $stmt->close();
      $db->close();
    }
  }
  static function isRecordExistsMulti($table, $column, $record, $db = null)
  {

    $recordArray = explode(',', $record);

    // Limpieza de los IDs (opcional, en caso de que haya espacios)
    $recordArray = array_map('trim', $recordArray);

    // Convertir el array en enteros para seguridad
    $recordArray = array_map('intval', $recordArray);

    // Crear los marcadores de posición para la consulta
    $placeholders = implode(',', array_fill(0, count($recordArray), '?'));
    $sql = "SELECT * FROM $table WHERE `$column` IN ($placeholders)";

    try {

      $db = DataBase::getConnection($db);
      $stmt = $db->prepare($sql);

      // Vincular los IDs de productos
      $bindParams = [];
      $types = str_repeat('i', count($recordArray)); // Suponiendo que son todos enteros

      // Vincular los parámetros
      foreach ($recordArray as $key => $id) {
        $bindParams[$key] = $id; // Asignar cada ID a una posición del array
      }

      // El operador splat (...) se usa para pasar el array como parámetros
      $stmt->bind_param($types, ...$bindParams);
      $stmt->execute();
      $result = $stmt->get_result();

      // Agregar productos a la respuesta

      if ($result->num_rows == 0) {
        return ["boolean" => false, "data" => null, "total" => 0];
      } else if ($result->num_rows == 1) {
        foreach ($result->fetch_assoc() as $key => $value) {
          ($value !== NULL && substr($value, 0, 3) === '[{"') ? $row[$key] = json_decode($value, true) : $row[$key] = $value;
        }
        return ["boolean" => true, "data" => $row, "total" => $result->num_rows];
      } else {
        $myArray = [];
        while ($row = $result->fetch_assoc()) {
          foreach ($row as $key => $value) {
            ($value !== NULL && substr($value, 0, 3) === '[{"') ? $row[$key] = json_decode($value, true) : $row[$key] = $value;
          }
          array_push($myArray, $row);
        }
        return ["boolean" => true, "data" => $myArray, "total" => $result->num_rows];
      }
    } catch (mysqli_sql_exception $error) {
      $json = [
        'status' => 500,
        'body' => [],
        'statusText' => $error->getMessage()
      ];
      echo json_encode($json, http_response_code($json["status"]));
      die();
    } finally {
      $stmt->close();
      $db->close();
    }
  }
  static function ResizeAndSaveImg($img, $ext, $folder)
  {
    $nAncho = 1000; // Nuevo ancho
    $nAlto = 1000;  // Nuevo alto

    // Validamos la extensión y creamos una nueva imagen a partir del fichero inicial
    switch ($ext) {
      case 'jpg':
      case 'jpeg':
        $imagen = imagecreatefromjpeg($img);
        break;
      case 'png':
        $imagen = imagecreatefrompng($img);
        break;
      default:
        $json = [
          'status' => 400,
          'body' => [],
          'statusText' => "It is not a valid image, only formats, jpg and png"
        ];
        echo json_encode($json, http_response_code($json["status"]));
        die();
    }

    // Obtenemos el tamaño 
    $x = imagesx($imagen);
    $y = imagesy($imagen);

    if ($x > 1000 || $y > 1000) {
      // Validamos los tamaños y calculamos la relación de aspecto
      if ($x >= $y) {
        $nAlto = $nAncho * $y / $x;
      } else {
        $nAncho = $x / $y * $nAlto;
      }

      // Crear una nueva imagen, copia y cambia el tamaño de la imagen
      // Aquí es donde configuramos la transparencia
      $img = imagecreatetruecolor($nAncho, $nAlto);

      // Habilitar la transparencia en la nueva imagen
      imagealphablending($img, false);
      imagesavealpha($img, true);
      $transparent = imagecolorallocatealpha($img, 255, 255, 255, 127); // Color blanco con 100% de transparencia
      imagefill($img, 0, 0, $transparent);

      imagecopyresampled($img, $imagen, 0, 0, 0, 0, floor($nAncho), floor($nAlto), $x, $y);
    } else {
      $img = $imagen; // Usar la imagen original si no es necesaria la redimensión
    }

    // Verificar si el directorio existe, si no, crearlo
    if (!is_dir(dirname($folder))) {
      mkdir(dirname($folder), 0755, true); // Crea el directorio con permisos 0755
    }

    // Guardar la imagen con la extensión correcta
    switch ($ext) {
      case 'jpg':
      case 'jpeg':
        imagejpeg($img, $folder);
        break;
      case 'png':
        imagepng($img, $folder);
        break;
    }
  }

  static function normalizeFileName($filename)
  {
    // Remove accents
    $filename = iconv('UTF-8', 'ASCII//TRANSLIT', $filename);
    // Replace spaces and special characters with underscores
    $filename = preg_replace('/[^A-Za-z0-9_\-]/', '_', $filename);
    // Remove multiple underscores
    $filename = preg_replace('/_+/', '_', $filename);
    // Trim underscores from start and end
    $filename = trim($filename, '_');
    return $filename;
  }

  static public function JwtEncode($data)
  {
    $key = AUTHORIZATION_TOKEN;
    $token = [
      "iat" => time(),
      "exp" => time() + (60 * 60 * 24 * 30),
      "data" => $data
    ];

    $jwt = JWT::encode($token, $key, 'HS512');
    // foreach ($data as $clave => $valor) {
    //   $token[$clave] = $valor;
    // }
    return ["jwt" => $jwt, "iat" => $token['iat'], "exp" => $token['exp']];
  }

  static function JwtDecode($tokenEncode)
  {
    $key = AUTHORIZATION_TOKEN;

    // esto nos devuelve un objeto
    $decoded = JWT::decode($tokenEncode, new Key($key, 'HS512'));

    //  con esto lo convertimos a un array entendible para php
    $decoded_array = (array) $decoded;

    return $decoded_array;
  }

  static function saveAuditLog($action, $table, $recordId, $oldValues = null, $newValues = null)
  {
    $userId = null;
    $token = null;

    // Obtener el token de los cabeceras o cookies
    $headers = array_change_key_case(getallheaders(), CASE_LOWER);
    if (isset($headers['authorization'])) {
      $token = str_replace('bearer ', '', strtolower($headers['authorization']));
      // reload original token case if needed, but JwtDecode should handle it
      $token = str_ireplace('bearer ', '', $headers['authorization']);
    } elseif (isset($_COOKIE["MyMarketTok3nHttp0lnt"])) {
      $token = $_COOKIE["MyMarketTok3nHttp0lnt"];
    }

    if ($token) {
      try {
        $decoded = Utils::JwtDecode($token);
        // El decodificador suele devolver un objeto para 'data'
        if (isset($decoded['data']->id)) {
          $userId = $decoded['data']->id;
        } else if (isset($decoded['data']['id'])) {
          $userId = $decoded['data']['id'];
        }
      } catch (Exception $e) {
        // Ignorar errores de token para el log
      }
    }

    try {
      $db = DataBase::getConnection();
      $sql = "INSERT INTO audit_logs (user_id, table_name, record_id, action, old_values, new_values) VALUES (?, ?, ?, ?, ?, ?)";
      $stmt = $db->prepare($sql);
      $oldValuesStr = $oldValues ? json_encode($oldValues) : null;
      $newValuesStr = $newValues ? json_encode($newValues) : null;
      $stmt->bind_param("isssss", $userId, $table, $recordId, $action, $oldValuesStr, $newValuesStr);
      $stmt->execute();
      $stmt->close();
      $db->close();
    } catch (Exception $e) {
      // Evitar que un error en el log detenga la ejecución principal
      error_log("Audit Log Error: " . $e->getMessage());
    }
  }

  static function ValidateTableName($table)
  {
    try {
      $db = DataBase::getConnection();
      $sql = "SHOW TABLES LIKE ?";
      $stmt = $db->prepare($sql);
      $stmt->bind_param("s", $table);
      $stmt->execute();
      $result = $stmt->get_result();
      $count = $result->num_rows;
      $stmt->close();
      $db->close();
      return $count;
    } catch (Exception $e) {
      return 0;
    }
  }
  static function validateAppToken()
  {
    $headers = apache_request_headers();
    $authHeader = $headers['Authorization'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? null;

    if (!$authHeader || $authHeader !== AUTHORIZATION_TOKEN) {
      $json = [
        'status' => 403,
        'body' => [],
        'statusText' => 'Forbidden: Invalid Authorization Token'
      ];
      echo json_encode($json, http_response_code($json["status"]));
      die();
    }
  }

  static function updateDailyBalance($date)
  {
    $db = DataBase::getConnection();
    try {
      // Calculate total_debt (Me Deben - Sales)
      $stmtSales = $db->prepare("SELECT SUM(total) as total FROM sales WHERE sale_date = ? AND status != 'Inactivo' AND payment_type = 'credito'");
      $stmtSales->bind_param("s", $date);
      $stmtSales->execute();
      $resSales = $stmtSales->get_result()->fetch_assoc();
      $totalDebt = $resSales['total'] ?? 0;

      // Calculate total_credit (Yo Debo - Purchases)
      $stmtPurchases = $db->prepare("SELECT SUM(total) as total FROM purchases WHERE purchase_date = ? AND status != 'Inactivo' AND payment_type = 'credito'");
      $stmtPurchases->bind_param("s", $date);
      $stmtPurchases->execute();
      $resPurchases = $stmtPurchases->get_result()->fetch_assoc();
      $totalCredit = $resPurchases['total'] ?? 0;

      // Upsert into account_balances
      $sql = "INSERT INTO account_balances (date, total_debt, total_credit) VALUES (?, ?, ?) 
              ON DUPLICATE KEY UPDATE total_debt = VALUES(total_debt), total_credit = VALUES(total_credit)";
      $stmtUpsert = $db->prepare($sql);
      $stmtUpsert->bind_param("sdd", $date, $totalDebt, $totalCredit);
      $stmtUpsert->execute();
    } catch (Exception $e) {
      error_log("Error in updateDailyBalance: " . $e->getMessage());
    } finally {
      if (isset($db)) $db->close();
    }
  }
}
