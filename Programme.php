<?php
include "base.php";

// --- API AJAX pour horaires ---
if (isset($_GET['get_horaires']) && isset($_GET['Id_ProgNom'])) {
    $id = intval($_GET['Id_ProgNom']);
    $rows = [];
    $res = mssql("SELECT Id,Lun,Mar,Mer,Jeu,Ven,Sam,Dim,Heure,OnOff,Mode,SetTemp,Fan FROM PHoraires WHERE Id_ProgNom=$id");
    while ($r = sqlnext($res)) $rows[] = $r;
    header('Content-Type: application/json');
    echo json_encode($rows);
    exit;
}


// --- Traitement CRUD pour ProgNom ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['Nom'])) {
    $nom = $_POST['Nom'];
    $id = isset($_POST['Id']) && $_POST['Id'] !== '' ? intval($_POST['Id']) : null;
    if ($id) {
        mssql("UPDATE ProgNom SET Nom='".addslashes($nom)."' WHERE Id=$id");
        echo 'modifié';
    } else {
        mssql("INSERT INTO ProgNom (Nom) VALUES ('".addslashes($nom)."')");
        echo 'ajouté';
    }
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete' && isset($_POST['Id'])) {
    $id = intval($_POST['Id']);
    mssql("DELETE FROM ProgNom WHERE Id=$id");
    echo 'supprimé';
    exit;
}

// --- Traitement CRUD pour PHoraires (AJAX) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_horaire') {
    $id = intval($_POST['Id']);
    $fields = ['Lun','Mar','Mer','Jeu','Ven','Sam','Dim','Heure','OnOff','Mode','SetTemp','Fan'];
    $sets = [];
    foreach ($fields as $f) {
        if (isset($_POST[$f])) $sets[] = "$f='".addslashes($_POST[$f])."'";
    }
    if ($id && count($sets)) {
        mssql("UPDATE PHoraires SET ".implode(',', $sets)." WHERE Id=$id");
        echo 'modifié';
    }
    exit;
}
// Ajout du traitement PHP pour l'ajout d'une ligne horaire
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_horaire') {
    $id_prog = intval($_POST['Id_ProgNom']);
    $fields = ['Lun','Mar','Mer','Jeu','Ven','Sam','Dim','Heure','OnOff','Mode','SetTemp','Fan'];
    $cols = [];
    $vals = [];
    foreach ($fields as $f) {
        $cols[] = $f;
        $vals[] = isset($_POST[$f]) ? "'".addslashes($_POST[$f])."'" : "''";
    }
    mssql("INSERT INTO PHoraires (Id_ProgNom,".implode(',', $cols).") VALUES ($id_prog,".implode(',', $vals).")");
    echo 'ajouté';
    exit;
}
// Ajout du traitement PHP pour la suppression d'une ligne horaire
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_horaire' && isset($_POST['Id'])) {
    $id = intval($_POST['Id']);
    mssql("DELETE FROM PHoraires WHERE Id=$id");
    echo 'supprimé';
    exit;
}

