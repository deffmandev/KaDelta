<?php
include "base.php";

    if (isset($_GET["Groupe"]))
        {
            $GroupeActive=true;
            $GroupeId=$_GET["Groupe"];
            if ($GroupeId === "all") 
            {
                $UniteId = mssql("SELECT Id,Gr FROM [DefUnites]");
                if ($UniteId!=null)
                    $UniteId = sqlnext($UniteId);
                if ($UniteId!=null) $UniteId=$UniteId["Id"];

            }
            else
            {
                $UniteId = mssql("SELECT Id,Gr FROM [DefUnites] WHERE Gr = $GroupeId");
                if ($UniteId!=null)
                    $UniteId = sqlnext($UniteId);
                if ($UniteId!=null) $UniteId=$UniteId["Id"];
            }
        }
    else
        {    
            $GroupeActive=false;
            $GroupeId='0';
        }

    if (isset($_GET["IdSel"]))     
        $UniteId = $_GET["IdSel"];

if (!isset($UniteId)) 
{
    echo '<script>window.parent.OverScreenWunites.style.display = "none";</script>';
    exit(0);
}

    echo '
    <script>
        GroupeId="'.$GroupeId.'";
        GroupeActive='.($GroupeActive ? 'true' : 'false').';
    </script>
    ';
    

// Récupération des noms de Modbus
$modbusNames = [];
$result = mssql("SELECT Id,Nom FROM [DefModBus]");

if ($result) 
    while ($row = sqlnext($result))
        $modbusNames[$row['Id']] = $row['Nom'];

// Récupération des noms de groupes
$groupNames = [];
$result = mssql("SELECT Id,Groupe FROM [Groupe]");

$progNames = [];
$resultProg = mssql("SELECT Id, Nom FROM [ProgNom]");
if ($resultProg) 
    while ($row = sqlnext($resultProg)) 
        $progNames[$row['Id']] = $row['Nom'];

if ($result) 
    while ($row = sqlnext($result)) 
        $groupNames[$row['Id']] = $row['Groupe'];
            
// Récupération des informations de l'unité en fonction de l'ID
$result = mssql("SELECT * FROM [DefUnites] WHERE Id = $UniteId");
if ($result) 
        $unites = sqlnext($result);

        
$result = mssql("SELECT * FROM [ValUnites] WHERE Id = $UniteId");
if ($result) 
        $valUnites = sqlnext($result);

        
Function RegistreOption($valeur)
{
    $options = [
        1   => 'Coil',
        3   => 'Registre',
        300 => 'R-Bit 0',
        301 => 'R-Bit 1',
        302 => 'R-Bit 2',
        303 => 'R-Bit 3',
        304 => 'R-Bit 4',
        305 => 'R-Bit 5',
        306 => 'R-Bit 6',
        307 => 'R-Bit 7',
        308 => 'R-Bit 8',
        309 => 'R-Bit 9',
        310 => 'R-Bit 10',
        311 => 'R-Bit 11',
        312 => 'R-Bit 12',
        313 => 'R-Bit 13',
        314 => 'R-Bit 14',
        315 => 'R-Bit 15',
    ];
    foreach ($options as $key => $label) 
    {
        $selected = ($valeur == $key) ? 'selected' : '';
        echo '<option '.$selected.' value="'.$key.'">'.htmlspecialchars($label).'</option>';
    }
}

