<?php
ob_start();

ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

// Toujours renvoyer un statut HTTP OK et afficher les erreurs en echo.
if (!headers_sent()) {
    http_response_code(200);
}

set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) {
        return false;
    }

    if (!headers_sent()) {
        http_response_code(200);
    }

    echo "[ERR-PHP-{$severity}] {$message} @ {$file}:{$line}" . PHP_EOL;
    return true;
});

set_exception_handler(static function (Throwable $exception): void {
    if (!headers_sent()) {
        http_response_code(200);
    }

    $code = (int)$exception->getCode();
    if ($code <= 0) {
        $code = 1000;
    }

    echo "[ERR-EXC-{$code}] {$exception->getMessage()} @ {$exception->getFile()}:{$exception->getLine()}" . PHP_EOL;
});

register_shutdown_function(static function (): void {
    $fatal = error_get_last();
    if ($fatal === null) {
        return;
    }

    $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
    if (!in_array($fatal['type'], $fatalTypes, true)) {
        return;
    }

    if (!headers_sent()) {
        http_response_code(200);
    }

    echo "[ERR-FATAL-{$fatal['type']}] {$fatal['message']} @ {$fatal['file']}:{$fatal['line']}" . PHP_EOL;
});

setlocale(LC_TIME, 'fr_FR.UTF-8');
date_default_timezone_set('Europe/Paris');

// Fonction d'affichage en français sans strftime
function date_fr($format, $timestamp = null) 
{
    $jours = ['Sunday'=>'Dimanche','Monday'=>'Lundi','Tuesday'=>'Mardi','Wednesday'=>'Mercredi','Thursday'=>'Jeudi','Friday'=>'Vendredi','Saturday'=>'Samedi'];
    $mois = ['January'=>'Janvier','February'=>'Février','March'=>'Mars','April'=>'Avril','May'=>'Mai','June'=>'Juin','July'=>'Juillet','August'=>'Août','September'=>'Septembre','October'=>'Octobre','November'=>'Novembre','December'=>'Décembre'];
    $date = date($format, $timestamp ?? time());
    $date = strtr($date, $jours);
    $date = strtr($date, $mois);
    return $date;
}

$instanceCookieName = 'SrvInstanceEnCours';
$instanceAttemptCookieName = 'SrvInstanceTentatives';
$instanceCookieTtl = 120;

if (!empty($_COOKIE[$instanceCookieName])) {
    $instanceAttemptCount = isset($_COOKIE[$instanceAttemptCookieName]) ? (int)$_COOKIE[$instanceAttemptCookieName] : 0;
    $instanceAttemptCount++;

    if ($instanceAttemptCount >= 10) {
        setcookie($instanceCookieName, '', time() - 3600, '/');
        setcookie($instanceAttemptCookieName, '', time() - 3600, '/');
        unset($_COOKIE[$instanceCookieName], $_COOKIE[$instanceAttemptCookieName]);
    } else {
        setcookie($instanceAttemptCookieName, (string)$instanceAttemptCount, time() + $instanceCookieTtl, '/');
        $_COOKIE[$instanceAttemptCookieName] = (string)$instanceAttemptCount;
        exit;
    }
}

setcookie($instanceCookieName, '1', time() + $instanceCookieTtl, '/');
$_COOKIE[$instanceCookieName] = '1';
setcookie($instanceAttemptCookieName, '0', time() + $instanceCookieTtl, '/');
$_COOKIE[$instanceAttemptCookieName] = '0';

register_shutdown_function(static function () use ($instanceCookieName, $instanceAttemptCookieName): void {
    if (!headers_sent()) {
        setcookie($instanceCookieName, '', time() - 3600, '/');
        setcookie($instanceAttemptCookieName, '', time() - 3600, '/');
    }

    unset($_COOKIE[$instanceCookieName], $_COOKIE[$instanceAttemptCookieName]);
});

echo "Dernière mise à jour : " . date_fr("l d F Y H:i:s");
   
if (!empty($_GET["Commande"])) 
    echo $_GET["Commande"];


$now = time();
$lastCall = 0;
$Memocompte = isset($_COOKIE["compte"]) ? (int)$_COOKIE["compte"] : 0;
$lastCall = isset($_COOKIE["compteTime"]) ? (int)$_COOKIE["compteTime"] : 0;
$lecture = isset($_COOKIE["Lecture"]) ? (int)$_COOKIE["Lecture"] : 0;

// Charger compteLog depuis le cookie ou le fichier JSON
$compteLogFile = 'CompteLog.json';
$compteLogDataRaw = @file_get_contents($compteLogFile);
$compteLogData = is_string($compteLogDataRaw) ? json_decode($compteLogDataRaw, true) : null;
$Comptelogbase = (is_array($compteLogData) && isset($compteLogData['compteLog'])) ? (int)$compteLogData['compteLog'] : 10;

if (isset($_COOKIE["compteLog"])) {
    $compteLog = (int)$_COOKIE["compteLog"];
} else {
    $compteLog = $Comptelogbase;
    setcookie("compteLog", $compteLog, time() + 30600, "/");
}

        echo "  Compteur Log:".$compteLog;

  
            $Comp = (int)($now);
            echo "  ".$Comp-$Memocompte.chr(10).chr(13)."\n\r";

            if ($Comp-$Memocompte>5)
            {
                setcookie("compte", $Comp, time() + 30600, "/"); // Mettre à jour le cookie avec la nouvelle valeur

                    $srvprog_result = "Pas de Modbus";
                    if ($lecture==0) $srvprog_result = @file_get_contents('http://localhost/SrvLennox.php');
                    if ($lecture==1) $srvprog_result = @file_get_contents('http://localhost/SrvLG.php');
                    
                        echo htmlspecialchars($srvprog_result);
                

                $lecture++;
                    if ($lecture > 1) 
                    {
                        $lecture = 0; // Réinitialiser la valeur si elle dépasse 2
                    }
                setcookie("Lecture", $lecture, time() + 30600, "/"); // Mettre à jour le cookie avec la nouvelle valeur

                    
                    echo chr(10).chr(13)."\n\r";

            }



    if ($now - $lastCall >= 59) 
    {
        
    
        if ($compteLog-- < 2)
        {            
            $compteLog = $Comptelogbase;
            setcookie("compteLog", $compteLog, time() + 30600, "/");
            $srvprog_result = @file_get_contents('http://localhost/SrvInLog.php');
            echo htmlspecialchars($srvprog_result);
        }     
            
        
        // Appel SrvProg.php
        setcookie("compteTime", $now, time() + 30600, "/"); // Mettre à jour le cookie avec le temps actuel
        $srvprog_result = @file_get_contents('http://localhost/SrvProg.php');
        echo htmlspecialchars($srvprog_result);

        $srvprog_result = @file_get_contents('http://localhost/SrvDefaut.php');
        echo htmlspecialchars($srvprog_result);

        $srvprog_result = @file_get_contents('http://localhost/SrvSend.php');
        echo htmlspecialchars($srvprog_result);

        //test provisoir de controle par gtc des lennox
        //$srvprog_result = @file_get_contents('http://localhost/lgp.php');
        //echo htmlspecialchars($srvprog_result);

        //$srvprog_result = @file_get_contents('http://localhost/SrvKaLogIndex.php');
        //echo htmlspecialchars($srvprog_result);
    }

        // Sauvegarder la nouvelle valeur dans le cookie
            setcookie("compteLog", $compteLog, time() + 30600, "/");

            var_dump($_COOKIE);
    sleep(1);
?>