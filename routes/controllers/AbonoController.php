<?php
require_once __DIR__ . '/Controller.php';
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../utils.php';

use OpenApi\Annotations as OA;

/**
 * @OA\Tag(
 *     name="Abonos",
 *     description="Operations related to abonos"
 * )
 */
class AbonoController extends Controller
{
  /**
   * @OA\Get(
   *     path="/abonos",
   *     tags={"Abonos"},
   *     summary="Get all active abonos",
   *     @OA\Response(response=200, description="Successful operation")
   * )
   */
  public function getAll()
  {
    $db = DataBase::getConnection();
    $sql = "SELECT a.*, c.name as contact_name 
            FROM abonos a 
            LEFT JOIN contacts c ON a.contact_id = c.id 
            WHERE a.status = 'Activo' 
            ORDER BY a.id DESC";
    $result = $db->query($sql);

    $abonos = [];
    while ($row = $result->fetch_assoc()) {
      $abonos[] = $row;
    }

    $this->jsonResponse($abonos, 200, 'success');
  }

  /**
   * @OA\Post(
   *     path="/create-abono",
   *     tags={"Abonos"},
   *     summary="Create a new abono",
   *     @OA\Response(response=201, description="Abono created successfully")
   * )
   */
  public function create()
  {
    $data = $this->getBody();

    if (empty($data['contact']) || empty($data['total'])) {
      $this->jsonResponse([], 400, 'Contact and Total are required');
    }

    $db = DataBase::getConnection();
    $db->begin_transaction();

    try {
      $contact = $data['contact'];
      $contactId = $contact['id'] ?? null;
      $contactName = $contact['name'] ?? '';

      // Create contact if it doesn't exist
      if (empty($contactId) && !empty($contactName)) {
        // First check if it already exists by name
        $stmtCheck = $db->prepare("SELECT id FROM contacts WHERE name = ? LIMIT 1");
        $stmtCheck->bind_param("s", $contactName);
        $stmtCheck->execute();
        $resCheck = $stmtCheck->get_result();

        if ($rowCheck = $resCheck->fetch_assoc()) {
          $contactId = $rowCheck['id'];
        } else {
          $stmtContact = $db->prepare("INSERT INTO contacts (name, type, status, currency) VALUES (?, ?, ?, ?)");
          $type = 'cliente'; // Asumiendo cliente para abonos
          $status = 'Activo';
          $currency = $data['currency'] ?? 'USD';
          $stmtContact->bind_param("ssss", $contactName, $type, $status, $currency);
          $stmtContact->execute();
          $contactId = $stmtContact->insert_id;
        }
      } else if (empty($contactId)) {
        $this->jsonResponse([], 400, 'Contact is required');
      }

      $stmt = $db->prepare("INSERT INTO abonos (abono_date, contact_id, contact_name, total, status, created_at, currency, type) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");

      $abonoDate = $data['sale_date'] ?? ($data['date'] ?? date('Y-m-d'));
      $total = $data['total'];
      $status = 'Activo';
      $createdAt = $data['create_at'] ?? date('Y-m-d H:i:s');
      $currency = $data['currency'] ?? 'USD';
      $type = $data['type'] ?? 'ingreso';

      $stmt->bind_param("sisdssss", $abonoDate, $contactId, $contactName, $total, $status, $createdAt, $currency, $type);
      $stmt->execute();
      $abonoId = $stmt->insert_id;

      $db->commit();

      // Update balance internally based on abono_date
      Utils::updateDailyBalance($abonoDate);
      Utils::refreshContactBalance($contactId);

      $this->jsonResponse(['id' => $abonoId], 201, 'Abono created successfully');
    } catch (Exception $e) {
      $db->rollback();
      $this->jsonResponse([], 500, $e->getMessage());
    }
  }

  /**
   * @OA\Delete(
   *     path="/delete-abono",
   *     tags={"Abonos"},
   *     summary="Soft delete an abono",
   *     @OA\Response(response=200, description="Abono deactivated")
   * )
   */
  public function delete()
  {
    $id = $_GET['id'] ?? null;
    if (!$id) {
      $this->jsonResponse([], 400, 'ID is required');
    }

    $db = DataBase::getConnection();
    $stmt = $db->prepare("UPDATE abonos SET status = 'Inactivo' WHERE id = ?");
    $stmt->bind_param("i", $id);

    try {
      $stmt->execute();

      // Get contact_id to refresh balance
      $stmtInfo = $db->prepare("SELECT contact_id FROM abonos WHERE id = ?");
      $stmtInfo->bind_param("i", $id);
      $stmtInfo->execute();
      if ($row = $stmtInfo->get_result()->fetch_assoc()) {
        Utils::refreshContactBalance($row['contact_id']);
      }

      $this->jsonResponse([], 200, 'Abono deactivated');
    } catch (Exception $e) {
      $this->jsonResponse([], 500, $e->getMessage());
    }
  }
}
