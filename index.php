<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ka Delta</title>
</head>
<body>

<div class="FrameTitre">

<img src="Images/LogoKaDelta.png" alt="Ka Delta Logo" style="height: 172px;">
<h1>Ka Delta Modbus</h1>

<div class="TitreGroupe">
  <button id="btn-groupe" class="groupe-btn">Groupe</button>
  <button onclick="ClimGroupe()" class="groupe-btn">Action sur<br>groupe</button>
  <button id="fullscreen-btn" class="groupe-btn">Plein écran</button>
  <button id="btn-programmation" class="groupe-btn">Programmation</button>
  <button onclick="MaintenanceWindows()" class="groupe-btn">Maintenance</button>
</div>


<!-- Affichage la Date et l'heures -->
<div class="horloges">
  <span id="date"></span><br>
  <span id="heure" style="font-variant-numeric: tabular-nums;"><span id="h"></span><span id="colon" style="transition:0.4s;opacity: 0;">:</span><span id="m"></span></span>
</div>
<script>
function updateDateHeure() {
  const now = new Date();
  const options = { weekday: 'long', year: 'numeric', month: 'numeric', day: 'numeric' };
  const dateStr = now.toLocaleDateString('fr-FR', options);

  // Sépare les heures et minutes, le clignotement ne déplace pas les chiffres
  const minutes = now.getMinutes().toString().padStart(2, '0');
  const heures = now.getHours().toString().padStart(2, '0');
  const showColon = now.getSeconds() % 2 === 0 ? '1' : '0'; // espace insécable
  document.getElementById('date').textContent = dateStr;
  document.getElementById('h').textContent = heures;
  document.getElementById('colon').style.opacity = showColon === '1' ? '1' : '0';
  document.getElementById('m').textContent = minutes;
}
updateDateHeure();
setInterval(updateDateHeure, 1000);
</script>



<script>
document.getElementById('fullscreen-btn').addEventListener('click', function() {
  if (!document.fullscreenElement) {
    document.documentElement.requestFullscreen();
  } else {
    document.exitFullscreen();
  }
});
</script>

    
</head>
<body>

</div>




<div id='OverScreenWunites' class="OverScreen">
    <iframe id='OSWunite' src="" frameborder="0" width="100%" height="100%"></iframe>
</div>

<?php
error_reporting(E_ALL);

include "Style.php";
include "Base.php";

$names = [];
$GroupeIdGr = [];
try {
    $result = mssql("SELECT Id,Name,Gr FROM DefUnites");
    if ($result) 
          while ($row = sqlnext($result))
        { 
              $names[$row['Id']] = $row['Name'];
              $GroupeIdGr[$row['Id']] = $row['Gr'];
        }
    }  
    catch (Exception $e) 
    {
    die("Erreur lors de la récupération des noms d'unités : " . $e->getMessage());
    }
    
echo "<div class='CadreUnites'>";

for ($Nbmax=1;$Nbmax<4000;$Nbmax++)
{
    if (isset($names[$Nbmax])) 
echo '
<div onclick="ClimTest('.$Nbmax.')" class="Vignette ViOff" id="Vig'.$Nbmax.'" data-groupe="'.$GroupeIdGr[$Nbmax].'">
    <div class="ViT1">'.$names[$Nbmax].'</div>
    <div class="ViT2"></div>
    <div class="ViT3"></div>
    <div class="ViT4"></div>
    <div class="ViT5">'.$GroupeIdGr[$Nbmax].'</div>
</div>';

else

echo '
<div onclick="ClimTest('.$Nbmax.')" class="Vignette ViOff" id="Vig'.$Nbmax.'" data-groupe="0" style="display:none;">
    <div class="ViT1">'.$Nbmax.'</div>
    <div class="ViT2"></div>
    <div class="ViT3"></div>
    <div class="ViT4"></div>
    <div class="ViT5">0</div>
</div>';

}


echo "</div>";

?>


<?php