?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Régler la Température</title>
    <style>
        @keyframes fondu-enchaine {
          from { opacity: 0; }
          to   { opacity: 1; }
        }

        .fenetre, .modal, .popup {
          animation: fondu-enchaine 0.4s ease; /* Réduction de 0.8s à 0.4s */
        }

        body {
            margin: 0;
            font-family: 'Roboto', Arial, sans-serif;
            min-height: 100vh;

        }
        .modal-bg {
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.35);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.2s; /* Réduction de 0.4s à 0.2s */
        }
        .modal-bg.visible {
            opacity: 1;
            pointer-events: auto;
        }
        .modal {
            background: #fff;
            padding: 2.5em 2em 2em 2em;
            border-radius: 18px;
            min-width: 340px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.18);
            text-align: center;
            position: relative;
            transition: box-shadow 0.15s; /* Réduction de 0.3s à 0.15s */
            -webkit-user-select: none;
            -moz-user-select: none;
            -ms-user-select: none;

        }
        .modal:hover {
            box-shadow: 0 12px 40px rgba(0,0,0,0.22);
        }
        h2 {
            font-weight: 700;
            margin-bottom: 1.2em;
            color: #1a2947;
            letter-spacing: 0.02em;
        }
        .temp-controls {
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1.5em;
        }
        .temp-btn {
            font-size: 2.2em;
            width: 52px;
            height: 52px;
            margin: 0 1.2em;
            cursor: pointer;
            border: none;
            border-radius: 50%;
            background: #f0f4fa;
            color: #0078d7;
            box-shadow: 0 2px 8px rgba(0,120,215,0.07);
            transition: background 0.2s, color 0.2s, box-shadow 0.2s;
        }
        .temp-btn:hover {
            background: #0078d7;
            color: #fff;
            box-shadow: 0 4px 16px rgba(0,120,215,0.13);
        }
        .temp-value {
            font-size: 2.2em;
            width: 70px;
            display: inline-block;
            font-weight: 700;
            color: #1a2947;
        }
        .modes, .fan-speeds {
            margin: 1.2em 0 0.8em 0;
        }
        .mode-btn, .fan-btn {
            margin: 0 0.5em;
            padding: 0.5em 1.2em;
            border: none;
            border-radius: 22px;
            background: #f0f4fa;
            color: #1a2947;
            font-size: 1em;
            font-weight: 500;
            cursor: pointer;
            transition: background 0.2s, color 0.2s;
            box-shadow: 0 1px 4px rgba(0,120,215,0.04);
        }
        .mode-btn.selected, .fan-btn.selected {
            background: #0078d7;
            color: #fff;
        }
        .mode-btn:hover:not(.selected), .fan-btn:hover:not(.selected) {
            background: #e3f0ff;
            color: #0078d7;
        }
        .settings-icon {
            position: absolute;
            top: 18px;
            right: 18px;
            width: 28px;
            height: 28px;
            cursor: pointer;
            fill: #b0b8c1;
            transition: fill 0.2s;
        }
        .settings-icon:hover {
            fill: #0078d7;
        }
        #close-modal-btn, #close-settings-btn, #close-advanced-btn {
            margin-top: 1.8em;
            padding: 0.7em 2.2em;
            border: none;
            border-radius: 22px;
            background: #e3f0ff;
            color: #0078d7;
            font-size: 1.1em;
            font-weight: 700;
            cursor: pointer;
            transition: background 0.2s, color 0.2s;
            box-shadow: 0 1px 4px rgba(0,120,215,0.04);
        }
        #close-modal-btn:hover, #close-settings-btn:hover, #close-advanced-btn:hover {
            background: #0078d7;
            color: #fff;
        }
        .settings-form label {
            display: block;
            margin: 1em 0 0.3em 0;
            font-weight: 500;
            color: #1a2947;
        }
        .settings-form input[type="number"], .settings-form select {
            width: 80px;
            padding: 0.4em;
            border-radius: 8px;
            border: 1px solid #b0b8c1;
            font-size: 1em;
            margin-right: 1em;
        }
        .settings-form select {
            width: 300px;
        }
        @media (max-width: 480px) {
            .modal {
                min-width: 90vw;
                padding: 1.2em 0.5em 1em 0.5em;
            }
            .temp-value {
                width: 50px;
            }
            .settings-form select {
                width: 90vw;
            }
        }
        .conf-label {
            min-width: 110px;
            font-weight: 500;
            color: #1a2947;
            text-align: right;
            display: inline-block;
        }
        .conf-field {
            display: flex;
            align-items: center;
            gap: 0.3em;
        }
        #advanced-form input[type="number"], #advanced-form select {
            width: 110px;
            padding: 0.4em;
            border-radius: 8px;
            border: 1px solid #b0b8c1;
            font-size: 1em;
            margin-right: 0;
        }
        /* Style pour le champ nom dans la fenêtre avancée */
        #device-name {
            width: 260px;
            font-size: 1.35em;
            font-weight: 600;
            padding: 0.5em 0.7em;
            border-radius: 10px;
            border: 1.5px solid #b0b8c1;
            letter-spacing: 0.04em;
        }
    </style>
