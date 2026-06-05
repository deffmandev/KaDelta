<?php
require_once __DIR__ . '/auth.php';
auth_bootstrap();
auth_require_active_session();
include "Style.php";
include "stylelennox.php";
include "Base.php";
include "TopBar.php";
?>


<div style="margin-top:33px;"></div>
<div class="tabsRow">
  <div class="tabs" style="display:flex; gap:6px;">
    <button class="tab-button active" onclick="showTab(0)"><img src="images/defaut.png" alt="" class="tab-icon-red">ROOFTOP 1</button>
    <button class="tab-button" onclick="showTab(1)"><img src="images/defaut.png" alt="" class="tab-icon-red">ROOFTOP 2</button>
    <button class="tab-button" onclick="showTab(2)"><img src="images/defaut.png" alt="" class="tab-icon-red">ROOFTOP 3</button>
  </div>
</div>

<!-- Modal d'acquittement -->
<div id="modal-acquittement">
  <div class="modal-acquittement-content">
    <div class="modal-acquittement-header">
      <svg viewBox="0 0 24 24">
        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
      </svg>
      Confirmation d'acquittement
    </div>
    <div class="modal-acquittement-body">
      Êtes-vous sûr de vouloir acquitter le défaut du <strong id="modal-rooftop-name">Rooftop X</strong> ?
    </div>
    <div class="modal-acquittement-buttons">
      <button class="modal-btn modal-btn-cancel" id="modal-btn-cancel">Annuler</button>
      <button class="modal-btn modal-btn-confirm" id="modal-btn-confirm">Acquitter</button>
    </div>
  </div>
</div>

<dialog id="modal-consigne" class="vg-dialog lx-consigne-dialog">
  <form method="dialog" id="modal-consigne-form">
    <div class="lx-consigne-header">
      <h2 id="modal-consigne-title">Consigne Thermostat</h2>
      <button type="button" class="lx-consigne-close" id="modal-consigne-close" aria-label="Fermer">&times;</button>
    </div>
    <div class="temp-controls">
      <button type="button" class="temp-btn" id="modal-consigne-minus" aria-label="Diminuer">-</button>
      <output class="temp-value" id="modal-consigne-value" for="modal-consigne-minus modal-consigne-plus">21&deg;C</output>
      <button type="button" class="temp-btn" id="modal-consigne-plus" aria-label="Augmenter">+</button>
    </div>
  </form>
