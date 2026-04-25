<?php
include 'base.php';
include 'modbus.php';

echo "\n\rLecture Modbus LENNOX\r\n";

$timeoutFile = __DIR__ . '/LennoxTo.json';

function lireTimeoutLennox($timeoutFile)
{
    if (!file_exists($timeoutFile)) {
        return array();
    }

    $contenu = file_get_contents($timeoutFile);
    $donnees = json_decode($contenu, true);

    return is_array($donnees) ? $donnees : array();
}

function ecrireTimeoutLennox($timeoutFile, array $donnees)
{
    file_put_contents($timeoutFile, json_encode($donnees, JSON_PRETTY_PRINT));
}


// Fonction pour lire les registres Modbus 0 à 200
function lireRegistresModbus($socket, $unitId = 1) 
{
    global $timeoutFile;

    $Registre10a100 = readModbusRegisters($socket, $unitId, 0,50);
    
    if ($Registre10a100 === false) 
    {
        echo("Erreur: Impossible de lire les registres Modbus $unitId \r\n");
        return array_fill(0, 200, 10080); // Retourner un tableau vide en cas d'erreur
    }

    if ($Registre10a100)
    {
        $Registre1101a200 = readModbusRegisters($socket, $unitId, 50, 50);
            if ($Registre1101a200 === false) {
                                            echo("Erreur: Impossible de lire les registres Modbus 2é $unitId \r\n");
                                            return array_fill(0, 200, 10080); // Retourner un tableau vide en cas d'erreur
                                            }
    }

    if ($Registre10a100)
    {
        $Reg3 = readModbusRegisters($socket, $unitId, 100, 50);
            if ($Reg3 === false) {
                                            echo("Erreur: Impossible de lire les registres Modbus 3é $unitId \r\n");
                                            return array_fill(0, 200, 10080); // Retourner un tableau vide en cas d'erreur
                                            }
    }

    if ($Registre10a100)
    {
        $Reg4 = readModbusRegisters($socket, $unitId, 150, 50);
            if ($Reg4 === false) {
                                            echo("Erreur: Impossible de lire les registres Modbus 4é $unitId \r\n");
                                            return array_fill(0, 200, 10080); // Retourner un tableau vide en cas d'erreur
                                            }
    }

    $timeouts = lireTimeoutLennox($timeoutFile);
    $timeouts['TO' . $unitId] = 15;
    ecrireTimeoutLennox($timeoutFile, $timeouts);

    return array_merge($Registre10a100, $Registre1101a200,$Reg3,$Reg4);
}




date_default_timezone_set('Europe/Paris');
$config = json_decode(file_get_contents(__DIR__ . '/configurationlennox.json'), true);//lire la configuration Pour Lennox


//recuperation de la config modbus pour lennox dans la table DefModbus
$IdModbus = (int)$config['modbus'];
$ConfigModbus = mssql("SELECT Addresse, Port FROM DefModBus WHERE id = $IdModbus");

$RowModbus = null;
if ($ConfigModbus) 
    {
    $RowModbus = sqlnext($ConfigModbus);
    if ($RowModbus) 
        {
        $Ip = $RowModbus['Addresse'];
        $Port = $RowModbus['Port'];
        } 
    else 
        {
            $Ip = null;
            $Port = null;
        }
    } 
    else 
    {
            $Ip = null;
            $Port = null;
    }

if ($Ip === null || $Port === null) {
    echo('Erreur: Adresse IP ou Port Modbus non défini.');
}


//Ouverture de la connexion modbus
$socket = connectModbusTcp($Ip, $Port);
if (!$socket) {
    echo('Erreur: Impossible de se connecter au serveur Modbus.');
}   

