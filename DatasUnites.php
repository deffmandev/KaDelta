<?php
header('Content-Type: application/json');

include "Base.php";

$sql = "SELECT * FROM  [ValUnites]";
try {
    $stmt = mssql($sql);
} catch (Exception $e) {
    die(json_encode(['error' => $e->getMessage()]));
}


if ($stmt === false) {
    die(json_encode(['error' => sqlsrv_errors()]));
}


$data = [];
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $data[] = $row;
}

echo json_encode($data, JSON_UNESCAPED_UNICODE);

?>