// --- Récupération des données ---
$progs = [];
$result = mssql("SELECT Id,Nom FROM ProgNom");
while ($row = sqlnext($result)) {
    $progs[] = $row;
}
$colonnes = ['Nom'];
?>
<!DOCTYPE html>
<html>
<head>
    <title>Gestion Programmes</title>
    <style>
        html { box-sizing: border-box; }
        *, *:before, *:after { box-sizing: inherit; }
        body { font-family: 'Segoe UI',Arial,sans-serif;  margin: 0; }
        .container { width: 1080px; margin: 4vw auto; background: #fff; border-radius: 18px; box-shadow: 0 4px 24px #0002; padding: 2vw 3vw; }
        h2 { margin-bottom: 2vw; font-size: 2em; color: #2c3e50; }
        .btn { display: inline-block; padding: 1em 2em; border: none; border-radius: 8px; cursor: pointer; font-size: 1.1em; margin: 0.2em 0.2em 0.2em 0; transition: background 0.2s, color 0.2s; }
        .btn-primary { background: #2563eb; color: #fff; }
        .btn-primary:hover { background: #1741a6; }
        .btn-warning { background: #ffe082; color: #7c5c00; }
        .btn-warning:hover { background: #ffd54f; }
        .btn-danger { background: #ef4444; color: #fff; }
        .btn-danger:hover { background: #b91c1c; }
        .btn-secondary { background: #64748b; color: #fff; }
        .btn-secondary:hover { background: #334155; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 0; background: #fafbfc; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 8px #0001; font-size: 1.0em; table-layout: fixed; }
        th, td { border: none; padding: 1em 0.7em; text-align: left; }
        th { background: #e0e7ef; color: #34495e; font-weight: 600; position: sticky; top: 0; z-index: 2; }
        tr:nth-child(even) { background: #f6f8fa; }
        tr:hover { background: #e3e9f7; }
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100vw; height: 100vh; overflow: auto; background: rgba(44,62,80,0.18); }
        .modal-content { background: #fff; margin: 10vh auto; padding: 2vw 2vw 1vw 2vw; border-radius: 16px; max-width: 3960px; width:1184px; min-width: 280px; box-shadow: 0 8px 32px #0003; position: relative; }
        /* Animation de transition de hauteur pour la modale principale */
        #progModal .modal-content {
            transition: height 0.35s cubic-bezier(.4,0,.2,1), max-height 0.35s cubic-bezier(.4,0,.2,1);
            overflow: hidden;
        }
        .modal-header { display: flex; justify-content: space-between; align-items: center; }
        .modal-title { font-size: 1.3em; color: #2563eb; }
        .modal-footer { text-align: right; margin-top: 2vw; }
        .btn-close { background: none; border: none; font-size: 1.5em; cursor: pointer; color: #64748b; }
        .btn-close:hover { color: #ef4444; }
        .mb-3 { margin-bottom: 1.2em; }
        label { display: block; margin-bottom: 0.3em; color: #34495e; font-weight: 500; }
        input[type="text"] { width: 100%; padding: 0.7em; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 1.1em; background: #f8fafc; transition: border 0.2s; }
        input[type="text"]:focus { border: 1.5px solid #2563eb; outline: none; background: #fff; }
        .table-scroll { max-height: 28em; overflow-y: auto; margin-bottom: 2vw; border-radius: 12px; box-shadow: 0 2px 8px #0001; }

        /* Ajout du fondu enchaîné sur toutes les modales et la fenêtre principale */
        .modal, #mainProgrammesContainer {
            opacity: 0;
            transition: opacity 0.35s cubic-bezier(.4,0,.2,1);
            pointer-events: none;
        }
        .modal.show, #mainProgrammesContainer.show {
            opacity: 1;
            pointer-events: auto;
        }
        /* Pour éviter le flash à l'ouverture */
        #mainProgrammesContainer { transition: opacity 0.35s cubic-bezier(.4,0,.2,1); }

        /* Centrage de la fenêtre principale Programmes */
        #mainProgrammesContainer {
            position: fixed !important;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            margin: 0 !important;
            z-index: 100;
        }
    </style>
</head>
<body>
<div class="container" id="mainProgrammesContainer" style="position:relative;">
    <button type="button" class="btn-close" onclick="closeMainProgrammes()" title="Fermer" style="position:absolute;top:18px;right:24px;font-size:1.7em;z-index:20;line-height:1;">&times;</button>
    <h2 style="display:flex;align-items:center;justify-content:space-between;">Programmes
    </h2>
    <button class="btn btn-primary mb-2" onclick="openModal()">Ajouter</button>
    <div style="width:100%;overflow:initial;border-radius:12px;box-shadow:0 2px 8px #0001;margin-bottom:2vw;background:#fafbfc">
        <table style="width:100%;table-layout:fixed;">
            <colgroup>
                <col style="width:60%">
                <col style="width:40%">
            </colgroup>
            <thead>
            <tr>
                <th>Nom</th>
                <th>Actions</th>
            </tr>
            </thead>
        </table>
        <div class="table-scroll" style="max-height:28em;overflow-y:auto;">
            <table style="width:100%;table-layout:fixed;">
                <colgroup>
                    <col style="width:60%">
                    <col style="width:40%">
                </colgroup>
                <tbody>
                <?php foreach ($progs as $row): ?>
                    <tr>
                        <td title="<?= htmlspecialchars($row['Nom']) ?>"><?= htmlspecialchars(mb_strimwidth($row['Nom'], 0, 20, '…')) ?></td>
                        <td>
                            <button class="btn btn-warning" onclick='openModal(<?= json_encode($row) ?>)'>Modifier</button>
                            <button class="btn btn-danger" onclick="confirmDelete(<?= $row['Id'] ?>, '<?= htmlspecialchars($row['Nom'], ENT_QUOTES) ?>')">Supprimer</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<!-- Modal principal -->
<div class="modal" id="progModal">
    <div class="modal-content" style="max-width:3960px;width:1184px"> 
        <div class="modal-header" style="position:relative;">
            <span class="modal-title" id="progModalLabel">Gérer Programme</span>
            <button type="button" class="btn-close" onclick="closeModal('progModal')" style="position:absolute;top:12px;right:18px;font-size:1.5em;z-index:10;">&times;</button>
        </div>
        <form method="post" id="progForm">
            <div class="modal-body" id="modalBody">
                <!-- Les champs du formulaire seront générés en JS -->
            </div>
            <div class="modal-footer">
                <button type="submit" class="btn btn-success">Enregistrer</button>
                <button type="button" class="btn btn-secondary" onclick="closeModal('progModal')">Annuler</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal de confirmation de suppression -->
<div class="modal" id="confirmModal">
    <div class="modal-content" style="max-width:600px;">
        <div class="modal-header">
            <span class="modal-title" style="color:#ef4444">Confirmation suppression</span>
            <button type="button" class="btn-close" onclick="closeModal('confirmModal')" style="position:absolute;top:12px;right:18px;font-size:1.5em;">&times;</button>
        </div>
        <div class="modal-body" id="confirmModalBody">
            <!-- Message JS -->
        </div>
        <div class="modal-footer" id="confirmModalFooter">
            <!-- Boutons JS -->
        </div>
    </div>
</div>
<!-- Modal d'erreur -->
<div class="modal" id="errorModal">
    <div class="modal-content" style="max-width:400px;">
        <div class="modal-header">
            <span class="modal-title">Erreur</span>
            <button type="button" class="btn-close" onclick="closeModal('errorModal')" style="position:absolute;top:12px;right:18px;font-size:1.5em;">&times;</button>
        </div>
        <div class="modal-body">
            <p>Tous les champs sont obligatoires.</p>
        </div>
        <div class="modal-footer" id="errorModalFooter">
            <button type="button" class="btn btn-primary" onclick="closeModal('errorModal')">Compris</button>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    let deleteId = null;
    window.openModal = function(data = null) {
        let html = '';
        if (data && data.Id !== undefined) {
            html += `<input type="hidden" name="Id" value="${data.Id}">`;
        }
        html += `
            <div class="mb-3">
                <label class="form-label">Nom</label>
                <input type="text" class="form-control" name="Nom" value="${data ? data.Nom.substring(0,20) : ''}" maxlength="20">
            </div>
        `;
        // Ajout de la gestion des horaires si édition (Id présent)
        if (data && data.Id !== undefined) {
            html += `<div class="mb-3"><label class="form-label">Horaires associés</label><div id="horairesTableContainer" style="margin-top:0.5em;"></div></div>`;
            setTimeout(function() {
                fetch(`?get_horaires=1&Id_ProgNom=${data.Id}`)
                .then(r => {
                    if (!r.ok) throw new Error('Erreur AJAX');
                    return r.json();
                })
                .then(rows => {
                    // Génération du tableau des horaires avec scroll, suppression du bouton sauvegarde global
                    let addBtn = `<div style='text-align:right;margin-bottom:0.7em;'><button type='button' class='btn btn-primary' id='btnAddHoraire'>+ Ajouter une ligne</button></div>`;
                    let table = `<div style='width:100%;overflow:auto;max-height:19em;border-radius:12px;box-shadow:0 2px 8px #0001;background:#fafbfc'>`;
                    table += `<table style='width:100%;table-layout:fixed;font-size:0.98em;'>`;
                    table += `<colgroup>
<col style='width:28px'>
<col style='width:28px'>
<col style='width:28px'>
<col style='width:28px'>
<col style='width:28px'>
<col style='width:28px'>
<col style='width:28px'>
<col style='width:80px'>
<col style='width:66px'>
<col style='width:88px'>
<col style='width:66px'>
<col style='width:68px'>
<col style='width:45px'>
</colgroup>`;
                    table += `<thead><tr><th>Lun</th><th>Mar</th><th>Mer</th><th>Jeu</th><th>Ven</th><th>Sam</th><th>Dim</th><th>Heure</th><th>OnOff</th><th>Mode</th><th>SetTemp</th><th>Fan</th><th>Supp.</th></tr></thead>`;
                    table += `<tbody>`;
                    if(!Array.isArray(rows) || rows.length === 0) {
                        table += `<tr><td colspan='13' style='text-align:center;color:#64748b;'>Aucun horaire pour ce programme</td></tr>`;
                    } else {
                        rows.forEach(row => {
                            table += `<tr data-horaire-id='${row.Id}'>`;
                            // Suppression du doublon de la colonne Heure dans la génération du tableau
                            ['Lun','Mar','Mer','Jeu','Ven','Sam','Dim','Heure','OnOff','Mode','SetTemp','Fan'].forEach(col => {
                                if (["Lun","Mar","Mer","Jeu","Ven","Sam","Dim"].includes(col)) {
                                    table += `<td><input type='checkbox' data-id='${row.Id}' data-col='${col}' ${row[col]==1||row[col]==="1"?"checked":""} style='width:24px;'></td>`;
                                } else if (col === 'Mode') {
                                    table += `<td><select data-id='${row.Id}' data-col='Mode' style='width:88px;padding:0.3em 0.2em;font-size:1.1em;border:1px solid #cbd5e1;border-radius:4px;background:#f8fafc;'>`;
                                    table += `<option value='' ${!row[col]||row[col]==='0'?"selected":""}>---</option>`;
                                    table += `<option value='2' ${row[col]==2||row[col]==='2'?"selected":""}>Auto</option>`;
                                    table += `<option value='3' ${row[col]==3||row[col]==='3'?"selected":""}>Froid</option>`;
                                    table += `<option value='4' ${row[col]==4||row[col]==='4'?"selected":""}>Chaud</option>`;
                                    table += `<option value='5' ${row[col]==5||row[col]==='5'?"selected":""}>Deshu</option>`;
                                    table += `<option value='1' ${row[col]==1||row[col]==='1'?"selected":""}>Fan</option>`;
                                    table += `</select></td>`;
                                } else if (col === 'SetTemp') {
                                    table += `<td><select data-id='${row.Id}' data-col='SetTemp' style='width:68px;padding:0.3em 0.2em;font-size:1.1em;border:1px solid #cbd5e1;border-radius:4px;background:#f8fafc;'>`;
                                    table += `<option value='' ${!row[col]||row[col]==='0'?"selected":""}>---</option>`;
                                    for(let t=10;t<=32;t++) {
                                        table += `<option value='${t}' ${(row[col]==t||row[col]===''+t)?"selected":""}>${t}</option>`;
                                    }
                                    table += `</select></td>`;
                                } else if (col === 'OnOff') {
                                    let isNull = row[col] === null || row[col] === undefined || row[col] === '' || row[col] === 'null';
                                    table += `<td style='padding-left:0;'><select data-id='${row.Id}' data-col='OnOff' style='width:66px;padding:0.3em 0.2em;font-size:1.1em;border:1px solid #cbd5e1;border-radius:4px;background:#f8fafc;'>`;
                                    table += `<option value='' ${(isNull ? "selected" : "")}>---</option>`;
                                    table += `<option value='1' ${(row[col]==1||row[col]==='1')&&!isNull?"selected":""}>On</option>`;
                                    table += `<option value='0' ${(row[col]==0||row[col]==='0')&&!isNull?"selected":""}>Off</option>`;
                                    table += `</select></td>`;
                                } else if (col === 'Fan') {
                                    table += `<td><select data-id='${row.Id}' data-col='Fan' style='width:66px;padding:0.2em 0.1em;font-size:1em;border:1px solid #cbd5e1;border-radius:4px;background:#f8fafc;'>`;
                                    table += `<option value='' ${!row[col]||row[col]==='0'?"selected":""}>---</option>`;
                                    table += `<option value='4' ${row[col]==4||row[col]==='4'?"selected":""}>Auto</option>`;
                                    table += `<option value='1' ${row[col]==1||row[col]==='1'?"selected":""}>1</option>`;
                                    table += `<option value='2' ${row[col]==2||row[col]==='2'?"selected":""}>2</option>`;
                                    table += `<option value='3' ${row[col]==3||row[col]==='3'?"selected":""}>3</option>`;
                                    table += `</select></td>`;
                                } else if (col === 'Heure') {
                                    table += `<td style='padding-right:0;'><input type='time' data-id='${row.Id}' data-col='Heure' value='${row[col] ? row[col].slice(0,5) : ''}' style='width:80px;padding:0.1em 0.1em;font-size:0.98em;font-family:monospace,Consolas,Menlo,monaco,monospace;border:1px solid #cbd5e1;border-radius:4px;background:#f8fafc;'></td>`;
                                } else {
                                    table += `<td><input type='text' data-id='${row.Id}' data-col='${col}' value='${row[col] ?? ''}' style='width:95px;padding:0.3em 0.2em;font-size:1em;border:1px solid #cbd5e1;border-radius:4px;background:#f8fafc;'></td>`;
                                }
                            });
                            table += `<td><button type='button' class='btn btn-danger btn-sm' style='font-size:1.2em;padding:0.2em 0.5em;line-height:1.1;min-width:32px;min-height:32px;display:flex;align-items:center;justify-content:center;' title='Supprimer la ligne' onclick='confirmDeleteHoraire(${row.Id})'>🗑️</button></td>`;
                            table += `</tr>`;
                        });
                    }
                    table += `</tbody></table></div>`;
                    document.getElementById('horairesTableContainer').innerHTML = addBtn + table;
                    // Handler pour édition inline et sauvegarde automatique
                    setTimeout(function() {
                        // Sauvegarde automatique à la modification
                        document.querySelectorAll('#horairesTableContainer [data-id]').forEach(input => {
                            input.onchange = function() {
                                const tr = input.closest('tr');
                                const id = tr.getAttribute('data-horaire-id');
                                const inputs = tr.querySelectorAll('[data-id]');
                                const formData = new FormData();
                                formData.append('action', 'edit_horaire');
                                formData.append('Id', id);
                                inputs.forEach(inp => {
                                    if (inp.type === 'checkbox') {
                                        formData.append(inp.dataset.col, inp.checked ? '1' : '0');
                                    } else {
                                        formData.append(inp.dataset.col, inp.value);
                                    }
                                });
                                fetch('', { method: 'POST', body: formData })
                                .then(r => r.text())
                                .then(msg => { tr.style.background = '#a3e635'; setTimeout(()=>tr.style.background='', 800); })
                                .catch(e => alert('Erreur AJAX : ' + e.message));
                            };
                        });
                        // Handler pour suppression d'une ligne
                        window.confirmDeleteHoraire = function(id) {
                            // Affiche la modale de confirmation pour la suppression d'une ligne horaire
                            window.deleteHoraireId = id;
                            document.getElementById('confirmModalBody').innerHTML = `Voulez-vous vraiment supprimer cette ligne d'horaire ?`;
                            document.getElementById('confirmModalFooter').innerHTML = `
                                <button type='button' class='btn btn-danger' id='btnOuiHoraire'>Oui</button>
                                <button type='button' class='btn btn-secondary' id='btnNonHoraire'>Non</button>
                            `;
                            document.getElementById('confirmModal').style.display = 'block';
                            setTimeout(()=>{ document.getElementById('confirmModal').classList.add('show'); }, 10);
                            document.getElementById('btnOuiHoraire').onclick = function() {
                                if (window.deleteHoraireId) {
                                    const formData = new FormData();
                                    formData.append('action', 'delete_horaire');
                                    formData.append('Id', window.deleteHoraireId);
                                    fetch('', { method: 'POST', body: formData })
                                        .then(r => r.text())
                                        .then(msg => {
                                            closeModal('confirmModal');
                                            openModal(data);
                                        })
                                        .catch(e => { closeModal('confirmModal'); alert('Erreur AJAX : ' + e.message); });
                                }
                            };
                            document.getElementById('btnNonHoraire').onclick = function() {
                                closeModal('confirmModal');
                            };
                        }
                        // Handler pour ajout (déjà présent)
                        const btnAdd = document.getElementById('btnAddHoraire');
                        if(btnAdd) {
                            btnAdd.onclick = function() {
                                const formData = new FormData();
                                formData.append('action', 'add_horaire');
                                formData.append('Id_ProgNom', data.Id);
                                ['Lun','Mar','Mer','Jeu','Ven','Sam','Dim','Heure','OnOff','Mode','SetTemp','Fan'].forEach(col => {
                                    if (["Lun","Mar","Mer","Jeu","Ven","Sam","Dim"].includes(col)) {
                                        formData.append(col, '0');
                                    } else {
                                        formData.append(col, '');
                                    }
                                });
                                fetch('', { method: 'POST', body: formData })
                                .then(r => r.text())
                                .then(msg => { openModal(data); })
                                .catch(e => alert('Erreur AJAX : ' + e.message));
                            }
                        }
                    }, 200);
                })
                .catch(e => {
                    document.getElementById('horairesTableContainer').innerHTML = `<div style='color:#ef4444;text-align:center;padding:1em;'>Erreur de chargement des horaires : ${e.message}</div>`;
                });
            }, 100);
        }
        document.getElementById('modalBody').innerHTML = html;
        // Affichage avec fondu enchaîné
        var progModal = document.getElementById('progModal');
        progModal.style.display = 'block';
        setTimeout(()=>{ progModal.classList.add('show'); }, 10);
        document.getElementById('progForm').onsubmit = function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            if (!formData.get('Nom') || formData.get('Nom').toString().trim() === '') {
                var errModal = document.getElementById('errorModal');
                errModal.style.display = 'block';
                setTimeout(()=>{ errModal.classList.add('show'); }, 10);
                return;
            }
            fetch('', { method: 'POST', body: formData })
            .then(r => r.text())
            .then(msg => { location.reload(); });
            closeModal('progModal');
        };
    }
    window.closeModal = function(id) {
        var modal = document.getElementById(id);
        if(modal) {
            modal.classList.remove('show');
            setTimeout(()=>{ modal.style.display = 'none'; }, 350);
        }
        if(id === 'errorModal') {
            document.getElementById('errorModalFooter').innerHTML = '<button type="button" class="btn btn-primary" onclick="closeModal(\'errorModal\')">Compris</button>';
        }
    }
    window.confirmDelete = function(id, nom) {
        deleteId = id;
        document.getElementById('confirmModalBody').innerHTML = `Voulez-vous vraiment supprimer <strong>${nom}</strong> ?`;
        document.getElementById('confirmModalFooter').innerHTML = `
            <button type='button' class='btn btn-danger' id='btnOui'>Oui</button>
            <button type='button' class='btn btn-secondary' id='btnNon'>Non</button>
        `;
        document.getElementById('confirmModal').style.display = 'block';
        setTimeout(()=>{ document.getElementById('confirmModal').classList.add('show'); }, 10);
        document.getElementById('btnOui').onclick = function() {
            if (deleteId) {
                const formData = new FormData();
                formData.append('action', 'delete');
                formData.append('Id', deleteId);
                fetch('', { method: 'POST', body: formData })
                .then(r => r.text())
                .then(msg => { location.reload(); });
                closeModal('confirmModal');
            }
        };
        document.getElementById('btnNon').onclick = function() {
            closeModal('confirmModal');
        };
    }
    window.saveHoraire = function(id, btn) {
        const tr = btn.closest('tr');
        const inputs = tr.querySelectorAll('[data-id]');
        const formData = new FormData();
        formData.append('action', 'edit_horaire');
        formData.append('Id', id);
        inputs.forEach(input => {
            if (input.type === 'checkbox') {
                formData.append(input.dataset.col, input.checked ? '1' : '0');
            } else {
                formData.append(input.dataset.col, input.value);
            }
        });
        fetch('', { method: 'POST', body: formData })
        .then(r => r.text())
        .then(msg => { btn.style.background = '#a3e635'; setTimeout(()=>btn.style.background='', 800); })
        .catch(e => alert('Erreur AJAX : ' + e.message));
    }
    // Fondu sur la fenêtre principale Programmes
    var mainCont = document.getElementById('mainProgrammesContainer');
    if(mainCont) {
        mainCont.classList.add('show');
    }
    window.closeMainProgrammes = function() {
        var cont = document.getElementById('mainProgrammesContainer');
        if(cont) {
            cont.classList.remove('show');
            setTimeout(()=>{ cont.style.display = 'none'; }, 350);
        }
        if(window.parent && window.parent.document && window.parent.document.getElementById('OverScreenWunites')) {
            window.parent.document.getElementById('OverScreenWunites').style.display = "none";
        }
    }
});
</script>
</body>
</html>
