<?php
require_once 'db.php';
$db = DataBase::getConnection("main");
$res = $db->query('DESCRIBE purchases');
while($row = $res->fetch_assoc()) {
    echo $row['Field'] . " (" . $row['Type'] . ")\n";
}
$res = $db->query('DESCRIBE sales');
while($row = $res->fetch_assoc()) {
    echo $row['Field'] . " (" . $row['Type'] . ")\n";
}