</head>
<body>
<div class="modal-bg" id="modal-bg">
    <!-- Fenêtre principale -->
    <div class="modal" id="main-modal">
        <!-- Paramétrage Icone SVG -->
        <svg class="settings-icon" id="settings-icon" viewBox="0 0 24 24" title="Paramétrage">
            <path d="M19.14,12.94a7.07,7.07,0,0,0,.06-1,7.07,7.07,0,0,0-.06-1l2.11-1.65a.5.5,0,0,0,.12-.65l-2-3.46a.5.5,0,0,0-.61-.22l-2.49,1a7,7,0,0,0-1.73-1l-.38-2.65A.5.5,0,0,0,13,2H11a.5.5,0,0,0-.5.42l-.38,2.65a7,7,0,0,0-1.73,1l-2.49-1a.5.5,0,0,0-.61.22l-2,3.46a.5.5,0,0,0,.12.65l2.11,1.65a7.07,7.07,0,0,0-.06,1,7.07,7.07,0,0,0,.06,1L2.86,14.59a.5.5,0,0,0-.12.65l2,3.46a.5.5,0,0,0,.61.22l2.49-1a7,7,0,0,0,1.73,1l.38,2.65A.5.5,0,0,0,11,22h2a.5.5,0,0,0,.5-.42l.38-2.65a7,7,0,0,0,1.73-1l2.49,1a.5.5,0,0,0,.61-.22l2-3.46a.5.5,0,0,0-.12-.65ZM12,15.5A3.5,3.5,0,1,1,15.5,12,3.5,3.5,0,0,1,12,15.5Z"/>
        </svg>
        <?php
            if ($GroupeActive === true) {
                if ($GroupeId === "all") {
                    echo '<h2 id="DeviceNameHtml" style="margin-top:-18px;margin-bottom:40px">GROUPE<br>toutes les unites</h2>';
                } else {
                    echo '<h2 id="DeviceNameHtml" style="margin-top:-18px;margin-bottom:40px">GROUPE<br>' . htmlspecialchars($groupNames[$GroupeId] ?? '') . '</h2>';
                }
            } elseif ($GroupeActive === null) {
                // Affiche telle quelle la ligne actuelle
                echo '<h2 id="DeviceNameHtml" style="margin-top:-18px;margin-bottom:40px">' . $unites["Name"] . '</h2>';
            } else {
                // Cas par défaut (pas de groupe actif)
                echo '<h2 id="DeviceNameHtml" style="margin-top:-18px;margin-bottom:40px">' . $unites["Name"] . '</h2>';
            }
        ?>
        <div class="temp-controls">
            <button class="temp-btn" id="minus-btn">-</button>
            <span class="temp-value" id="temp-value"><?php echo $valUnites["SetRoom"];?></span>°C
            <button class="temp-btn" id="plus-btn">+</button>
        </div>
        <div class="modes">
            <span>Mode:</span>
            <button class="mode-btn<?php echo ($valUnites["OnOff"] == 0 ? " selected" : ""); ?>" data-mode="off">Off</button>
            <button class="mode-btn<?php echo ($valUnites["OnOff"] == 1 && $valUnites["Mode"] == 5 ? " selected" : ""); ?>" data-mode="chaud">Chaud</button>
            <button class="mode-btn<?php echo ($valUnites["OnOff"] == 1 && $valUnites["Mode"] == 1 ? " selected" : ""); ?>" data-mode="froid">Froid</button>
            <button class="mode-btn<?php echo ($valUnites["OnOff"] == 1 && $valUnites["Mode"] == 2 ? " selected" : ""); ?>" data-mode="dry">Dry</button>
            <button class="mode-btn<?php echo ($valUnites["OnOff"] == 1 && $valUnites["Mode"] == 3 ? " selected" : ""); ?>" data-mode="fan">Fan</button>
            <button class="mode-btn<?php echo ($valUnites["OnOff"] == 1 && $valUnites["Mode"] == 4 ? " selected" : ""); ?>" data-mode="auto">Auto</button>
        </div>
        <div class="fan-speeds">
            <span>Ventilation:</span>
            <button class="fan-btn<?php echo ($valUnites["Fan"] == 4 ? " selected" : ""); ?>" data-fan="auto">Auto</button>
            <button class="fan-btn<?php echo ($valUnites["Fan"] == 1 ? " selected" : ""); ?>" data-fan="1">1</button>
            <button class="fan-btn<?php echo ($valUnites["Fan"] == 2 ? " selected" : ""); ?>" data-fan="2">2</button>
            <button class="fan-btn<?php echo ($valUnites["Fan"] == 3 ? " selected" : ""); ?>" data-fan="3">3</button>
        </div>
        <button id="close-modal-btn">Fermer</button>
    </div>
    <!-- Fenêtre Paramétrage -->
    <div class="modal" id="settings-modal" style="display:none;">
        <?php if (!$GroupeActive): ?>
            <!-- Icône clé à molette pour paramétrage avancé -->
            <svg class="settings-icon" id="advanced-settings-icon" viewBox="0 0 24 24" title="Paramétrage avancé" style="position:absolute;top:18px;right:18px;">
                <path d="M22.7 19.3l-4.1-4.1c1.1-1.7.9-4-0.6-5.5-1.5-1.5-3.8-1.7-5.5-0.6l-4.1-4.1c-0.4-0.4-1-0.4-1.4 0l-2.1 2.1c-0.4 0.4-0.4 1 0 1.4l4.1 4.1c-1.1 1.7-0.9 4 0.6 5.5 1.5 1.5 3.8 1.7 5.5 0.6l4.1 4.1c0.4 0.4 1 0.4 1.4 0l2.1-2.1c0.4-0.4 0.4-1 0-1.4zM7.1 6.7l1.4-1.4 3.5 3.5-1.4 1.4-3.5-3.5zm7.8 7.8c-1.1 1.1-2.9 1.1-4 0s-1.1-2.9 0-4 2.9-1.1 4 0 1.1 2.9 0 4zm3.5 3.5l-1.4 1.4-3.5-3.5 1.4-1.4 3.5 3.5z"/>
            </svg>
        <?php endif; ?>
        <h2>Paramétrage</h2>
        <form class="settings-form">
            <div style="display:flex;align-items:center;gap:1.5em;margin-bottom:1.5em;">
            </div>
            <label>Limite Froid :</label>
            <div style="display:inline-flex;align-items:center;">
                <button type="button" class="temp-btn" id="cold-min-minus">-</button>
                <input type="number" id="cold-min" min="10" max="32" value="<?php echo $unites["LimiteClimB"]?>">
                <button type="button" class="temp-btn" id="cold-min-plus">+</button>
                à
                <button type="button" class="temp-btn" id="cold-max-minus">-</button>
                <input type="number" id="cold-max" min="10" max="32" value="<?php echo $unites["LimiteClimH"]?>">
                <button type="button" class="temp-btn" id="cold-max-plus">+</button>
                °C
            </div>
            <label>Limite Chaud :</label>
            <div style="display:inline-flex;align-items:center;">
                <button type="button" class="temp-btn" id="hot-min-minus">-</button>
                <input type="number" id="hot-min" min="10" max="32" value="<?php echo $unites["LimiteChaudB"]?>">
                <button type="button" class="temp-btn" id="hot-min-plus">+</button>
                à
                <button type="button" class="temp-btn" id="hot-max-minus">-</button>
                <input type="number" id="hot-max" min="10" max="32" value="<?php echo $unites["LimiteChaudH"]?>">
                <button type="button" class="temp-btn" id="hot-max-plus">+</button>
                °C
            </div>
            <label>Programme horaire :</label>
            <select id="schedule-select" onchange="ChangeProgramme()">
                    <?php 
                        $selval = $unites['Prog'];
                        echo '<option value="0" >Aucun</option>';
                        foreach ($progNames as $id => $Nom) 
                        {
                            $selected = ($selval == $id) ? 'selected' : '';
                            echo '<option '.$selected.' value="'.$id.'">'.htmlspecialchars($Nom).'</option>'; 
                        }
                    ?>
            </select>
        </form>
        <button id="close-settings-btn">Fermer</button>
    </div>
    <!-- Fenêtre Paramétrage Avancé -->
    <div class="modal" id="advanced-modal" style="display:none; position:relative;">
        <!-- Icône corbeille -->
        <svg id="delete-unit-btn" title="Supprimer l'unité" style="position:absolute;top:18px;left:18px;cursor:pointer;width:32px;height:32px;fill:#d32f2f;transition:fill 0.2s;" viewBox="0 0 24 24">
            <path d="M3 6h18v2H3V6zm2 3h14l-1.5 12.5c-.1.8-.8 1.5-1.6 1.5H8.1c-.8 0-1.5-.7-1.6-1.5L5 9zm5 2v7h2v-7h-2zm4 0v7h2v-7h-2zm-8 0v7h2v-7H6zM9 4V2h6v2h5v2H4V4h5z"/>
        </svg>
        <!-- Modale de confirmation suppression -->
        <div id="delete-confirm-modal" style="display:none;position:fixed;top:0;left:0;width:100vw;height:100vh;background:rgba(0,0,0,0.35);z-index:10001;align-items:center;justify-content:center;">
            <div style="background:#fff;padding:2em 2.5em;border-radius:16px;box-shadow:0 8px 32px #0002;text-align:center;min-width:320px;">
                <h3 style="color:#d32f2f;margin-bottom:18px;">Confirmer la suppression ?</h3>
                <p>Voulez-vous vraiment supprimer cette unité ?<br><b style="color:#d32f2f;">Cette action est irréversible.</b></p>
                <button id="confirm-delete-btn" style="background:#d32f2f;color:#fff;padding:0.7em 2.2em;border:none;border-radius:22px;font-size:1.1em;font-weight:700;cursor:pointer;margin-right:1em;">Supprimer</button>
                <button id="cancel-delete-btn" style="background:#e3f0ff;color:#0078d7;padding:0.7em 2.2em;border:none;border-radius:22px;font-size:1.1em;font-weight:700;cursor:pointer;">Annuler</button>
            </div>
        </div>
        <h2>Paramétrage avancé</h2>
        <form id="advanced-form" class="settings-form">
            <!-- Ligne nom appareil -->
            <div style="display:flex;align-items:center;gap:1.5em;margin-bottom:1.5em;">
                <span class="conf-label" style="font-size:1.15em;">Nom</span>
                <div class="conf-field">
                    <input 
                        type="text"
                        onchange="ChangeName()"
                        id="device-name" 
                        maxlength="18" 
                        value="<?php echo htmlspecialchars($unites['Name']); ?>"
                        style="width:260px;font-size:1.35em;font-weight:600;padding:0.5em 0.7em;border-radius:10px;border:1.5px solid #b0b8c1;letter-spacing:0.04em;" 
                        placeholder="Nom de l'appareil"
                        oninput="if(this.value.length>12)this.value=this.value.slice(0,12);"
                    >
                </div>
            </div>
            <!-- Sélection Groupe -->
            <div style="display:flex;align-items:center;gap:1.5em;margin-bottom:1em;">
                <span class="conf-label">Groupe</span>
                <div class="conf-field">
                    <select onchange="ChangeGroupe()" id="groupe-select" style="width:380px;">
                    <?php 
                            $valsel = $unites['Gr'];
                            foreach ($groupNames as $id => $Groupe) 
                            {
                                $selected = ($valsel == $id) ? 'selected' : '';
                                echo '<option '.$selected.' value="'.$id.'">'.htmlspecialchars($Groupe).'</option>'; 
                            };
                    ?>
                    </select>
                </div>
            </div>
            <!-- Sélection Modbus -->
            <div style="display:flex;align-items:center;gap:1.5em;margin-bottom:1em;">
                <span class="conf-label">Modbus</span>
                <div class="conf-field">
                    <select onchange="ChangeModbus()" id="modbus-select" style="width:380px;">
                        <?php 
                            foreach ($modbusNames as $id => $nom)
                            {
                                $valsel = $unites['ModbusId'];
                                $selected = ($valsel == $id) ? 'selected' : '';
                                echo '<option '.$selected.' value="'.$id.'">'.htmlspecialchars($nom).'</option>'; 
                            } 
                        ?>
                    </select>
                </div>
            </div>

            <div style="display:flex;align-items:center;gap:1.5em;margin-bottom:1em;">
                <span class="conf-label">Device</span>
                <div class="conf-field">
                    <span>Adresse&nbsp;:</span>
                    <input onchange='ChangeDEVICE()' type="number" id="Device-config" min="0" max="65535" value="<?php echo $unites['Device']; ?>">
                </div>
            </div>

            <div style="display:flex;align-items:center;gap:1.5em;margin-bottom:1em;">
                <span class="conf-label">OnOff</span>
                <div class="conf-field">
                    <span>Adresse&nbsp;:</span>
                    <input onchange='ChangeOnOff()' type="number" id="address-config" min="0" max="65535" value="<?php echo $unites['OnOff']; ?>">
                </div>
                <div class="conf-field">
                    <span>Type&nbsp;:</span>
                    <select onchange='ChangeOnOff()' id="type-config">
                            <?php RegistreOption($unites["Type_OnOff"]); ?>
                    </select>
                </div>
            </div>
            <div style="display:flex;align-items:center;gap:1.5em;margin-bottom:1em;">
                <span class="conf-label">Mode</span>
                <div class="conf-field">
                    <span>Adresse&nbsp;:</span>
                    <input onchange="ChangeMode()" type="number" id="mode-address-config" min="0" max="65535" value="<?php echo $unites['Mode']; ?>">
                </div>
                <div class="conf-field">
                    <span>Type&nbsp;:</span>
                    <select onchange="ChangeMode()" id="mode-type-config">
                    <?php RegistreOption($unites["Type_Mode"]); ?>
                    </select>
                </div>
            </div>
            <div style="display:flex;align-items:center;gap:1.5em;margin-bottom:1em;">
                <span class="conf-label">Fan</span>
                <div class="conf-field">
                    <span>Adresse&nbsp;:</span>
                    <input onchange="ChangeFan()" type="number" id="fan-address-config" min="0" max="65535"    value="<?php echo $unites['Fan']; ?>">
                </div>
                <div class="conf-field">
                    <span>Type&nbsp;:</span>
                    <select onchange="ChangeFan()" id="fan-type-config">
                    <?php RegistreOption($unites["Type_Fan"]); ?>
                    </select>
                </div>
            </div>
            <div style="display:flex;align-items:center;gap:1.5em;margin-bottom:1em;">
                <span class="conf-label">Temperature</span>
                <div class="conf-field">
                    <span>Adresse&nbsp;:</span>
                    <input onchange="ChangeTemperature()" type="number" id="temp-address-config" min="0" max="65535" value="<?php echo $unites['Room']; ?>">
                </div>
                <div class="conf-field">
                    <span>Type&nbsp;:</span>
                    <select onchange="ChangeTemperature()" id="temp-type-config">
                    <?php RegistreOption($unites["Type_Room"]); ?>
                    </select>
                </div>
            </div>
            <div style="display:flex;align-items:center;gap:1.5em;margin-bottom:1em;">
                <span class="conf-label">Set Temp</span>
                <div class="conf-field">
                    <span>Adresse&nbsp;:</span>
                    <input onchange="ChangeSetTemp()" type="number" id="settemp-address-config" min="0" max="65535" value="<?php echo $unites['SetRoom']; ?>">
                </div>
                <div class="conf-field">
                    <span>Type&nbsp;:</span>
                    <select onchange="ChangeSetTemp()" id="settemp-type-config">
                    <?php RegistreOption($unites["Type_SetRoom"]); ?>
                    </select>
                </div>
            </div>
            <div style="display:flex;align-items:center;gap:1.5em;margin-bottom:1em;">
                <span class="conf-label">Alarm</span>
                <div class="conf-field">
                    <span>Adresse&nbsp;:</span>
                    <input onchange="ChangeAlarm()" type="number" id="alarm-address-config" min="0" max="65535" value="<?php echo $unites['Alarm']; ?>">
                </div>
                <div class="conf-field">
                    <span>Type&nbsp;:</span>
                    <select onchange="ChangeAlarm()" id="alarm-type-config">
                    <?php RegistreOption($unites["Type_Alarm"]); ?>
                    </select>
                </div>
            </div>
            <div style="display:flex;align-items:center;gap:1.5em;margin-bottom:1em;">
                <span class="conf-label">Code Alarm</span>
                <div class="conf-field">
                    <span>Adresse&nbsp;:</span>
                    <input onchange="ChangeCodeAlarm()" type="number" id="codealarm-address-config" min="0" max="65535" value="<?php echo $unites['CodeErreur']; ?>">
                </div>
                <div class="conf-field">
                    <span>Type&nbsp;:</span>
                    <select onchange="ChangeCodeAlarm()" id="codealarm-type-config">
                    <?php RegistreOption($unites["Type_CodeErreur"]); ?>
                    </select>
                </div>
            </div>
        </form>
        <button id="close-advanced-btn">Fermer</button>
    </div>
