<?php
header('Content-Type: application/json');

//devra retourner les valeur de la table lennox 

$Id = isset($_GET["Id"]) ? $_GET["Id"] : 1;

$data = [];
$data = array_fill(0, 200, 0);

$data[0] = $Id;
date_default_timezone_set('Europe/Paris');

$data[15]= date("H");
$data[16] = date("i");
$data[17] = date("d");
$data[18] = date("m");
$data[19] = date("Y");




$data[36] = 251;//code defaut lennox

$data[2] = 215;
$data[3] = 195;
$data[37] = 232;
$data[38] = 211;
$data[39] = 128;
$data[40] = 213;
$data[135] = 1;
$data[137] = 1;
$data[140] = 0;


// Traitement des temperatures
$data[2]   = $data[2]/10;
$data[3]   = $data[3]/10;
$data[37]  = $data[37]/10;
$data[38]  = $data[38]/10;
$data[39]  = $data[39]/10;
$data[40]  = $data[40]/10;


$data[135] = ($data[135] == 1) ? "ON" : "OFF";
$data[137] = ($data[137] == 1) ? "ON" : "OFF";
$data[140] = ($data[140] == 1) ? "ON" : "OFF";

$data[45] = 79;

if ($Id == 2) $data[36] = 0;


$data[20] = $data[15].":".$data[16]."  ".$data[17]."/".$data[18]."/".$data[19];//heures et date du rooftop



echo json_encode($data);
