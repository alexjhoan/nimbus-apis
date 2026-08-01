<?php
if (!function_exists('getallheaders')) { function getallheaders() { return []; } }
require 'db.php';
$db = DataBase::getConnection();
$res = $db->query("SHOW INDEX FROM contact_balances");
while($row = $res->fetch_assoc()) {
    echo $row['Column_name'] . " - " . $row['Key_name'] . "\n";
}
?>