</div>
<script>
    // Animation ouverture
    const modalBg = document.getElementById('modal-bg');
    window.addEventListener('DOMContentLoaded', () => {
        setTimeout(() => {
            modalBg.classList.add('visible');
        }, 5); // Réduction du délai de 10ms à 5ms
    });

    // Fenêtres modales
    const mainModal = document.getElementById('main-modal');
    const settingsModal = document.getElementById('settings-modal');
    const advancedModal = document.getElementById('advanced-modal');
    const settingsIcon = document.getElementById('settings-icon');
    const closeModalBtn = document.getElementById('close-modal-btn');
    const closeSettingsBtn = document.getElementById('close-settings-btn');
    const advancedSettingsIcon = document.getElementById('advanced-settings-icon');
    const closeAdvancedBtn = document.getElementById('close-advanced-btn');

    // Affichage/fermeture des fenêtres
    settingsIcon.onclick = () => {
        mainModal.style.display = 'none';
        settingsModal.style.display = 'block';
    };
    closeSettingsBtn.onclick = () => {
        settingsModal.style.display = 'none';
        mainModal.style.display = 'block';
    };

    <?php if (!$GroupeActive): ?>
        advancedSettingsIcon.onclick = () => {
            settingsModal.style.display = 'none';
            advancedModal.style.display = 'block';
        };
        
        closeAdvancedBtn.onclick = () => {
            advancedModal.style.display = 'none';
            settingsModal.style.display = 'block';
        };
    <?php endif; ?>
    closeModalBtn.onclick = () => {
        modalBg.classList.remove('visible');
        setTimeout(() => {
            modalBg.style.display = 'none';
            window.parent.OverScreenWunites.style.display = "none";
        }, 200);
    };

    // Suppression unité : ouverture modale
    document.getElementById('delete-unit-btn').onclick = function() {
        document.getElementById('delete-confirm-modal').style.display = 'flex';
    };
    // Annuler suppression
    document.getElementById('cancel-delete-btn').onclick = function() {
        document.getElementById('delete-confirm-modal').style.display = 'none';
    };
    // Confirmer suppression
    document.getElementById('confirm-delete-btn').onclick = function() {
        // Appel API suppression
        const id = typeof IdUnite !== 'undefined' ? IdUnite : <?php echo json_encode($UniteId); ?>;
        fetch('Base.php?table=DefUnites&delete=1&Id=' + encodeURIComponent(id), { method: 'GET' })
            .then(response => response.text())
            .then((text) => {
                // Optionnel : vérifier le succès côté serveur
                // console.log('Suppression:', text);
                modalBg.classList.remove('visible');
                setTimeout(() => {
                    modalBg.style.display = 'none';
                    if (window.parent && window.parent.OverScreenWunites)
                        window.parent.OverScreenWunites.style.display = "none";
                    
                }, 200);
            });
    };

    // Température
    let temp = <?php echo $valUnites["SetRoom"];?>;
    let IdUnite = <?php echo $UniteId;?>;
    const minTemp = 10;
    const maxTemp = 32;
    document.getElementById('minus-btn').onclick = () => {
        if (temp > minTemp) {
            temp--;
            document.getElementById('temp-value').textContent = temp;
            SetModeBus(IdUnite,'Type_SetRoom','SetRoom', temp);
        }
    };
    document.getElementById('plus-btn').onclick = () => {
        if (temp < maxTemp) {
            temp++;
            document.getElementById('temp-value').textContent = temp;
            SetModeBus(IdUnite,'Type_SetRoom','SetRoom', temp);
        }
    };

    // Sélection mode/fan
    document.querySelectorAll('.mode-btn').forEach(btn => {
        btn.onclick = () => {
            document.querySelectorAll('.mode-btn').forEach(b => b.classList.remove('selected'));
            btn.classList.add('selected');
            const mode = btn.getAttribute('data-mode');
            let modeValue;
            switch (mode) {
                case 'off':
                    modeValue = 0;
                    break;
                case 'chaud':
                    modeValue = 5;
                    break;
                case 'froid':
                    modeValue = 1;
                    break;
                case 'dry':
                    modeValue = 2;
                    break;
                case 'fan':
                    modeValue = 3;
                    break;
                case 'auto':
                    modeValue = 4;
                    break;
            }
            SetModeBus(IdUnite, 'Type_Mode', 'Mode', modeValue);

            if (mode== 'off') {
                SetModeBus(IdUnite, 'Type_OnOff', 'OnOff', 0); // Réinitialise la température à 0 si mode off
            }
            else {
                SetModeBus(IdUnite, 'Type_OnOff', 'OnOff', 1); // Active l'unité si un mode est sélectionné
            }

        };
    });


    document.querySelectorAll('.fan-btn').forEach(btn => {
        btn.onclick = () => {
            document.querySelectorAll('.fan-btn').forEach(b => b.classList.remove('selected'));
            btn.classList.add('selected');
            const fanSpeed = btn.getAttribute('data-fan');
            let fanValue;
            switch (fanSpeed) {
                case 'auto':
                    fanValue = 4;
                    break;
                case '1':
                    fanValue = 1;
                    break;
                case '2':
                    fanValue = 2;
                    break;
                case '3':
                    fanValue = 3;
                    break;
            }   
            SetModeBus(IdUnite, 'Type_Fan', 'Fan', fanValue);

        };
    });

    // Fonctions pour les boutons +/− des limites de température
    function setupTempLimitBtns(inputId, minusBtnId, plusBtnId, min, max, IdName)
    {
        const input = document.getElementById(inputId);
        document.getElementById(minusBtnId).onclick = () => {
            let val = parseInt(input.value, 10);
            if (val > min) input.value = val - 1;

            if (document.getElementById("cold-max").value<document.getElementById("cold-min").value) 
                document.getElementById("cold-max").value = document.getElementById("cold-min").value;
            if (document.getElementById("hot-max").value<document.getElementById("hot-min").value) 
                document.getElementById("hot-max").value = document.getElementById("hot-min").value;


            updateTable('DefUnites', IdName, input.value, 'Id', IdUnite);
        };
        document.getElementById(plusBtnId).onclick = () => {
            let val = parseInt(input.value, 10);
            if (val < max) input.value = val + 1;

            if (document.getElementById("cold-min").value>document.getElementById("cold-max").value) 
                document.getElementById("cold-min").value = document.getElementById("cold-max").value;
            if (document.getElementById("hot-min").value>document.getElementById("hot-max").value) 
                document.getElementById("hot-min").value = document.getElementById("hot-max").value;
            


            updateTable('DefUnites', IdName, input.value, 'Id', IdUnite);
        };
    }
    setupTempLimitBtns('cold-min', 'cold-min-minus', 'cold-min-plus', 10, 32,'LimiteClimB');
    setupTempLimitBtns('cold-max', 'cold-max-minus', 'cold-max-plus', 10, 32,'LimiteClimH');
    setupTempLimitBtns('hot-min', 'hot-min-minus', 'hot-min-plus', 10, 32, 'LimiteChaudB');
    setupTempLimitBtns('hot-max', 'hot-max-minus', 'hot-max-plus', 10, 32, 'LimiteChaudH');

