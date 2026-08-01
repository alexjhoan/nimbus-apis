<?php
require_once __DIR__ . '/Controller.php';
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../utils.php';

use OpenApi\Annotations as OA;

/**
 * @OA\Tag(
 *     name="Users",
 *     description="Operations related to users"
 * )
 */
class UserController extends Controller
{
  /**
   * @OA\Get(
   *     path="/users",
   *     tags={"Users"},
   *     summary="Get all users",
   *     @OA\Response(
   *         response=200,
   *         description="Successful operation",
   *         @OA\JsonContent(
   *             type="array",
   *             @OA\Items(
   *                 type="object",
   *                 @OA\Property(property="id", type="integer"),
   *                 @OA\Property(property="email", type="string"),
   *                 @OA\Property(property="first_name", type="string")
   *             )
   *         )
   *     ),
   *     @OA\Response(response=401, description="Unauthorized")
   * )
   */
  public function getAll()
  {
    $db = DataBase::getConnection("nimbus");
    $sql = "SELECT * FROM users ORDER BY `id` DESC";
    $result = $db->query($sql);

    $users = [];
    while ($row = $result->fetch_assoc()) {
      unset($row['password']);
      $users[] = $row;
    }

    $this->jsonResponse($users, 200, 'success');
  }

  /**
   * @OA\Get(
   *     path="/user-by-id",
   *     tags={"Users"},
   *     summary="Get user by ID",
   *     @OA\Parameter(
   *         name="id",
   *         in="query",
   *         required=true,
   *         @OA\Schema(type="integer")
   *     ),
   *     @OA\Response(
   *         response=200,
   *         description="Successful operation",
   *         @OA\JsonContent(
   *             type="object",
   *             @OA\Property(property="id", type="integer"),
   *             @OA\Property(property="email", type="string")
   *         )
   *     ),
   *     @OA\Response(response=404, description="User not found")
   * )
   */
  public function getById()
  {
    if (!isset($_GET['id'])) {
      $this->jsonResponse([], 400, 'Missing ID parameter');
    }

    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    $db = DataBase::getConnection("nimbus");
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
      unset($row['password']);
      $this->jsonResponse([$row], 200, 'success');
    } else {
      $this->jsonResponse([], 404, 'User not found');
    }
  }

  /**
   * @OA\Post(
   *     path="/create-user",
   *     tags={"Users"},
   *     summary="Create a new user",
   *     @OA\RequestBody(
   *         required=true,
   *         @OA\JsonContent(
   *             required={"email","password","first_name"},
   *             @OA\Property(property="email", type="string", format="email"),
   *             @OA\Property(property="password", type="string", format="password"),
   *             @OA\Property(property="first_name", type="string"),
   *             @OA\Property(property="last_name", type="string"),
   *             @OA\Property(property="phone", type="string")
   *         )
   *     ),
   *     @OA\Response(
   *         response=201,
   *         description="User created successfully"
   *     ),
   *     @OA\Response(response=409, description="Email already exists")
   * )
   */
  public function create()
  {
    $data = $this->getBody();

    if (empty($data['email']) || empty($data['password'])) {
      $this->jsonResponse([], 400, 'Email and password are required');
    }

    $userExist = Utils::isRecordExists('users', 'email', $data['email'])['boolean'];
    if ($userExist) {
      $this->jsonResponse([], 409, "El correo '{$data['email']}' ya existe");
    }

    $passwordHash = password_hash($data['password'], PASSWORD_BCRYPT);

    $db = DataBase::getConnection("nimbus");
    $stmt = $db->prepare("INSERT INTO users (first_name, last_name, email, password, phone, address, user_role_id, active) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");

    $firstName = $data['first_name'] ?? '';
    $lastName = $data['last_name'] ?? '';
    $phone = $data['phone'] ?? '';
    $address = $data['address'] ?? '';
    $roleId = $data['user_role_id'] ?? 2; // Default role
    $active = 1;

    $stmt->bind_param("ssssssii", $firstName, $lastName, $data['email'], $passwordHash, $phone, $address, $roleId, $active);

    try {
      $stmt->execute();
      $this->jsonResponse(['id' => $stmt->insert_id], 201, 'Created successfully');
    } catch (Exception $e) {
      $this->jsonResponse([], 500, $e->getMessage());
    }
  }

  /**
   * @OA\Post(
   *     path="/login-user",
   *     tags={"Users"},
   *     summary="User login",
   *     @OA\RequestBody(
   *         required=true,
   *         @OA\JsonContent(
   *             required={"identity","password"},
   *             @OA\Property(property="identity", type="string", description="Email o Nickname del usuario"),
   *             @OA\Property(property="password", type="string", format="password")
   *         )
   *     ),
   *     @OA\Response(
   *         response=200,
   *         description="Login successful",
   *         @OA\JsonContent(
   *             @OA\Property(property="id", type="integer"),
   *             @OA\Property(property="token", type="string"),
   *             @OA\Property(property="name", type="string")
   *         )
   *     ),
   *     @OA\Response(response=401, description="Invalid credentials"),
   *     @OA\Response(response=404, description="User or Tenant not found")
   * )
   */
  public function login()
  {
    $data = $this->getBody();
    // Aceptamos 'identity' (email o nickname), con fallback a 'email' por compatibilidad con el frontend actual
    $identity = $data['identity'] ?? $data['email'] ?? '';
    $password = $data['password'] ?? '';

    if (empty($identity)) {
      $this->jsonResponse([], 400, 'El campo email o nickname es requerido');
    }

    if (empty($password)) {
      $this->jsonResponse([], 400, 'La contraseña es requerida');
    }

    // 1. Buscar en global_users_map el tenant_db_name usando la identidad (email o nickname)
    $dbAdmin = DataBase::getConnection('nimbus');
    $stmtAdmin = $dbAdmin->prepare("SELECT tenant_db_name, status FROM global_users_map WHERE (email = ? OR nickname = ?) LIMIT 1");
    $stmtAdmin->bind_param("ss", $identity, $identity);
    $stmtAdmin->execute();
    $resultAdmin = $stmtAdmin->get_result();

    if ($resultAdmin->num_rows === 0) {
      $this->jsonResponse([], 404, "El usuario no existe");
    }

    $globalUser = $resultAdmin->fetch_assoc();

    if ($globalUser['status'] != "active") {
      $this->jsonResponse([], 401, "El acceso para este usuario está inactivo");
    }

    $tenancyDbName = $globalUser['tenant_db_name'];

    // 2. Obtener los datos del tenant desde la BD nimbus usando el tenancy_db_name
    $tenantExist = Utils::isRecordExists("tenants", "tenancy_db_name", $tenancyDbName, 'nimbus');

    if (!$tenantExist["boolean"]) {
      $this->jsonResponse([], 404, "El comercio asociado a este usuario no existe");
    }

    $tenantData = $tenantExist["data"];
    $tenantName = $tenantData['name'];

    // 3. Buscar el usuario dentro de la tabla users dentro de la base de datos del comercio
    $dbTenant = DataBase::getConnection($tenancyDbName);

    // Intentamos buscar por email o nickname si la columna existe, sino fallback a solo email
    $stmtUser = $dbTenant->prepare("SELECT * FROM users WHERE email = ? OR nickname = ? LIMIT 1");
    if (!$stmtUser) {
      $stmtUser = $dbTenant->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
      $stmtUser->bind_param("s", $identity);
    } else {
      $stmtUser->bind_param("ss", $identity, $identity);
    }

    $stmtUser->execute();
    $resultUser = $stmtUser->get_result();

    if ($resultUser->num_rows > 0) {
      $user = $resultUser->fetch_assoc();

      $userStatus = $user['status'] ?? $user['active'] ?? 1;
      if ($userStatus != 1) {
        $this->jsonResponse([], 401, 'El acceso para este usuario está inactivo en el sistema local');
      }

      // 4. Validar la contraseña
      if (password_verify($password, $user['password'])) {
        $token = Utils::JwtEncode([
          'id' => $user['id'],
          'email' => $user['email'],
          'tenant' => $tenantName,
          'first_name' => $user['first_name'],
          'last_name' => $user['last_name'],
        ]);

        $tenancyToken = Utils::JwtEncode([
          'id' => $tenantData['id'],
          'db_name' => $tenantData['tenancy_db_name'],
          'tenant' => $tenantName
        ]);

        // Actualizar el token en la base de datos del comercio
        $stmtUpdate = $dbTenant->prepare("UPDATE users SET user_token=?, exp_token=? WHERE id = ?");
        $stmtUpdate->bind_param("ssi", $token['jwt'], $token['exp'], $user['id']);
        $stmtUpdate->execute();

        $responseBody = [
          'id' => $user['id'],
          'token' => $token['jwt'],
          'tenancy_token' => $tenancyToken['jwt'],
          'first_name' => $user['first_name'],
          'last_name' => $user['last_name'],
          'role_id' => $user['role_id'],
          'role_name' => $user['role_name'] ?? null,
          'status' => $user['status'] ?? null,
          'tenant' => $tenantName
        ];

        $this->jsonResponse($responseBody, 200, "Bienvenido " . ($user['first_name'] . ' ' . $user['last_name'] ?? ''));
      } else {
        $this->jsonResponse([], 401, 'Los datos ingresados son incorrectos');
      }
    } else {
      $this->jsonResponse([], 404, 'Los datos ingresados son incorrectos');
    }
  }
}
