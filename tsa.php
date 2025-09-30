<?php
declare(strict_types=1);

// URL de l'API et clé lues depuis les variables d'environnement pour ne pas stocker de secret dans le code
$url = getenv('TSA_API_URL') ?: null;
$apiKey = getenv('TSA_API_KEY') ?: null;

// Fallback : charger configapi.php si présent (fichier local non versionné)
if (($url === null || $apiKey === null) && file_exists(__DIR__ . DIRECTORY_SEPARATOR . 'configapi.php')) {
    $cfg = include __DIR__ . DIRECTORY_SEPARATOR . 'configapi.php';
    if (is_array($cfg)) {
        if ($url === null && !empty($cfg['TSA_API_URL'])) $url = $cfg['TSA_API_URL'];
        if ($apiKey === null && !empty($cfg['TSA_API_KEY'])) $apiKey = $cfg['TSA_API_KEY'];
    }
}

// Fallback final pour l'URL
if ($url === null) {
    $url = 'https://timaim.kadelta.fr/send_api.php';
}

$payload = [
    // Le compte SMTP/IMAP est fixe côté serveur (send_api.php). Ici on envoie seulement le contenu du message.
    'from' => 'kadelta@timaim.kadelta.fr',
    'from_name' => 'Ka Delta',
    'to' => 'df38+ka@live.fr',
    'subject' => 'Test API envoi',
    'body' => '<div style="font-family:Arial,sans-serif;line-height:1.5"><h2 style="color:#1e88e5;margin:0 0 12px">Message de test</h2><p style="color:#333;margin:0 0 16px">Ceci est un message de test en <span style="color:#d81b60;font-weight:700">couleur</span> avec une jolie image.</p><img src="https://picsum.photos/seed/ka-delta/800/400" alt="Image aléatoire" style="display:block;width:100%;max-width:720px;border-radius:8px;border:1px solid #e5e5e5" /></div>',
    'is_html' => true,
];

$json = json_encode($payload);

// Préparer les en-têtes
$headers = [
    'Content-Type: application/json',
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