// Fonction pour change valeur Modbus
// Envoie une requête à l'API ModbusApi.php pour changer la valeur d'un registre Modbus
// Utilise fetch pour envoyer la requête GET avec les paramètres IdUnite, Type, Idem et Valeur
// Affiche la réponse dans la console pour le débogage
// IdUnite : identifiant de l'unité
// Type : type de registre Modbus (par exemple, 'Type_SetRoom')
// Idem : identifiant du registre Modbus (par exemple, 'SetRoom')
// Valeur : nouvelle valeur à écrire dans le registre Modbus
// exemple d'utilisation : SetModeBus(1, 'Type_SetRoom', 'SetRoom', 22)
// Cette fonction est appelée lorsque l'utilisateur change la température, le mode ou la ventilation

function SetModeBus(IdUnite,Type,idem,valeur)
{

        const params = new URLSearchParams(
        {
            IdUnite: IdUnite,
            GroupeActif: GroupeActive,
            GroupeId: GroupeId,
            Type: Type,
            Idem: idem,
            Valeur: valeur
        });
        
        let responsetext = "";
        fetch('ModbusApi.php?' + params.toString(), {
            method: 'GET'
        })
        .then(response => response.text())
        .then(text => {
            responsetext = text;
        })
        .catch(error => {
            console.error('Erreur lors de l\'appel ModbusApi:', error);
        });
}


