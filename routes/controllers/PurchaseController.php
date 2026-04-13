<?php
require_once __DIR__ . '/Controller.php';
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../utils.php';

use OpenApi\Annotations as OA;

/**
 * @OA\Tag(
 *     name="Purchases",
 *     description="Operations related to purchases"
 * )
 */
class PurchaseController extends Controller
{
  /**
   * @OA\Get(
   *     path="/purchases",
   *     tags={"Purchases"},
   *     summary="Get all active purchases",
   *     @OA\Response(response=200, description="Successful operation")
   * )
   */
  public function getAll()
  {
    $db = DataBase::getConnection();
    $date = $_GET['date'] ?? null;
    $sql = "SELECT p.*, c.name as contact_name 
            FROM purchases p 
            LEFT JOIN contacts c ON p.contact_id = c.id 
            WHERE p.status != 'Inactivo'";

    if ($date) {
      $sql .= " AND p.purchase_date = '" . $db->real_escape_string($date) . "'";
    }

    $sql .= " ORDER BY p.id DESC";
    $result = $db->query($sql);

    $purchases = [];
    while ($row = $result->fetch_assoc()) {
      $purchases[] = $row;
    }

    $this->jsonResponse($purchases, 200, 'success');
  }

  /**
   * @OA\Get(
   *     path="/purchase-by-id",
   *     tags={"Purchases"},
   *     summary="Get purchase by ID with items",
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

    $stmt = $db->prepare("SELECT p.*, c.name as contact_name FROM purchases p LEFT JOIN contacts c ON p.contact_id = c.id WHERE p.id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($purchase = $result->fetch_assoc()) {
      // Decode items from JSON column
      $purchase['items'] = isset($purchase['items']) ? json_decode($purchase['items'], true) : [];

      $this->jsonResponse($purchase, 200, 'success');
    } else {
      $this->jsonResponse([], 404, 'Purchase not found');
    }
  }

  /**
   * @OA\Post(
   *     path="/create-purchase",
   *     tags={"Purchases"},
   *     summary="Create a new purchase with items",
   *     @OA\RequestBody(
   *         required=true,
   *         @OA\JsonContent(
   *             required={"purchase_date", "contact", "total", "items"},
   *             @OA\Property(property="purchase_date", type="string", format="date"),
   *             @OA\Property(property="contact", type="object",
   *                 @OA\Property(property="id", type="integer", nullable=true),
   *                 @OA\Property(property="name", type="string")
   *             ),
   *             @OA\Property(property="total", type="number"),
   *             @OA\Property(property="ref_invoice", type="string"),
   *             @OA\Property(property="items", type="array", @OA\Items(
   *                 @OA\Property(property="product_id", type="integer", nullable=true),
   *                 @OA\Property(property="product_name", type="string"),
   *                 @OA\Property(property="quantity", type="number"),
   *                 @OA\Property(property="unit_price", type="number"),
   *                 @OA\Property(property="subtotal", type="number")
   *             ))
   *         )
   *     ),
   *     @OA\Response(response=201, description="Purchase created successfully")
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
        $type = 'proveedor';
        $status = 'Activo';
        $stmtContact->bind_param("sss", $contactName, $type, $status);
        $stmtContact->execute();
        $contactId = $stmtContact->insert_id;
      }

      $stmt = $db->prepare("INSERT INTO purchases (purchase_date, contact_id, contact_name, total, items, ref_invoice, status, payment_type) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
      $date = $data['purchase_date'] ?? date('Y-m-d');
      $total = $data['total'];
      $items = json_encode($data['items'] ?? []);
      $refInvoice = $data['ref_invoice'] ?? '';
      $paymentType = $data['payment_type'] ?? 'contado';
      $status = ($paymentType === 'credito') ? 'Por Pagar' : 'Pagado';

      $stmt->bind_param("sisdssss", $date, $contactId, $contactName, $total, $items, $refInvoice, $status, $paymentType);
      $stmt->execute();
      $purchaseId = $stmt->insert_id;

      $db->commit();
      if ($paymentType === 'credito') {
        Utils::updateDailyBalance($date);
      }
      $this->jsonResponse(['id' => $purchaseId], 201, 'Purchase created successfully');
    } catch (Exception $e) {
      $db->rollback();
      $this->jsonResponse([], 500, $e->getMessage());
    }
  }

  /**
   * @OA\Post(
   *     path="/update-purchase",
   *     tags={"Purchases"},
   *     summary="Update purchase header",
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
    if (isset($data['ref_invoice'])) {
      $fields[] = "ref_invoice = ?";
      $params[] = $data['ref_invoice'];
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

    $sql = "UPDATE purchases SET " . implode(", ", $fields) . " WHERE id = ?";
    $params[] = $id;
    $types .= "i";

    $stmt = $db->prepare($sql);
    $stmt->bind_param($types, ...$params);

    try {
      $stmt->execute();

      // Fetch purchase info to update balance
      $stmtInfo = $db->prepare("SELECT purchase_date, payment_type FROM purchases WHERE id = ?");
      $stmtInfo->bind_param("i", $id);
      $stmtInfo->execute();
      if ($row = $stmtInfo->get_result()->fetch_assoc()) {
        $newPaymentType = $data['payment_type'] ?? $row['payment_type'];
        if ($row['payment_type'] === 'credito' || $newPaymentType === 'credito') {
          Utils::updateDailyBalance($row['purchase_date']);
        }
      }

      $this->jsonResponse([], 200, 'Updated successfully');
    } catch (Exception $e) {
      $this->jsonResponse([], 500, $e->getMessage());
    }
  }

  /**
   * @OA\Delete(
   *     path="/delete-purchase",
   *     tags={"Purchases"},
   *     summary="Soft delete a purchase",
   *     @OA\Parameter(name="id", in="query", required=true, @OA\Schema(type="integer")),
   *     @OA\Response(response=200, description="Purchase deactivated")
   * )
   */
  public function delete()
  {
    $id = $_GET['id'] ?? null;
    if (!$id) {
      $this->jsonResponse([], 400, 'ID is required');
    }

    $db = DataBase::getConnection();
    $stmt = $db->prepare("UPDATE purchases SET status = 'Inactivo' WHERE id = ?");
    $stmt->bind_param("i", $id);

    try {
      $stmt->execute();

      // Fetch purchase info to update balance
      $stmtInfo = $db->prepare("SELECT purchase_date, payment_type FROM purchases WHERE id = ?");
      $stmtInfo->bind_param("i", $id);
      $stmtInfo->execute();
      if ($row = $stmtInfo->get_result()->fetch_assoc()) {
        if ($row['payment_type'] === 'credito') {
          Utils::updateDailyBalance($row['purchase_date']);
        }
      }

      $this->jsonResponse([], 200, 'Purchase deactivated');
    } catch (Exception $e) {
      $this->jsonResponse([], 500, $e->getMessage());
    }
  }
}
