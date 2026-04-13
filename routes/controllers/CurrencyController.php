<?php
require_once __DIR__ . '/Controller.php';
require_once __DIR__ . '/../../db.php';

class CurrencyController extends Controller
{
  public function getRates()
  {
    $db = DataBase::getConnection();
    $sql = "SELECT id, rate_cop, rate_ves, last_updated FROM currency_settings LIMIT 1";
    $result = $db->query($sql);

    // If no settings exist, return defaults and create them
    if ($result->num_rows === 0) {
      $db->query("INSERT INTO currency_settings (rate_cop, rate_ves) VALUES (3900.00, 36.00)");
      $this->jsonResponse([
        'rate_cop' => 3900.00,
        'rate_ves' => 36.00,
        'last_updated' => date('Y-m-d H:i:s')
      ]);
    }

    $row = $result->fetch_assoc();
    $this->jsonResponse($row);
  }

  public function updateRates()
  {
    $data = $this->getBody();
    $db = DataBase::getConnection();

    $fields = [];
    $types = "";
    $params = [];

    if (isset($data['rate_cop'])) {
      $fields[] = "rate_cop = ?";
      $types .= "d";
      $params[] = (float)$data['rate_cop'];
    }

    if (isset($data['rate_ves'])) {
      $fields[] = "rate_ves = ?";
      $types .= "d";
      $params[] = (float)$data['rate_ves'];
    }

    if (empty($fields)) {
      $this->jsonResponse([], 400, 'No fields provided');
    }

    // Since we only have 1 row for currency settings
    $sql = "UPDATE currency_settings SET " . implode(", ", $fields) . " WHERE id = 1";
    $stmt = $db->prepare($sql);
    $stmt->bind_param($types, ...$params);

    try {
      $stmt->execute();
      $this->jsonResponse([], 200, 'Rates updated successfully');
    } catch (Exception $e) {
      $this->jsonResponse([], 500, $e->getMessage());
    }
  }
}
