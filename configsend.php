<?php
include "base.php";

// Validation serveur: Contact = 10 chiffres ou email valide (vide autorisé)
function is_valid_contact($c) {
	$c = trim((string)$c);
	if ($c === '') return true; // autoriser vide
	if (preg_match('/^\d{10}$/', $c)) return true;
	if (filter_var($c, FILTER_VALIDATE_EMAIL)) return true;
	return false;
}

// --- CRUD AJAX pour DefSendDefaut ---
// Mise à jour d'une ligne (édition inline)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_senddef') {
	$id = intval($_POST['Id'] ?? 0);
	if ($id > 0) {
	$allowed = ['Lun','Mar','Mer','Jeu','Ven','Sam','Dim','HDebut','Hfin','Contact','CodeValide','Actife'];
		$sets = [];
		foreach ($allowed as $f) {
			if (isset($_POST[$f])) {
				$val = $_POST[$f];
				if (in_array($f, ['Lun','Mar','Mer','Jeu','Ven','Sam','Dim','Actife'])) {
					$val = ($val === '1' || $val === 1) ? '1' : '0';
				}
				if (in_array($f, ['HDebut','Hfin'])) {
					// normaliser HH:MM
					$val = substr(trim((string)$val), 0, 5);
				}
				if ($f === 'CodeValide') {
					$val = substr((string)$val, 0, 4);
				}
				if ($f === 'Contact') {
					$val = substr((string)$val, 0, 128);
					if (!is_valid_contact($val)) {
						echo 'invalid_contact';
						exit;
					}
				}
				$sets[] = "[$f]='" . addslashes($val) . "'";
			}
		}
		if (!empty($sets)) {
			mssql("UPDATE [DefSendDefaut] SET " . implode(',', $sets) . " WHERE [Id]=".$id);
			echo 'modifié';
		} else {
			echo 'aucun_champ';
		}
	} else {
		echo 'id_invalide';
	}
	exit;
}

// Ajout d'une ligne
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_senddef') {
	$cols = ['Lun','Mar','Mer','Jeu','Ven','Sam','Dim','HDebut','Hfin','Contact','CodeValide','Actife'];
	$vals = [];
	// Contrôle contact si fourni
	if (isset($_POST['Contact'])) {
		$c = substr((string)$_POST['Contact'], 0, 128);
		if (!is_valid_contact($c)) { echo 'invalid_contact'; exit; }
	}
	foreach ($cols as $c) {
	if (in_array($c, ['Lun','Mar','Mer','Jeu','Ven','Sam','Dim','Actife'])) {
			$vals[] = "'" . (isset($_POST[$c]) ? addslashes($_POST[$c]) : '0') . "'";
	} elseif (in_array($c, ['HDebut','Hfin'])) {
			$vals[] = "'" . (isset($_POST[$c]) ? addslashes(substr((string)$_POST[$c],0,5)) : '') . "'";
		} elseif ($c === 'CodeValide') {
			$vals[] = "'" . (isset($_POST[$c]) ? addslashes(substr((string)$_POST[$c],0,4)) : '') . "'";
		} elseif ($c === 'Contact') {
			$vals[] = "'" . (isset($_POST[$c]) ? addslashes(substr((string)$_POST[$c],0,128)) : '') . "'";
		} else {
			$vals[] = "''";
		}
	}
	$sql = "INSERT INTO [DefSendDefaut] ([".implode('],[', $cols)."]) VALUES (".implode(',', $vals).")";
	mssql($sql);
	echo 'ajouté';
	exit;
}

// Suppression d'une ligne
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_senddef' && isset($_POST['Id'])) {
	$id = intval($_POST['Id']);
	mssql("DELETE FROM [DefSendDefaut] WHERE [Id]=".$id);
	echo 'supprimé';
	exit;
}

