<?php

require_once "db.php";
require_once "utils.php";

function deleteData($table)
{
  $table = htmlspecialchars($table);
  $table = explode("?", $table)[0];
  $column = htmlspecialchars($_GET['column']);
  $id = htmlspecialchars($_GET["id"]);

  $validations = Utils::ValidateTableName($table);

  if ($validations == 0) {
    $json = [
      'status' => 404,
      'result' => 'tabla no encontrada'
    ];
    echo json_encode($json, http_response_code($json["status"]));
    die();
  }

  if (isset($id) && isset($column)) {

    $sql = "DELETE FROM $table WHERE $column = ?";

    try {
      $db = DataBase::getConnection();
      $stmt = $db->prepare($sql);
      $stmt->bind_param("s", $id);
      $stmt->execute();
      $result = $stmt->affected_rows;
      if ($result > 0) {
        $json = [
          'status' => 200,
          'result' => 'The process was success'
        ];
      } else {
        $json = [
          'status' => 404,
          'result' => 'ID not found'
        ];
      }
      echo json_encode($json, http_response_code($json["status"]));
    } catch (mysqli_sql_exception $error) {
      $json = [
        'status' => 500,
        'result' => $error->getMessage()
      ];
      echo json_encode($json, http_response_code($json["status"]));
    }

    $stmt->close();
    $db->close();
  } else {
    $json = [
      'status' => 500,
      'result' => 'ID o column no valido'
    ];
    echo json_encode($json, http_response_code($json["status"]));
  }
}
