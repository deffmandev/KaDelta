<?php
include "Base.php";
include "modbus.php";


//chargement des entree modbus
$result = mssql("SELECT Id,Addresse,Port FROM [dbo].[DefModBus]");
$defModBusData = [];
while ($row = sqlnext($result)) $defModBusData[$row["Id"]] = $row;


function LireModbus($socket, $unitId, $startAddress, $type) {
    if ($type == "1")   
    {
        return readModbusCoil($socket, $unitId, $startAddress, 1)[0];
    } 
    if ($type == "3") 
    {
        return readModbusRegisters($socket, $unitId, $startAddress, 1)[0];
    }

    if ($type > "299")   
    {

        $valeur = readModbusRegisters($socket, $unitId, $startAddress, 1)[0];
        $ValBit=to16BitBinary($valeur);
        $bit=15-($type-300);
        if ($ValBit[$bit] === '1') {
            return 1; // Le bit est à 1
        } else {
            return 0; // Le bit est à 0
        }
    } 

    
}


$resultUnite = mssql("SELECT * FROM [dbo].[DefUnites]");
while ($row = sqlnext($resultUnite)) 
{
    $ip = $defModBusData[$row["ModbusId"]]["Addresse"];
    $port = $defModBusData[$row["ModbusId"]]["Port"];
    $unitId = $row["Device"]; 
    $Id=$row["Id"];

    try {
    $socket = connectModbusTcp($ip, $port);

    if ($socket)
    {
    $type= $row["Type_OnOff"];$startAddress= $row["OnOff"];
    $OnOff=LireModbus($socket,$unitId,$startAddress,$type);

    $type= $row["Type_Alarm"];$startAddress= $row["Alarm"];
    $Alarm=LireModbus($socket,$unitId,$startAddress,$type); 

    $type= $row["Type_Mode"];$startAddress=$row["Mode"];
    $Mode=LireModbus($socket,$unitId,$startAddress,$type);

    $type= $row["Type_Fan"];$startAddress=$row["Fan"];
    $Fan=LireModbus($socket,$unitId,$startAddress,$type);

    $type= $row["Type_Room"];$startAddress=$row["Room"];
    $Room=LireModbus($socket,$unitId,$startAddress,$type);

    $type= $row["Type_SetRoom"];$startAddress=$row["SetRoom"];
    $SetRoom=LireModbus($socket,$unitId,$startAddress,$type);

    $type= $row["Type_CodeErreur"];$startAddress=$row["CodeErreur"];
    $CodeErreur=LireModbus($socket,$unitId,$startAddress,$type);

    fclose($socket);
    }
    else
    {
        $OnOff=0;
        $Alarm=1;
        $Mode=0;
        $Fan=0;
        $Room=0;
        $SetRoom=0;
        $CodeErreur=100060;
    }
        mssql("DELETE FROM [ValUnites] Where Id=$Id");
        mssql("INSERT INTO [ValUnites] (Id, OnOff, Alarm, Mode, Fan, Room, SetRoom, CodeErreur) VALUES ($Id,$OnOff,$Alarm,$Mode,$Fan,$Room,$SetRoom,$CodeErreur)");            
} 
catch (Exception $e) {
    echo "Erreur : " . $e->getMessage();
}

}


       // GetTable("ValUnites");

    echo strftime("Dernière mise à jour : %A %d %B %Y %H:%M:%S", time());
   
if (!empty($_GET["Commande"])) 
{
    echo $_GET["Commande"];
}

// Appel automatique à SrvProg.php toutes les minutes (via variable/fichier local)
$lastSrvProgFile = __DIR__ . '/last_srvprog.txt';
$now = time();
$lastCall = 0;
if (file_exists($lastSrvProgFile)) {
    $lastCall = (int)file_get_contents($lastSrvProgFile);
}
if ($now - $lastCall >= 60) {
    // Appel SrvProg.php
    $srvprog_result = @file_get_contents('http://localhost/SrvProg.php');
    file_put_contents($lastSrvProgFile, $now);
    echo htmlspecialchars($srvprog_result);
}

?>