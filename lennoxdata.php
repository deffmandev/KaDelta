<?php
header('Content-Type: application/json');
include "base.php";
include "modbus.php";

// Devra retourner les valeur de la table lennox 

$Id = isset($_GET["Id"]) ? $_GET["Id"] : 1;

$data = [];
$data = array_fill(0, 200, 0);

$data[0] = $Id;
date_default_timezone_set('Europe/Paris');
$date = (string)date("d-m-Y");
$heure = (string)date("H:i");

// Lecture du fichier JSON Lennox$Id.json et remplissage de $data
$jsonFile = __DIR__ . "/Lennox{$Id}.json";
if (file_exists($jsonFile)) 
{
    $jsonContent = file_get_contents($jsonFile);
    if ($jsonContent !== false) {
        $data = json_decode($jsonContent, true);
    }
}
if (!is_array($data)) 
    {
        $data = array_fill(0, 200, 0); // Réinitialiser si le JSON est invalide
    }



// Traitement des informations
$data[2]   = $data[2]/10;
$data[3]   = $data[3]/10;
$data[37]  = $data[37]/10;
$data[38]  = $data[38]/10;
$data[39]  = $data[39]/10;
$data[40]  = $data[40]/10;
$data[47]  = $data[47]/10;

$data[135] = ($data[135] == 1) ? "ON" : "OFF";
$data[137] = ($data[137] == 1) ? "ON" : "OFF";
$data[140] = ($data[140] == 1) ? "ON" : "OFF";
$data[139] = ($data[139] == 1) ? "Ch" : "Fr";
$data[142] = ($data[142] == 1) ? "Ch" : "Fr";

$data[47].='%';

$data[20] = $data[15].":".$data[16]."  ".$data[17]."/".$data[18]."/".$data[19];//heures et date du rooftop

$IdD=500+$Id;


echo json_encode($data); //Sortie des informations en json 

?>