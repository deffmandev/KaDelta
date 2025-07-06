<?php
header('Content-Type: application/json');

include "Base.php";

$sql = "SELECT vu.*, du.* FROM [DefUnites] vu JOIN [ValUnites] du ON vu.Id = du.Id";

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

if (isset($_GET['groupes']) && $_GET['groupes'] == '1') {
    $groupes = [];
    $res = mssql('SELECT Id, Groupe FROM Groupe');
    while ($row = sqlnext($res)) {
        $groupes[] = $row;
    }
    header('Content-Type: application/json');
    echo json_encode(['groupes' => $groupes]);
    exit;
}

else
 {
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
 }

?>