<?php

// URL de l'API d'envoi (peut être surchargée par configapi.php)
$apiUrl = getenv('ALIZE_API_URL') ?: 'https://tools.alize-sas.fr/sms/sms.php'; //api d'envoie de sms

// Charger configapi.php si présent pour obtenir le token (fichier non versionné)
if (file_exists(__DIR__ . DIRECTORY_SEPARATOR . 'configapi.php')) {
    $cfg = include __DIR__ . DIRECTORY_SEPARATOR . 'configapi.php';
    if (is_array($cfg)) {
        if (!empty($cfg['ALIZE_TOKEN'])) {
            putenv('ALIZE_TOKEN=' . $cfg['ALIZE_TOKEN']);
        }
        if (!empty($cfg['ALIZE_API_URL'])) {
            $apiUrl = $cfg['ALIZE_API_URL'];
        }
    }
}


// Récupération des paramètres GET
$numero = "0743338940";
$message = "
---------------------
** Externalisation **
---------------------

Utilisation de l'api Alize SMS, avec un site externe , coucou
";

$data = [
    'number' => $numero,
    'message' => $message,
    'token' => getenv('ALIZE_TOKEN') ?: "Hrtu@M&8ttruibistk58T4'5Ghçr"
];

$options = [
    'http' => [
        'method' => 'POST',
        'header' => "Content-Type: application/json\r\n",
        'content' => json_encode($data)
    ]
];

try {
    $response = @file_get_contents($apiUrl, false, stream_context_create($options)); //Appel de l'api

    if ($response === false) {
        echo "\nErreur : L'appel à l'API a échoué.";
    } else {
        $result = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            echo "\nErreur : Réponse API non valide.";
        } elseif (isset($result['success']) && $result['success'] === true) {
            echo "\nSuccès : SMS envoyé avec succès.";
        } elseif (isset($result['error'])) {
            echo "\nErreur lors de l'envoi du SMS : " . htmlspecialchars($result['error']);
        } else {
            echo "\nRéponse inattendue de l'API.";
        }
    }
} catch (Throwable $e) {
    echo "\nException attrapée : " . htmlspecialchars($e->getMessage());
}

echo $response;


?>