<?php

require_once "db.php";
require_once "utils.php";
require_once "get.php";

function patchData($item)
{
  $item = htmlspecialchars($item);
  $post = json_decode(file_get_contents("php://input"), true);

  // Validar que el ID esté presente en el post
  if (!isset($post['id'])) {
    echo json_encode([
      'status' => 400,
      'body' => [],
      'statusText' => 'Bad Request: Missing ID parameter'
    ], http_response_code(400));
    return;
  }

  // Mapeo de tablas
  $table = [
    "update-payment" => "payments",
    "update-product" => "products",
    "update-level" => "levels",
    "update-user-role" => "user_roles",
    "update-transaction" => "transactions",
    "update-transaction-detail" => "transaction_details",
    "update-system-version" => "system_versions",
  ];

  // Obtener los valores antiguos antes de la actualización para el log de auditoría
  $oldValuesRecord = Utils::isRecordExists($table[$item], 'id', $post['id']);
  $oldValues = $oldValuesRecord['boolean'] ? $oldValuesRecord['data'] : null;

  // Prepara los campos para la actualización
  $sqlsets = [];
  $sqlValues = [];
  $types = '';

  // Construir las partes de la consulta
  foreach ($post as $key => $value) {
    if ($key !== 'id') { // Excluir el ID de la lista de campos a actualizar
      $sqlsets[] = "`$key` = ?"; // Usamos `?` para la vinculación de parámetros
      if (is_float($value)) {
        $sqlValues[] = $value;
        $types .= 'd'; // 'd' para double
      } else if (is_int($value)) {
        $sqlValues[] = $value;
        $types .= 'i'; // 'i' para integer
      } else if (is_array($value) || is_object($value)) {
        $sqlValues[] = json_encode($value);
        $types .= 's'; // 's' para string, ya que es un JSON string
      } else {
        $sqlValues[] = $value;
        $types .= 's'; // 's' para string
      }
    }
  }

  $sqlsets = implode(', ', $sqlsets); // Unir las partes de la consulta para la actualización
  $id = $post['id']; // Obtener el ID del post
  $sql = "UPDATE `" . $table[$item] . "` SET $sqlsets WHERE `id` = ?"; // Consulta de actualización

  try {
    $db = DataBase::getConnection();
    $stmt = $db->prepare($sql);

    // Vincular los parámetros
    $sqlValues[] = $id; // Agregar el ID al final de los valores
    $types .= 'i'; // Agregar el tipo del ID
    $stmt->bind_param($types, ...$sqlValues);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
      Utils::saveAuditLog('UPDATE', $table[$item], $id, $oldValues, $post);

      // Update Daily Balance if applicable
      if ($table[$item] === 'sales' || $table[$item] === 'purchases') {
          $dateField = ($table[$item] === 'sales') ? 'sale_date' : 'purchase_date';
          $date = $post[$dateField] ?? ($oldValues[$dateField] ?? date('Y-m-d'));
          $newPaymentType = $post['payment_type'] ?? ($oldValues['payment_type'] ?? 'contado');
          if (($oldValues['payment_type'] ?? 'contado') === 'credito' || $newPaymentType === 'credito') {
              Utils::updateDailyBalance($date);
          }
      }
      echo json_encode([
        'status' => 201,
        'body' => [],
        'statusText' => 'Updated successfully'
      ], http_response_code(201));
    } else {
      echo json_encode([
        'status' => 500,
        'body' => [],
        'statusText' => 'An error occurred during the update process'
      ], http_response_code(500));
    }
  } catch (mysqli_sql_exception $error) {
    echo json_encode([
      'status' => 500,
      'body' => [],
      'statusText' => $error->getMessage()
    ], http_response_code(500));
  } finally {
    if (isset($stmt)) {
      $stmt->close();
    }
    if (isset($db)) {
      $db->close();
    }
  }
}

function updateTokenUser($user, $token)
{
  $sql = "UPDATE users SET user_token=?, exp_token=? WHERE id = ?";
  // $sql = Procedures::updateTokenUser();

  $db = DataBase::getConnection();

  try {
    $stmt = $db->prepare($sql);
    $stmt->bind_param("ssi", $token['jwt'], $token['exp'], $user['id']);
    $stmt->execute();

    return $stmt->affected_rows > 0;

    // if ($stmt->affected_rows > 0) {
    //   $json = [
    //     'status' => 201,
    //     'body' => [],
    //     'statusText' => 'The user created successfully'
    //   ];
    //   echo json_encode($json, http_response_code($json["status"]));
    // }
  } catch (mysqli_sql_exception $error) {
    $json = [
      'status' => 500,
      'body' => [],
      'statusText' => $error->getMessage()
    ];
    echo json_encode($json, http_response_code($json["status"]));
    die();
  }
}

function newPassword()
{
  $post = json_decode(file_get_contents("php://input"), true);
  $userData =  Utils::JwtDecode($post['query']);
  $updateAt = date('Y-m-d H:i:s');

  $TimeIsUp = $userData['data']->time + 3600 < time();

  if ($TimeIsUp) {
    $json = [
      'status' => 403,
      'body' => [],
      'statusText' => "Finalizo el tiempo de 60 minutos para cambiar la contraseña"
    ];
    echo json_encode($json, http_response_code($json["status"]));
  } else {
    $idUser = $userData['data']->id;
    $crypt = password_hash($post['password'], PASSWORD_BCRYPT);

    $sql = "UPDATE users SET `password` = ?, `update_at` = ? WHERE id = ? ";

    try {
      $db = DataBase::getConnection();
      $stmt = $db->prepare($sql);
      $stmt->bind_param("ssi", $crypt, $updateAt, $idUser);
      $stmt->execute();
      if ($stmt->affected_rows > 0) {
        $json = [
          'status' => 201,
          'body' => [],
          'statusText' => 'Contraseña cambiada exitosamente'
        ];
        echo json_encode($json, http_response_code($json["status"]));
      } else {
        $json = [
          'status' => 500,
          'body' => [],
          'statusText' => 'Ocurrio un error cambiando su contraseñar, por favor intente mas tarde'
        ];
        echo json_encode($json, http_response_code($json["status"]));
      }
      $stmt->close();
      $db->close();
    } catch (mysqli_sql_exception $error) {
      $json = [
        'status' => 500,
        'body' => [],
        'statusText' => $error->getMessage()
      ];
      echo json_encode($json, http_response_code($json["status"]));
    }
  }
}
