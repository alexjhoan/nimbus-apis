<?php
require_once __DIR__ . '/Controller.php';
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../utils.php';

use OpenApi\Annotations as OA;

/**
 * @OA\Tag(
 *     name="Balances",
 *     description="Operations related to contact balances and financial summaries"
 * )
 */
class BalanceController extends Controller
{
  /**
   * @OA\Get(
   *     path="/debt-directory",
   *     tags={"Balances"},
   *     summary="Get contacts with active debts or credits (from contact_balances)",
   *     @OA\Response(response=200, description="Successful operation")
   * )
   */
  public function getDirectory()
  {
    $db = DataBase::getConnection();
    $sql = "SELECT cb.*, c.name as contact_name, c.type, c.currency
            FROM contact_balances cb 
            JOIN contacts c ON cb.contact_id = c.id 
            WHERE cb.total_debt > 0 OR cb.total_credit > 0 
            ORDER BY c.name ASC";
    $result = $db->query($sql);

    $directory = [];
    while ($row = $result->fetch_assoc()) {
      $directory[] = $row;
    }

    $this->jsonResponse($directory, 200, 'success');
  }

  /**
   * @OA\Get(
   *     path="/contact-balance",
   *     tags={"Balances"},
   *     summary="Get single contact balance from contact_balances table",
   *     @OA\Parameter(name="id", in="query", required=true, @OA\Schema(type="integer")),
   *     @OA\Response(response=200, description="Successful operation")
   * )
   */
  public function getContactBalance()
  {
    if (!isset($_GET['id'])) {
      $this->jsonResponse([], 400, 'Missing ID parameter');
    }

    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    $db = DataBase::getConnection();

    $stmt = $db->prepare("SELECT total_debt, total_credit, type, currency FROM contact_balances WHERE contact_id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
      $row['balance'] = $row['total_debt'] - $row['total_credit'];
      $this->jsonResponse($row, 200, 'success');
    } else {
      // If not found, attempt to initialize it
      Utils::refreshContactBalance($id);
      $stmt->execute();
      $result = $stmt->get_result();
      if ($row = $result->fetch_assoc()) {
        $row['balance'] = $row['total_debt'] - $row['total_credit'];
        $this->jsonResponse($row, 200, 'success');
      } else {
        $this->jsonResponse(['total_debt' => 0, 'total_credit' => 0, 'balance' => 0, 'type' => 'cliente', 'currency' => 'USD'], 200, 'success');
      }
    }
  }
}
