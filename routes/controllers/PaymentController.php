<?php
require_once __DIR__ . '/Controller.php';
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../utils.php';

use OpenApi\Annotations as OA;

/**
 * @OA\Tag(
 *     name="Payments",
 *     description="Operations related to payments"
 * )
 */
class PaymentController extends Controller
{
  /**
   * @OA\Get(
   *     path="/payments",
   *     tags={"Payments"},
   *     summary="Get all active payments",
   *     @OA\Response(response=200, description="Successful operation")
   * )
   */
  public function getAll()
  {
    $db = DataBase::getConnection();
    $sql = "SELECT p.*, c.name as contact_name 
            FROM payments p 
            LEFT JOIN contacts c ON p.contact_id = c.id 
            WHERE p.status = 'Activo' 
            ORDER BY p.id DESC";
    $result = $db->query($sql);

    $payments = [];
    while ($row = $result->fetch_assoc()) {
      $payments[] = $row;
    }

    $this->jsonResponse($payments, 200, 'success');
  }

  /**
   * @OA\Get(
   *     path="/payment-by-id",
   *     tags={"Payments"},
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
    $stmt = $db->prepare("SELECT p.*, c.name as contact_name FROM payments p LEFT JOIN contacts c ON p.contact_id = c.id WHERE p.id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
      $this->jsonResponse($row, 200, 'success');
    } else {
      $this->jsonResponse([], 404, 'Payment not found');
    }
  }

  /**
   * @OA\Post(
   *     path="/create-payment",
   *     tags={"Payments"},
   *     @OA\RequestBody(
   *         required=true,
   *         @OA\JsonContent(
   *             required={"payment_date", "contact_id", "amount"},
   *             @OA\Property(property="payment_date", type="string", format="date"),
   *             @OA\Property(property="contact_id", type="integer"),
   *             @OA\Property(property="amount", type="number"),
   *             @OA\Property(property="method", type="string", default="Efectivo"),
   *             @OA\Property(property="detail", type="string"),
   *             @OA\Property(property="sale_id", type="integer"),
   *             @OA\Property(property="purchase_id", type="integer")
   *         )
   *     ),
   *     @OA\Response(response=201, description="Payment created successfully")
   * )
   */
  public function create()
  {
    $data = $this->getBody();

    if (empty($data['contact_id']) || empty($data['amount'])) {
      $this->jsonResponse([], 400, 'Contact ID and Amount are required');
    }

    $db = DataBase::getConnection();
    $stmt = $db->prepare("INSERT INTO payments (payment_date, contact_id, amount, method, detail, sale_id, purchase_id, status, currency) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    $date = $data['payment_date'] ?? date('Y-m-d');
    $contactId = $data['contact_id'];
    $amount = $data['amount'];
    $method = $data['method'] ?? 'Efectivo';
    $detail = $data['detail'] ?? '';
    $saleId = $data['sale_id'] ?? null;
    $purchaseId = $data['purchase_id'] ?? null;
    $status = 'Activo';
    $currency = $data['currency'] ?? 'USD';

    $stmt->bind_param("sidssiiss", $date, $contactId, $amount, $method, $detail, $saleId, $purchaseId, $status, $currency);

    try {
      $stmt->execute();
      $insertedId = $stmt->insert_id;
      Utils::refreshContactBalance($contactId);
      $this->jsonResponse(['id' => $insertedId], 201, 'Payment created successfully');
    } catch (Exception $e) {
      $this->jsonResponse([], 500, $e->getMessage());
    }
  }

  /**
   * @OA\Delete(
   *     path="/delete-payment",
   *     tags={"Payments"},
   *     summary="Soft delete a payment",
   *     @OA\Parameter(name="id", in="query", required=true, @OA\Schema(type="integer")),
   *     @OA\Response(response=200, description="Payment deactivated")
   * )
   */
  public function delete()
  {
    $id = $_GET['id'] ?? null;
    if (!$id) {
      $this->jsonResponse([], 400, 'ID is required');
    }

    $db = DataBase::getConnection();
    $stmt = $db->prepare("UPDATE payments SET status = 'Inactivo' WHERE id = ?");
    $stmt->bind_param("i", $id);

    try {
      $stmt->execute();

      // Get contact_id to refresh balance
      $stmtInfo = $db->prepare("SELECT contact_id FROM payments WHERE id = ?");
      $stmtInfo->bind_param("i", $id);
      $stmtInfo->execute();
      if ($row = $stmtInfo->get_result()->fetch_assoc()) {
          Utils::refreshContactBalance($row['contact_id']);
      }

      $this->jsonResponse([], 200, 'Payment deactivated');
    } catch (Exception $e) {
      $this->jsonResponse([], 500, $e->getMessage());
    }
  }
}
