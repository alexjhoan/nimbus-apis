<?php
if (!function_exists('getallheaders')) {
    function getallheaders() {
        return [];
    }
}
require 'db.php';
$db = DataBase::getConnection();
$res = $db->query("SHOW CREATE TABLE contact_balances");
if($res){
    print_r($res->fetch_assoc());
} else {
    echo "Table does not exist";
}
