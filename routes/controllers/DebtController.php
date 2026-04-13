<?php
require_once __DIR__ . '/Controller.php';
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../utils.php';

use OpenApi\Annotations as OA;

/**
 * @OA\Tag(
 *     name="Debts",
 *     description="Operations related to debts"
 * )
 */
class DebtController extends Controller
{
  /**
   * @OA\Get(
   *     path="/debts",
   *     tags={"Debts"},
   *     summary="Get all active debts",
   *     @OA\Response(response=200, description="Successful operation")
   * )
   */
  public function getAll()
  {
    $db = DataBase::getConnection();
    $sql = "SELECT d.*, c.name as contact_name 
            FROM debts d 
            LEFT JOIN contacts c ON d.contact_id = c.id 
            WHERE d.status != 'Inactivo' 
            ORDER BY d.id DESC";
    $result = $db->query($sql);

    $debts = [];
    while ($row = $result->fetch_assoc()) {
      $debts[] = $row;
    }

    $this->jsonResponse($debts, 200, 'success');
  }

  /**
   * @OA\Get(
   *     path="/debt-by-id",
   *     tags={"Debts"},
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
    $stmt = $db->prepare("SELECT d.*, c.name as contact_name FROM debts d LEFT JOIN contacts c ON d.contact_id = c.id WHERE d.id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
      $this->jsonResponse($row, 200, 'success');
    } else {
      $this->jsonResponse([], 404, 'Debt not found');
    }
  }

  /**
   * @OA\Post(
   *     path="/create-debt",
   *     tags={"Debts"},
   *     @OA\RequestBody(
   *         required=true,
   *         @OA\JsonContent(
   *             required={"contact_id", "total", "type"},
   *             @OA\Property(property="contact_id", type="integer"),
   *             @OA\Property(property="total", type="number"),
   *             @OA\Property(property="type", type="string", description="cobrar, pagar"),
   *             @OA\Property(property="status", type="string", default="Pendiente")
   *         )
   *     ),
   *     @OA\Response(response=201, description="Debt created successfully")
   * )
   */
  public function create()
  {
    $data = $this->getBody();

    if (empty($data['contact_id']) || empty($data['total']) || empty($data['type'])) {
      $this->jsonResponse([], 400, 'Contact ID, Total and Type are required');
    }

    $db = DataBase::getConnection();
    $stmt = $db->prepare("INSERT INTO debts (contact_id, total, status, type) VALUES (?, ?, ?, ?)");
    
    $contactId = $data['contact_id'];
    $total = $data['total'];
    $status = $data['status'] ?? 'Pendiente';
    $type = $data['type']; // cobrar, pagar

    $stmt->bind_param("idss", $contactId, $total, $status, $type);

    try {
      $stmt->execute();
      $this->jsonResponse(['id' => $stmt->insert_id], 201, 'Debt created successfully');
    } catch (Exception $e) {
      $this->jsonResponse([], 500, $e->getMessage());
    }
  }

  /**
   * @OA\Delete(
   *     path="/delete-debt",
   *     tags={"Debts"},
   *     summary="Soft delete a debt",
   *     @OA\Parameter(name="id", in="query", required=true, @OA\Schema(type="integer")),
   *     @OA\Response(response=200, description="Debt deactivated")
   * )
   */
  public function delete()
  {
    $id = $_GET['id'] ?? null;
    if (!$id) {
      $this->jsonResponse([], 400, 'ID is required');
    }

    $db = DataBase::getConnection();
    $stmt = $db->prepare("UPDATE debts SET status = 'Inactivo' WHERE id = ?");
    $stmt->bind_param("i", $id);

    try {
      $stmt->execute();
      $this->jsonResponse([], 200, 'Debt deactivated');
    } catch (Exception $e) {
      $this->jsonResponse([], 500, $e->getMessage());
    }
  }
}
