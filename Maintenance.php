<?php
// ...existing code...
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Menu Maintenance</title>
    <style>
        @keyframes fondu-enchaine {
            from { opacity: 0; }
            to   { opacity: 1; }
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
            transition: opacity 0.2s;
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
            animation: fondu-enchaine 0.4s ease;
        }
        .close-btn {
            position: absolute;
            top: 18px;
            right: 18px;
            background: transparent;
            border: none;
            font-size: 2em;
            cursor: pointer;
            color: #888;
            transition: color 0.2s;
        }
        .close-btn:hover {
            color: #1976d2;
        }
        .modal-menu {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-top: 2em;
        }
        .modal-menu button {
            padding: 1.2em;
            font-size: 1.1em;
            border: none;
            border-radius: 10px;
            background: #1976d2;
            color: #fff;
            cursor: pointer;
            font-weight: 600;
            transition: background 0.2s, transform 0.1s;
        }
        .modal-menu button:hover {
            background: #1565c0;
            transform: scale(1.04);
        }
        @media (max-width: 480px) {
            .modal {
                min-width: 90vw;
                padding: 1.2em 0.5em 1em 0.5em;
            }
            .modal-menu {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
<div class="modal-bg" id="modal-bg">
    <div class="modal" id="main-modal">
        <button class="close-btn" id="close-modal-btn" title="Fermer">&times;</button>
        <h2 style="margin-bottom:30px;">Menu Maintenance</h2>
        <div class="modal-menu">
            <button onclick="">Interface Lennox 109</button>
            <button onclick="openModbusIframe()">Gestion ModBus</button>
            <button onclick="openGroupeIframe()">Gestion Groupe</button>
            <button onclick="openAjouteUniteLgIframe()">Ajoute Unité LG</button>
            <button onclick="openContactIframe()">Contact défaut</button>
        </div>
    </div>
</div>

<!-- Modal pour l'iframe configsend.php (plein écran) -->
<div class="modal-bg" id="contact-iframe-bg" style="z-index:10004; display:none; background:rgba(0,0,0,0.35);">
    <div class="modal" id="contact-iframe-modal" style="width:100vw;height:100vh;max-width:100vw;max-height:100vh;padding:0;border-radius:0;overflow:hidden;background:transparent;box-shadow:none;">
        <iframe src="" style="width:100vw;height:100vh;border:none;display:block;position:absolute;top:0;left:0;background:transparent;"></iframe>
    </div>
    
</div>
<!-- Modal pour l'iframe AjouteUniteLg.php -->
<div class="modal-bg" id="ajoute-lg-iframe-bg" style="z-index:10003; display:none; background:rgba(0,0,0,0.35);">
    <div class="modal" id="ajoute-lg-iframe-modal" style="width:100vw;height:100vh;max-width:100vw;max-height:100vh;padding:0;border-radius:0;overflow:hidden;background:transparent;box-shadow:none;">
        <iframe src="" style="width:100vw;height:100vh;border:none;display:block;position:absolute;top:0;left:0;background:transparent;"></iframe>
    </div>
</div>
<div class="modal-bg" id="iframe-modal-bg" style="z-index:10000; display:none;">
    <div class="modal" id="iframe-modal" style="height: 724px;width: 853px;padding: 36px;overflow: hidden;">
        <button class="close-btn" id="close-iframe-btn" title="Fermer" style="top:18px; right:18px; background:transparent; border:none; padding:0; cursor:pointer;">
            <span style="
                display:inline-flex;
                align-items:center;
                justify-content:center;
                width:48px;
                height:48px;
                background:#d32f2f;
                border-radius:50%;
                color:#fff;
                font-size:2.5em;
                font-weight:bold;
                box-shadow:0 2px 12px rgba(211,47,47,0.28);
                border:3px solid #fff;
                transition:background 0.2s;
                border-color:#b71c1c;
            ">&times;</span>
        </button>
        <iframe src="" style="    
        width: 100%;
        height: 100%;
        border: none;
        display: flex;
        overflow: hidden;
        align-items: center;
        justify-content: center;
        align-content: center;
    "></iframe>
    </div>
</div>

<!-- Modal pour l'iframe GtModbus.php (plein écran) -->
<div class="modal-bg" id="modbus-iframe-bg" style="z-index:10001; display:none; background:rgba(0,0,0,0.35);">
    <div class="modal" id="modbus-iframe-modal" style="width:100vw;height:100vh;max-width:100vw;max-height:100vh;padding:0;border-radius:0;overflow:hidden;background:transparent;box-shadow:none;">
        <iframe src="" style="width:100vw;height:100vh;border:none;display:block;position:absolute;top:0;left:0;background:transparent;"></iframe>
    </div>
</div>
<!-- Modal pour l'iframe Gtgroupe.php (plein écran) -->
<div class="modal-bg" id="groupe-iframe-bg" style="z-index:10002; display:none; background:rgba(0,0,0,0.35);">
    <div class="modal" id="groupe-iframe-modal" style="width:100vw;height:100vh;max-width:100vw;max-height:100vh;padding:0;border-radius:0;overflow:hidden;background:transparent;box-shadow:none;">
        <iframe src="" style="width:100vw;height:100vh;border:none;display:block;position:absolute;top:0;left:0;background:transparent;"></iframe>
    </div>
</div>

<script>
    // Ouvre la modale iframe pour AjouteUniteLg.php
    function openAjouteUniteLgIframe() {
        var ajouteBg = document.getElementById('ajoute-lg-iframe-bg');
        var ajouteModal = document.getElementById('ajoute-lg-iframe-modal');
        ajouteBg.style.display = 'flex';
        setTimeout(() => {
            ajouteBg.classList.add('visible');
            ajouteModal.querySelector('iframe').src = 'AjouteUniteLg.php';
        }, 5);
    }
    // Plus de croix rouge pour fermer la modale AjouteUniteLg.php
    // Ouvre la modale iframe quand on clique sur "Interface Lennox 109"
    document.querySelectorAll('.modal-menu button')[0].onclick = function() {
        document.getElementById('iframe-modal-bg').style.display = 'flex';
        setTimeout(() => {
            document.getElementById('iframe-modal-bg').classList.add('visible');
            document.querySelector('#iframe-modal iframe').src = 'proxy109.php';
        }, 5);
    };
    // Ferme la modale iframe
    document.getElementById('close-iframe-btn').onclick = function() {
        document.getElementById('iframe-modal-bg').classList.remove('visible');
        setTimeout(() => {
            document.getElementById('iframe-modal-bg').style.display = 'none';
        }, 200);
    };

    // Ouvre la modale iframe en plein écran pour Gestion Modbus
    function openModbusIframe() {
        var modbusBg = document.getElementById('modbus-iframe-bg');
        var modbusModal = document.getElementById('modbus-iframe-modal');
        modbusBg.style.display = 'flex';
        setTimeout(() => {
            modbusBg.classList.add('visible');
            modbusModal.querySelector('iframe').src = 'GtModbus.php';
        }, 5);
    }

    // Ouvre la modale iframe en plein écran pour Gestion Groupe
    function openGroupeIframe() {
        var groupeBg = document.getElementById('groupe-iframe-bg');
        var groupeModal = document.getElementById('groupe-iframe-modal');
        groupeBg.style.display = 'flex';
        setTimeout(() => {
            groupeBg.classList.add('visible');
            groupeModal.querySelector('iframe').src = 'Gtgroupe.php';
        }, 5);
    }
    // Ouvre la modale iframe en plein écran pour Contact défaut (configsend.php)
    function openContactIframe() {
        var bg = document.getElementById('contact-iframe-bg');
        var modal = document.getElementById('contact-iframe-modal');
        bg.style.display = 'flex';
        setTimeout(() => {
            bg.classList.add('visible');
            modal.querySelector('iframe').src = 'configsend.php';
        }, 5);
    }
    // Ferme la modale iframe Gestion Groupe depuis l'intérieur de Gtgroupe.php
    // (déjà géré par le script de Gtgroupe.php via window.parent)

    // Animation ouverture
    const modalBg = document.getElementById('modal-bg');
    window.addEventListener('DOMContentLoaded', () => {
        setTimeout(() => {
            modalBg.classList.add('visible');
        }, 5);
    });

    // Fermeture modale
    document.getElementById('close-modal-btn').onclick = () => {
        modalBg.classList.remove('visible');
        setTimeout(() => {
            modalBg.style.display = 'none';
            window.parent.OverScreenWunites && (window.parent.OverScreenWunites.style.display = "none");
        }, 200);
    };

</script>
</body>
</html>