<?php
require_once __DIR__ . '/Controller.php';
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../utils.php';

use OpenApi\Annotations as OA;

/**
 * @OA\Tag(
 *     name="Dispatches",
 *     description="Operations related to dispatches"
 * )
 */
class DispatchController extends Controller
{
  /**
   * @OA\Get(
   *     path="/dispatches",
   *     tags={"Dispatches"},
   *     summary="Get all active dispatches",
   *     @OA\Response(response=200, description="Successful operation")
   * )
   */
  public function getAll()
  {
    $db = DataBase::getConnection();
    $sql = "SELECT d.*, c.name as contact_name 
            FROM dispatches d 
            LEFT JOIN contacts c ON d.contact_id = c.id 
            WHERE d.status = 'Activo' 
            ORDER BY d.id DESC";
    $result = $db->query($sql);

    $dispatches = [];
    while ($row = $result->fetch_assoc()) {
      $dispatches[] = $row;
    }

    $this->jsonResponse($dispatches, 200, 'success');
  }

  /**
   * @OA\Get(
   *     path="/dispatch-by-id",
   *     tags={"Dispatches"},
   *     @OA\Parameter(name="id", in="query", required=true, @OA\Schema(type="integer")),
   *     @OA\Response(response=200, description="Successful operation")
   * )
   */
  public function getById()
  {
    if (!isset($_GET['id'])) {
      $this->jsonResponse([], 400, 'Missing ID');
    }

    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    $db = DataBase::getConnection();
    $stmt = $db->prepare("SELECT d.*, c.name as contact_name FROM dispatches d LEFT JOIN contacts c ON d.contact_id = c.id WHERE d.id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
      $this->jsonResponse($row, 200, 'success');
    } else {
      $this->jsonResponse([], 404, 'Dispatch not found');
    }
  }

  /**
   * @OA\Post(
   *     path="/create-dispatch",
   *     tags={"Dispatches"},
   *     @OA\RequestBody(
   *         required=true,
   *         @OA\JsonContent(
   *             required={"dispatch_date", "contact_id", "total"},
   *             @OA\Property(property="dispatch_date", type="string", format="date"),
   *             @OA\Property(property="contact_id", type="integer"),
   *             @OA\Property(property="total", type="number"),
   *             @OA\Property(property="detail", type="string"),
   *             @OA\Property(property="paid", type="boolean", default=false)
   *         )
   *     ),
   *     @OA\Response(response=201, description="Dispatch created successfully")
   * )
   */
  public function create()
  {
    $data = $this->getBody();

    if (empty($data['contact']) || empty($data['total'])) {
      $this->jsonResponse([], 400, 'Contact ID and Total are required');
    }

    $db = DataBase::getConnection();
    try {
      $contact = $data['contact'];
      $contactId = $contact['id'] ?? null;
      $contactName = $contact['name'] ?? '';

      // Create contact if it doesn't exist
      if (empty($contactId)) {
        $stmtContact = $db->prepare("INSERT INTO contacts (name, type, status) VALUES (?, ?, ?)");
        $type = 'proveedor';
        $status = 'Activo';
        $stmtContact->bind_param("sss", $contactName, $type, $status);
        $stmtContact->execute();
        $contactId = $stmtContact->insert_id;
      }
      $stmt = $db->prepare("INSERT INTO dispatches (dispatch_date, contact_id, contact_name, total, detail, paid, status) VALUES (?, ?, ?, ?, ?, ?, ?)");

      $date = $data['dispatch_date'] ?? date('Y-m-d');
      $total = $data['total'];
      $detail = $data['detail'] ?? '';
      $paid = (bool) ($data['paid'] ?? false);
      $status = 'Activo';

      $stmt->bind_param("sisdsis", $date, $contactId, $contactName, $total, $detail, $paid, $status);

      $stmt->execute();
      $this->jsonResponse(['id' => $stmt->insert_id], 201, 'Dispatch created successfully');
    } catch (Exception $e) {
      $this->jsonResponse([], 500, $e->getMessage());
    }
  }

  /**
   * @OA\Delete(
   *     path="/delete-dispatch",
   *     tags={"Dispatches"},
   *     summary="Soft delete a dispatch",
   *     @OA\Parameter(name="id", in="query", required=true, @OA\Schema(type="integer")),
   *     @OA\Response(response=200, description="Dispatch deactivated")
   * )
   */
  public function delete()
  {
    $id = $_GET['id'] ?? null;
    if (!$id) {
      $this->jsonResponse([], 400, 'ID is required');
    }

    $db = DataBase::getConnection();
    $stmt = $db->prepare("UPDATE dispatches SET status = 'Inactivo' WHERE id = ?");
    $stmt->bind_param("i", $id);

    try {
      $stmt->execute();
      $this->jsonResponse([], 200, 'Dispatch deactivated');
    } catch (Exception $e) {
      $this->jsonResponse([], 500, $e->getMessage());
    }
  }
}
