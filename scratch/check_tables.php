<?php
if (!function_exists('getallheaders')) {
    function getallheaders() {
        return [];
    }
}
require 'db.php';
$db = DataBase::getConnection();
$res = $db->query("SHOW TABLES");
while($row = $res->fetch_array()) {
    echo $row[0] . "\n";
}
echo "--- Schema of account_balances ---\n";
$res = $db->query("SHOW CREATE TABLE account_balances");
if ($res) {
    echo $res->fetch_array()[1] . "\n";
} else {
    echo "account_balances does not exist\n";
}
echo "--- Schema of contact_balances ---\n";
$res = $db->query("SHOW CREATE TABLE contact_balances");
if ($res) {
    echo $res->fetch_array()[1] . "\n";
} else {
    echo "contact_balances does not exist\n";
}
