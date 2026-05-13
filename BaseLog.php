<?php

$serverName = "localhost,1433";
$connectionOptions = [
    "Database"                  => "KaLog",
    "Uid"                       => "BaseKa",
    "PWD"                       => "deffdeff",
    "CharacterSet"              => "UTF-8",
    "ConnectionPooling"         => true,
    "MultipleActiveResultSets"  => false,
];


$connLog = sqlsrv_connect($serverName, $connectionOptions);

function mssqllog($sql)
{
    global $connLog;
    try {
        $stmt = sqlsrv_query($connLog, $sql);
        if ($stmt === false) {
            logSqlError('mssql');
            return false;
        }
        return $stmt;
    } catch (Exception $e) {
        error_log("[EXCEPTION] " . $e->getMessage());
        echo "<div style='color:red;font-weight:bold;'>Erreur SQL. Consultez les logs.</div>";
        return false;
    }

}

date_default_timezone_set('Europe/Paris');

function LogIn($device, $point, $valeur)
{
    $date  = (string) date('d-m-Y');
    $heure = (string) date('H:i');
    // DateNV = meme valeur que Date : permet l'index seek sur IX_Log_Device_Point_DateNV
    $sql    = 'INSERT INTO Log (Date, Heure, Device, Point, Valeur, DateNV) VALUES (?, ?, ?, ?, ?, ?)';
    $params = [$date, $heure, (string)$device, (string)$point, (string)$valeur, $date];
    global $connLog;
    $stmt = sqlsrv_query($connLog, $sql, $params);
    if ($stmt !== false) { sqlsrv_free_stmt($stmt); }
}


?>