$groupes =[];
$basegroupe=mssql('SELECT Id,Groupe FROM Groupe');
while ($row=sqlnext($basegroupe))
    $groupes[]=$row;

?>


<!-- Overlay sombre -->
<div id="overlay-groupe" style="
  display:none;
  position:fixed;
  top:0; left:0; width:100vw; height:100vh;
  background: rgba(111, 124, 150, 0.47);
  z-index:999;
"></div>

<!-- Fenêtre popup cachée par défaut -->
<div id="popup-groupe" style="
  display: none;
  position: absolute;
  background: rgba(20, 40, 80, 0.98);
  border-radius: 18px;
  box-shadow: rgba(31, 38, 135, 0.25) 0px 8px 24px 0px;
  padding: 20px 20px 24px;
  z-index: 1000;
  min-width: 700px;
  max-width: 90vw;
  backdrop-filter: blur(10px);
  border: 1.5px solid rgba(255, 255, 255, 0.18);
  ">
  <h4 style="margin-top:0; font-size:1.15em; color:#fff; text-align:center; letter-spacing:1px;">Sélectionnez un groupe</h4>
  <div id="groupe-list" style="display:flex; flex-direction:column; gap:18px; margin-top:16px;"></div>
</div>

<script>
// Gestion de la popup pour la sélection de groupe
const btn = document.getElementById('btn-groupe');
const popup = document.getElementById('popup-groupe');
const overlay = document.getElementById('overlay-groupe');

// Affiche la popup sous la souris
btn.addEventListener('click', function(e) {
  overlay.style.display = 'block';
  popup.style.display = 'block';

  // Positionne la popup sous la souris
  let x = e.clientX;
  let y = e.clientY;

  // Ajuste si la popup dépasse l'écran
  const popupRect = popup.getBoundingClientRect();
  const padding = 16;
  if (x + popupRect.width > window.innerWidth - padding) {
    x = window.innerWidth - popupRect.width - padding;
  }
  if (y + popupRect.height > window.innerHeight - padding) {
    y = window.innerHeight - popupRect.height - padding;
  }

  popup.style.left = x + 'px';
  popup.style.top = y + 'px';
});

// Ferme la popup si on clique sur l'overlay ou ailleurs
overlay.addEventListener('click', function() {
  popup.style.display = 'none';
  overlay.style.display = 'none';
});
document.addEventListener('click', function(e) {
  if (!popup.contains(e.target) && e.target !== btn && e.target !== overlay) {
    popup.style.display = 'none';
    overlay.style.display = 'none';
  }
});

// Ferme la popup lors de la sélection d'un groupe (sans alerte)
document.querySelectorAll('.groupe-item').forEach(function(item) {
  item.addEventListener('click', function() {
    popup.style.display = 'none';
    overlay.style.display = 'none';
  });
});

// Effet tactile sur les boutons (pour retour visuel)
document.querySelectorAll('.groupe-item').forEach(function(item) {
  item.addEventListener('touchstart', function() {
    this.style.transform = 'scale(0.97)';
    this.style.background = 'linear-gradient(90deg,#2563eb,#1e40af)';
  });
  item.addEventListener('touchend', function() {
    this.style.transform = '';
    this.style.background = 'linear-gradient(90deg,#3b82f6,#2563eb)';
  });
  item.addEventListener('mousedown', function() {
    this.style.transform = 'scale(0.97)';
    this.style.background = 'linear-gradient(90deg,#2563eb,#1e40af)';
  });
  item.addEventListener('mouseup', function() {
    this.style.transform = '';
    this.style.background = 'linear-gradient(90deg,#3b82f6,#2563eb)';
  });
});


