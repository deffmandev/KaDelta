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

$data[37] = 232;
$data[38] = 211;
$data[39] = 128;
$data[40] = 213;

// Traitement des temperatures
$data[37] = $data[37]/10;
$data[38] = $data[38]/10;
$data[39] = $data[39]/10;
$data[40] = $data[40]/10;



$data[45] = 79;



echo json_encode($data);
