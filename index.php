<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ka Delta</title>
</head>
<body>
    
</body>
</html>

<div class="FrameTitre">

<img src="LogoKaDelta.png" alt="Ka Delta Logo" style="width: 132px; height: auto;">
<h1>Ka Delta Modbus</h1>

<div class="TitreGroupe">
    <button id="btn-groupe" class="groupe-btn">Groupe</button>
    <button class="groupe-btn">Action sur<br>groupe</button>
</div>

    
</head>
<body>

</div>


<hr>



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

for ($Nbmax=1;$Nbmax<256;$Nbmax++)
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
  display:none;
  position:absolute;
  background: rgba(20, 40, 80, 0.98);
  border-radius: 18px;
  box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.37);
  padding: 28px 20px 24px 20px;
  z-index:1000;
  min-width:260px;
  max-width:95vw;
  backdrop-filter: blur(10px);
  border: 1px solid rgba(255,255,255,0.18);
">
  <h4 style="margin-top:0; font-size:1.3em; color:#fff; text-align:center; letter-spacing:1px;">Sélectionnez un groupe</h4>
  <div style="display:flex; flex-direction:column; gap:22px; margin-top:18px;">
      <button class="groupe-item" data-numero="all" style="padding:22px; font-size:1.25em; border-radius:14px; border:none; background:linear-gradient(90deg,#3b82f6,#2563eb); color:#fff; font-weight:700; box-shadow:0 2px 8px #0002; transition:background 0.2s, transform 0.1s; letter-spacing:1px;">
        Toutes les unités
      </button>
      <?php foreach($groupes as $GroupeLoad): ?>
      <button class="groupe-item" data-numero="<?= htmlspecialchars($GroupeLoad["Id"]) ?>" style="padding:22px; font-size:1.25em; border-radius:14px; border:none; background:linear-gradient(90deg,#3b82f6,#2563eb); color:#fff; font-weight:700; box-shadow:0 2px 8px #0002; transition:background 0.2s, transform 0.1s; letter-spacing:1px;">
        <?= htmlspecialchars($GroupeLoad["Groupe"]) ?>
      </button>
    <?php endforeach; ?>
  </div>
</div>

<script>
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
    if (numero === null || numero === 'all') {
      v.style.display = 'block';
    } else {
      if (v.getAttribute('data-groupe') === numero) {
        v.style.display = 'block';
      } else {
        v.style.display = 'none';
      }
    }
  });
}

// Ferme la popup lors de la sélection d'un groupe (avec filtrage)
document.querySelectorAll('.groupe-item').forEach(function(item) {
  item.addEventListener('click', function() {
    popup.style.display = 'none';
    overlay.style.display = 'none';
    // Filtrer par numéro de groupe (attribut data-numero)
    const numero = this.getAttribute('data-numero');
    if (numero === 'all') {
      filtrerParGroupeNumero(null);
    } else {
      filtrerParGroupeNumero(numero);
    }
  });
});
</script>

<script>

function ClimTest(IdSel)
{
  
    OverScreenWunites.style.display = "block";
    OSWunite.src = "Wunites.php?IdSel=" + IdSel;
   
}

async function LoadDatas()
{
    const url = 'DatasUnites.php';
    try {
        const response = await fetch(url);
        if (!response.ok) {
            throw new Error('Erreur réseau');
        }
        const data = await response.json();
        return data;
    } catch (error) {
        console.error('Erreur lors de la récupération des données :', error);
        return null;
    }
}


function UpdateVignettes(data) {
    data.forEach(item => {
        const vignette = document.getElementById("Vig" + item.Id_Unite);
        if (vignette) {
            vignette.querySelector('.ViT2').textContent = item.Room + '°C';
            vignette.querySelector('.ViT3').textContent = item.SetRoom + '°C';
            vignette.querySelector('.ViT4').textContent = item.Fan;
            
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

        //vignette.style.display = "block";

    });
}


function UpLoadData() {
    
    LoadDatas().then(data => {
        if (data) {
            UpdateVignettes(data);
        }
    });
}

setInterval(() => {fetch('Srv.php'); }, 1000);

UpLoadData();
setInterval(UpLoadData,500);



</script>

