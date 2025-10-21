<?php
// Mesure du temps d'exécution
$__srvinlog_start = microtime(true);
    
     date_default_timezone_set('Europe/Paris');

    $heure = (string)date("H:i");
    $logheure = isset($_COOKIE['logheure']) ? $_COOKIE['logheure'] : '0';

    if ($logheure == $heure) 
    {
        echo "Identique";
        exit(0);
    }

    setcookie('logheure', $heure, time() + 365 * 24 * 3600, '/');

include "base.php";
include "BaseLog.php";


// Enregistrer les valeurs des unités dans la table de log de chaque unites 
$Sql="SELECT * FROM ValUnites";
$Res=mssql($Sql);
if ($Res) {
    while ($Row=sqlnext($Res)) {
        if ($Row === null) break;

        $Id = (int)$Row['Id'];

        for ($i = 1; $i <= 7; $i++)
            LogIn($Id,$i, $Row[$i]);
    }
}   

// Fonction pour enregistrer les valeurs Lennox depuis un fichier JSON
function LogLennox($Id,$Path)
{
$jsonPath = __DIR__ . '/'.$Path;
if (is_file($jsonPath)) {
    $json = @file_get_contents($jsonPath);
    if ($json !== false) {
        $lennoxData = json_decode($json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('SrvInLog: invalid JSON in Lennox1.json: ' . json_last_error_msg());
        }
    } else {
        error_log('SrvInLog: cannot read Lennox1.json');
    }
} else {
    error_log('SrvInLog: Lennox1.json not found');
}

// Enregistrer les données dans la table de log des 200 points Modbus 
if (isset($lennoxData) && is_array($lennoxData)) 
    {
    foreach ($lennoxData as $key => $value) 
        {
        $index = (int)$key;
        LogIn($Id, $index, $value);
        }
    }
}

// Enregistrer les valeurs Lennox depuis le fichier JSON de chaque groupes 
    LogLennox(501,'Lennox1.json');
    LogLennox(502,'Lennox2.json');
    LogLennox(503,'Lennox2.json');


// Afficher le temps d'exécution total
$__srvinlog_end = microtime(true);
$__srvinlog_elapsed = $__srvinlog_end - $__srvinlog_start;
echo "\nTemps d'exécution SrvInLog: " . number_format($__srvinlog_elapsed, 3, ',', '') . " secondes\n";
?>