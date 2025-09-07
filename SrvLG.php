<?php
include "Base.php";
include "modbus.php";

//chargement des entree modbus
$result = mssql("SELECT Id,Addresse,Port FROM [dbo].[DefModBus]");
$defModBusData = [];
    while ($row = sqlnext($result))
        $defModBusData[$row["Id"]] = $row;

    $ModBusmemo = "";//memoire du mode bus ouvert
    $socket = null;

    $resultUnite = mssql("SELECT * FROM [dbo].[DefUnites] Order By ModbusId");
while ($row = sqlnext($resultUnite)) 
{
        echo chr(10).chr(13)."\n\r";

    $ip = $defModBusData[$row["ModbusId"]]["Addresse"];
    $port = $defModBusData[$row["ModbusId"]]["Port"];
    $unitId = $row["Device"]; 
    $Id=$row["Id"];

    echo "Lire unite ".$Id." Addresse : ".$ip."  port : ".$port." Premiere addresse :" . $row["OnOff"]."  ";

try {
    $ModbusValue = $row["ModbusId"];
    if ($ModbusValue!=$ModBusmemo)
    {
        $ModBusmemo = $ModbusValue;
        if ($socket) fclose($socket);
        $socket = connectModbusTcp($ip, $port);
        echo " CONNECTER .";
    }

    if ($socket)
    {
        $type= $row["Type_OnOff"];$startAddress= $row["OnOff"];
        $OnOff=LireModbus($socket,$unitId,$startAddress,$type);

            if ($OnOff===false)
                {   
                    Defaut();
                    continue;
                }
    
    
    if (!($OnOff===false))
    {

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

            if ($Mode === 1) // Mode Climatisation
        {
            if ($SetRoom < $row["LimiteClimB"]) ModbusWrite($socket,$unitId,$startAddress,$type,$row["LimiteClimB"]);
            if ($SetRoom > $row["LimiteClimH"]) ModbusWrite($socket,$unitId,$startAddress,$type,$row["LimiteClimH"]);
        }
            if ($Mode === 5) // Mode Chauffage
        {
            if ($SetRoom < $row["LimiteChaudB"]) ModbusWrite($socket,$unitId,$startAddress,$type,$row["LimiteChaudB"]);
            if ($SetRoom > $row["LimiteChaudH"]) ModbusWrite($socket,$unitId,$startAddress,$type,$row["LimiteChaudH"]);
        }

            if ($Mode === 4) // Mode Automatique
        {
            $Minimum = min($row["LimiteChaudB"], $row["LimiteClimB"]);
            $Maximum = max($row["LimiteChaudH"], $row["LimiteClimH"]);
            
            if ($SetRoom < $Minimum)  ModbusWrite($socket,$unitId,$startAddress,$type,$Minimum);
            if ($SetRoom > $Maximum)  ModbusWrite($socket,$unitId,$startAddress,$type,$Maximum);
        }

    $type= $row["Type_CodeErreur"];$startAddress=$row["CodeErreur"];
    $CodeErreur=LireModbus($socket,$unitId,$startAddress,$type);

    if ($CodeErreur)
        echo "   Erreur : ".$CodeErreur;
    }
}

    else
    {
        $OnOff=0;
        $Alarm=1;
        $Mode=0;
        $Fan=0;
        $Room=0;
        $SetRoom=0;
        $CodeErreur=100066;
    }
    // Efface uniquement si l'Id existe déjà dans ValUnites

if ($OnOff!==false) 
    {
            $resCheck = mssql("SELECT 1 FROM [ValUnites] WHERE Id=$Id");
            if ($resCheck && sqlnext($resCheck)) 
                mssql("DELETE FROM [ValUnites] Where Id=$Id");
        
                mssql("INSERT INTO [ValUnites] (Id, OnOff, Alarm, Mode, Fan, Room, SetRoom, CodeErreur) VALUES ($Id,$OnOff,$Alarm,$Mode,$Fan,$Room,$SetRoom,$CodeErreur)");            
    } 
} 


catch (Exception $e) 
{
    echo "Erreur : " . $e->getMessage();
}
}


function Defaut()
{
        global $Id;
        global $socket;
        global $ModBusmemo;

        $OnOff=0;
        $Alarm=1;
        $Mode=0;
        $Fan=0;
        $Room=0;
        $SetRoom=0;
        $CodeErreur=100088;

        echo "Defaut Unite : ".$Id;

        $resCheck = mssql("SELECT 1 FROM [ValUnites] WHERE Id=$Id");
        if ($resCheck && sqlnext($resCheck)) 
            mssql("DELETE FROM [ValUnites] Where Id=$Id");
        mssql("INSERT INTO [ValUnites] (Id, OnOff, Alarm, Mode, Fan, Room, SetRoom, CodeErreur) VALUES ($Id,$OnOff,$Alarm,$Mode,$Fan,$Room,$SetRoom,$CodeErreur)");            
}

?>