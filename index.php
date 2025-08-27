<?php include "Style.php";?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Ka Delta</title>
    <!-- Preload images critiques -->
    <link rel="preload" as="image" href="Images/LogoKaDelta.png" />
    <link rel="preload" as="image" href="Images/defaut.png" />
    <link rel="preload" as="image" href="Images/auto.png" />
    <link rel="preload" as="image" href="Images/chaud.png" />
    <link rel="preload" as="image" href="Images/clim.png" />
    <link rel="preload" as="image" href="Images/dry.png" />
    <link rel="preload" as="image" href="Images/fan.png" />
    <link rel="preload" as="image" href="Images/fan1.png" />
    <link rel="preload" as="image" href="Images/fan2.png" />
    <link rel="preload" as="image" href="Images/fan3.png" />
    <link rel="preload" as="image" href="Images/fan4.png" />
    <link rel="preload" as="image" href="Images/standby.png" />
    <script>
        // Préchargement JS (fallback + garanties pour backgrounds CSS)
        (function(){
            const imgs = [
                'Images/LogoKaDelta.png','Images/defaut.png','Images/auto.png','Images/chaud.png','Images/clim.png','Images/dry.png',
                'Images/fan.png','Images/fan1.png','Images/fan2.png','Images/fan3.png','Images/fan4.png','Images/standby.png'
            ];
            imgs.forEach(src=>{ const i=new Image(); i.src=src; });
        })();
    </script>