// Nouvelle fonction pour filtrer par numéro de groupe
function filtrerParGroupeNumero(numero) {
  const vignettes = document.querySelectorAll('.Vignette');
  vignettes.forEach(v => {
    if (numero === null || numero === 'all' && v.getAttribute('data-groupe') !== "0") {
      v.style.display = 'block';
    } else {
      if (v.getAttribute('data-groupe') === numero && v.getAttribute('data-groupe') !== "0") {
        v.style.display = 'block';
      } else {
        v.style.display = 'none';
      }
    }
  });
}
NumeroDeGroupeValide="all"
// Filtrer par défaut pour afficher toutes les unités
// Ferme la popup lors de la sélection d'un groupe (avec filtrage)
document.querySelectorAll('.groupe-item').forEach(function(item) {
  item.addEventListener('click', function() {
    popup.style.display = 'none';
    overlay.style.display = 'none';
    // Filtrer par numéro de groupe (attribut data-numero)
    const numero = this.getAttribute('data-numero');
    NumeroDeGroupeValide= numero; // Mettre à jour la variable globale
    if (numero === 'all') {
      filtrerParGroupeNumero('all');
    } else {
      filtrerParGroupeNumero(numero);
    }
  });
});
</script>

<script>
window.groupesData = [
  { id: 'all', label: 'Toutes les unités' },
  ...<?php echo json_encode(array_map(function($g){return ["id"=>$g["Id"],"label"=>$g["Groupe"]];}, $groupes)); ?>
];
const groupeListDiv = document.getElementById('groupe-list');
function renderGroupeButtons() {
  groupeListDiv.innerHTML = '';
  let groupes = window.groupesData;
  if (groupes.length > 24) {
    // 3 colonnes
    popup.style.minWidth = '700px';
    popup.style.width = '';
    groupeListDiv.style.flexDirection = 'row';
    groupeListDiv.style.flexWrap = 'wrap';
    groupeListDiv.style.gap = '18px';
    groupeListDiv.style.justifyContent = 'center';
    let col1 = document.createElement('div');
    let col2 = document.createElement('div');
    let col3 = document.createElement('div');
    [col1, col2, col3].forEach(col => {
      col.style.display = 'flex';
      col.style.flexDirection = 'column';
      col.style.gap = '18px';
      col.style.width = '320px';
    });
    let third = Math.ceil(groupes.length/3);
    groupes.forEach((g,i)=>{
      let btn = document.createElement('button');
      btn.className = 'groupe-item';
      btn.setAttribute('data-numero', g.id);
      btn.style = 'width:100%; box-sizing:border-box; padding:20px; font-size:1.15em; border-radius:14px; border:none; background:linear-gradient(90deg,#3b82f6,#2563eb); color:#fff; font-weight:700; box-shadow:0 2px 8px #0002; transition:background 0.2s, transform 0.1s; letter-spacing:1px;';
      btn.textContent = g.label;
      if (i < third) col1.appendChild(btn);
      else if (i < 2*third) col2.appendChild(btn);
      else col3.appendChild(btn);
    });
    groupeListDiv.appendChild(col1);
    groupeListDiv.appendChild(col2);
    groupeListDiv.appendChild(col3);
  } else if (groupes.length > 12) {
    // 2 colonnes
    popup.style.minWidth = '700px';
    popup.style.width = '';
    groupeListDiv.style.flexDirection = 'row';
    groupeListDiv.style.flexWrap = 'wrap';
    groupeListDiv.style.gap = '18px';
    groupeListDiv.style.justifyContent = 'center';
    let col1 = document.createElement('div');
    let col2 = document.createElement('div');
    col1.style.display = col2.style.display = 'flex';
    col1.style.flexDirection = col2.style.flexDirection = 'column';
    col1.style.gap = col2.style.gap = '18px';
    col1.style.width = col2.style.width = '320px';
    let half = Math.ceil(groupes.length/2);
    groupes.forEach((g,i)=>{
      let btn = document.createElement('button');
      btn.className = 'groupe-item';
      btn.setAttribute('data-numero', g.id);
      btn.style = 'width:100%; box-sizing:border-box; padding:20px; font-size:1.15em; border-radius:14px; border:none; background:linear-gradient(90deg,#3b82f6,#2563eb); color:#fff; font-weight:700; box-shadow:0 2px 8px #0002; transition:background 0.2s, transform 0.1s; letter-spacing:1px;';
      btn.textContent = g.label;
      (i<half?col1:col2).appendChild(btn);
    });
    groupeListDiv.appendChild(col1);
    groupeListDiv.appendChild(col2);
  } else {
    // 1 colonne
    popup.style.minWidth = '';
    popup.style.width = '320px';
    groupeListDiv.style.flexDirection = 'column';
    groupeListDiv.style.flexWrap = 'nowrap';
    groupes.forEach(g=>{
      let btn = document.createElement('button');
      btn.className = 'groupe-item';
      btn.setAttribute('data-numero', g.id);
      btn.style = 'width:320px; box-sizing:border-box; padding:20px; font-size:1.15em; border-radius:14px; border:none; background:linear-gradient(90deg,#3b82f6,#2563eb); color:#fff; font-weight:700; box-shadow:0 2px 8px #0002; transition:background 0.2s, transform 0.1s; letter-spacing:1px;';
      btn.textContent = g.label;
      groupeListDiv.appendChild(btn);
    });
  }
}
renderGroupeButtons();
</script>

