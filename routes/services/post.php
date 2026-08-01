<?php

require_once "db.php";
require_once "get.php";
require_once "patch.php";
require_once "utils.php";

function postData($item)
{
  // Verificar el tipo de contenido
  $contentType = isset($_SERVER["CONTENT_TYPE"]) ? trim($_SERVER["CONTENT_TYPE"]) : '';

  // Manejo de datos según el tipo de contenido
  if (stripos($contentType, 'application/json') === 0 || stripos($contentType, 'text/plain') === 0) {
    $post = json_decode(file_get_contents("php://input"), true);
    $files = []; // Inicializar archivos como array vacío
  } else if (stripos($contentType, 'multipart/form-data') === 0) {
    $post = $_POST; // Obtener datos del formulario
    $files = $_FILES; // Obtener archivos subidos
  } else {
    echo json_encode([
      'status' => 415,
      'body' => [],
      'statusText' => 'Unsupported Media Type'
    ], http_response_code(415));
    return;
  }

  $table = [
    "create-user" => "users",
    "create-product" => "products",
    "create-payment" => "payments",
    "create-level" => "levels",
    "create-user-role" => "user_roles",
    "create-transaction" => "transactions",
    "create-transaction-detail" => "transaction_details",
    "create-system-version" => "system_versions",
  ];

  // Manejo de imágenes
  $imageGallery = "";

  if (isset($files) && isset($files['image_gallery'])) {
    $folder = "../api-images/" . $table[$item] . "/"; // Ruta a la carpeta de imágenes
    $image_files = Utils::diverseArray($files['image_gallery']);

    foreach ($image_files as $index => $file) {
      $nameFile = htmlspecialchars($post['name']) . "/" . $index;
      // Normalizar el nombre del archivo
      $nameFile = Utils::normalizeFileName($nameFile);
      $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)); // Obtener la extensión en minúsculas
      $newFile = $folder . $nameFile . "." . $ext; // Usar la extensión correcta

      // Verificar que la extensión sea válida
      if ($ext === "jpg" || $ext === "jpeg" || $ext === "png") {
        // Redimensionar y guardar imagen
        Utils::ResizeAndSaveImg($file['tmp_name'], $ext, $newFile);
        $imageGallery .= "$nameFile.$ext,"; // Agregar el nombre del archivo a la galería
      } else {
        // Manejar el error de extensión no soportada
        return json_encode([
          'status' => 400,
          'body' => [],
          'statusText' => "La imagen '$file[name]' no es un formato válido. Solo se aceptan JPG y PNG."
        ], http_response_code(400));
      }
    }
    $imageGallery = rtrim($imageGallery, ','); // Eliminar la última coma
  }


  $sqlkeys = [];
  $sqlValues = [];
  $types = '';

  // Eliminar campos innecesarios
  unset($post["created_at"], $post["updated_at"], $post["image_gallery"]);

  // Preparar los datos para la inserción
  foreach ($post as $key => $value) {
    $sqlkeys[] = "`$key`";
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

  // Agregar imágenes a los valores

  if ($imageGallery) {
    $sqlkeys[] = "`image_gallery`";
    $sqlValues[] = $imageGallery;
    $types .= 's'; // Para la galería de imágenes
  }

  $sqlkeys = implode(',', $sqlkeys);
  $sqlPlaceholders = implode(',', array_fill(0, count($sqlValues), '?'));

  $sql = "INSERT INTO `" . $table[$item] . "` ($sqlkeys) VALUES ($sqlPlaceholders)";

  try {
    $db = DataBase::getConnection();
    $stmt = $db->prepare($sql);
    $params = array_merge([$types], $sqlValues);
    $stmt->bind_param(...$params);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
      $insertedId = $db->insert_id;
      Utils::saveAuditLog('INSERT', $table[$item], $insertedId, null, $post);
      
      // Update Daily Balance if applicable
      if ($table[$item] === 'sales' || $table[$item] === 'purchases') {
          $dateField = ($table[$item] === 'sales') ? 'sale_date' : 'purchase_date';
          $date = $post[$dateField] ?? date('Y-m-d');
          $paymentType = $post['payment_type'] ?? 'contado';
          if ($paymentType === 'credito') {
              Utils::updateDailyBalance($date);
          }
      }

      // Update Contact Balance if applicable
      $contactId = $post['contact_id'] ?? ($post['contact']['id'] ?? null);
      if ($contactId && in_array($table[$item], ['sales', 'purchases', 'payments', 'abonos', 'transactions'])) {
          Utils::refreshContactBalance($contactId);
      }
      echo json_encode([
        'status' => 201,
        'body' => ['id' => $insertedId],
        'statusText' => 'Created successfully'
      ], http_response_code(201));
    } else {
      echo json_encode([
        'status' => 500,
        'body' => [],
        'statusText' => 'An error occurred during the process'
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

function registerUser()
{
  // Verificar el tipo de contenido
  $contentType = isset($_SERVER["CONTENT_TYPE"]) ? trim($_SERVER["CONTENT_TYPE"]) : '';

  // Manejo de datos según el tipo de contenido
  if (stripos($contentType, 'application/json') === 0 || stripos($contentType, 'text/plain') === 0) {
    $post = json_decode(file_get_contents("php://input"), true);
    $files = []; // Inicializar archivos como array vacío
  } else if (stripos($contentType, 'multipart/form-data') === 0) {
    $post = $_POST; // Obtener datos del formulario
    $files = $_FILES; // Obtener archivos subidos
  } else {
    echo json_encode([
      'status' => 415,
      'body' => [],
      'statusText' => 'Unsupported Media Type'
    ], http_response_code(415));
    return;
  }

  $userExist = Utils::isRecordExists('users', 'email', $post['email'])['boolean'];

  if ($userExist) {
    echo json_encode([
      'status' => 409,
      'body' => [],
      'statusText' => "El correo '" . $post['email'] . "' ya existe"
    ], http_response_code(409));
    return;
  }

  // Manejo de imágenes
  $imageAvatar = "";

  if (isset($files) && isset($files['avatar'])) {
    $folder = "../api-images/users/"; // Ruta a la carpeta de imágenes

    // Normalizar el nombre del archivo
    $firstName = Utils::normalizeFileName(htmlspecialchars($post['first_name']));
    $lastName = Utils::normalizeFileName(htmlspecialchars($post['last_name']));
    $nameFile = strtolower($firstName . "_" . $lastName);
    $ext = strtolower(pathinfo($files['avatar']['name'], PATHINFO_EXTENSION)); // Obtener la extensión en minúsculas

    $newFile = $folder . $nameFile . "." . $ext; // Usar la extensión correcta

    // Verificar que la extensión sea válida
    if ($ext === "jpg" || $ext === "jpeg" || $ext === "png") {
      // Redimensionar y guardar imagen
      Utils::ResizeAndSaveImg($files['avatar']['tmp_name'], $ext, $newFile);
      $imageAvatar = "$nameFile.$ext"; // Agregar el nombre del archivo a la galería
    } else {
      // Manejar el error de extensión no soportada
      return json_encode([
        'status' => 400,
        'body' => [],
        'statusText' => "La imagen '" . $files['avatar']['name'] . "' no es un formato válido. Solo se aceptan JPG y PNG."
      ], http_response_code(400));
    }
  }

  $sqlkeys = [];
  $sqlValues = [];
  $types = '';

  // Eliminar campos innecesarios
  unset($post["created_at"], $post["updated_at"], $post["avatar"]);

  if (isset($post['password'])) {
    $post['password'] = password_hash($post['password'], PASSWORD_BCRYPT);
  }

  // Preparar los datos para la inserción
  foreach ($post as $key => $value) {
    $sqlkeys[] = "`$key`";
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


  // Agregar imágenes a los valores
  if ($imageAvatar) {
    $sqlkeys[] = "`avatar`";
    $sqlValues[] = $imageAvatar;
    $types .= 's'; // Para la galería de imágenes
  }

  $sqlkeys = implode(',', $sqlkeys);
  $sqlPlaceholders = implode(',', array_fill(0, count($sqlValues), '?'));

  $sql = "INSERT INTO `users` ($sqlkeys) VALUES ($sqlPlaceholders)";

  try {
    $db = DataBase::getConnection();
    $stmt = $db->prepare($sql);
    $params = array_merge([$types], $sqlValues);
    $stmt->bind_param(...$params);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
      echo json_encode([
        'status' => 201,
        'body' => [],
        'statusText' => 'Created successfully'
      ], http_response_code(201));
    } else {
      echo json_encode([
        'status' => 500,
        'body' => [],
        'statusText' => 'An error occurred during the process'
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

function updateUser()
{
  $contentType = isset($_SERVER["CONTENT_TYPE"]) ? trim($_SERVER["CONTENT_TYPE"]) : '';

  // Manejo de datos según el tipo de contenido
  if (stripos($contentType, 'application/json') === 0 || stripos($contentType, 'text/plain') === 0) {
    $post = json_decode(file_get_contents("php://input"), true);
    $files = []; // Inicializar archivos como array vacío
  } else if (stripos($contentType, 'multipart/form-data') === 0) {
    $post = $_POST; // Obtener datos del formulario
    $files = $_FILES; // Obtener archivos subidos
  } else {
    echo json_encode([
      'status' => 415,
      'body' => [],
      'statusText' => 'Unsupported Media Type'
    ], http_response_code(415));
    return;
  }

  // Validar que el ID esté presente en el post
  if (!isset($post['id'])) {
    echo json_encode([
      'status' => 400,
      'body' => [],
      'statusText' => 'Bad Request: Missing ID parameter'
    ], http_response_code(400));
    return;
  }

  // Manejo de imágenes
  $imageAvatar = "";

  if (isset($files) && isset($files['avatar'])) {
    $folder = "../api-images/users/"; // Ruta a la carpeta de imágenes

    // Normalizar el nombre del archivo
    $firstName = Utils::normalizeFileName(htmlspecialchars($post['first_name']));
    $lastName = Utils::normalizeFileName(htmlspecialchars($post['last_name']));
    $nameFile = strtolower($firstName . "_" . $lastName);
    $ext = strtolower(pathinfo($files['avatar']['name'], PATHINFO_EXTENSION)); // Obtener la extensión en minúsculas

    $newFile = $folder . $nameFile . "." . $ext; // Usar la extensión correcta

    // Verificar que la extensión sea válida
    if ($ext === "jpg" || $ext === "jpeg" || $ext === "png") {
      // Redimensionar y guardar imagen
      Utils::ResizeAndSaveImg($files['avatar']['tmp_name'], $ext, $newFile);
      $imageAvatar = "$nameFile.$ext"; // Agregar el nombre del archivo a la galería
    } else {
      // Manejar el error de extensión no soportada
      return json_encode([
        'status' => 400,
        'body' => [],
        'statusText' => "La imagen '" . $files['avatar']['name'] . "' no es un formato válido. Solo se aceptan JPG y PNG."
      ], http_response_code(400));
    }
  }

  // Prepara los campos para la actualización
  $sqlsets = [];
  $sqlValues = [];
  $types = '';

  unset($post["created_at"], $post["updated_at"], $post["avatar"]);

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

  if ($imageAvatar) {
    $sqlsets[] = "`avatar` = ?";
    $sqlValues[] = $imageAvatar;
    $types .= 's'; // Para la galería de imágenes
  }

  $sqlsets = implode(', ', $sqlsets); // Unir las partes de la consulta para la actualización
  $id = $post['id']; // Obtener el ID del post
  $sql = "UPDATE `users` SET $sqlsets WHERE `id` = ?"; // Consulta de actualización

  try {
    $db = DataBase::getConnection();
    $stmt = $db->prepare($sql);

    // Vincular los parámetros
    $sqlValues[] = $id; // Agregar el ID al final de los valores
    $types .= 'i'; // Agregar el tipo del ID
    $stmt->bind_param($types, ...$sqlValues);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
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

function userLogin()
{
  $post = json_decode(file_get_contents("php://input"), true);
  $email = filter_var(strtolower($post['email']), FILTER_SANITIZE_EMAIL);
  $password = $post['password'];
  $userExist = Utils::isRecordExists("users", "email", $email);

  if ($userExist["boolean"]) {
    $userExist = $userExist["data"];
    // desencriptar el password  
    $verify = password_verify($password, $userExist['password']);

    if ($verify) {
      $token = Utils::JwtEncode([
        'id' => $userExist['id'],
        'email' => $userExist['email'],
        'first_name' => $userExist['first_name'],
        'last_name' => $userExist['last_name']
      ]);

      updateTokenUser($userExist, $token);

      // 2592000 equivale a un mes en segundos

      $json = [
        'status' => 200,
        'body' => [
          'id' => $userExist['id'],
          'first_name' => $userExist['first_name'],
          'last_name' => $userExist['last_name'],
          'avatar' => $userExist['avatar'],
          'email' => $userExist['email'],
          'phone' => $userExist['phone'],
          'address' => $userExist['address'],
          'status' => $userExist['status'],
          'role_id' => $userExist['user_role_id'],
          'role_name' => $userExist['user_role_name'],
          'token' => $token['jwt'],
          'exp_token' => 2592000
        ],
        'statusText' => "Bienvenido " . $userExist['first_name'] . " " . $userExist['last_name']
      ];
      echo json_encode($json, http_response_code($json["status"]));
    } else {
      $json = [
        'status' => 401,
        'body' => [],
        'statusText' => 'Los datos ingresados son incorrectos'
      ];
      echo json_encode($json, http_response_code($json["status"]));
    }
  } else {
    $json = [
      'status' => 404,
      'body' => [],
      'statusText' => 'El correo no exite'
    ];
    echo json_encode($json, http_response_code($json["status"]));
  }
}

function recoveryPassword()
{
  $post = json_decode(file_get_contents("php://input"), true);
  $email = filter_var(strtolower($post['email']), FILTER_SANITIZE_EMAIL);

  $userExist = Utils::isRecordExists('users', 'email', $email);

  // print_r($userExist);

  if ($userExist['boolean']) {
    $userExist = $userExist['data'];
    $token = Utils::JwtEncode([
      'id' => $userExist['id'],
      'email' => $userExist['email'],
      'first_name' => $userExist['first_name'],
      'last_name' => $userExist['last_name'],
      'time' => time()
    ]);

    include_once "sendPasswordRecoveryEmail.php";
    sendPasswordRecoveryEmail($userExist, $token);
    $json = [
      'status' => 200,
      'body' => $token,
      'statusText' => 'Por Favor verifique su correo para la recuperacion de contraseña'
    ];
    echo json_encode($json, http_response_code($json["status"]));
  } else {
    $json = [
      'status' => 500,
      'body' => [],
      'statusText' => "El correo $email no exite, por favor registrese"
    ];
    echo json_encode($json, http_response_code($json["status"]));
  }
}
