<?php
include 'base.php';
include 'modbus.php';

echo "\n\rLecture Modbus LENNOX \n\r";

// Fonction pour lire les registres Modbus 0 à 200
function lireRegistresModbus($socket, $unitId = 1) 
{
    $Registre10a100 = readModbusRegisters($socket, $unitId, 0, 100);
    if ($Registre10a100 === false) 
    {
        echo("Erreur: Impossible de lire les registres Modbus $unitId \r\n");
        return array_fill(0, 200, 10080); // Retourner un tableau vide en cas d'erreur
    }

    if ($Registre10a100)
    {
        $Registre1101a200 = readModbusRegisters($socket, $unitId, 100, 100);
            if ($Registre1101a200 === false) {
                                            echo("Erreur: Impossible de lire les registres Modbus $unitId \r\n");
                                            return array_fill(0, 200, 10080); // Retourner un tableau vide en cas d'erreur
                                            }

    return array_merge($Registre10a100, $Registre1101a200);
}
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

$Registre1 = lireRegistresModbus($socket, $config['device1']);
echo "\r\n  --- Lennox 1 terminer \r\n";
$Registre2 = lireRegistresModbus($socket, $config['device2']);
echo "  --- Lennox 2 terminer \r\n";
$Registre3 = lireRegistresModbus($socket, $config['device3']);
echo "  --- Lennox 3 terminer \r\n";

//Fermeture de la connexion modbus
if ($socket) CloseModbusTcp($socket);    



// Valeur en provision des donnes pour simule la lectures MODBUS des unites interieurs
if ($config['modbus']==26)
{
$Registre1[15]= date("H");
$Registre1[16] = date("i");
$Registre1[17] = date("d");
$Registre1[18] = date("m");
$Registre1[19] = date("Y");
$Registre1[36] = 0;//code defaut lennox
$Registre1[2] = 215;
$Registre2[2] = 228;
$Registre1[3] = 195;
$Registre1[37] = 232;
$Registre1[38] = 211;
$Registre1[39] = 128;
$Registre1[40] = 213;
$Registre1[135] = 1;
$Registre1[137] = 1;
$Registre1[140] = 0;
$Registre1[45] = 79;
$Registre1[139] = 0;
$Registre1[142] = 1;
$Registre1[47]=688;
}


// Sauvegarde des fichiers JSON pour chaque unité
file_put_contents(__DIR__ . '/Lennox1.json', json_encode($Registre1, JSON_PRETTY_PRINT));
file_put_contents(__DIR__ . '/Lennox2.json', json_encode($Registre2, JSON_PRETTY_PRINT));
file_put_contents(__DIR__ . '/Lennox3.json', json_encode($Registre3, JSON_PRETTY_PRINT));

// Gestion des défauts Lennox
gererDefautLennox($Registre1, 501);
gererDefautLennox($Registre2, 502);
gererDefautLennox($Registre3, 503);


// Fonction pour exécuter une requête SQL et retourner le résultat
function gererDefautLennox($Registre, $IdD)
{
    $defautcode = $Registre[36];
    $date = date("Y-m-d");
    $heure = date("H:i:s");

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