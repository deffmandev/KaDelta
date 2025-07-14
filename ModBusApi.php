<?php

include "base.php";
include "ModBus.php";

extract($_GET,EXTR_PREFIX_ALL,"Page");

$modbusRows = [];
$ModbusData = mssql("SELECT Addresse,Port,Id FROM [dbo].[DefModBus]");
while ($modbusRow = sqlnext($ModbusData)) {
    $modbusRows[$modbusRow["Id"]] = $modbusRow;
}


function ModUnite($Id)
{
    global $Page_Type;
    global $Page_Idem;
    global $Page_Valeur;
    global $modbusRows;

    
    $BaseData=mssql("SELECT * FROM [dbo].[DefUnites] WHERE Id = $Id");
    $row=sqlnext($BaseData);

    $Modbusid=$row["ModbusId"];

    $ip = $modbusRows[$Modbusid]["Addresse"];
    $port = $modbusRows[$Modbusid]["Port"];
    $Type = $row[$Page_Type];
    $Adddr = $row[$Page_Idem];
    $valeur = $Page_Valeur;
    $DeviceId = $row["Device"];



    $Modbus=connectModbusTcp($ip, $port);
    if (!$Modbus) {
        echo "Erreur de connexion au Modbus à l'adresse $ip:$port";
        return;
    }

    ModbusWrite($Modbus,$DeviceId,$Adddr,$Type,$valeur);

    if ($Modbus) CloseModbusTcp($Modbus);
}


if ($Page_GroupeActif === 'false')
            ModUnite($Page_IdUnite);


if (($Page_GroupeActif === 'true') && ($Page_GroupeId!="all"))
{
    $BaseData=mssql("SELECT Id FROM [dbo].[DefUnites] WHERE Gr = $Page_GroupeId");
        while ($row=sqlnext($BaseData)) 
        {
            ModUnite($row["Id"]);
        }
}

if (($Page_GroupeActif === 'true') && ($Page_GroupeId === "all"))
{
    $BaseData=mssql("SELECT Id FROM [dbo].[DefUnites]");
        while ($row=sqlnext($BaseData)) 
        {
            ModUnite($row["Id"]);
        }
}


?>