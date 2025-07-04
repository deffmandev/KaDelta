<?php
// proxy.php - PHP proxy avec gestion cookies, redirections et sessions

$target = 'https://192.168.1.110';

// Reconstituer l'URL cible
$proxy_prefix = '/';
$path = $_SERVER['REQUEST_URI'];
if (strpos($path, $proxy_prefix) === 0) {
    $path = substr($path, strlen($proxy_prefix));
    if ($path === '' || $path[0] !== '/') $path = '/' . $path;
}
$query = $_SERVER['QUERY_STRING'];
$url = rtrim($target, '/') . $path . ($query ? "?$query" : '');

// Fichier temporaire pour stocker les cookies
$tmp_cookie = @tempnam(sys_get_temp_dir(), 'proxy_cookie_');

// Initialiser cURL
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_ENCODING, ''); // Désactive la compression, décompresse automatiquement

// Méthode HTTP et body
$method = $_SERVER['REQUEST_METHOD'];
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
if (in_array($method, ['POST', 'PUT', 'PATCH'])) {
    $body = file_get_contents('php://input');
    curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
}

// Forward headers (sauf Host)
$headers = [];
foreach (getallheaders() as $key => $value) {
    if (strtolower($key) === 'host') continue;
    if (strtolower($key) === 'cookie') continue; // On gère les cookies à part
    $headers[] = "$key: $value";
}
$headers[] = 'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36';
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

// Forward cookies du client vers la cible
if (isset($_SERVER['HTTP_COOKIE'])) {
    curl_setopt($ch, CURLOPT_COOKIE, $_SERVER['HTTP_COOKIE']);
}

// Gérer les cookies de la cible
curl_setopt($ch, CURLOPT_COOKIEJAR, $tmp_cookie);
curl_setopt($ch, CURLOPT_COOKIEFILE, $tmp_cookie);

// SSL options
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

// Suivre les redirections
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_MAXREDIRS, 5);

// Retourner headers + body
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, true);

// --- Début cache proxy ---
$cache_dir = sys_get_temp_dir() . '/proxy_cache';
if (!is_dir($cache_dir)) @mkdir($cache_dir, 0777, true);
$cache_key = md5($url);
$cache_file = "$cache_dir/$cache_key";
$cache_ttl = 6000; // Durée de vie en secondes (10 min, adapte si besoin)

// Extensions à mettre en cache
$cache_exts = ['js','png','jpg','jpeg','gif','svg','webp','ico','bmp','shtml','htm', 'txt','shtml'];
$parsed_url = parse_url($url);
$cache_ext = '';
if (isset($parsed_url['path'])) {
    $ext = strtolower(pathinfo($parsed_url['path'], PATHINFO_EXTENSION));
    if (in_array($ext, $cache_exts)) {
        $cache_ext = $ext;
    }
}
$use_cache = ($cache_ext !== '' && $method === 'GET'); // On ne met en cache que pour GET

// On ne lit le cache que pour les fichiers images/js/css (GET uniquement)
if ($use_cache && file_exists($cache_file) && (filemtime($cache_file) + $cache_ttl > time())) {
    // Sert le cache
    readfile($cache_file);
    exit;
}
// --- Fin cache lecture ---

// Exécuter
$response = curl_exec($ch);
$header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$resp_headers = substr($response, 0, $header_size);
$resp_body = substr($response, $header_size);

// Forward response headers (sauf certains)
$has_content_type = false;
foreach (explode("\r\n", $resp_headers) as $header) {
    if (stripos($header, 'Transfer-Encoding:') === 0) continue;
    if (stripos($header, 'Content-Length:') === 0) continue;
    if (stripos($header, 'Connection:') === 0) continue;
    if (stripos($header, 'Content-Encoding:') === 0) continue; // Ne pas forwarder l'encodage compressé
    if (stripos($header, 'Content-Type:') === 0) {
        $has_content_type = true;
        // Forcer charset UTF-8 pour le texte
        if (stripos($header, 'text/html') !== false || stripos($header, 'application/javascript') !== false || stripos($header, 'text/css') !== false) {
            header(preg_replace('/;\s*charset=.+/i', '', $header) . '; charset=UTF-8', false);
            continue;
        }
        // Forcer charset UTF-8 pour JSON
        if (stripos($header, 'application/json') !== false) {
            header('Content-Type: application/json; charset=UTF-8', false);
            continue;
        }
    }
    if (stripos($header, 'Set-Cookie:') === 0) {
        header($header, false);
        continue;
    }
    if ($header) header($header, false);
}
// Si pas de Content-Type, on le force pour HTML
if (!$has_content_type && stripos($resp_headers, 'text/html') !== false) {
    header('Content-Type: text/html; charset=UTF-8');
}
if (!$has_content_type && $cache_ext === 'json') {
    header('Content-Type: application/json; charset=UTF-8');
}
if (!$has_content_type && $cache_ext === 'css') {
    header('Content-Type: text/css; charset=UTF-8');
}

// Output body
// Remplacement de l'IP cible par l'IP publique dans le body (pour toutes les réponses)
$resp_body = str_replace('192.168.1.110', '192.168.1.152', $resp_body);
// --- Début vérification JSON ---
if ($cache_ext === 'json' || (isset($has_content_type) && stripos($resp_headers, 'application/json') !== false)) {
    $json_test = json_decode($resp_body);
    if (json_last_error() !== JSON_ERROR_NONE) {
        header('HTTP/1.1 502 Bad Gateway');
        header('X-Proxy-Error: Invalid JSON');
        echo '{"error":"Invalid JSON from backend"}';
        curl_close($ch);
        @unlink($tmp_cookie);
        exit;
    }
}
// --- Fin vérification JSON ---
// --- Début cache écriture ---
if ($use_cache && $method === 'GET') {
    file_put_contents($cache_file, $resp_body);
}
// --- Fin cache écriture ---
echo $resp_body;

curl_close($ch);
@unlink($tmp_cookie);
?>