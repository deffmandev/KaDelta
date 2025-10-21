<?php
//declare(strict_types=1);
include "base.php";

// Charger token Alize depuis l'environnement ou configapi.php (fichier non versionné)
$ALIZE_TOKEN = getenv('ALIZE_TOKEN') ?: null;
if (($ALIZE_TOKEN === null) && file_exists(__DIR__ . DIRECTORY_SEPARATOR . '.configapi.php')) {
    $__cfg = include __DIR__ . DIRECTORY_SEPARATOR . '.configapi.php';
    if (is_array($__cfg) && !empty($__cfg['ALIZE_TOKEN'])) {
        $ALIZE_TOKEN = $__cfg['ALIZE_TOKEN'];
    }
}

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
            $NbrDefautActuelle = count($Defauts);

            
$UnitesName = [];
$resultUnites = mssql("SELECT Id,name FROM DefUnites");
        while ($resultUnites && ($rowUnite = sqlnext($resultUnites))) 
             $UnitesName[$rowUnite['Id']] = $rowUnite['name'];

$UnitesName[501] = "RoofTop 1";
$UnitesName[502] = "RoofTop 2";
$UnitesName[503] = "RoofTop 3";

           
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
    global $NbrDefautActuelle;
    $MessageSMS = "";
    $MessageEmail = "";
    $Nbdef=0;
    $Id="[".$row["Id"]."]";
    $contact = $row["Contact"];

foreach ($Defauts as $defaut) 
{

    if (strpos($defaut['Send'], $Id) === false) 
        {
        //indique que le defaut a ete envoye
        mssql("UPDATE Defauts SET Send = CONCAT(Send, '$Id') WHERE Id = " . intval($defaut['Id']));

        // Vérifie si Contact est un numéro de téléphone ou une adresse mail
        if (filter_var($contact, FILTER_VALIDATE_EMAIL)) 
        {
            $MessageEmail.=" Defaut unitee:".$defaut["Unite"]."-".$UnitesName[$defaut["Unite"]]."  Code:".$defaut["Code"]."<br>";
            $Nbdef++;

        } 
        elseif (preg_match('/^\+?\d{7,15}$/', $contact)) 
        {
            $MessageSMS.=" Defaut unitee : ".$defaut["Unite"]."-".$UnitesName[$defaut["Unite"]]."  Code:".$defaut["Code"]."\n\r";
            $Nbdef++;
        } 
        else 
        {
            echo "Type de contact : inconnu\n\r";
        }


    }
}
        if ($MessageSMS!="")
            SendSms($contact, "COVIDIEN-MEDTRONIC\n\r".$Nbdef." défaut(s) détecté(s) sur ".$NbrDefautActuelle." actifs : \n\r\n\r".$MessageSMS);

        if ($MessageEmail!="")
            SendMail("<br>".$Nbdef." nouveau défaut(s) détecté(s) sur ".$NbrDefautActuelle." actifs<br><br>".$MessageEmail, $contact);

}

function SendMail($message,$destinataire)
{

// URL de l'API et clé lues depuis les variables d'environnement pour ne pas stocker de secret dans le code
$url = getenv('TSA_API_URL') ?: null;
$apiKey = getenv('TSA_API_KEY') ?: null;

// Fallback : charger configapi.php si présent (fichier local non versionné)
if (($url === null || $apiKey === null) && file_exists(__DIR__ . DIRECTORY_SEPARATOR . '.configapi.php')) {
    $cfg = include __DIR__ . DIRECTORY_SEPARATOR . '.configapi.php';
    if (is_array($cfg)) {
        if ($url === null && !empty($cfg['TSA_API_URL'])) $url = $cfg['TSA_API_URL'];
        if ($apiKey === null && !empty($cfg['TSA_API_KEY'])) $apiKey = $cfg['TSA_API_KEY'];
    }
}

// Fallback final pour l'URL
if ($url === null) {
    $url = 'https://timaim.kadelta.fr/send_api.php';
}


$message = '<style>
body {font-family: Arial, sans-serif;background-color: #f4f4f4;margin: auto;padding: 20px;}
.cadre{background: #b4b5c9;padding: 11px;border-radius: 20px;margin: auto;border: 1px solid black;width: 500px;box-shadow: 5px 3px 10px rgb(0 0 0);}
.Titres {font-family: math;font-size: 43px;color: #327;text-align: center;margin-bottom: 20px;background: linear-gradient(332deg, #918c8c, #4fa9841c);width: 500px;font-style: italic;text-align: center;border-radius: 12px;}
</style>
<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Defaut</title></head>
<body><div class="cadre"><div class="Titres">COVIDIEN-MEDTRONIC</div>
<p style="text-align:center;font-weight:600;margin-top:16px;">Le message a été envoyé automatiquement car des défauts ont été détectés sur le site. Merci de ne pas répondre à ce courriel.</p>
'.$message.'<br></div></body></html>';

    
$payload = [
    // Le compte SMTP/IMAP est fixe côté serveur (send_api.php). Ici on envoie seulement le contenu du message.
    'from' => 'kadelta@timaim.kadelta.fr',
    'from_name' => 'Ka Delta',
    'to' => $destinataire,
    'subject' => 'Test API envoi',
    'body' => $message,
    'is_html' => true,
];

$json = json_encode($payload, JSON_UNESCAPED_UNICODE);

// Vérifier l'encodage JSON
if ($json === false) {
    error_log('[SrvSend::SendMail] json_encode error: ' . json_last_error_msg());
    return;
}

// Préparer les en-têtes (forcer charset UTF-8)
$headers = [
    'Content-Type: application/json; charset=utf-8',
];
if ($apiKey) {
    $headers[] = 'Authorization: Bearer ' . $apiKey;
} else {
    // Alerte légère si la clé n'est pas définie — ne pas exposer la clé dans les logs
    error_log('[tsa.php] TSA_API_KEY non défini dans l\'environnement ou configapi.php; appel API sans Authorization');
}

// Construire le contexte HTTP
$options = [
    'http' => [
        'method' => 'POST',
        'header' => implode("\r\n", $headers) . "\r\n",
        'content' => $json,
        'ignore_errors' => true,
        'timeout' => 10,
    ],
];

// Pas de configuration SSL spécifique ici — on utilise le contexte HTTP tel quel
$context = stream_context_create($options);

$result = @file_get_contents($url, false, $context);
if ($result === false) {
    $err = error_get_last();
    echo "Erreur HTTP: " . ($err['message'] ?? 'unknown') . PHP_EOL;
    if (!empty($http_response_header)) {
        echo "Headers reçus:\n" . implode("\n", $http_response_header) . PHP_EOL;
    }
} else {
    echo "Réponse API :\n" . $result . PHP_EOL;
}
}


function SendSms($numero, $message)
{
    $apiUrl = "https://tools.alize-sas.fr/sms/sms.php"; //api d'envoie de sms

    // Utiliser le token chargé depuis l'environnement ou configapi.php
    global $ALIZE_TOKEN;
    $token = $ALIZE_TOKEN ?: getenv('ALIZE_TOKEN') ?: "no more"; // fallback pour compat

    $data = [
        'number' => $numero,
        'message' => $message,
        'token' => $token
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