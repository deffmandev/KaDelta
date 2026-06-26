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

$dateLog = (string)date('d-m-Y');
$heureLog = (string)date('H:i');


// Enregistrer les valeurs des unités dans la table de log de chaque unites 
$Sql="SELECT * FROM ValUnites";
$Res=mssql($Sql);
$numbres=0;
if ($Res) {
    while ($Row=sqlnext($Res)) {
        if ($Row === null) break;

        $Id = (int)$Row['Id'];
        $numbres++;

        $pointValues = [];
        for ($i = 1; $i <= 7; $i++) {
            if (array_key_exists($i, $Row)) {
                $pointValues[$i] = $Row[$i];
            }
        }

        if (!log_upsert_wide_points($dateLog, $heureLog, $Id, $pointValues)) {
            log_report_sql_error('SrvInLog.ValUnites.upsert_points');
        }
    }
}   

echo "Nombre d'unités enregistrées : $numbres\n";
echo "Date : $dateLog, Heure : $heureLog\n";

// Fonction pour enregistrer les valeurs Lennox depuis un fichier JSON
function LogLennox($Id, $Path, $dateLog, $heureLog)
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

// Enregistrer toutes les donnees de l'unite en une seule ecriture SQL.
if (isset($lennoxData) && is_array($lennoxData)) {
    $pointValues = [];
    foreach ($lennoxData as $key => $value) {
        if (!is_numeric($key)) {
            continue;
        }

        $index = (int)$key;
        if ($index < 0 || $index > 500) {
            continue;
        }

        $pointValues[$index] = $value;
    }

    if (!log_upsert_wide_points($dateLog, $heureLog, $Id, $pointValues)) {
        log_report_sql_error('SrvInLog.LogLennox.upsert_points');
    }
}
}

// Enregistrer les valeurs Lennox depuis le fichier JSON de chaque groupes 
    LogLennox(501,'Lennox1.json', $dateLog, $heureLog);
    LogLennox(502,'Lennox2.json', $dateLog, $heureLog);
    LogLennox(503,'Lennox3.json', $dateLog, $heureLog);


// Afficher le temps d'exécution total
$__srvinlog_end = microtime(true);
$__srvinlog_elapsed = $__srvinlog_end - $__srvinlog_start;
echo "\n\rTemps d'exécution SrvInLog: " . number_format($__srvinlog_elapsed, 3, ',', '') . " secondes\n\r";
?>