<?php
include "Base.php";
include "modbus.php";

$Heureactuelle = date("H:i");
$Heureactuelle = "16:42";
$jours_semaine = [0 => 'Dim',1 => 'Lun',2 => 'Mar',3 => 'Mer',4 => 'Jeu',5 => 'Ven',6 => 'Sam'];
$Joursactuelle = $jours_semaine[date("w")];




//chargement des entree modbus
$result = mssql("SELECT Id,Addresse,Port FROM [dbo].[DefModBus]");
$defModBusData = [];
while ($row = sqlnext($result)) $defModBusData[$row["Id"]] = $row;


//changement de la table des horaires
$phoraires = [];
    $result = mssql("SELECT * FROM PHoraires");
        while ($result && ($row = sqlnext($result))) 
            $phoraires[] = $row;

echo "<pre>";
//print_r($phoraires);

echo "<br>Heure actuelle: " . $Heureactuelle;
echo "<br>Jour actuel: " . $Joursactuelle;


foreach ($phoraires as $horaire) 
{
    if ($horaire[$Joursactuelle]=== 1)
    {
        $Id_ProgNom= $horaire["Id_ProgNom"];
        echo $horaire["Id"]."-- Jours validee";
        echo "<br>Heure: " . $horaire["Heure"];
        if (strpos($horaire["Heure"], $Heureactuelle) !== false) 
        {
            echo "  ---  Horaire validee <br>";

            $TableUnites=mssql("SELECT * FROM [DefUnites] WHERE Prog = $Id_ProgNom");
            while ($row = sqlnext($TableUnites)) 
            {
                    $ip = $defModBusData[$row["ModbusId"]]["Addresse"];
                    $port = $defModBusData[$row["ModbusId"]]["Port"];
                    $unitId = $row["Device"]; 
                    $Id=$row["Id"];
                    echo "Unite: " . $row["Id"] . "<br>";
                                echo $ip . " - " . $port . " - " . $unitId . "<br>";

                    try {
                            $socket = connectModbusTcp($ip, $port);

                            if ($socket) //envoie
                            {
                                $type= $row["Type_OnOff"];$startAddress= $row["OnOff"];$valeur = $horaire["OnOff"];
                                ModbusWrite($socket, $unitId, $startAddress, $type, $valeur);

                                $type= $row["Type_Mode"];$startAddress= $row["Mode"];$valeur = $horaire["Mode"];
                                ModbusWrite($socket, $unitId, $startAddress, $type, $valeur);

                                $type= $row["Type_Fan"];$startAddress= $row["Fan"];$valeur = $horaire["Fan"];
                                ModbusWrite($socket, $unitId, $startAddress, $type, $valeur);

                                $type= $row["Type_SetRoom"];$startAddress= $row["SetRoom"];$valeur = $horaire["SetTemp"];
                                ModbusWrite($socket, $unitId, $startAddress, $type, $valeur);

                                CloseModbusTcp($socket);
                            }


                        }
                        catch (Exception $e) {
                            echo "Erreur de connexion: " . $e->getMessage();
                        }
            }

        }
    }
}





?>