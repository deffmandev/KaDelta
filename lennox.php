<?php
 error_reporting(E_ALL);
    include "Style.php";
    include "Base.php";
?>

<style>


.cadreCTA {
  position: relative;
  width: 800px;
  height: 475px;
  margin-left: auto !important;
  margin-right: auto !important;
  display: flex;
  justify-content: center;
  align-items: center;
  background: #fff;
  border: 2px solid #bbb;
  border-top: none;
  border-radius: 0 12px 12px 12px;
  box-shadow: 0 2px 8px #0002;
  overflow: hidden;
  box-sizing: border-box;
}

.CtaImageFond 
{
        position: absolute;
        top: 0;
        left: 0;
        z-index: 1;
}

.etiquette
{
    position: absolute;
    font-size: 1.11em;
    color: #193cf1;
    font-weight: bold;
    text-shadow: 1px 2px 4px #ffffff8c;
    padding: 4px 12px;
    border: 1px solid gray;
    z-index: 10;
    background: #dfdfdf;
    border-radius: 12px;
    box-shadow: inset 3px 2px 6px #878686d9, inset -2px -3px 3px #cdcccce6, 4px 3px 4px #45424270;
    width: 3em;
    text-align: center;
    /* Empêche la sélection et le menu contextuel sur mobile */
    -webkit-user-select: none;
    -moz-user-select: none;
    -ms-user-select: none;
    user-select: none;
    -webkit-touch-callout: none;
}

.eti1
{
    top: 20px;
    left: 135px;
}

.eti2
{
    top: 307px;
    left: 256px;
}

.eti3
{
    top: 161px;
    left: 708px;
}

.eti4
{
    top: 20px;
    left: 707px;
}

.eti5
{
    top: 161px;
    left: 3px;
}

.eti6
{
    top: 307px;
    left: 164px;
}

.eti7
{
    top: 307px;
    left: 504px;
}

.eti8
{
    top: 307px;
    left: 378px;
}

.eti9
{
    top: 348px;
    left: 378px;
}

.eti10
{
    top: 389px;
    left: 378px;
}

.eti11
{
    top: 20px;
    left: 378px;
}

.eti12
{
    top: 61px;
    left: 378px;
}


.labeltt
{
    position: absolute;
    font-size: 1.11em;
    color: #082bb3;
    z-index: 10;
    /* Empêche la sélection des labels */
    -webkit-user-select: none;
    -moz-user-select: none;
    -ms-user-select: none;
    user-select: none;
}
.tt1
{
    top: 353px;
    left: 263px;
}
.tt2
{
    top: 394px;
    left: 263px;
}
.tt3
{
    top: 14px;
    left: 275px;
    text-align: right;
}
.tt4
{
       top: 56px;
    left: 295px;
    text-align: right;
}

.tt5
{
    top: 25px;
    left: 607px;
    text-align: right;
    color: #ff0000;
    font-family: fangsong;
    font-weight: 900;
}

.tabs {
  margin-bottom: 0;
  justify-content: flex-start;
  width: 800px;
  margin-left: auto !important;
  margin-right: auto !important;
  border-bottom: none;
  border-radius: 12px 12px 0 0;
  overflow: hidden;
  position: relative;
  box-sizing: border-box;
}
.tab-button {
  font-family: serif;
  padding: 10px 30px;
  font-size: 1.1em;
  cursor: pointer;
  background: #e0e0e0;
  border: 1px solid #bbb;
  border-bottom: none;
  border-radius: 8px 8px 0 0;
  margin-right: 0;
  font-weight: bold;
  transition: box-shadow 0.45s;
  position: relative;
  z-index: 1;
}
.tab-button:not(.active) {
  transform: none;
}
.tab-button.active {
  background: #fff;
  color: #193cf1;
  /* border-bottom: none; */
  box-shadow: 0 6px 24px #193cf180;
  z-index: 3;
  transition: box-shadow 0.45s;
  transform-origin: left center;
}
.tab-content {
  display: none;
}
.tab-content.active {
      display: block;
      transform: scale(1);
      box-shadow: none;
      z-index: 2;
    }

.tab-icon-red {
  height: 1.6em;
  vertical-align: middle;
  margin-right: 4px;
  margin-left:-18px;
  filter: brightness(0) saturate(100%) invert(18%) sepia(99%) saturate(7492%) hue-rotate(-1deg) brightness(97%) contrast(119%);
}
</style>


<div class="tabs">
  <button class="tab-button active" onclick="showTab(0)"><img src="images/defaut.png" alt="" class="tab-icon-red">ROOFTOP 1</button>
  <button class="tab-button"        onclick="showTab(1)"><img src="images/defaut.png" alt="" class="tab-icon-red">ROOFTOP 2</button>
  <button class="tab-button"        onclick="showTab(2)"><img src="images/defaut.png" alt="" class="tab-icon-red">ROOFTOP 3</button>
</div>


<?php for ($id=0; $id < 3; $id++) { ?>
  <div class="tab-content<?php echo $id === 0 ? ' active' : ''; ?>">
    <div class="cadreCTA">
      <IMG class="CtaImageFond" SRC="images/ctalennox.png" WIDTH=800px alt="ROOFTOP" BORDER=0>
      <div class="etiquette eti1">-</div>
      <div class="etiquette eti2">-</div>
      <div class="etiquette eti3">-</div>
      <div class="etiquette eti4">-</div>
      <div class="etiquette eti5">-</div>
      <div class="etiquette eti6">-</div>
      <div class="etiquette eti7">-</div>
      <div class="etiquette eti8">-</div>
      <div class="etiquette eti9">-</div>
      <div class="etiquette eti10">-</div>
      <div class="etiquette eti11">-</div>
      <div class="etiquette eti12">-</div>
      <div class="labeltt tt1">Compresseur 1</div>
      <div class="labeltt tt2">Compresseur 2</div>
      <div class="labeltt tt3">Température<br>Ambiante</div>
      <div class="labeltt tt4">Consigne<br>Ambiante</div>
      <div class="labeltt tt5">Code défaut</div>
    </div>
  </div>
<?php } ?>