// Envoi des données de configuration
// Fonction pour envoyer les données de configuration à l'API
// Utilise fetch pour envoyer les données de configuration à l'API
// Met à jour la table DefUnites avec les nouvelles valeurs
// Utilise la méthode GET pour envoyer les données
// Retourne une promesse qui se résout avec la réponse de l'API
// Exemple d'utilisation de updateTable pour modifier le nom d'une unité
// Met à jour la table DefUnites, champ Name à "bureauF" où Id = 1
// Exemple de mise à jour du nom
// Fonction pour envoyer les données de configuration à l'API
// Utilise fetch pour envoyer les données de configuration à l'API
// Met à jour la table DefUnites avec les nouvelles valeurs
// Utilise la méthode GET pour envoyer les données
// Retourne une promesse qui se résout avec la réponse de l'API

    function updateTable(table, field, value, whereField, whereValue) {

        // Si $GroupeActive est non null, on modifie whereField et whereValue
        <?php if ($GroupeActive !== false && $GroupeActive !== null): ?>
            whereField = 'Gr';
            <?php if ($GroupeId === "all"): ?>
            whereValue = "-1"; // Pour tous les groupes
            <?php else: ?>
            whereValue = <?php echo $GroupeId; ?>;
            <?php endif; ?>
        <?php endif; ?>

        const params = new URLSearchParams({
            table: table,
            field: field,
            value: value,
            whereField: whereField,
            whereValue: whereValue
        });
        return fetch('Base.php?' + params.toString(), {
            method: 'GET'
        })
        .then(response => response.text())
        .catch(err => {
            console.error('API error:', err);
            return null;
        });
    } 

