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
    global $Page_GroupeActif;
    global $Page_IdUnite;

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

    if ($Type=='3') {
        try {
            writeModbusRegister($Modbus, $DeviceId, $Adddr, $valeur);
            }
            catch (Exception $e) {
                echo "Erreur lors de l'écriture du registre Modbus : " . $e->getMessage();
            return;
        }
    } 

    if ($Type=='1') {
        try {
            writeModbusCoil($Modbus, $DeviceId, $Adddr, $valeur);
            }
            catch (Exception $e) {
                echo "Erreur lors de l'écriture de la bobine Modbus : " . $e->getMessage();
            return;
        }
    }
    if ($Type>299)
    {
        $Bit = $Type - 300; // Calculer le bit à partir du type
        $Valeur=readModbusRegisters($Modbus, $DeviceId, $Adddr, 1)[0]; // Lire la valeur actuelle du registre
        $ValBit=to16BitBinary($valeur);

        // Met à jour le bit $Bit de $Valeur avec la valeur $valeur (0 ou 1)
        if ($valeur) {
            $NewValeur = $Valeur | (1 << $Bit);
        } else {
            $NewValeur = $Valeur & ~(1 << $Bit);
        }

        $ValBit=to16BitBinary($NewValeur);

        
        try {
            writeModbusRegister($Modbus, $DeviceId, $Adddr, $NewValeur);
            }
            catch (Exception $e) {
                echo "Erreur lors de l'écriture du registre Modbus : " . $e->getMessage();
            return;
        }
    }

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