<script>
function showTab(idx) {
  var tabs = document.querySelectorAll('.tab-button');
  var contents = document.querySelectorAll('.tab-content');
  tabs.forEach(function(tab, i) {
    tab.classList.toggle('active', i === idx);
    tab.style.marginLeft = '';
    tab.style.marginRight = '';
    tab.style.transform = '';
  });
  contents.forEach(function(content, i) {
    content.classList.toggle('active', i === idx);
  });
}
window.addEventListener('DOMContentLoaded', function() {
  showTab(document.querySelector('.tab-button.active') ? Array.from(document.querySelectorAll('.tab-button')).findIndex(tab => tab.classList.contains('active')) : 0);
});

async function getLennoxData(id = 1) {
  // Ne doit jamais lever une exception non gérée
  try {
    const url = 'lennoxdata.php?Id=' + encodeURIComponent(id);
    const controller = new AbortController();
    const timeout = setTimeout(() => controller.abort(), 3000);
    let response;
    try {
      response = await fetch(url, { method: 'GET', headers: { 'Accept': 'application/json' }, signal: controller.signal });
    } catch (e) {
      clearTimeout(timeout);
      if (e.name === 'AbortError') console.error('Timeout 3s pour lennoxdata id=', id);
      else console.error('Erreur fetch lennoxdata id=', id, e);
      // renvoyer données en cache si disponibles, sinon null
      return window.lennoxTabData && window.lennoxTabData[id] ? window.lennoxTabData[id] : null;
    }
    clearTimeout(timeout);

    if (!response.ok) {
      console.error('Réponse non ok pour lennoxdata id=', id, response.status);
      return window.lennoxTabData && window.lennoxTabData[id] ? window.lennoxTabData[id] : null;
    }

    let data;
    try {
      data = await response.json();
    } catch (e) {
      console.error('JSON invalide reçu pour lennoxdata id=', id, e);
      return window.lennoxTabData && window.lennoxTabData[id] ? window.lennoxTabData[id] : null;
    }

    // Si l'API ne renvoie pas un tableau, log et renvoyer le cache
    if (!Array.isArray(data)) {
      console.error('Format inattendu pour lennoxdata id=', id, data);
      // si c'est un objet avec 'error', log et retourner cache
      if (data && typeof data === 'object' && 'error' in data) {
        console.error('Erreur API:', data.error);
      }
      return window.lennoxTabData && window.lennoxTabData[id] ? window.lennoxTabData[id] : null;
    }

    // Tout est OK — stocke et rend
    if (!window.lennoxTabData) window.lennoxTabData = {};
    window.lennoxTabData[id] = data;
    try { renderLennoxToTab(id, data); } catch (e) { console.error('Erreur render après fetch:', e); }
    return data;
  } catch (err) {
    console.error('Erreur inattendue dans getLennoxData:', err);
    return window.lennoxTabData && window.lennoxTabData[id] ? window.lennoxTabData[id] : null;
  }
}

// Remplit les éléments du tab correspondant à l'Id avec les valeurs reçues
function renderLennoxToTab(id, data) {
  try {
    const parsed = parseInt(id, 10);
    if (isNaN(parsed)) return;
    // Les Ids externes vont de 1 à 3, les tabs dans la page sont indexés 0..2
    const idx = parsed - 1;
    const tabs = document.querySelectorAll('.tab-content');
    if (idx < 0 || idx >= tabs.length) return;
    const tab = tabs[idx];
    if (!tab) return;
    // Map des indices de données vers les sélecteurs
    const mapping = [
      ['.eti1', 40], ['.eti2', 1], ['.eti3', 39], ['.eti4', 36], ['.eti5', 38], ['.eti6', 45],
      ['.eti7', 6], ['.eti8', 7], ['.eti9', 8], ['.eti10', 9], ['.eti11', 37], ['.eti12', 11],
    ];

    // Si data absent, tenter d'utiliser le cache ou remplir avec '-'
    let source = data;
    if (!Array.isArray(source)) {
      source = (window.lennoxTabData && window.lennoxTabData[id]) ? window.lennoxTabData[id] : [];
    }

    mapping.forEach(([sel, index]) => {
      const el = tab.querySelector(sel);
      if (!el) return;
      let display = '-';
      if (Array.isArray(source) && source.length > index) {
        let val = String(source[index]);
        // Si la longueur dépasse 6 caractères, considérer la valeur comme erronée
        if (val.length > 6) display = '#';
        else display = val;
      }
      el.textContent = display;
    });
  } catch (e) {
    console.error('Erreur renderLennoxToTab:', e);
  }
}

// Appel au chargement de la page : démarrer une boucle qui récupère les Ids 1 à 3 toutes les secondes
window.addEventListener('DOMContentLoaded', () => {
  (async function loopLennoxData(){
    while (true) {
      for (let id = 1; id <= 3; id++) {
        try {
          await getLennoxData(id);
        } catch (e) {
          // Protection supplémentaire : on log l'erreur mais on continue
          console.error('Erreur getLennoxData loop:', e);
        }
      }
      // attendre 1000 ms avant le prochain cycle
      await new Promise(resolve => setTimeout(resolve, 1000));
    }
  })();
});


</script>