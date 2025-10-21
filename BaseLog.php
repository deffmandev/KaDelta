<?php

$serverName = "localhost,1433";
$connectionOptions = [
    "Database" => "KaLog",
    "Uid" => "BaseKa",
    "PWD" => "deffdeff",
    "CharacterSet" => "UTF-8",
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
    $date = (string)date("d-m-Y");
    $heure = (string)date("H:i");
    $sql="INSERT INTO Log (Date, Heure, Device, Point, Valeur) values ('".$date."','".$heure."','".$device."','".$point."','".$valeur."')";
    mssqllog($sql);
}


?>