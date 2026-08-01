<?php

require_once "db.php";
require_once "utils.php";

function getDataFromDB($item, $dbName = NULL)
{
  $id = '';

  if (isset($_GET['id'])) {
    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT); // Validar el ID
  }

  // Mapeo de tablas
  $sqlType = [
    "payments" => "SELECT * FROM payments ORDER BY `id` DESC",
    "payment-by-id" => "SELECT * FROM payments WHERE id = ?",
    "products" => "SELECT * FROM products ORDER BY `id` DESC",
    "product-by-id" => "SELECT * FROM products WHERE id = ?",
    "transactions" => "SELECT * FROM transactions ORDER BY `id` DESC",
    "transaction-by-id" => "SELECT * FROM transactions WHERE id = ?",
    "transaction-details-by-transaction" => "SELECT * FROM transaction_details WHERE transaction_id = ?",
    "audit_logs" => "SELECT * FROM audit_logs ORDER BY `id` DESC",
    "system-versions" => "SELECT * FROM system_versions ORDER BY `id` DESC limit 1",
    "dashboard-summary" => "SELECT date, total_debt, total_credit FROM account_balances WHERE date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH) ORDER BY date ASC",
  ];

  // Establecer la conexión
  $db = DataBase::getConnection($dbName);

  try {
    // Prepara la consulta
    $sql = $sqlType[$item];
    $stmt = $db->prepare($sql);

    // Vincular el ID si corresponde
    if (strpos($sql, '?') !== false) {
      $stmt->bind_param('i', $id); // Vincular el ID como entero
    }

    $stmt->execute();
    $result = $stmt->get_result();

    $json = [
      'status' => 200,
      'body' => [],
      'statusText' => 'success',
      'total' => $result->num_rows
    ];

    while ($row = $result->fetch_assoc()) {
      // Limpiar datos sensibles
      unset($row['password']);

      // Decodificar JSON si es necesario
      foreach ($row as $key => $value) {
        if (
          $value !== NULL && is_string($value) &&
          (substr($value, 0, 3) === '[{"' || substr($value, 0, 2) === '{"' || substr($value, 0, 2) === '["')
        ) {
          $row[$key] = json_decode($value, true);
        } else if ($value !== NULL && is_string($value)) {
          $row[$key] = $value;
        } else if ($value === NULL) {
          $row[$key] = '';
        }
      }

      // Construir la URL de la galería de imágenes
      if (isset($row['image_gallery']) && $row['image_gallery'] !== '' && $row['image_gallery'] !== '[]') {
        $row['image_gallery'] = buildImageGalleryUrl($item, $row['image_gallery']);
      }
      if (isset($row['avatar']) && $row['avatar'] !== "") {
        $row['avatar'] = buildImageUrl($item, $row['avatar']);
      }
      $json['body'][] = $row;
    }

    echo json_encode($json, http_response_code($json["status"]));
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

// Función para construir la URL de la imagen principal
function buildImageUrl($item, $imageName)
{
  $fileName = [
    "users" => "users",
    "product-by-id" => "products",
    // Agregar otros mappings si es necesario
  ];
  return $_SERVER['REQUEST_SCHEME'] . "://" . $_SERVER['HTTP_HOST'] . "/api-images/" . $fileName[$item] . "/" . $imageName;
}

// Función para construir la URL de la galería de imágenes
function buildImageGalleryUrl($item, $imageGallery)
{
  $images = explode(',', $imageGallery);
  $galleryUrls = [];
  foreach ($images as $image) {
    $galleryUrls[] = buildImageUrl($item, trim($image));
  }
  return $galleryUrls;
}
