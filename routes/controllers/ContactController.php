<?php
require_once __DIR__ . '/Controller.php';
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../utils.php';

use OpenApi\Annotations as OA;

/**
 * @OA\Tag(
 *     name="Contacts",
 *     description="Operations related to contacts"
 * )
 */
class ContactController extends Controller
{
  /**
   * @OA\Get(
   *     path="/contacts",
   *     tags={"Contacts"},
   *     summary="Get all active contacts",
   *     @OA\Response(response=200, description="Successful operation")
   * )
   */
  public function getAll()
  {
    $db = DataBase::getConnection();
    $sql = "SELECT * FROM contacts WHERE status = 'Activo' ORDER BY id DESC";
    $result = $db->query($sql);

    $contacts = [];
    while ($row = $result->fetch_assoc()) {
      $contacts[] = $row;
    }

    $this->jsonResponse($contacts, 200, 'success');
  }

  /**
   * @OA\Get(
   *     path="/contact-by-id",
   *     tags={"Contacts"},
   *     summary="Get contact by ID",
   *     @OA\Parameter(name="id", in="query", required=true, @OA\Schema(type="integer")),
   *     @OA\Response(response=200, description="Successful operation"),
   *     @OA\Response(response=404, description="Contact not found")
   * )
   */
  public function getById()
  {
    if (!isset($_GET['id'])) {
      $this->jsonResponse([], 400, 'Missing ID parameter');
    }

    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    $db = DataBase::getConnection();
    $stmt = $db->prepare("SELECT * FROM contacts WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
      $this->jsonResponse($row, 200, 'success');
    } else {
      $this->jsonResponse([], 404, 'Contact not found');
    }
  }

  /**
   * @OA\Post(
   *     path="/create-contact",
   *     tags={"Contacts"},
   *     summary="Create a new contact",
   *     @OA\RequestBody(
   *         required=true,
   *         @OA\JsonContent(
   *             required={"name"},
   *             @OA\Property(property="name", type="string"),
   *             @OA\Property(property="type", type="string", default="cliente")
   *         )
   *     ),
   *     @OA\Response(response=201, description="Contact created successfully")
   * )
   */
  public function create()
  {
    $data = $this->getBody();

    if (empty($data['name'])) {
      $this->jsonResponse([], 400, 'Name is required');
    }

    $db = DataBase::getConnection();
    $stmt = $db->prepare("INSERT INTO contacts (name, type, status) VALUES (?, ?, ?)");
    
    $name = $data['name'];
    $type = $data['type'] ?? 'cliente';
    $status = 'Activo';

    $stmt->bind_param("sss", $name, $type, $status);

    try {
      $stmt->execute();
      $this->jsonResponse(['id' => $stmt->insert_id], 201, 'Created successfully');
    } catch (Exception $e) {
      $this->jsonResponse([], 500, $e->getMessage());
    }
  }

  /**
   * @OA\Post(
   *     path="/update-contact",
   *     tags={"Contacts"},
   *     summary="Update existing contact",
   *     @OA\RequestBody(
   *         required=true,
   *         @OA\JsonContent(
   *             required={"id"},
   *             @OA\Property(property="id", type="integer"),
   *             @OA\Property(property="name", type="string"),
   *             @OA\Property(property="type", type="string")
   *         )
   *     ),
   *     @OA\Response(response=200, description="Contact updated successfully")
   * )
   */
  public function update()
  {
    $data = $this->getBody();

    if (empty($data['id'])) {
      $this->jsonResponse([], 400, 'ID is required for update');
    }

    $db = DataBase::getConnection();
    $id = $data['id'];
    
    // Build update query dynamically
    $fields = [];
    $params = [];
    $types = "";

    if (isset($data['name'])) {
      $fields[] = "name = ?";
      $params[] = $data['name'];
      $types .= "s";
    }
    if (isset($data['type'])) {
      $fields[] = "type = ?";
      $params[] = $data['type'];
      $types .= "s";
    }

    if (empty($fields)) {
      $this->jsonResponse([], 400, 'No fields provided for update');
    }

    $sql = "UPDATE contacts SET " . implode(", ", $fields) . " WHERE id = ?";
    $params[] = $id;
    $types .= "i";

    $stmt = $db->prepare($sql);
    $stmt->bind_param($types, ...$params);

    try {
      $stmt->execute();
      $this->jsonResponse([], 200, 'Updated successfully');
    } catch (Exception $e) {
      $this->jsonResponse([], 500, $e->getMessage());
    }
  }

  /**
   * @OA\Delete(
   *     path="/delete-contact",
   *     tags={"Contacts"},
   *     summary="Soft delete a contact",
   *     @OA\Parameter(name="id", in="query", required=true, @OA\Schema(type="integer")),
   *     @OA\Response(response=200, description="Contact deactivated successfully")
   * )
   */
  public function delete()
  {
    $id = $_GET['id'] ?? null;

    if (!$id) {
      $this->jsonResponse([], 400, 'ID is required');
    }

    $db = DataBase::getConnection();
    $stmt = $db->prepare("UPDATE contacts SET status = 'Inactivo' WHERE id = ?");
    $stmt->bind_param("i", $id);

    try {
      $stmt->execute();
      if ($stmt->affected_rows > 0) {
        $this->jsonResponse([], 200, 'Contact deactivated successfully');
      } else {
        $this->jsonResponse([], 404, 'Contact not found');
      }
    } catch (Exception $e) {
      $this->jsonResponse([], 500, $e->getMessage());
    }
  }
}
