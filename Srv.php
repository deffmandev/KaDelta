<?php
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(0);

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
echo "Dernière mise à jour : " . date_fr("l d F Y H:i:s");
   
if (!empty($_GET["Commande"])) 
    echo $_GET["Commande"];


$now = time();
$lastCall = 0;
$Memocompte=$_COOKIE["compte"];
$lastCall = (int)$_COOKIE["compteTime"];

    if ($now - $lastCall >= 5) 
    {
            echo "   ";
            $Comp = (int)(($now - $lastCall)/5);
            echo $Comp."=".$Memocompte;
            if ($Memocompte != $Comp)
            {
                // Appel SrvLG.php
                setcookie("compte", $Comp, time() + 3600, "/"); // Mettre à jour le cookie avec la nouvelle valeur
                $srvprog_result = @file_get_contents('http://localhost/SrvLG.php');
                echo htmlspecialchars($srvprog_result);
            }
            echo chr(10).chr(13)."\n\r";

    }


    if ($now - $lastCall >= 60) 
    {
        // Appel SrvProg.php
        setcookie("compteTime", $now, time() + 3600, "/"); // Mettre à jour le cookie avec le temps actuel
        $srvprog_result = @file_get_contents('http://localhost/SrvProg.php');
        echo htmlspecialchars($srvprog_result);

        $srvprog_result = @file_get_contents('http://localhost/SrvDefaut.php');
        echo htmlspecialchars($srvprog_result);

    }

    sleep(1);
?>