// Fonction pour changer le programme horaire
function ChangeProgramme() 
{
    const select = document.getElementById('schedule-select');
    const selectedValue = select.value;
    
    updateTable('DefUnites', 'Prog', selectedValue, 'Id', IdUnite);
}

// Fonction pour changer le nom de l'appareil
function ChangeName()
{
    const input = document.getElementById('device-name');
    const newName = input.value;
    updateTable('DefUnites', 'Name', newName, 'Id', IdUnite);
    DeviceNameHtml.innerText= newName; // Met à jour le champ pour éviter les erreurs de saisie
}

// Fonction pour changer le groupe de l'appareil
function ChangeGroupe()
{
    const select = document.getElementById('groupe-select');
    const selectedValue = select.value;
    updateTable('DefUnites', 'Gr', selectedValue, 'Id', IdUnite);
}

// Fonction pour changer le Modbus de l'appareil
function ChangeModbus()
{
    const select = document.getElementById('modbus-select');
    const selectedValue = select.value;
    updateTable('DefUnites', 'ModbusId', selectedValue, 'Id', IdUnite);
}

function ChangeDEVICE()
{
    const address = document.getElementById('Device-config').value;
    updateTable('DefUnites', 'Device', address, 'Id', IdUnite);
}

// Fonction pour changer l'adresse et le type OnOff
function ChangeOnOff()
{
    const address = document.getElementById('address-config').value;
    const type = document.getElementById('type-config').value;
    updateTable('DefUnites', 'OnOff', address, 'Id', IdUnite);
    updateTable('DefUnites', 'Type_OnOff', type, 'Id', IdUnite);
}