<script>
// Remplacer tous les querySelectorAll('.groupe-item') par une délégation d'événement :
groupeListDiv.addEventListener('click', function(e) {
  if (e.target.classList.contains('groupe-item')) {
    popup.style.display = 'none';
    overlay.style.display = 'none';
    const numero = e.target.getAttribute('data-numero');
    NumeroDeGroupeValide= numero;
    if (numero === 'all') {
      filtrerParGroupeNumero('all');
    } else {
      filtrerParGroupeNumero(numero);
    }
  }
});
</script>

<script>

function ClimTest(IdSel)
{
  
    OverScreenWunites.style.display = "block";
    OSWunite.src = "Wunites.php?IdSel=" + IdSel;
   
}

function ClimGroupe()
{
    OverScreenWunites.style.display = "block";
    OSWunite.src = "Wunites.php?Groupe=" + NumeroDeGroupeValide;

}

function MaintenanceWindows()
{
    OverScreenWunites.style.display = "block";
    OSWunite.src = "Maintenance.php";
}



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
        if (!response.ok) {
            throw new Error('Erreur réseau');
        }
        const data = await response.json();
        return data;
    } catch (error) {
        if (error.name === 'AbortError') {
            console.error('Timeout de 3s dépassé pour LoadDatas');
        } else {
            console.error('Erreur lors de la récupération des données :', error);
        }
        return null;
    } finally {
        loadDatasPending = false;
    }
}


function UpdateVignettes(data) {
    // Si data contient une erreur, on arrête la fonction
    if (data && typeof data === 'object' && 'error' in data) return;
    

    // Met à jour ou masque chaque vignette selon la présence dans data
    const idsExistants = new Set(data.map(item => String(item.Id)));
    document.querySelectorAll('.Vignette').forEach(v => {
        const id = v.id.replace('Vig', '');
        if (!idsExistants.has(id)) {
            v.style.display = 'none';
            // Efface le contenu des cases
            let t1 = v.querySelector('.ViT1');
            let t2 = v.querySelector('.ViT2');
            let t3 = v.querySelector('.ViT3');
            let t4 = v.querySelector('.ViT4');
            let t5 = v.querySelector('.ViT5');
            if (t1) t1.textContent = '';
            if (t2) t2.textContent = '';
            if (t3) t3.textContent = '';
            if (t4) t4.textContent = '';
            if (t5) t5.textContent = '';
            v.setAttribute('data-groupe', '0');
        } else {
            v.style.display = 'block';
        }
    });
    data.forEach(item => {
        const vignette = document.getElementById("Vig" + item.Id);
        if (vignette) {
            vignette.querySelector('.ViT1').textContent = item.Name;
            vignette.querySelector('.ViT2').textContent = item.Room + '°C';
            vignette.querySelector('.ViT3').textContent = item.SetRoom + '°C';
            vignette.querySelector('.ViT4').textContent = item.Fan;
            vignette.querySelector('.ViT5').textContent = item.Gr;
            vignette.setAttribute('data-groupe', item.Gr);
            filtrerParGroupeNumero(NumeroDeGroupeValide);
            // Mettez à jour la classe de la vignette en fonction de l'état
            if (item.OnOff === 1) {
                vignette.className = "Vignette ViOnClim";
                if (item.Mode === 5) vignette.className = "Vignette ViOnChaud";
                if (item.Mode === 3) vignette.className = "Vignette ViOnDefaut";
                if (item.Mode === 2) vignette.className = "Vignette ViChaud";
            } else {
                vignette.className = "Vignette ViOff";
            }
        }
        if (item.Alarm === 1) {
            vignette.className = "Vignette ViOnDefaut";
            vignette.querySelector('.ViT2').textContent = "ALARM";
            vignette.querySelector('.ViT3').textContent = item.CodeErreur;
            vignette.querySelector('.ViT4').textContent = "";
        }
    });
}