// --- Récupération des données ---
$rows = [];
$res = mssql("SELECT [Id],[Lun],[Mar],[Mer],[Jeu],[Ven],[Sam],[Dim],[HDebut],[Hfin],[Contact],[CodeValide],[Actife] FROM [DefSendDefaut] ORDER BY [Id]");
if ($res) {
	while ($r = sqlnext($res)) { $rows[] = $r; }
}
?>
<!DOCTYPE html>
<html>
<head>
	<meta charset="utf-8">
	<title>Configuration envoi défauts</title>
	<style>
		html { box-sizing: border-box; }
		*, *:before, *:after { box-sizing: inherit; }
	body { font-family: 'Segoe UI', Arial, sans-serif; margin: 0; font-size: 0.95em; overflow-x: hidden; }
		.container { width: 1184px; margin: 4vw auto; background: #fff; border-radius: 18px; box-shadow: 0 4px 24px #0002; padding: 2vw 3vw; }
		h2 { margin-bottom: 2vw; font-size: 2em; color: #2c3e50; }
	.btn { display: inline-block; padding: 0.7em 1.2em; border: none; border-radius: 8px; cursor: pointer; font-size: 0.98em; margin: 0.2em 0.2em 0.2em 0; transition: background .2s, color .2s; }
		.btn-primary { background: #2563eb; color: #fff; }
		.btn-primary:hover { background: #1741a6; }
		.btn-danger { background: #ef4444; color: #fff; }
		.btn-danger:hover { background: #b91c1c; }
		.btn-secondary { background: #64748b; color: #fff; }
		.btn-secondary:hover { background: #334155; }
	table { width: 100%; border-collapse: collapse; background: #fafbfc; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 8px #0001; font-size: 0.92em; table-layout: fixed; }
		th, td { border: none; padding: 0.7em; text-align: left; }
		th { background: #e0e7ef; color: #34495e; font-weight: 600; position: sticky; top: 0; z-index: 2; }
		tr:nth-child(even) { background: #f6f8fa; }
		tr:hover { background: #e3e9f7; }
	.table-wrap { width: 100%; overflow: hidden; border-radius: 12px; box-shadow: 0 2px 8px #0001; margin-bottom: 2vw; background: #fafbfc; }
	.table-scroll { max-height: 28em; overflow-y: auto; overflow-x: hidden; }
	th, td { word-break: break-word; overflow-wrap: anywhere; }

		/* Fenêtre principale centrée + fondu comme Programme.php */
		#mainConfigSendContainer { opacity: 0; transition: opacity .35s cubic-bezier(.4,0,.2,1); pointer-events: none; }
		#mainConfigSendContainer.show { opacity: 1; pointer-events: auto; }
		#mainConfigSendContainer { position: fixed !important; top: 50%; left: 50%; transform: translate(-50%, -50%); margin: 0 !important; z-index: 100; }

		.btn-close { background: none; border: none; font-size: 1.7em; cursor: pointer; color: #64748b; }
		.btn-close:hover { color: #ef4444; }

	input[type="time"] { width: 94px; padding: 0.25em 0.2em; font-size: 0.95em; border: 1px solid #cbd5e1; border-radius: 4px; background: #f8fafc; font-family: monospace, Consolas, Menlo, Monaco, monospace; }
	input[type="text"] { width: 100%; padding: 0.35em 0.5em; font-size: 0.95em; border: 1px solid #cbd5e1; border-radius: 4px; background: #f8fafc; }
	input[type="checkbox"] { width: 18px; height: 18px; vertical-align: middle; }
		.cell-center { text-align: center; }
		.actions { white-space: nowrap; }
	</style>
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<meta http-equiv="Cache-Control" content="no-store" />
	<meta http-equiv="Pragma" content="no-cache" />
	<meta http-equiv="Expires" content="0" />
</head>
<body>
<div class="container" id="mainConfigSendContainer" style="position:relative;">
	<button type="button" class="btn-close" onclick="closeMainConfigSend()" title="Fermer" style="position:absolute;top:18px;right:24px;font-size:1.7em;z-index:20;line-height:1;">&times;</button>
	<h2>Configuration envoi défauts</h2>
	<div style="text-align:right;margin-bottom:10px;">
		<button class="btn btn-primary" id="btnAddRow">+ Ajouter un contact</button>
	</div>
	<div class="table-wrap">
		<table style="width:100%;table-layout:fixed;">
				<colgroup>
					<col style="width:48px"><col style="width:48px"><col style="width:48px"><col style="width:48px"><col style="width:48px"><col style="width:48px"><col style="width:48px">
					<col style="width:110px"><col style="width:110px">
					<col style="width:260px"><col style="width:110px">
					<col style="width:74px">
					<col style="width:64px">
				</colgroup>
			<thead>
				<tr>
					<th class="cell-center" title="Lundi">Lun</th>
					<th class="cell-center" title="Mardi">Mar</th>
					<th class="cell-center" title="Mercredi">Mer</th>
					<th class="cell-center" title="Jeudi">Jeu</th>
					<th class="cell-center" title="Vendredi">Ven</th>
					<th class="cell-center" title="Samedi">Sam</th>
					<th class="cell-center" title="Dimanche">Dim</th>
					<th>HDébut</th>
					<th>Hfin</th>
					<th>Contact (tél 10 chiffres ou email)</th>
					<th>CodeValide</th>
					<th class="cell-center">Actife</th>
					<th class="actions">Actions</th>
				</tr>
			</thead>
		</table>
		<div class="table-scroll">
			<table style="width:100%;table-layout:fixed;">
				<colgroup>
					<col style="width:48px"><col style="width:48px"><col style="width:48px"><col style="width:48px"><col style="width:48px"><col style="width:48px"><col style="width:48px">
					<col style="width:110px"><col style="width:110px">
					<col style="width:260px"><col style="width:110px">
					<col style="width:74px">
					<col style="width:64px">
				</colgroup>
				<tbody>
					<?php if (!is_array($rows) || count($rows) === 0): ?>
						<tr><td colspan="13" style="text-align:center;color:#64748b;padding:1em;font-size:1.4em;">Aucun contact enregistré</td></tr>
					<?php else: foreach($rows as $row): ?>
						<tr data-row-id="<?= $row['Id'] ?>">
							<?php foreach(['Lun','Mar','Mer','Jeu','Ven','Sam','Dim'] as $d): ?>
								<td class="cell-center"><input type="checkbox" data-id="<?= $row['Id'] ?>" data-col="<?= $d ?>" <?= ($row[$d]==1||$row[$d]==='1')?'checked':'' ?>></td>
							<?php endforeach; ?>
							<td><input type="time" data-id="<?= $row['Id'] ?>" data-col="HDebut" value="<?= isset($row['HDebut']) && $row['HDebut']? substr($row['HDebut'],0,5) : '' ?>"></td>
							<td><input type="time" data-id="<?= $row['Id'] ?>" data-col="Hfin" value="<?= isset($row['Hfin']) && $row['Hfin']? substr($row['Hfin'],0,5) : '' ?>"></td>
							<td><input type="text" data-id="<?= $row['Id'] ?>" data-col="Contact" value="<?= htmlspecialchars((string)($row['Contact'] ?? ''), ENT_QUOTES) ?>" placeholder="0601020304 ou email@domaine.com" title="10 chiffres ou adresse e‑mail valide"></td>
							<td><input type="text" data-id="<?= $row['Id'] ?>" data-col="CodeValide" value="<?= htmlspecialchars((string)($row['CodeValide'] ?? ''), ENT_QUOTES) ?>" maxlength="4" style="width:100px"></td>
							<td class="cell-center"><input type="checkbox" data-id="<?= $row['Id'] ?>" data-col="Actife" <?= ($row['Actife']==1||$row['Actife']==='1')?'checked':'' ?>></td>
							<td class="actions"><button type="button" class="btn btn-danger btn-sm" onclick="confirmDeleteSendDef(<?= $row['Id'] ?>)" title="Supprimer" style="font-size:1.2em;padding:0.2em 0.5em;line-height:1.1;min-width:32px;min-height:32px;display:flex;align-items:center;justify-content:center;">🗑️</button></td>
						</tr>
					<?php endforeach; endif; ?>
				</tbody>
			</table>
		</div>
	</div>
</div>

<!-- Modal confirmation suppression -->
<div class="modal" id="confirmModal" style="display:none; position:fixed; z-index:1000; left:0; top:0; width:100vw; height:100vh; background: rgba(44,62,80,0.18);">
  <div class="modal-content" style="background:#fff; margin: 20vh auto; padding: 1.4em 1.4em 1em; border-radius: 14px; max-width: 480px; width: 92%; position:relative; box-shadow:0 8px 32px #0003;">
	<div class="modal-header" style="display:flex;align-items:center;justify-content:space-between;">
	<span class="modal-title" style="font-size:1.2em;color:#ef4444;">Suppression du contact</span>
	  <button type="button" class="btn-close" onclick="closeModal('confirmModal')" style="position:absolute;top:10px;right:12px;">&times;</button>
	</div>
	<div class="modal-body" id="confirmModalBody" style="margin-top:6px;">Suppression du contact ?</div>
	<div class="modal-footer" id="confirmModalFooter" style="text-align:right;margin-top:1em;">
	  <button type="button" class="btn btn-danger" id="btnOuiDelete">Supprimer</button>
	  <button type="button" class="btn btn-secondary" onclick="closeModal('confirmModal')">Annuler</button>
	</div>
  </div>
  <style>
	.modal.show { opacity:1; pointer-events:auto; }
	.modal { opacity:0; transition: opacity .25s ease; pointer-events:none; }
  </style>
  <script>
	(function(){
	  let toDelId = null;
			window.confirmDeleteSendDef = function(id){
				toDelId = id;
				// Récupérer le contact dans la ligne
				var tr = document.querySelector('tr[data-row-id="'+id+'"]');
				var contact = '';
				if (tr) {
					var input = tr.querySelector('[data-col="Contact"]');
					if (input) contact = (input.value || '').toString();
				}
				// Échapper pour HTML
				var esc = function(s){ return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;'); };
				var msg = 'Suppression du contact'+(contact? ' <strong>'+esc(contact)+'</strong>' : '')+' ?';
				var body = document.getElementById('confirmModalBody');
				if (body) body.innerHTML = msg;
				const m = document.getElementById('confirmModal');
				m.style.display='block';
				setTimeout(()=>m.classList.add('show'),10);
				const yes = document.getElementById('btnOuiDelete');
		yes.onclick = function(){
		  if(!toDelId) return;
		  const fd = new FormData();
		  fd.append('action','delete_senddef');
		  fd.append('Id', toDelId);
		  fetch('', { method:'POST', body: fd })
			.then(r=>r.text())
			.then(()=>{ location.reload(); })
			.catch(e=>{ alert('Erreur AJAX: '+e.message); closeModal('confirmModal'); });
		};
	  }
	  window.closeModal = function(id){
		const m = document.getElementById(id);
		if(!m) return;
		m.classList.remove('show');
		setTimeout(()=>{ m.style.display='none'; }, 250);
	  }
	})();
  </script>
</div>

<!-- Modal d'erreur générique -->
<div class="modal" id="errorModal" style="display:none; position:fixed; z-index:1000; left:0; top:0; width:100vw; height:100vh; background: rgba(44,62,80,0.18);">
	<div class="modal-content" style="background:#fff; margin: 22vh auto; padding: 1.4em 1.4em 1em; border-radius: 14px; max-width: 520px; width: 92%; position:relative; box-shadow:0 8px 32px #0003;">
		<div class="modal-header" style="display:flex;align-items:center;justify-content:space-between;">
			<span class="modal-title" style="font-size:1.05em;color:#ef4444;">Erreur</span>
			<button type="button" class="btn-close" onclick="closeModal('errorModal')" style="position:absolute;top:10px;right:12px;">&times;</button>
		</div>
		<div class="modal-body" id="errorModalBody" style="margin-top:6px;">Une erreur est survenue.</div>
		<div class="modal-footer" style="text-align:right;margin-top:1em;">
			<button type="button" class="btn btn-primary" onclick="closeModal('errorModal')">OK</button>
		</div>
	</div>
	<style>
		/* Réutiliser les mêmes transitions */
		#errorModal.modal { opacity:0; transition: opacity .25s ease; pointer-events:none; }
		#errorModal.modal.show { opacity:1; pointer-events:auto; }
	</style>
</div>

<!-- Overlay de chargement pour rafraîchissement -->
<div id="pageRefreshOverlay" style="display:none; position:fixed; inset:0; z-index:1500; background: rgba(255,255,255,0.6); backdrop-filter: blur(1px); align-items:center; justify-content:center;">
	<div style="display:flex;flex-direction:column;align-items:center;gap:12px;">
		<div class="spinner" style="width:56px;height:56px;border-radius:50%;border:4px solid #d1d5db;border-top-color:#2563eb;animation: spin 0.9s linear infinite;"></div>
		<div style="font-size:1.05em;color:#374151;">Actualisation...</div>
	</div>
	<style>
		@keyframes spin { from { transform: rotate(0deg);} to { transform: rotate(360deg);} }
	</style>
  
</div>

<script>
document.addEventListener('DOMContentLoaded', function(){
  // Afficher la fenêtre principale avec fondu
  var main = document.getElementById('mainConfigSendContainer');
  if (main) main.classList.add('show');

	// Limiter l'affichage à 6 lignes visibles dans le tableau (zone scrollable)
	function clampVisibleRows(n){
		try {
			n = n || 6;
			var sc = document.querySelector('.table-scroll');
			if (!sc) return;
			var row = sc.querySelector('tbody tr');
			var rowH = row ? row.getBoundingClientRect().height : 40; // fallback 40px
			sc.style.maxHeight = Math.round(rowH * n + 2) + 'px';
		} catch(e) { /* no-op */ }
	}
	clampVisibleRows(6);
	var _rsT;
	window.addEventListener('resize', function(){ clearTimeout(_rsT); _rsT = setTimeout(()=>clampVisibleRows(6), 120); });

  // Ajout d'une ligne
  const btnAdd = document.getElementById('btnAddRow');
  if (btnAdd) {
	btnAdd.addEventListener('click', function(){
	  const fd = new FormData();
	  fd.append('action','add_senddef');
			['Lun','Mar','Mer','Jeu','Ven','Sam','Dim','Actife'].forEach(k=>fd.append(k,'0'));
	  fd.append('HDebut','');
	fd.append('Hfin','');
	  fd.append('Contact','');
	  fd.append('CodeValide','');
					fetch('', { method:'POST', body: fd })
						.then(r=>r.text())
	.then(txt=>{ if (txt==='invalid_contact'){ showErrorModal('Contact invalide: 10 chiffres ou email requis'); return; } showPageLoader(); setTimeout(()=>location.reload(), 60); })
						.catch(e=> showErrorModal('Erreur AJAX: '+e.message));
	});
  }

  // Edition inline et sauvegarde auto
  document.querySelectorAll('[data-id][data-col]').forEach(inp => {
	inp.addEventListener('change', function(){
	  const tr = inp.closest('tr');
	  const id = tr && tr.getAttribute('data-row-id');
	  if (!id) return;
			// Validation côté client du contact (si non vide)
			const contactEl = tr.querySelector('[data-col="Contact"]');
			const contactVal = contactEl ? (contactEl.value || '').trim() : '';
			const isPhone = /^\d{10}$/.test(contactVal);
			const isEmail = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(contactVal);
					if (contactVal !== '' && !(isPhone || isEmail)) {
				if (contactEl) {
					contactEl.style.borderColor = '#ef4444';
					contactEl.style.outline = '2px solid #ef444466';
					contactEl.title = 'Contact invalide: 10 chiffres ou email valide';
					contactEl.focus();
				}
						showErrorModal('Contact invalide: entrez 10 chiffres ou une adresse e‑mail valide.');
				return; // ne pas envoyer
			} else if (contactEl) {
				contactEl.style.borderColor = '#cbd5e1';
				contactEl.style.outline = '';
				contactEl.title = '10 chiffres ou adresse e‑mail valide';
			}
	  const inputs = tr.querySelectorAll('[data-id][data-col]');
	  const fd = new FormData();
	  fd.append('action','edit_senddef');
	  fd.append('Id', id);
	  inputs.forEach(el => {
		if (el.type === 'checkbox') fd.append(el.dataset.col, el.checked ? '1':'0');
		else fd.append(el.dataset.col, el.value);
	  });
					fetch('', { method:'POST', body: fd })
						.then(r=>r.text())
						.then(txt=>{
							if (txt==='invalid_contact') {
								if (contactEl) {
									contactEl.style.borderColor = '#ef4444';
									contactEl.style.outline = '2px solid #ef444466';
								}
								showErrorModal('Contact invalide: 10 chiffres ou email requis');
								return;
							}
							tr.style.background = '#a3e635'; setTimeout(()=>{ tr.style.background=''; }, 800);
						})
						.catch(e=> showErrorModal('Erreur AJAX: '+e.message));
	});
  });

			// Modale d'erreur réutilisable
			window.showErrorModal = function(message){
				var m = document.getElementById('errorModal');
				var b = document.getElementById('errorModalBody');
				if (!m || !b) return;
				b.textContent = message || 'Une erreur est survenue.';
				m.style.display = 'block';
				setTimeout(()=> m.classList.add('show'), 10);
			}

					// Overlay de chargement pendant les rafraîchissements
					window.showPageLoader = function(){
						var l = document.getElementById('pageRefreshOverlay');
						if (!l) return;
						l.style.display = 'flex';
					};
					// Tenter d'afficher lors des navigations/refresh manuels
					window.addEventListener('beforeunload', function(){
						var l = document.getElementById('pageRefreshOverlay');
						if (l) l.style.display = 'flex';
					});

  // Bouton de fermeture principal (iframe parent OverScreen)
  window.closeMainConfigSend = function(){
	var cont = document.getElementById('mainConfigSendContainer');
	if(cont){ cont.classList.remove('show'); setTimeout(()=>{ cont.style.display='none'; }, 300); }
	if(window.parent && window.parent.document){
			// Fermer l'overlay du TopBar si utilisé
			var over = window.parent.document.getElementById('OverScreenWunites');
			if (over) over.style.display = 'none';
			// Fermer la modale plein écran de Maintenance (contact-iframe-bg)
			var contactBg = window.parent.document.getElementById('contact-iframe-bg');
			if (contactBg) {
				contactBg.classList.remove('visible');
				setTimeout(()=>{ contactBg.style.display = 'none'; }, 200);
			}
	}
  }
});
</script>
</body>
</html>

