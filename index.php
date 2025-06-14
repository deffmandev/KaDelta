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
GROUPE - TOUTS LE GROUPE 
</div>


</div>


<hr>



<div id='OverScreenWunites' class="OverScreen">
    <iframe id='OSWunite' src="" frameborder="0" width="100%" height="100%"></iframe>
</div>

<?php
error_reporting(E_ALL);

include "Style.php";
include "Base.php";

mssqlinfo();


$names = [];
try {
    $result = mssql("SELECT Id,Name FROM DefUnites");
    if ($result) 
        while ($row = sqlnext($result)) 
            $names[$row['Id']] = $row['Name'];
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
<div onclick="ClimTest('.$Nbmax.')" class="Vignette ViOff" id="Vig'.$Nbmax.'">
    <div class="ViT1">'.$names[$Nbmax].'</div>
    <div class="ViT2"></div>
    <div class="ViT3"></div>
    <div class="ViT4"></div>
</div>';
}

echo "</div>";

?>

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

        vignette.style.display = "block";

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

