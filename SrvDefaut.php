<?php
include "base.php"; // fournit mssql(), sqlnext()

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

date_default_timezone_set('Europe/Paris');
$date = (string)date("d-m-Y");
$heure = (string)date("H:i");

echo "<pre>";

// Requête : on sélectionne les unités avec un défaut (Alarm=1) ou code erreur non nul
$sql = "SELECT * FROM ValUnites WHERE (Alarm = 1) OR (CodeErreur IS NOT NULL AND CodeErreur <> 0) ORDER BY Id";
$res = mssql($sql);
if ($res) {
	while ($row = sqlnext($res)) 
    {
		if ($row === null) break;
		
        $id = (int)$row['Id'];
        $aarm = (int)$row['Alarm'];
        $codeErreur = (string)$row['CodeErreur'];


        // Vérifie si l'entrée existe déjà dans la table defaut
        $sqlCheck = "SELECT * FROM Defauts WHERE [Unite] like '$id' AND [Code] like '$codeErreur' and [Etat] = 1";
        $resCheck = mssql($sqlCheck);
        $exists = false;
        if ($resCheck && sqlnext($resCheck) !== null) {
            $exists = true;
        }

        // Insère si non existant
        if (!$exists) {
            $sqlIns = "INSERT INTO defauts ([Unite], [Code], [Etat], [Date], [Heure])
                   VALUES ('{$id}', '{$codeErreur}', 1, '".$date."', '".$heure."')";
        mssql($sqlIns);
        }
	}
}

// --- Contrôle des enregistrements existants dans Defauts ---
$defRes = mssql("SELECT Id, Unite, Code, Etat FROM Defauts WHERE Unite <= 230");
if ($defRes) {
    while ($d = sqlnext($defRes)) {
        if ($d === null) break;
        $defId   = (int)$d['Id'];
        $uniteId = (int)$d['Unite'];
        $codeDef = (string)$d['Code'];
        $etat    = isset($d['Etat']) ? (int)$d['Etat'] : 0;

        // Récupère le code actuel dans ValUnites
        $vuRes = mssql("SELECT CodeErreur FROM ValUnites WHERE Id=".$uniteId);
        $codeActuel = null;
        if ($vuRes) {
            $vuRow = sqlnext($vuRes);
            if ($vuRow !== null) {
                $codeActuel = (string)$vuRow['CodeErreur'];
            }
        }

        $doUpdate = false;
        // Si aucune unité trouvée OU code différent OU codeActuel vide/0 alors on passe l'état à 2
        if ($codeActuel === null) {
            $doUpdate = true; // unité n'existe plus
        } else {
            $trimActuel = trim((string)$codeActuel);
            $trimDef = trim((string)$codeDef);
            if ($trimActuel === '' || $trimActuel === '0') {
                $doUpdate = true; // plus de défaut réel
            } elseif ($trimActuel !== $trimDef) {
                $doUpdate = true; // code changé
            }
        }

        if ($doUpdate && $etat != 2) {
            mssql("UPDATE Defauts SET Etat=2 WHERE Id=".$defId);
        }
    }
}

        GetTable("Defauts");


?>