</head>
<body>
    <?php include 'TopBar.php'; ?>

    <!-- Loader démarrage -->
    <div id="initial-loader" style="position:fixed;inset:0;z-index:3500;display:flex;flex-direction:column;align-items:center;justify-content:center;background:radial-gradient(circle at 40% 40%,rgba(15,23,42,.95),rgba(2,6,23,.98));color:#fff;font-family:sans-serif;letter-spacing:1px;">
        <div style="width:82px;height:82px;border:8px solid #334155;border-top-color:#fff;border-radius:50%;animation:spin 0.85s linear infinite;filter:drop-shadow(0 0 6px #ffffff88);"></div>
        <div style="margin-top:24px;font-size:.85em;text-transform:uppercase;letter-spacing:4px;opacity:.85;">Initialisation...</div>
    </div>
    <style>
        @keyframes spin { to { transform: rotate(360deg); } }
        #initial-loader.fade-out { animation: fadeOut .45s ease forwards; }
        @keyframes fadeOut { to { opacity:0; visibility:hidden; } }
    </style>

    <!-- OverScreen / Popups désormais injectés par TopBar si absents -->

    <?php
    // --- PHP : Chargement des unités ---
    error_reporting(E_ALL);
    include "Base.php";

    $names = [];
    $GroupeIdGr = [];
    try {
        $result = mssql("SELECT Id,Name,Gr FROM DefUnites ORDER BY Id");
        if ($result) {
            while ($row = sqlnext($result)) {
                $names[$row['Id']] = $row['Name'];
                $GroupeIdGr[$row['Id']] = $row['Gr'];
            }
        }
    } catch (Exception $e) {
        die("Erreur lors de la récupération des noms d'unités : " . $e->getMessage());
    }

    echo "<div class='CadreUnites'>";
    if (!empty($names)) {
        $maxId = max(array_keys($names));
        for ($Nbmax = 0; $Nbmax <= $maxId; $Nbmax++) {
            if (!isset($names[$Nbmax])) continue;
            $nom = htmlspecialchars($names[$Nbmax], ENT_QUOTES, 'UTF-8');
            $grp = $GroupeIdGr[$Nbmax];
            echo "<div onclick=\"ClimTest($Nbmax)\" class=\"Vignette ViOff\" id=\"Vig$Nbmax\" data-groupe=\"$grp\">" .
                 "<div class=\"ViT1\">$nom</div>" .
                 "<div class=\"ViT2\"></div>" .
                 "<div class=\"ViT3\"></div>" .
                 "<div class=\"ViT4\"></div>" .
                 "<div class=\"ViT5\">$grp</div>" .
                 "</div>";
        }
    }
    echo "</div>";

    // Groupes
    $groupes = [];
    $basegroupe = mssql('SELECT Id,Groupe FROM Groupe');
    while ($row = sqlnext($basegroupe)) {
        $groupes[] = $row;
    }
    ?>

    <!-- Popups groupes/pages fournis par TopBar -->

    <script>
    // Fonctions globales fournies par TopBar (ClimTest, ClimGroupe...) déjà définies

        // --- Chargement des données unités ---
        let loadDatasPending = false;
        async function LoadDatas() {
            if (loadDatasPending) return null;
            loadDatasPending = true;
            const url = 'DatasUnites.php';
            let timeoutId;
            try {
                const controller = new AbortController();
                timeoutId = setTimeout(() => controller.abort(), 3000);
                const response = await fetch(url, { signal: controller.signal });
                clearTimeout(timeoutId);
                if (!response.ok) throw new Error('Erreur réseau');
                return await response.json();
            } catch (e) {
                if (e.name === 'AbortError') console.error('Timeout 3s LoadDatas'); else console.error('Erreur données :', e);
                return null;
            } finally {
                loadDatasPending = false;
            }
        }

        // Helper: créer une vignette si absente
        function creerVignette(id) {
            const cont = document.querySelector('.CadreUnites');
            if (!cont) return null;
            const d = document.createElement('div');
            d.id = 'Vig' + id;
            d.className = 'Vignette ViOff';
            d.setAttribute('data-groupe', '0');
            d.onclick = () => ClimTest(id);
            d.innerHTML = '<div class="ViT1"></div>'+
                          '<div class="ViT2"></div>'+
                          '<div class="ViT3"></div>'+
                          '<div class="ViT4"></div>'+
                          '<div class="ViT5"></div>';
            cont.appendChild(d);
            return d;
        }

        function UpdateVignettes(data) {
            if (!Array.isArray(data)) return;
            const idsData = new Set(data.map(it => String(it.Id)));

            // Supprimer les vignettes qui n'existent plus
            document.querySelectorAll('.Vignette').forEach(v => {
                const id = v.id.replace('Vig','');
                if (!idsData.has(id)) {
                    v.remove();
                }
            });

            data.forEach(item => {
                let vignette = document.getElementById('Vig' + item.Id);
                if (!vignette) vignette = creerVignette(item.Id);
                if (!vignette) return;
                vignette.querySelector('.ViT1').textContent = item.Name;
                vignette.querySelector('.ViT2').textContent = item.Room + '°C';
                vignette.querySelector('.ViT3').textContent = item.SetRoom + '°C';
                const vi4 = vignette.querySelector('.ViT4');
                vi4.style.display = 'block';
                vi4.className = 'ViT4 fan' + item.Fan;
                vignette.querySelector('.ViT5').textContent = item.Gr;
                vignette.setAttribute('data-groupe', item.Gr);

                // Etat marche / arrêt / modes
                if (item.OnOff === 1) {
                    let cls = 'Vignette ViOnClim';
                    if (item.Mode === 5) cls = 'Vignette ViOnChaud';
                    else if (item.Mode === 3) cls = 'Vignette ViOnFan';
                    else if (item.Mode === 2) cls = 'Vignette ViOnDry';
                    else if (item.Mode === 4) cls = 'Vignette ViOnAuto';
                    vignette.className = cls;
                } else {
                    vignette.className = 'Vignette ViOff';
                    vi4.style.display = 'none';
                }

                // Alarme
                if (item.Alarm === 1) {
                    vignette.className = 'Vignette ViOnDefaut';
                    vignette.querySelector('.ViT2').textContent = 'ALARM';
                    vignette.querySelector('.ViT3').textContent = item.CodeErreur;
                    vi4.style.display = 'none';
                    vi4.textContent = '';
                }
            });

            if (window.filtrerParGroupeNumero)
                window.filtrerParGroupeNumero(window.NumeroDeGroupeValide);
        }

        let firstDataShown = false;
        async function loopUpLoadData() {
            while (true) {
                const d = await LoadDatas();
                if (d) {
                    UpdateVignettes(d);
                    if(!firstDataShown){
                        firstDataShown = true;
                        const ld=document.getElementById('initial-loader');
                        if(ld){ ld.classList.add('fade-out'); setTimeout(()=>ld.remove(), 600); }
                    }
                }
                await new Promise(r => setTimeout(r, 1000));
            }
        }
        // Retire le loader en sécurité au bout de 4s si pas de données
        setTimeout(()=>{ if(!firstDataShown){ const ld=document.getElementById('initial-loader'); if(ld){ ld.classList.add('fade-out'); setTimeout(()=>ld.remove(),600); } } }, 4000);
        loopUpLoadData();

    </script>
</body>
</html>