// Fonction pour changer l'adresse et le type Mode
function ChangeMode()
{
    const address = document.getElementById('mode-address-config').value;
    const type = document.getElementById('mode-type-config').value;
    updateTable('DefUnites', 'Mode', address, 'Id', IdUnite);
    updateTable('DefUnites', 'Type_Mode', type, 'Id', IdUnite);
}
// Fonction pour changer l'adresse et le type Fan
function ChangeFan()
{
    const address = document.getElementById('fan-address-config').value;
    const type = document.getElementById('fan-type-config').value;
    updateTable('DefUnites', 'Fan', address, 'Id', IdUnite);
    updateTable('DefUnites', 'Type_Fan', type, 'Id', IdUnite);
}
// Fonction pour changer l'adresse et le type Temperature
function ChangeTemperature()
{   
    const address = document.getElementById('temp-address-config').value;
    const type = document.getElementById('temp-type-config').value;
    updateTable('DefUnites', 'Room', address, 'Id', IdUnite);
    updateTable('DefUnites', 'Type_Room', type, 'Id', IdUnite);
}
// Fonction pour changer l'adresse et le type Set Temp
function ChangeSetTemp()
{
    const address = document.getElementById('settemp-address-config').value;    
    const type = document.getElementById('settemp-type-config').value;
    updateTable('DefUnites', 'SetRoom', address, 'Id', IdUnite);
    updateTable('DefUnites', 'Type_SetRoom', type, 'Id', IdUnite);
}
// Fonction pour changer l'adresse et le type Alarm 
function ChangeAlarm()
{
    const address = document.getElementById('alarm-address-config').value;
    const type = document.getElementById('alarm-type-config').value;
    updateTable('DefUnites', 'Alarm', address, 'Id', IdUnite);
    updateTable('DefUnites', 'Type_Alarm', type, 'Id', IdUnite);
}
// Fonction pour changer l'adresse et le type Code Alarm
function ChangeCodeAlarm()
{
    const address = document.getElementById('codealarm-address-config').value;
    const type = document.getElementById('codealarm-type-config').value;
    updateTable('DefUnites', 'CodeErreur', address, 'Id', IdUnite);
    updateTable('DefUnites', 'Type_CodeErreur', type, 'Id', IdUnite);
}



</script>
</body>
</html>