//Lecture des registres modbus
$Registre2 = lireRegistresModbus($socket, $config['device2']);
echo "  --- Lennox 2 terminer \r\n";
sleep(2);
$Registre3 = lireRegistresModbus($socket, $config['device3']);
echo "  --- Lennox 3 terminer \r\n";
sleep(2);
$Registre1 = lireRegistresModbus($socket, $config['device1']);
echo "  --- Lennox 1 terminer \r\n";
sleep(2);
 
//Fermeture de la connexion modbus
if ($socket) CloseModbusTcp($socket);    

// Sauvegarde des fichiers JSON pour chaque unité
if ($Registre1[36]!="10080")
    file_put_contents(__DIR__ . '/Lennox1.json', json_encode($Registre1, JSON_PRETTY_PRINT));
if ($Registre2[36]!="10080")
    file_put_contents(__DIR__ . '/Lennox2.json', json_encode($Registre2, JSON_PRETTY_PRINT));
if ($Registre3[36]!="10080")
    file_put_contents(__DIR__ . '/Lennox3.json', json_encode($Registre3, JSON_PRETTY_PRINT));

// Gestion des défauts Lennox
        gererDefautLennox($Registre1, 501,1);
        gererDefautLennox($Registre2, 502,2);
        gererDefautLennox($Registre3, 503,3);


// Fonction pour exécuter une requête SQL et retourner le résultat
function gererDefautLennox($Registre, $IdD,$device)
{
    $defautcode = $Registre[36];
    
    global $timeoutFile;

    $timeoutName = 'TO' . $device;
    $timeouts = lireTimeoutLennox($timeoutFile);
    $timeoutCounter = isset($timeouts[$timeoutName]) ? (int)$timeouts[$timeoutName] : 10;

    if ($defautcode == 10080) 
        {
        $timeoutCounter = max(0, $timeoutCounter - 1);
        $timeouts[$timeoutName] = $timeoutCounter;
        ecrireTimeoutLennox($timeoutFile, $timeouts);

        if ($timeoutCounter > 1) 
        {
            echo "\r\nErreur Lennox timeout $IdD : $defautcode Timeout : $timeoutCounter\r\n";
            return;
        }
        }

        file_put_contents(__DIR__ . '/Lennox'.$device.'.json', json_encode($Registre, JSON_PRETTY_PRINT));
    
      if ($defautcode) echo "\r\nErreur Lennox timeout $IdD : $defautcode Timeout : $timeoutCounter\r\n";

    $date = date("Y-m-d");
    $heure = date("H:i");

    // Gestion des defaut, Lennox, envoie dans la base de donnes SQL defauts
    if ($defautcode != 0) {
        // Vérifier si le défaut existe déjà
        $sqlCheck = "SELECT COUNT(1) AS cnt FROM defauts WHERE [Unite] like '{$IdD}' AND [Code] like '{$defautcode}' AND [Etat]=1";
        $stmtCheck = mssql($sqlCheck);
        $rowCheck = $stmtCheck ? sqlnext($stmtCheck) : false;
        if ($rowCheck && $rowCheck['cnt'] == 0) {
            $sqlIns = "INSERT INTO defauts ([Unite], [Code], [Etat], [Date], [Heure]) VALUES ('{$IdD}', '{$defautcode}', 1, '{$date}', '{$heure}')";
            mssql($sqlIns);
        }
    }

    // Ajout : si code défaut = 0, vérifier si IdD existe et passer Etat à 2
    if ($defautcode == 0) {
        $sqlCheck0 = "SELECT COUNT(1) AS cnt FROM defauts WHERE [Unite] like '{$IdD}' AND [Etat] like 1";
        $stmtCheck0 = mssql($sqlCheck0);
        $rowCheck0 = $stmtCheck0 ? sqlnext($stmtCheck0) : false;
        if ($rowCheck0 && $rowCheck0['cnt'] > 0) {
            $sqlUpdate = "UPDATE defauts SET [Etat]=2 WHERE [Unite] like '{$IdD}' AND [Etat] like 1";
            mssql($sqlUpdate);
        }
    }
}



?>