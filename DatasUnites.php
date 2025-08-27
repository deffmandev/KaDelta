<?php
header('Content-Type: application/json');

include "Base.php";

$sql = "SELECT vu.*, du.* FROM [DefUnites] vu JOIN [ValUnites] du ON vu.Id = du.Id ORDER BY vu.Id";

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

// Compter le nombre d'entrées dans DefUnites
$countDefUnites = 0;
$resCount = mssql('SELECT COUNT(*) as cnt FROM DefUnites');
if ($resCount !== false) {
    $rowCount = sqlsrv_fetch_array($resCount, SQLSRV_FETCH_ASSOC);
    if ($rowCount && isset($rowCount['cnt'])) {
        $countDefUnites = (int)$rowCount['cnt'];
    }
}

// Comparer avec le nombre d'éléments dans $data
if (count($data) !== $countDefUnites) {
    echo json_encode(['error' => 'Nombre d\'entrées dans DefUnites ('.$countDefUnites.') différent du nombre de résultats ('.count($data).')']);
    exit;
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