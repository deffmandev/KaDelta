<?php
error_reporting(E_ALL & ~E_NOTICE);
// proxy.php - PHP proxy avec gestion cookies, redirections et sessions

$target = 'https://192.168.1.110';

// Reconstituer l'URL cible
$proxy_prefix = '/proxy.php';
$path = $_SERVER['REQUEST_URI'];
if (strpos($path, $proxy_prefix) === 0) {
    $path = substr($path, strlen($proxy_prefix));
    if ($path === '' || $path[0] !== '/') $path = '/' . $path;
} else {
    $path = '/';
}
$query = $_SERVER['QUERY_STRING'];
$url = rtrim($target, '/') . $path . ($query ? "?$query" : '');

// Fichier temporaire pour stocker les cookies
$tmp_cookie = tempnam(sys_get_temp_dir(), 'proxy_cookie_');

// Initialiser cURL
$ch = curl_init($url);

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

// Exécuter
$response = curl_exec($ch);
$header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$resp_headers = substr($response, 0, $header_size);
$resp_body = substr($response, $header_size);

// Forward response headers (sauf certains)
foreach (explode("\r\n", $resp_headers) as $header) {
    if (stripos($header, 'Transfer-Encoding:') === 0) continue;
    if (stripos($header, 'Content-Length:') === 0) continue;
    if (stripos($header, 'Connection:') === 0) continue;
    if (stripos($header, 'Set-Cookie:') === 0) {
        // Forward cookies au client
        header($header, false);
        continue;
    }
    if ($header) header($header, false);
}

// Réécriture des liens pour forcer le passage par le proxy
if (stripos($resp_headers, 'text/html') !== false) {
    $proxy_prefix = '/proxy.php';
    // Réécriture du <base href>
    $resp_body = preg_replace(
        '#<base\s+href=["\](/[^"\]*)["\]\s*/?>#i',
        '<base href="/proxy.php$1"/>',
        $resp_body
    );
    // Réécriture des liens href, src, action
    $resp_body = preg_replace_callback(
        '/\b(href|src|action)=["\]([^"\]+)["\]/i',
        function ($matches) use ($proxy_prefix) {
            $attr = $matches[1];
            $url = $matches[2];
            // Ne touche pas aux liens déjà proxyfiés ou externes
            if (strpos($url, $proxy_prefix) === 0 || preg_match('#^https?://#i', $url)) return $matches[0];
            // Liens relatifs ou commençant par /
            $new_url = $proxy_prefix . (strpos($url, '/') === 0 ? $url : '/' . $url);
            $new_url = preg_replace('#(?<!:)//+#', '/', $new_url);
            return $attr . '="' . $new_url . '"';
        },
        $resp_body
    );
}

// Réécriture des URLs absolues dans les chaînes JS (ex: '/img/app/...' ou '/api/...')
$resp_body = preg_replace_callback(
    '/([\'"])(\/(img|src|api|assets|js|css|images|login|static|modules|lib|bower_components|node_modules|auth)\/[^\'"]+)([\'"])/i',
    function ($matches) use ($proxy_prefix) {
        if (strpos($matches[2], $proxy_prefix) === 0) return $matches[0];
        $new_url = $proxy_prefix . $matches[2];
        $new_url = preg_replace('#(?<!:)//+#', '/', $new_url);
        return $matches[1] . $new_url . $matches[4];
    },
    $resp_body
);

// Output body
echo $resp_body;

curl_close($ch);
@unlink($tmp_cookie);
?>