<?php
include "base.php";

$Heureactuelle = date("H:i");
$jours_semaine = [0 => 'Dim',1 => 'Lun',2 => 'Mar',3 => 'Mer',4 => 'Jeu',5 => 'Ven',6 => 'Sam'];
$Joursactuelle = $jours_semaine[date("w")];

$TableSend = [];
    $result = mssql("SELECT * FROM DefSendDefaut WHERE Actife = 1 AND $Joursactuelle = 1");
        while ($result && ($row = sqlnext($result))) 
            $TableSend[] = $row;

$Defauts = [];
$resultDefauts = mssql("SELECT * FROM Defauts WHERE Etat = 1");
        while ($resultDefauts && ($rowDefaut = sqlnext($resultDefauts))) 
            $Defauts[] = $rowDefaut;
            
$UnitesName = [];
$resultUnites = mssql("SELECT Id,name FROM DefUnites");
        while ($resultUnites && ($rowUnite = sqlnext($resultUnites))) 
             $UnitesName[$rowUnite['Id']] = $rowUnite['name'];

// Fonction utilitaire : retourne true si $time est entre $start et $end (format H:i), gère passage minuit
function isTimeBetween(string $time, string $start, string $end): bool 
{
    $t = strtotime($time);
    $s = strtotime($start);
    $e = strtotime($end);
    if ($s === false || $e === false || $t === false) return false;
    // Si intervalle ne passe pas minuit
    if ($s <= $e) {
        return ($t >= $s && $t <= $e);
    }
    // Intervalle passe minuit, ex: 23:00 - 02:00
    return ($t >= $s || $t <= $e);
}

$toSend = [];
foreach ($TableSend as $row) 
    {
    // Supposons que la table a des colonnes 'HDebut' et 'HFin' au format 'HH:MM'
    $hDebut = isset($row['HDebut']) ? $row['HDebut'] : null;
    $hFin = isset($row['Hfin']) ? $row['Hfin'] : null;
    if (!$hDebut || !$hFin) {echo "Données incomplètes"; continue;} // données incomplètes

    if (isTimeBetween($Heureactuelle, $hDebut, $hFin)) 
    {
        echo "Horaire validee pour envoie: " . $row['Id'] . " entre " . $hDebut . " et " . $hFin . "\n\r";
        envoieAlarme($row);
    }
}

function envoieAlarme($row) 
{
    global $Defauts;
    global $UnitesName;
    $MessageSMS = "";
    $Nbdef=0;
    $Id="[".$row["Id"]."]";

foreach ($Defauts as $defaut) 
{
    if (strpos($defaut['Send'], $Id) === false) 
        {
        //indique que le defaut a ete envoye
        mssql("UPDATE Defauts SET Send = CONCAT(Send, '$Id') WHERE Id = " . intval($defaut['Id']));

        // Vérifie si Contact est un numéro de téléphone ou une adresse mail
        $contact = $row["Contact"];
        if (filter_var($contact, FILTER_VALIDATE_EMAIL)) 
        {
            echo "Type de contact : adresse mail\n\r";
        } 
        elseif (preg_match('/^\+?\d{7,15}$/', $contact)) 
        {
            $MessageSMS.=" Defaut unitee:".$defaut["Unite"]."-".$UnitesName[$defaut["Unite"]]."  Code:".$defaut["Code"]."\n\r";
            $Nbdef++;
        } 
        else 
        {
            echo "Type de contact : inconnu\n\r";
        }


    }
}
        if ($MessageSMS!="")
            SendSms($contact, "METRONIC EN DEFAUT\n\r".$Nbdef." défaut(s) détecté(s) :\n\r\n\r".$MessageSMS);

}


function SendSms($numero, $message)
{
    $apiUrl = "https://tools.alize-sas.fr/sms/sms.php"; //api d'envoie de sms

    $data = [
        'number' => $numero,
        'message' => $message,
        'token' => "Hrtu@M&8ttruibistk58T4'5Ghçr"
    ];


    // Fallback non-bloquant en utilisant stream context mais avec timeout
    $options = [
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/json\r\n",
            'content' => json_encode($data),
            'timeout' => 10
        ]
    ];

    $ctx = stream_context_create($options);
    $result = @file_get_contents($apiUrl, false, $ctx);
    if ($result === false) {
        echo "Erreur\r\n";
        return false;
    }

    echo "SMS envoyé \r\n";
    return true;
}   
?>