</dialog>

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
      <div class="etiquette eti13">-</div>
      <div class="etiquette eti14">-</div>
      <div class="etiquette eti15">-</div>
      <div class="etiquette eti16">-</div>
      <div class="labeltt tt1">Compresseur 1</div>
      <div class="labeltt tt2">Compresseur 2</div>
      <div class="labeltt tt3">Température<br>Ambiante</div>
      <div class="labeltt tt4">Consigne<br>en froid</div>
      <div class="labeltt tt5">Code défaut</div>
      <button class="btn-acquittement" data-rooftop="<?php echo $id + 1; ?>" style="display: none;">
        <span>✓</span> Acquitter
      </button>
      <div class="labeltt tt6">Consigne<br>en chaud</div>
      <div class="labeltt tt7">Heures du rooftop</div>
      <div class="labeltt tt8">Vanne 1</div>
      <div class="labeltt tt9">Vanne 2</div>
      <div class="labeltt tt10">Consigne<br>Thermostat</div>

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
      ['.eti1', 40], ['.eti2', 3], ['.eti3', 39], ['.eti4', 36], ['.eti5', 38], ['.eti6', 45],
      ['.eti7', 143], ['.eti8', 135], ['.eti9', 137], ['.eti10', 140], ['.eti11', 37], ['.eti12', 2], ['.tt7',20],
      ['.eti13', 139], ['.eti14', 142], ['.eti15', 47],['.eti16', 34]
    ];

    // Si data absent, tenter d'utiliser le cache ou remplir avec '-'
    let source = data;
    if (!Array.isArray(source)) {
      source = (window.lennoxTabData && window.lennoxTabData[id]) ? window.lennoxTabData[id] : [];
    }

    try {
      const btns = document.querySelectorAll('.tab-button');
      const btn = btns[idx];
      if (btn) {
      const img = btn.querySelector('.tab-icon-red');
      const code36 = Array.isArray(source) && source.length > 36 ? source[36] : null;
      // Déterminer l'opacité finale sans clignoter
      if (img) {
        img.style.opacity = (code36 !== null && code36 !== undefined && code36 !== 0 && code36 !== '0') ? '1' : '0';
      }
      }
    } catch (e) {
      console.error('Erreur gestion icone tab (opacité statique):', e);
    }

    // Gestion de l'affichage du bouton d'acquittement
    try {
      const btnAcquittement = tab.querySelector('.btn-acquittement');
      if (btnAcquittement) {
        const code36 = Array.isArray(source) && source.length > 36 ? source[36] : null;
        const hasDefaut = code36 !== null && code36 !== undefined && code36 !== 0 && code36 !== '0';
        btnAcquittement.style.display = hasDefaut ? 'flex' : 'none';
      }
    } catch (e) {
      console.error('Erreur gestion bouton acquittement:', e);
    }

    mapping.forEach(([sel, index]) => {
      const el = tab.querySelector(sel);
      if (!el) return;
      let display = '-';
      if (Array.isArray(source) && source.length > index) {
        let val = String(source[index]);
        // Si la longueur dépasse 6 caractères, considérer la valeur comme erronée
        if (val.length > 6) {
          // Exception : si c'est le "code 20" (index 20) on n'affiche pas '#' ni on ne crée un défaut — on met '-' à la place
          if (index === 20) display = val;
          else display = '#';
        }
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

// Écoute simple des messages provenant de l'iframe de configuration
(function(){
  const overlay = document.getElementById('overlay-config');
  const iframe = document.getElementById('iframe-config-lennox');
  window.addEventListener('message', function(event){
    try {
      const data = event.data;
      if (!data) return;
      if (typeof data === 'string' && data === 'closeOverlay') {
        if (overlay) overlay.style.display = 'none';
        if (iframe) iframe.src = '';
        return;
      }
      if (typeof data === 'object' && data.action) {
        if (data.action === 'configuration_saved') {
          console.log('Configuration Lennox enregistrée:', data.data);
          if (overlay) overlay.style.display = 'none';
          if (iframe) iframe.src = '';
        } else if (data.action === 'configuration_cancel') {
          if (overlay) overlay.style.display = 'none';
          if (iframe) iframe.src = '';
        }
      }
    } catch (e) {
      console.error('Erreur processing postMessage from iframe:', e);
    }
  }, false);
})();

// ── Modal consigne thermostat (etiquette 16) ─────────────────────────────────
const consigneModal = document.getElementById('modal-consigne');
const consigneForm = document.getElementById('modal-consigne-form');
const consigneTitle = document.getElementById('modal-consigne-title');
const consigneValueOutput = document.getElementById('modal-consigne-value');
const consigneMinus = document.getElementById('modal-consigne-minus');
const consignePlus = document.getElementById('modal-consigne-plus');
const consigneClose = document.getElementById('modal-consigne-close');

const consigneState = {
  rooftopId: 0,
  registerAddress: 34,
  min: 8,
  max: 32,
  value: 20,
  title: 'Consigne Thermostat',
  sourceElement: null,
  queue: Promise.resolve()
};

function clampConsigneValue(value) {
  return Math.min(consigneState.max, Math.max(consigneState.min, Math.round(value)));
}

function renderConsigneModal() {
  consigneTitle.textContent = consigneState.title;
  consigneValueOutput.textContent = String(consigneState.value) + '°C';
}

function closeConsigneModal() {
  if (consigneModal.open) {
    consigneModal.close();
  }
}

function openConsigneModal(options) {
  consigneState.rooftopId = Number(options.rooftopId || 0);
  consigneState.registerAddress = Number(options.registerAddress || 34);
  consigneState.min = Number(options.min || 8);
  consigneState.max = Number(options.max || 32);
  consigneState.title = String(options.title || 'Consigne Thermostat');
  consigneState.sourceElement = options.sourceElement || null;
  const parsedInitial = Number(options.initialValue);
  consigneState.value = clampConsigneValue(Number.isFinite(parsedInitial) ? parsedInitial : 20);
  renderConsigneModal();
  if (!consigneModal.open) {
    consigneModal.showModal();
  }
}

async function sendConsigneToApi(rooftopId, register, value) {
  const params = new URLSearchParams({
    Id: String(rooftopId),
    register: String(register),
    valeur: String(value)
  });

  const response = await fetch('LennoxSetConsigne.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
      'Accept': 'application/json'
    },
    body: params.toString()
  });

  const payload = await response.json().catch(() => null);
  if (!response.ok || !payload || payload.success !== true) {
    throw new Error(payload && payload.error ? payload.error : 'Ecriture Modbus impossible');
  }

  return payload;
}

function queueConsigneSend(nextValue) {
  if (!consigneState.rooftopId) {
    return;
  }

  const rooftopId = consigneState.rooftopId;
  const register = consigneState.registerAddress;
  const valueToSend = nextValue;

  if (consigneState.sourceElement) {
    consigneState.sourceElement.textContent = String(valueToSend);
  }

  consigneState.queue = consigneState.queue
    .then(async () => {
      try {
        await sendConsigneToApi(rooftopId, register, valueToSend);
      } catch (_) {
        await new Promise((resolve) => setTimeout(resolve, 10000));
        await sendConsigneToApi(rooftopId, register, valueToSend);
      }
    })
    .catch((e) => {
      console.error('Erreur consigne thermostat apres retry:', e);
    });
}

function changeConsigne(delta) {
  const before = consigneState.value;
  const after = clampConsigneValue(before + delta);
  if (after === before) {
    return;
  }
  consigneState.value = after;
  renderConsigneModal();
  queueConsigneSend(after);
}

consigneMinus.addEventListener('click', () => changeConsigne(-1));
consignePlus.addEventListener('click', () => changeConsigne(1));
consigneClose.addEventListener('click', closeConsigneModal);

consigneForm.addEventListener('submit', function(event) {
  event.preventDefault();
});

// Fermer en cliquant hors de la boite
consigneModal.addEventListener('click', function(event) {
  const rect = consigneModal.getBoundingClientRect();
  const inside = (
    event.clientX >= rect.left &&
    event.clientX <= rect.right &&
    event.clientY >= rect.top &&
    event.clientY <= rect.bottom
  );
  if (!inside) {
    closeConsigneModal();
  }
});

document.addEventListener('click', function(e) {
  const trigger = e.target.closest('.eti16');
  if (!trigger) {
    return;
  }

  const tab = trigger.closest('.tab-content');
  if (!tab) {
    return;
  }

  const tabs = Array.from(document.querySelectorAll('.tab-content'));
  const tabIndex = tabs.indexOf(tab);
  if (tabIndex < 0) {
    return;
  }

  const rooftopId = tabIndex + 1;
  const initialValue = Number(String(trigger.textContent || '').replace(',', '.').trim());
  openConsigneModal({
    rooftopId: rooftopId,
    registerAddress: 34,
    min: 8,
    max: 32,
    initialValue: Number.isFinite(initialValue) ? initialValue : 20,
    title: 'Consigne Thermostat',
    sourceElement: trigger
  });
});

// Gestion des boutons d'acquittement
document.addEventListener('click', function(e) {
  if (e.target.closest('.btn-acquittement')) {
    const btn = e.target.closest('.btn-acquittement');
    const rooftop = btn.dataset.rooftop;
    
    // Afficher la modal de confirmation
    const modal = document.getElementById('modal-acquittement');
    const rooftopName = document.getElementById('modal-rooftop-name');
    const btnConfirm = document.getElementById('modal-btn-confirm');
    const btnCancel = document.getElementById('modal-btn-cancel');
    
    rooftopName.textContent = `Rooftop ${rooftop}`;
    modal.style.display = 'flex';
    
    // Gestionnaire pour le bouton Confirmer
    const handleConfirm = () => {
      modal.style.display = 'none';
      
      // Animation du bouton
      btn.style.background = 'linear-gradient(135deg, #6c757d, #495057)';
      btn.innerHTML = '<span>✓</span> Acquitté';
      btn.disabled = true;
    
      (async () => {
        try {
          const params = new URLSearchParams({ Id: rooftop, reponce: '1' });
          const controller = new AbortController();
          const timeout = setTimeout(() => controller.abort(), 3000);

          const res = await fetch('LennoxAquit.php?' + params.toString(), {
            method: 'GET',
            headers: { 'Accept': 'text/plain' },
            signal: controller.signal
          });

          clearTimeout(timeout);

          const text = await res.text().catch(() => '');
          if (!res.ok) {
            console.error('Erreur HTTP LennoxAquit:', res.status, text);
          } else {
            console.log(text || '(vide)');
          }

          try { await getLennoxData(Number(rooftop)); } catch {}
        } catch (e) {
          console.error('Erreur appel LennoxAquit.php:', e);
        }
      })();
      
      // Remettre le bouton normal après 3 secondes
      setTimeout(() => {
        btn.style.background = '';
        btn.innerHTML = '<span>✓</span> Acquitter';
        btn.disabled = false;
      }, 3000);
      
      console.log(`Défaut acquitté pour Rooftop ${rooftop}`);
      
      // Nettoyer les événements
      btnConfirm.removeEventListener('click', handleConfirm);
      btnCancel.removeEventListener('click', handleCancel);
      modal.removeEventListener('click', handleModalClick);
    };
    
    // Gestionnaire pour le bouton Annuler
    const handleCancel = () => {
      modal.style.display = 'none';
      btnConfirm.removeEventListener('click', handleConfirm);
      btnCancel.removeEventListener('click', handleCancel);
      modal.removeEventListener('click', handleModalClick);
    };
    
    // Gestionnaire pour fermer en cliquant sur l'overlay
    const handleModalClick = (event) => {
      if (event.target === modal) {
        handleCancel();
      }
    };
    
    // Ajouter les événements
    btnConfirm.addEventListener('click', handleConfirm);
    btnCancel.addEventListener('click', handleCancel);
    modal.addEventListener('click', handleModalClick);
  }
});
</script>