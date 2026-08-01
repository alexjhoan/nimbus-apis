<?php
if (!function_exists('getallheaders')) { function getallheaders() { return []; } }
require_once "utils.php";
require_once "db.php";

$db = DataBase::getConnection();
$res = $db->query("SELECT id FROM contacts");

echo "Repopulating contact balances...\n";
$count = 0;
while ($row = $res->fetch_assoc()) {
    Utils::refreshContactBalance($row['id']);
    $count++;
    if ($count % 10 == 0) echo "Processed $count contacts...\n";
}

echo "Done! Repopulated $count contact balances.\n";
