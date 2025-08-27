<?php
include 'base.php'; 

// Récupération de la liste MODBUS via base.php
$mods = [];
$res = mssql("SELECT Id, Nom FROM DefModBus");
if ($res) {
    while($row = sqlnext($res)) 
        $mods[] = [ 'Id' => $row['Id'], 'Nom' => $row['Nom'] ];
}
// Récupération de la liste des groupes via base.php
$groupes = [];
$resg = mssql("SELECT Id, Groupe FROM Groupe");
if ($resg) {
    while($row = sqlnext($resg)) 
        $groupes[] = [ 'Id' => $row['Id'], 'Nom' => $row['Groupe'] ];
}
?>
<style>
#modal-bg-lg {
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0,0,0,0.35);
    z-index: 1000;
    display: flex;
    align-items: center;
    justify-content: center;
    animation: fadeInBg 0.3s;
}
@keyframes fadeInBg {
    from { background: rgba(0,0,0,0); }
    to { background: rgba(0,0,0,0.35); }
}
#modal-lg {
    background: linear-gradient(135deg, #f8fafc 60%, #e0e7ef 100%);
    border-radius: 14px;
    box-shadow: 0 8px 32px #0002, 0 1.5px 0 #3498db;
    width: 420px;
    padding: 32px 32px 22px 32px;
    position: relative;
    display: flex;
    flex-direction: column;
    gap: 22px;
    border: 1.5px solid #3498db;
    animation: popIn 0.25s;
}
@keyframes popIn {
    from { transform: scale(0.95); opacity: 0; }
    to { transform: scale(1); opacity: 1; }
}
#close-lg {
    position: absolute;
    top: 12px; right: 18px;
    font-size: 26px;
    color: #3498db;
    font-weight: bold;
    cursor: pointer;
    transition: color 0.2s;
}
#close-lg:hover { color: #d33; }
#modal-lg h2 {
    margin: 0 0 10px 0;
    color: #3498db;
    font-size: 1.35em;
    letter-spacing: 1px;
    text-align: center;
}
#modal-lg label {
    font-weight: 600;
    color: #3498db;
    margin-bottom: 8px;
    margin-top: 8px;
    display: block;
    letter-spacing: 0.5px;
}
#modal-lg input, #modal-lg select {
    width: 100%;
    padding: 8px 10px;
    border: 1.5px solid #b5c6d6;
    border-radius: 5px;
    margin-top: 2px;
    font-size: 1em;
    background: #fafdff;
    transition: border 0.2s;
}
#modal-lg input:focus, #modal-lg select:focus {
    border-color: #3498db;
    outline: none;
}
#valide-lg {
    background: linear-gradient(90deg, #3498db 60%, #2980b9 100%);
    color: #fff;
    border: none;
    border-radius: 5px;
    padding: 12px;
    font-size: 1.1em;
    font-weight: 600;
    cursor: pointer;
    margin-top: 0;
    box-shadow: 0 2px 8px #3498db22;
    transition: background 0.2s, box-shadow 0.2s;
    align-self: flex-end;
    margin-left: auto;
}
#valide-lg:hover {
    background: linear-gradient(90deg, #2980b9 60%, #3498db 100%);
    box-shadow: 0 4px 16px #3498db44;
}
</style>

        <?php
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            ?>
            <script>
                if(window.parent && window.parent.document)
                    {
                    var bg = window.parent.document.getElementById("ajoute-lg-iframe-bg");
                    if(bg) setTimeout(function(){ bg.style.display = "none"; }, 1);
                    }
            </script>
            <?php
        }
        ?>


<?php if ($_SERVER['REQUEST_METHOD'] !== 'POST') : ?>
<div id="modal-bg-lg">
  <div id="modal-lg">
    <span id="close-lg">&times;</span>
    <h2>Ajouter une unité LG</h2>
    <form method="post" id="form-lg" autocomplete="off" style="display: flex; flex-direction: column; gap: 18px;">
      <label>Nom</label>
      <input type="text" name="nom" required autocomplete="off" maxlength="12">
      <label>Adresse (Hex)</label>
      <input type="text" name="adresse" pattern="^[0-9A-Fa-f]+$" maxlength="4" required autocomplete="off">
      <label>MODBUS</label>
      <select name="modbus" required>
        <option value="">Sélectionner</option>
        <?php foreach($mods as $mod): ?>
          <option value="<?=htmlspecialchars($mod['Id'])?>"><?=htmlspecialchars($mod['Nom'])?></option>
        <?php endforeach; ?>
      </select>
      <label>Groupe</label>
      <select name="groupe" required>
        <option value="">Sélectionner</option>
        <?php foreach($groupes as $groupe): ?>
          <option value="<?=htmlspecialchars($groupe['Id'])?>"><?=htmlspecialchars($groupe['Nom'])?></option>
        <?php endforeach; ?>
      </select>
      <label>Numéro de device</label>
      <input type="number" name="device" min="1" max="255" required autocomplete="off">
      <button id="valide-lg" type="submit">Valider</button>
    </form>
  </div>
</div>

<script>
document.getElementById('close-lg').onclick = function() {
    document.getElementById('modal-bg-lg').style.display = 'none';
    // Ferme aussi la modale parent si appelée en iframe
    if(window.parent && window.parent.document){
        var bg = window.parent.document.getElementById("ajoute-lg-iframe-bg");
        if(bg) {
            bg.classList.remove("visible");
            setTimeout(function(){ bg.style.display = "none"; }, 200);
        }
    }
};
window.onload = function() {
    document.querySelector('#modal-lg input[name=nom]').focus();
};
</script>
<?php endif; ?>

<?php
// Traitement du formulaire avec les fonctions de base.php
if ($_SERVER['REQUEST_METHOD'] === 'POST') 
    {
    $nom = $_POST['nom'] ?? '';
    $Id = $_POST['adresse'] ?? '';
    $adresse = $_POST['adresse'] ?? '';
    $modbus = $_POST['modbus'] ?? '';
    $device = $_POST['device'] ?? '';
    $groupe = $_POST['groupe'] ?? '';
    if ($nom && $adresse && $modbus && $device) 
        {
        $adresse = strtoupper($adresse);
        $adresse = hexdec($adresse);
        $AddresseBase=(($adresse)*16); 
        $data=
    [
       'Id'             => $Id
      ,'SF'             => 1
      ,'Gr'             => $groupe
      ,'ModbusId'       => $modbus
      ,'Device'         => $device
      ,'Name'           => $nom
      ,'Type_OnOff'     => 1
      ,'OnOff'          => $AddresseBase+0
      ,'Type_Alarm'     => 1
      ,'Alarm'          => $AddresseBase+6
      ,'Type_Mode'      => 3
      ,'Mode'           => $AddresseBase+0
      ,'Type_Fan'       => 3
      ,'Fan'            => $AddresseBase+1
      ,'Type_Room'      => 3
      ,'Room'           => $AddresseBase+5
      ,'Type_SetRoom'   => 3
      ,'SetRoom'        => $AddresseBase+2
      ,'Type_CodeErreur'=> 3
      ,'CodeErreur'     => $AddresseBase+6
      ,'LimiteClimH'    => 32
      ,'LimiteClimB'    => 10
      ,'LimiteChaudH'   => 32
      ,'LimiteChaudB'   => 10
      ,'Prog' => 0
      ,'Com' => 'auto'
    ];

        mssql_insert('DefUnites', $data);

        }

        
    }

?>