<?php
require_once __DIR__ . '/Controller.php';
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../utils.php';

use OpenApi\Annotations as OA;

/**
 * @OA\Tag(
 *     name="Sales",
 *     description="Operations related to sales"
 * )
 */
class SaleController extends Controller
{
  /**
   * @OA\Get(
   *     path="/sales",
   *     tags={"Sales"},
   *     summary="Get all active sales",
   *     @OA\Response(response=200, description="Successful operation")
   * )
   */
  public function getAll()
  {
    $db = DataBase::getConnection();
    $date = $_GET['date'] ?? null;
    $sql = "SELECT s.*, c.name as contact_name 
            FROM sales s 
            LEFT JOIN contacts c ON s.contact_id = c.id 
            WHERE s.status != 'Inactivo'";

    if ($date) {
      $sql .= " AND s.sale_date = '" . $db->real_escape_string($date) . "'";
    }

    $sql .= " ORDER BY s.id DESC";
    $result = $db->query($sql);

    $sales = [];
    while ($row = $result->fetch_assoc()) {
      $sales[] = $row;
    }

    $this->jsonResponse($sales, 200, 'success');
  }

  /**
   * @OA\Get(
   *     path="/sale-by-id",
   *     tags={"Sales"},
   *     summary="Get sale by ID with items",
   *     @OA\Parameter(name="id", in="query", required=true, @OA\Schema(type="integer")),
   *     @OA\Response(response=200, description="Successful operation")
   * )
   */
  public function getById()
  {
    if (!isset($_GET['id'])) {
      $this->jsonResponse([], 400, 'Missing ID parameter');
    }

    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    $db = DataBase::getConnection();

    // Get Sale Master
    $stmt = $db->prepare("SELECT s.*, c.name as contact_name FROM sales s LEFT JOIN contacts c ON s.contact_id = c.id WHERE s.id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $saleResult = $stmt->get_result();

    if ($sale = $saleResult->fetch_assoc()) {
      // Decode items from JSON column
      $sale['items'] = isset($sale['items']) ? json_decode($sale['items'], true) : [];

      $this->jsonResponse($sale, 200, 'success');
    } else {
      $this->jsonResponse([], 404, 'Sale not found');
    }
  }

  /**
   * @OA\Post(
   *     path="/create-sale",
   *     tags={"Sales"},
   *     summary="Create a new sale with items",
   *     @OA\RequestBody(
   *         required=true,
   *         @OA\JsonContent(
   *             required={"sale_date", "contact", "total", "items"},
   *             @OA\Property(property="sale_date", type="string", format="date"),
   *             @OA\Property(property="contact", type="object",
   *                 @OA\Property(property="id", type="integer", nullable=true),
   *                 @OA\Property(property="name", type="string")
   *             ),
   *             @OA\Property(property="total", type="number"),
   *             @OA\Property(property="payment_type", type="string", default="contado"),
   *             @OA\Property(property="items", type="array", @OA\Items(
   *                 @OA\Property(property="product_id", type="integer", nullable=true),
   *                 @OA\Property(property="product_name", type="string"),
   *                 @OA\Property(property="quantity", type="number"),
   *                 @OA\Property(property="unit_price", type="number"),
   *                 @OA\Property(property="subtotal", type="number")
   *             ))
   *         )
   *     ),
   *     @OA\Response(response=201, description="Sale created successfully")
   * )
   */
  public function create()
  {
    $data = $this->getBody();

    if (empty($data['contact']) || empty($data['items'])) {
      $this->jsonResponse([], 400, 'Contact and Items are required');
    }

    $db = DataBase::getConnection();
    $db->begin_transaction();

    try {
      $contact = $data['contact'];
      $contactId = $contact['id'] ?? null;
      $contactName = $contact['name'] ?? '';

      // Create contact if it doesn't exist
      if (empty($contactId)) {
        $stmtContact = $db->prepare("INSERT INTO contacts (name, type, status) VALUES (?, ?, ?)");
        $type = 'cliente';
        $status = 'Activo';
        $stmtContact->bind_param("sss", $contactName, $type, $status);
        $stmtContact->execute();
        $contactId = $stmtContact->insert_id;
      }

      // 1. Insert Master
      $stmt = $db->prepare("INSERT INTO sales (sale_date, contact_id, contact_name, total, items, payment_type, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
      $saleDate = $data['sale_date'] ?? date('Y-m-d');
      $total = $data['total'];
      $items = json_encode($data['items'] ?? []);
      $paymentType = $data['payment_type'] ?? 'contado';
      $status = ($paymentType === 'credito') ? 'Por Pagar' : 'Pagado';

      $stmt->bind_param("sisdsss", $saleDate, $contactId, $contactName, $total, $items, $paymentType, $status);
      $stmt->execute();
      $saleId = $stmt->insert_id;

      $db->commit();
      if ($paymentType === 'credito') {
        Utils::updateDailyBalance($saleDate);
      }
      $this->jsonResponse(['id' => $saleId], 201, 'Sale created successfully');
    } catch (Exception $e) {
      $db->rollback();
      $this->jsonResponse([], 500, $e->getMessage());
    }
  }

  /**
   * @OA\Post(
   *     path="/update-sale",
   *     tags={"Sales"},
   *     summary="Update sale status or header",
   *     @OA\Response(response=200, description="Successful operation")
   * )
   */
  public function update()
  {
    $data = $this->getBody();
    if (empty($data['id'])) {
      $this->jsonResponse([], 400, 'ID is required');
    }

    $db = DataBase::getConnection();
    $id = $data['id'];

    $fields = [];
    $params = [];
    $types = "";

    if (isset($data['status'])) {
      $fields[] = "status = ?";
      $params[] = $data['status'];
      $types .= "s";
    }
    if (isset($data['payment_type'])) {
      $fields[] = "payment_type = ?";
      $params[] = $data['payment_type'];
      $types .= "s";
    }

    if (empty($fields)) {
      $this->jsonResponse([], 400, 'No fields to update');
    }

    $sql = "UPDATE sales SET " . implode(", ", $fields) . " WHERE id = ?";
    $params[] = $id;
    $types .= "i";

    $stmt = $db->prepare($sql);
    $stmt->bind_param($types, ...$params);

    try {
      $stmt->execute();

      // Fetch sale info to update balance
      $stmtInfo = $db->prepare("SELECT sale_date, payment_type FROM sales WHERE id = ?");
      $stmtInfo->bind_param("i", $id);
      $stmtInfo->execute();
      if ($row = $stmtInfo->get_result()->fetch_assoc()) {
        $newPaymentType = $data['payment_type'] ?? $row['payment_type'];
        if ($row['payment_type'] === 'credito' || $newPaymentType === 'credito') {
          Utils::updateDailyBalance($row['sale_date']);
        }
      }

      $this->jsonResponse([], 200, 'Updated successfully');
    } catch (Exception $e) {
      $this->jsonResponse([], 500, $e->getMessage());
    }
  }

  /**
   * @OA\Delete(
   *     path="/delete-sale",
   *     tags={"Sales"},
   *     summary="Soft delete a sale",
   *     @OA\Parameter(name="id", in="query", required=true, @OA\Schema(type="integer")),
   *     @OA\Response(response=200, description="Sale deactivated")
   * )
   */
  public function delete()
  {
    $id = $_GET['id'] ?? null;
    if (!$id) {
      $this->jsonResponse([], 400, 'ID is required');
    }

    $db = DataBase::getConnection();
    $stmt = $db->prepare("UPDATE sales SET status = 'Inactivo' WHERE id = ?");
    $stmt->bind_param("i", $id);

    try {
      $stmt->execute();

      // Fetch sale info to update balance
      $stmtInfo = $db->prepare("SELECT sale_date, payment_type FROM sales WHERE id = ?");
      $stmtInfo->bind_param("i", $id);
      $stmtInfo->execute();
      if ($row = $stmtInfo->get_result()->fetch_assoc()) {
        if ($row['payment_type'] === 'credito') {
          Utils::updateDailyBalance($row['sale_date']);
        }
      }

      $this->jsonResponse([], 200, 'Sale deactivated');
    } catch (Exception $e) {
      $this->jsonResponse([], 500, $e->getMessage());
    }
  }
}
