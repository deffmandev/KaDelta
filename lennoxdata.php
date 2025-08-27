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




// Valeur en provision des donnes pour simule la lectures MODBUS des unites interieurs
$data[15]= date("H");
$data[16] = date("i");
$data[17] = date("d");
$data[18] = date("m");
$data[19] = date("Y");

$data[36] = 21*$Id;//code defaut lennox
if ($Id == 3) $data[36] = 0;

$data[2] = 215;
$data[3] = 195;
$data[37] = 232;
$data[38] = 211;
$data[39] = 128;
$data[40] = 213;
$data[135] = 1;
$data[137] = 1;
$data[140] = 1;
$data[45] = 79;






// Traitement des informations
$data[2]   = $data[2]/10;
$data[3]   = $data[3]/10;
$data[37]  = $data[37]/10;
$data[38]  = $data[38]/10;
$data[39]  = $data[39]/10;
$data[40]  = $data[40]/10;


$data[135] = ($data[135] == 1) ? "ON" : "OFF";
$data[137] = ($data[137] == 1) ? "ON" : "OFF";
$data[140] = ($data[140] == 1) ? "ON" : "OFF";

$data[20] = $data[15].":".$data[16]."  ".$data[17]."/".$data[18]."/".$data[19];//heures et date du rooftop

$IdD=500+$Id;
$defautcode= $data[36];


// Gestion des defaut, Lennox, envoie dans la base de donnes SQL defauts
if ($defautcode!=0)
    {
        // Vérifier si le défaut existe déjà
        $sqlCheck = "SELECT COUNT(1) AS cnt FROM defauts WHERE [Unite] like '{$IdD}' AND [Code] like '{$defautcode}' AND [Etat]=1";
        $stmtCheck = mssql($sqlCheck);
        $rowCheck = $stmtCheck ? sqlnext($stmtCheck) : false;
        if ($rowCheck && $rowCheck['cnt'] == 0)  
        {
            echo "numbre ".$rowCheck['cnt'];
            $sqlIns = "INSERT INTO defauts ([Unite], [Code], [Etat], [Date], [Heure]) VALUES ('{$IdD}', '{$defautcode}', 1, '".$date."', '".$heure."')";
            mssql($sqlIns);
        }
   }

// Ajout : si code défaut = 0, vérifier si IdD existe et passer Etat à 2
if ($defautcode==0)
    {
        $sqlCheck0 = "SELECT COUNT(1) AS cnt FROM defauts WHERE [Unite] like '{$IdD}' AND [Etat] like 1";
        $stmtCheck0 = mssql($sqlCheck0);
        $rowCheck0 = $stmtCheck0 ? sqlnext($stmtCheck0) : false;
        if ($rowCheck0 && $rowCheck0['cnt'] > 0) 
        {
            $sqlUpdate = "UPDATE defauts SET [Etat]=2 WHERE [Unite] like '{$IdD}' AND [Etat] like 1";
            mssql($sqlUpdate);
        }
    }


echo json_encode($data); //Sortie des informations en json 

?>