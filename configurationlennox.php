<?php
include "base.php";



// Sauvegarde la configuration reçue en POST dans configurationlennox.json
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    if (is_array($data) && isset($data['modbus'], $data['device1'], $data['device2'], $data['device3'])) {
        file_put_contents('configurationlennox.json', json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
        exit;
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Données invalides']);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Configuration Lennox</title>
<style>
body { font-family: Arial, Helvetica, sans-serif; margin:0; background: rgba(0,0,0,0.2); }
.modal-wrap { width:100vw; height:100vh; display:flex; align-items:center; justify-content:center; backdrop-filter: blur(12px); position:fixed; top:0; left:0; }
.modal { width:640px; max-width:95vw; background:#fff; border-radius:16px; padding:24px; box-shadow:0 12px 60px rgba(0,0,0,0.4); }
.row { display:flex; gap:10px; margin-bottom:16px; align-items:center; }
label { width:213px; font-weight:700; color:#193cf1; font-size:1.33em; }
select, button { flex:1; padding:10px 13px; border-radius:10px; border:1.33px solid #ccc; font-size:1.33em; }
.btn-validate { background: linear-gradient(90deg,#3b82f6,#2563eb); color:#fff; border:none; font-weight:700; font-size:1.33em; }
</style>
</head>
<body>
<div class="modal-wrap">
  <div class="modal">
    <h3>Configuration Lennox</h3>
    <div class="row">
      <label for="sel-modbus">Sélection Modbus</label>
      <select id="sel-modbus">
        <?php
        $modbusOptions = [];
        $sql = "SELECT Id, Nom FROM DefModBus";
        $stmt = mssql($sql);
        if ($stmt) {
          while ($row = sqlnext($stmt)) {
            $id = htmlspecialchars($row['Id']);
            $nom = htmlspecialchars($row['Nom']);
            echo "<option value=\"$id\">$nom</option>";
          }
        } else {
          echo '<option value="">Aucun Modbus trouvé</option>';
        }
        ?>
      </select>
    </div>
    <div id="device-selectors"></div>
    <div style="display:flex; gap:8px; justify-content:flex-end; margin-top:16px;">
      <button id="btn-close">Annuler</button>
      <button id="btn-validate" class="btn-validate">Valide</button>
    </div>
  </div>
</div>
<script>
function createDeviceSelector(id, label) {
  const row = document.createElement('div');
  row.className = 'row';
  const lbl = document.createElement('label');
  lbl.htmlFor = id;
  lbl.textContent = label;
  const select = document.createElement('select');
  select.id = id;
  for(let i=1; i<=16; i++) {
    const opt = document.createElement('option');
    opt.value = i;
    opt.textContent = 'Device ' + i;
    select.appendChild(opt);
  }
  row.appendChild(lbl);
  row.appendChild(select);
  return row;
}
const deviceSelectors = document.getElementById('device-selectors');
deviceSelectors.appendChild(createDeviceSelector('sel-device-1', 'Device Rooftop 1'));
deviceSelectors.appendChild(createDeviceSelector('sel-device-2', 'Device Rooftop 2'));
deviceSelectors.appendChild(createDeviceSelector('sel-device-3', 'Device Rooftop 3'));

// Pré-remplissage depuis configurationlennox.json
fetch('configurationlennox.json')
  .then(r => r.json())
  .then(cfg => {
    console.log(cfg);
    if(cfg && cfg.modbus) 
    {
      document.getElementById('sel-modbus').value = cfg.modbus;
      document.getElementById('sel-device-1').value = cfg.device1;
      document.getElementById('sel-device-2').value = cfg.device2;
      document.getElementById('sel-device-3').value = cfg.device3;
    }
  
  })
  .catch(()=>{});

document.getElementById('btn-validate').addEventListener('click', function(){
  const payload = {
    modbus: document.getElementById('sel-modbus').value,
    device1: document.getElementById('sel-device-1').value,
    device2: document.getElementById('sel-device-2').value,
    device3: document.getElementById('sel-device-3').value
  };
  fetch(window.location.pathname, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload)
  })
  .then(r => r.json())
  .then(res => {
    if(res && res.success) {
      window.parent.postMessage({ action: 'configuration_saved', data: payload }, '*');
    } else {
      alert('Erreur lors de la sauvegarde : ' + (res.error || 'inconnue'));
    }
  })
  .catch(() => alert('Erreur réseau lors de la sauvegarde'));
});
document.getElementById('btn-close').addEventListener('click', function(){
  window.parent.postMessage({ action: 'configuration_cancel' }, '*');
});
</script>
</body>
</html>
