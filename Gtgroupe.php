<?php
include "base.php";

// Traitement de la sauvegarde (AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['Groupe'])) {
    $groupe = trim($_POST['Groupe']);
    $id = isset($_POST['Id']) && $_POST['Id'] !== '' ? intval($_POST['Id']) : null;
    if ($groupe === '') {
        echo 'erreur';
        exit;
    }
    if ($id) {
        // Modification
        mssql("UPDATE Groupe SET Groupe='".addslashes($groupe)."' WHERE Id=$id");
        echo 'modifié';
    } else {
        // Ajout
        mssql("INSERT INTO Groupe (Groupe) VALUES ('".addslashes($groupe)."')");
        echo 'ajouté';
    }
    exit;
}

// Traitement de la suppression (AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete' && isset($_POST['Id'])) {
    $id = intval($_POST['Id']);
    if ($id == 1) {
        echo 'forbidden';
        exit;
    }
    mssql("DELETE FROM Groupe WHERE Id=$id");
    echo 'supprimé';
    exit;
}

// Récupération des groupes
$groupes = [];
$result = mssql("SELECT Id, Groupe FROM Groupe");
while ($row = sqlnext($result)) {
    $groupes[] = $row;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Gestion des Groupes</title>
    <style>
        html { box-sizing: border-box; }
        *, *:before, *:after { box-sizing: inherit; }
        body {
            font-family: 'Segoe UI',Arial, sans-serif;
            background:rgba(86, 98, 114, 0.18);
            margin: 0;
        }
        .container {
            max-width: 98vw;
            width: 800px;
            min-width: 420px;
            margin: 0;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: #fff;
            padding: 2vw 3vw;
            border-radius: 18px;
            box-shadow: 0 4px 24px #0002;
        }
        h2 {
            margin-bottom: 1.2vw;
            font-size: 1.3em;
            color: #2c3e50;
        }
        .btn {
            display: inline-block;
            padding: 1em 2em;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1.1em;
            margin: 0.2em 0.2em 0.2em 0;
            transition: background 0.2s, color 0.2s;
        }
        .btn-primary { background: #2563eb; color: #fff; }
        .btn-primary:hover { background: #1741a6; }
        .btn-warning { background: #ffe082; color: #7c5c00; }
        .btn-warning:hover { background: #ffd54f; }
        .btn-danger { background: #ef4444; color: #fff; }
        .btn-danger:hover { background: #b91c1c; }
        .btn-secondary { background: #64748b; color: #fff; }
        .btn-secondary:hover { background: #334155; }
        table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin-bottom: 2vw;
            background: #fafbfc;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 8px #0001;
            font-size: 1.0em;
            table-layout: fixed;
        }
        .col-groupe { width: 15ch; max-width: 15ch; }
        th.groupe, td.groupe { width: 15ch; max-width: 15ch; word-break: break-all; }
        th:last-child, td:last-child { width: 16ch; max-width: 16ch; }
        th {
            position: sticky;
            top: 0;
            z-index: 10;
            background: #e0e7ef;
            color:rgb(41, 59, 77);
            font-weight: 800;
            font-style: italic;
            padding: 1.2em 0.7em;
            text-align: left;
            background-clip: padding-box;
        }
        td {
            border: none;
            padding: 1em 0.7em;
            text-align: left;
        }
        tr:nth-child(even) { background: #f6f8fa; }
        tr:hover { background: #e3e9f7; }
        @media (max-width: 900px) {
            .container { padding: 2vw 1vw; }
            table, thead, tbody, th, td, tr { display: block; }
            thead { display: none; }
            tr { margin-bottom: 1.5em; background: #fff; border-radius: 10px; box-shadow: 0 1px 4px #0001; }
            td { padding: 1em; border: none; position: relative; }
            td:before {
                content: attr(data-label);
                font-weight: bold;
                color: #2563eb;
                display: block;
                margin-bottom: 0.3em;
            }
        }
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0; top: 0;
            width: 100vw; height: 100vh;
            overflow: auto;
            background: rgba(44,62,80,0.18);
        }
        .modal-content {
            background: #fff;
            margin: 10vh auto;
            padding: 2vw 2vw 1vw 2vw;
            border-radius: 16px;
            max-width: 98vw;
            width: 600px;
            min-width: 320px;
            box-shadow: 0 8px 32px #0003;
            position: relative;
        }
        .modal-header { display: flex; justify-content: space-between; align-items: center; }
        .modal-title { font-size: 1.3em; color: #2563eb; }
        .modal-footer { text-align: right; margin-top: 2vw; }
        .btn-close { background: none; border: none; font-size: 1.5em; cursor: pointer; color: #64748b; }
        .btn-close:hover { color: #ef4444; }
        .mb-3 { margin-bottom: 1.2em; }
        label { display: block; margin-bottom: 0.3em; color: #34495e; font-weight: 500; }
        input[type="text"] {
            width: 100%;
            padding: 0.7em;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            font-size: 1.1em;
            background: #f8fafc;
            transition: border 0.2s;
        }
        input[type="text"]:focus { border: 1.5px solid #2563eb; outline: none; background: #fff; }
        .table-header {
            border-radius: 12px 12px 0 0;
            box-shadow: 0 2px 8px #0001;
            background: #fafbfc;
        }
        .table-header th {
            position: sticky;
            top: 0;
            z-index: 11;
            background: #e0e7ef;
            color:rgb(41, 59, 77);
            font-weight: 800;
            font-style: italic;
            padding: 1.2em 0.7em;
            text-align: left;
            background-clip: padding-box;
        }
        .table-scroll {
            max-height: 28em;
            overflow-y: auto;
            margin-bottom: 2vw;
            border-radius: 0 0 12px 12px;
            box-shadow: 0 2px 8px #0001;
            position: relative;
        }
        .table-scroll table {
            border-radius: 0 0 12px 12px;
        }
    </style>
</head>
<body>
<div class="container">
    <button class="btn-close" id="close-main-btn" title="Fermer" style="position:absolute;top:18px;right:18px;font-size:2em;color:#64748b;background:none;border:none;cursor:pointer;z-index:10;">&times;</button>
    <h2>Groupes</h2>
    <button class="btn btn-primary mb-2" onclick="openModal()">Ajouter</button>
    <div style="position:relative;">
        <table class="table-header" style="table-layout: fixed; width: 100%; margin-bottom: 0;">
            <colgroup>
                <col class="col-groupe">
                <col class="col-actions">
            </colgroup>
            <thead>
            <tr>
                <th class="groupe">Groupe</th>
                <th>Actions</th>
            </tr>
            </thead>
        </table>
        <div class="table-scroll">
            <table style="table-layout: fixed; width: 100%;">
                <colgroup>
                    <col class="col-groupe">
                    <col class="col-actions">
                </colgroup>
                <tbody>
                <?php foreach ($groupes as $i => $row): ?>
                    <tr>
                        <td class="groupe" data-label="Groupe">
                            <span><?= htmlspecialchars(mb_strimwidth($row['Groupe'], 0, 20, '...')) ?></span>
                        </td>
                        <td data-label="Actions">
                            <button class="btn btn-warning" onclick='openModal(<?= json_encode($row) ?>)'>Modifier</button>
                            <?php if ($i !== 0 && $row['Id'] != 1): ?>
                                <button class="btn btn-danger" onclick="confirmDelete(<?= $row['Id'] ?>, '<?= htmlspecialchars($row['Groupe'], ENT_QUOTES) ?>')">Supprimer</button>
                            <?php else: ?>
                                <button class="btn btn-danger" disabled title="Ce groupe ne peut pas être supprimé">Supprimer</button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal principal -->
<div class="modal" id="groupeModal">
    <div class="modal-content">
        <div class="modal-header">
            <span class="modal-title" id="groupeModalLabel">Gérer Groupe</span>
            <button type="button" class="btn-close" onclick="closeModal('groupeModal')">&times;</button>
        </div>
        <form method="post" id="groupeForm">
            <div class="modal-body" id="modalBody">
                <!-- Champ généré en JS -->
            </div>
            <div class="modal-footer">
                <button type="submit" class="btn btn-success">Enregistrer</button>
                <button type="button" class="btn btn-secondary" onclick="closeModal('groupeModal')">Annuler</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal de confirmation de suppression -->
<div class="modal" id="deleteConfirmModal">
    <div class="modal-content">
        <div class="modal-header">
            <span class="modal-title" id="deleteConfirmLabel">Confirmer la suppression</span>
            <button type="button" class="btn-close" onclick="closeModal('deleteConfirmModal')">&times;</button>
        </div>
        <div class="modal-body" id="deleteModalBody">
            <!-- Message de confirmation JS -->
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('deleteConfirmModal')">Annuler</button>
            <button type="button" class="btn btn-danger" id="confirmDeleteBtn">Supprimer</button>
        </div>
    </div>
</div>

<!-- Modal d'erreur -->
<div class="modal" id="errorModal">
    <div class="modal-content" style="max-width:400px;">
        <div class="modal-header">
            <span class="modal-title">Erreur</span>
            <button type="button" class="btn-close" onclick="closeModal('errorModal')">&times;</button>
        </div>
        <div class="modal-body">
            <p>Le champ Groupe est obligatoire.</p>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-primary" onclick="closeModal('errorModal')">Compris</button>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    if (window.parent) window.parent.groupeEditionActive = true;

    let deleteId = null;

    window.openModal = function(data = null) {
        if (window.parent) window.parent.groupeEditionActive = true;
        let html = '';
        if (data && data.Id !== undefined) {
            html += `<input type="hidden" name="Id" value="${data.Id}">`;
        }
        let value = data ? data['Groupe'] : '';
        html += `
            <div class="mb-3">
                <label class="form-label">Groupe</label>
                <input type="text" class="form-control" name="Groupe" value="${value ? String(value).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;') : ''}" maxlength="20" required>
            </div>
        `;
        document.getElementById('modalBody').innerHTML = html;
        document.getElementById('groupeModal').style.display = 'block';
        document.getElementById('groupeForm').onsubmit = function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            // Validation : champ obligatoire
            if (!formData.get('Groupe') || formData.get('Groupe').toString().trim() === '') {
                document.getElementById('errorModal').style.display = 'block';
                return;
            }
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(r => r.text())
            .then(msg => {
                // Rafraîchit dynamiquement la liste après ajout ou modification
                if (msg.includes('ajouté') || msg.includes('modifié')) {
                    reloadGroupesTable();
                    closeModal('groupeModal');
                }
            });
        };
    }

    window.closeModal = function(id) {
        document.getElementById(id).style.display = 'none';
        if (window.parent) window.parent.groupeEditionActive = false;

    }

    window.confirmDelete = function(id, nom) {
        deleteId = id;
        document.getElementById('deleteModalBody').innerHTML = `Voulez-vous vraiment supprimer <strong>${nom}</strong> ?`;
        document.getElementById('deleteConfirmModal').style.display = 'block';
    }

    document.getElementById('confirmDeleteBtn').onclick = function() {
        if (deleteId) {
            const formData = new FormData();
            formData.append('action', 'delete');
            formData.append('Id', deleteId);
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then (r => r.text())
            .then(msg => {
                // Ferme seulement la modale de confirmation
                closeModal('deleteConfirmModal');
                // Rafraîchit dynamiquement la liste après suppression
                if (msg.includes('supprimé')) reloadGroupesTable();
            });
        }
    };

    window.onclick = function(event) {
        ['groupeModal', 'deleteConfirmModal'].forEach(function(id) {
            let modal = document.getElementById(id);
            if (event.target === modal) {
                modal.style.display = 'none';
                if (window.parent) window.parent.groupeEditionActive = false;
            }
        });
    };

    // Gestion fermeture croix principale
    var closeBtn = document.getElementById('close-main-btn');
    if (closeBtn) {
        closeBtn.onclick = function() {
            if (window.parent && window.parent.document) {
                var iframeModalBg = window.parent.document.getElementById('groupe-iframe-bg');
                if (iframeModalBg) iframeModalBg.style.display = 'none';
            }
            if (window.parent) window.parent.groupeEditionActive = false;
        };
    }

    // Recharge dynamiquement la liste des groupes dans le tableau
    async function reloadGroupesTable() {
        try {
            const response = await fetch('DatasUnites.php?groupes=1');
            if (!response.ok) return;
            const data = await response.json();
            if (Array.isArray(data.groupes)) {
                const tbody = document.querySelector('.table-scroll tbody');
                tbody.innerHTML = '';
                data.groupes.forEach((row, i) => {
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td class="groupe" data-label="Groupe">
                            <span>${row.Groupe.length > 20 ? row.Groupe.substring(0, 20) + '...' : row.Groupe.replace(/</g,'&lt;').replace(/>/g,'&gt;')}</span>
                        </td>
                        <td data-label="Actions">
                            <button class="btn btn-warning" onclick='openModal(${JSON.stringify(row)})'>Modifier</button>
                            ${i !== 0 && row.Id != 1 ? `<button class="btn btn-danger" onclick="confirmDelete(${row.Id}, '${row.Groupe.replace(/'/g, "&#39;").replace(/</g,'&lt;').replace(/>/g,'&gt;')}')">Supprimer</button>` : `<button class="btn btn-danger" disabled title="Ce groupe ne peut pas être supprimé">Supprimer</button>`}
                        </td>
                    `;
                    tbody.appendChild(tr);
                });
            }
        } catch (e) { /* ignore */ }
    }
});
</script>
</body>
</html>