function UpLoadData() {
    
    LoadDatas().then(data => {
        if (data) {
            UpdateVignettes(data);
        }
    });
}

// Boucle asynchrone pour attendre la réponse avant de relancer l'appel API
async function loopUpLoadData() {
    while (true) {
        await UpLoadData();
        await new Promise(resolve => setTimeout(resolve, 400)); // 1 seconde d'attente
    }
}
loopUpLoadData();



</script>

<script>
// Rafraîchit dynamiquement la liste des groupes (popup) après modification dans Gtgroupe.php
window.reloadGroupes = async function() {
  try {
    const response = await fetch('DatasUnites.php?groupes=1');
    if (!response.ok) return;
    const data = await response.json();
    if (Array.isArray(data.groupes)) {
      window.groupesData = [
        { id: 'all', label: 'Toutes les unités' },
        ...data.groupes.map(g => ({ id: g.Id, label: g.Groupe }))
      ];
      renderGroupeButtons();
    }
  } catch (e) { /* ignore */ }
}

// Détecte si une modale quelconque est ouverte (OverScreen, overlay-groupe, popup-groupe)
function isAnyModalOpen() {
  // Vérifie la présence d'une modale visible
  const overScreen = document.getElementById('OverScreenWunites');
  const overlayGroupe = document.getElementById('overlay-groupe');
  const popupGroupe = document.getElementById('popup-groupe');
  // Ajoutez ici d'autres modales si besoin
  return (
    (overScreen && overScreen.style.display !== 'none' && overScreen.style.display !== '') ||
    (overlayGroupe && overlayGroupe.style.display !== 'none' && overlayGroupe.style.display !== '') ||
    (popupGroupe && popupGroupe.style.display !== 'none' && popupGroupe.style.display !== '')
  );
}

window.groupeEditionActive = false;
let lastGroupesHash = null;
async function checkGroupesChange() {
  if (window.groupeEditionActive || isAnyModalOpen()) return;
  try {
    const response = await fetch('DatasUnites.php?groupes=1');
    if (!response.ok) return;
    const data = await response.json();
    if (Array.isArray(data.groupes)) {
      const hash = data.groupes.length + ':' + data.groupes.map(g => g.Id + '-' + g.Groupe).join('|');
      if (lastGroupesHash !== null && hash !== lastGroupesHash) {
        location.reload();
      }
      lastGroupesHash = hash;
    }
  } catch (e) { /* ignore */ }
}
setInterval(checkGroupesChange, 4000);
</script>
<script>
// Ajout ouverture Programme.php en plein écran via overlay
function openProgrammation() {
  document.getElementById('OverScreenWunites').style.display = "block";
  document.getElementById('OSWunite').src = "Programme.php";
}
document.getElementById('btn-programmation').onclick = openProgrammation;
</script>
</body>
</html>

