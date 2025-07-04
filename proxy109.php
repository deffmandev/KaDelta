<?php
// filepath: \\192.168.1.152\wwwroot\proxy109.php

// CONFIGURATION : URL de base du site privé à proxyfier
$target_base = 'http://192.168.1.109'; // ou http://... selon ton cas

// Construction de l'URL cible à partir de la requête du client
$path = isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : '';
$query = isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : '';
$target_url = rtrim($target_base, '/') . $path . $query;

// Préparation des headers à transmettre
$headers = [];
foreach (getallheaders() as $key => $value) {
    if (strtolower($key) !== 'host') {
        $headers[] = "$key: $value";
    }
}
$headers[] = 'Host: ' . parse_url($target_base, PHP_URL_HOST);
$headers[] = 'Accept-Language: en-US,en;q=0.9,fr;q=0.8';
$headers[] = 'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:127.0) Gecko/20100101 Firefox/127.0';

// Préparation de la requête cURL
$ch = curl_init($target_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false); // On gère les redirections nous-mêmes
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

// Gestion POST/PUT
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, file_get_contents('php://input'));
} elseif (in_array($_SERVER['REQUEST_METHOD'], ['PUT', 'DELETE', 'PATCH'])) {
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $_SERVER['REQUEST_METHOD']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, file_get_contents('php://input'));
}

// Exécution de la requête
$response = curl_exec($ch);
$header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$header = substr($response, 0, $header_size);
$body = substr($response, $header_size);
$content_type = '';
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

// Gestion des headers de réponse
$header_lines = explode("\r\n", $header);
foreach ($header_lines as $hdr) {
    if (stripos($hdr, 'Content-Type:') === 0) {
        $content_type = trim(substr($hdr, 13));
        header($hdr);
    } elseif (stripos($hdr, 'Location:') === 0) {
        // Réécriture de la redirection pour passer par le proxy
        $location = trim(substr($hdr, 9));
        if (stripos($location, $target_base) === 0) {
            $proxied = $_SERVER['PHP_SELF'] . substr($location, strlen($target_base));
            header('Location: ' . $proxied, true, 302);
        } else {
            header($hdr, true, 302);
        }
    } elseif (
        stripos($hdr, 'Content-Length:') === false &&
        stripos($hdr, 'Transfer-Encoding:') === false &&
        stripos($hdr, 'Content-Encoding:') === false &&
        stripos($hdr, 'Set-Cookie:') === false
    ) {
        header($hdr, false);
    }
}

// Réécriture des liens dans le HTML
if (stripos($content_type, 'text/html') !== false) {
    $proxy_prefix = rtrim($_SERVER['PHP_SELF'], '/');
    // Réécriture des liens href, src, action
    $body = preg_replace_callback(
        '/\b(href|src|action)=["\']([^"\']+)["\']/i',
        function ($matches) use ($target_base, $proxy_prefix) {
            $attr = $matches[1];
            $url = $matches[2];
            // Liens absolus vers la cible
            if (stripos($url, $target_base) === 0) {
                $new_url = $proxy_prefix . substr($url, strlen($target_base));
            }
            // Liens absolus externes (laisser passer)
            elseif (preg_match('#^https?://#i', $url)) {
                $new_url = $url;
            }
            // Liens relatifs commençant par /
            elseif (strpos($url, '/') === 0) {
                $new_url = $proxy_prefix . $url;
            }
            // Liens relatifs simples (ex: cfg.js, dossier/fichier.js)
            else {
                $new_url = '/proxy109.php/' . ltrim($url, '/');
            }
            // Nettoie les doubles slashs sauf après http(s):
            $new_url = preg_replace('#(?<!:)//+#', '/', $new_url);
            return $attr . '="' . $new_url . '"';
        },
        $body
    );

    // Réécriture des URLs dans le JavaScript (window.location, document.location, etc.)
    $body = preg_replace_callback(
        '/((window|document)\.location(?:\.href)?\s*=\s*[\'"])([^\'"]+)([\'"])/i',
        function ($matches) use ($target_base, $proxy_prefix) {
            $prefix = $matches[1];
            $url = $matches[3];
            $suffix = $matches[4];
            if (stripos($url, $target_base) === 0) {
                $new_url = $proxy_prefix . substr($url, strlen($target_base));
            } elseif (preg_match('#^https?://#i', $url)) {
                $new_url = $url;
            } elseif (strpos($url, '/') === 0) {
                $new_url = $proxy_prefix . $url;
            } else {
                $new_url = '/proxy109.php/' . ltrim($url, '/');
            }
            $new_url = preg_replace('#(?<!:)//+#', '/', $new_url);
            return $prefix . $new_url . $suffix;
        },
        $body
    );

    // Réécriture des URLs dans les chaînes JS (ex : fetch, ajax, etc.)
    $body = preg_replace_callback(
        '/([\'"])(https?:\/\/' . preg_quote(parse_url($target_base, PHP_URL_HOST), '/') . '[^\'"]*)([\'"])/i',
        function ($matches) use ($target_base, $proxy_prefix) {
            $url = $matches[2];
            $new_url = $proxy_prefix . substr($url, strlen($target_base));
            $new_url = preg_replace('#(?<!:)//+#', '/', $new_url);
            return $matches[1] . $new_url . $matches[3];
        },
        $body
    );
}


$body = str_replace(
        "www.pusr.com",
        "KaDelta",
        $body
    );


$body = str_replace(
        "http://www.pusr.com",
        " ",
        $body
    );


$body = str_replace(
        "return 'Cn'",
        "return 'En'",
        $body
    );


echo $body;