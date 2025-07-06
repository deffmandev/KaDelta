<?php
include "base.php";

// Traitement de la sauvegarde (AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['Nom'], $_POST['Addresse'], $_POST['Port'])) {
    $nom = $_POST['Nom'];
    $addresse = $_POST['Addresse'];
    $port = (int)$_POST['Port'];
    $id = isset($_POST['Id']) && $_POST['Id'] !== '' ? intval($_POST['Id']) : null;
    if ($id) {
        // Modification
        mssql("UPDATE Defmodbus SET Nom='".addslashes($nom)."', Addresse='".addslashes($addresse)."', Port='".addslashes($port)."' WHERE Id=$id");
        echo 'modifié';
    } else {
        // Ajout
        mssql("INSERT INTO Defmodbus (Nom, Addresse, Port) VALUES ('".addslashes($nom)."', '".addslashes($addresse)."', '".addslashes($port)."')");
        echo 'ajouté';
    }
    exit;
}

// Traitement de la suppression (AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete' && isset($_POST['Id'])) {
    $id = intval($_POST['Id']);
    mssql("DELETE FROM Defmodbus WHERE Id=$id");
    echo 'supprimé';
    exit;
}

// Utilisation de la bibliothèque base.php pour récupérer les données
$defmodbus = [];
$result = mssql("SELECT Id,Nom,Addresse,Port FROM Defmodbus");
while ($row = sqlnext($result)) {
    $defmodbus[] = $row;
}
$colonnes = ['Nom', 'Addresse', 'Port'];
?>
<!DOCTYPE html>
<html>
<head>
    <title>Gestion Modbus</title>
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
            width: 1097px;
            height: auto;
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
            margin-bottom: 2vw;
            font-size: 2em;
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
        /* Largeurs identiques pour les colonnes dans les deux tables */
        .col-nom { width: 15ch; max-width: 15ch; }
        .col-addresse { width: 7ch; max-width: 7ch; }
        .col-port { width: 6ch; max-width: 6ch; }
        .col-actions { width: 16ch; max-width: 16ch; }
        th.nom, td.nom { width: 15ch; max-width: 15ch; word-break: break-all; }
        th.addresse, td.addresse { width: 7ch; max-width: 7ch; word-break: break-all; }
        th.port, td.port { width: 6ch; max-width: 6ch; word-break: break-all; }
        th:last-child, td:last-child { width: 16ch; max-width: 16ch; }
        th {
            position: sticky;
            top: 0;
            z-index: 10;
            background: #e0e7ef;
            color:rgb(41, 59, 77);
            font-weight: 800;
            border-bottom: none;
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
            max-width: 95vw;
            width: 560px;
            min-width: 280px;
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
        .table-scroll {
            max-height: 28em; /* Ajustez selon la hauteur de vos lignes (~3.5em x 8) */
            overflow-y: auto;
            margin-bottom: 2vw;
            border-radius: 12px;
            box-shadow: 0 2px 8px #0001;
            position: relative;
        }
    </style>
</head>
<body>
<div class="container">
    <h2>Table Modbus</h2>
    <button class="btn btn-primary mb-2" onclick="openModal()">Ajouter</button>
    <table class="table-header" style="table-layout: fixed; width: 100%; margin-bottom: 0;">
        <colgroup>
            <?php foreach ($colonnes as $col): ?>
                <col class="col-<?= strtolower($col) ?>">
            <?php endforeach; ?>
            <col class="col-actions">
        </colgroup>
        <thead>
        <tr>
            <?php foreach ($colonnes as $col): ?>
                <th class="<?= strtolower($col) ?>"><?= htmlspecialchars($col) ?></th>
            <?php endforeach; ?>
            <th>Actions</th>
        </tr>
        </thead>
    </table>
    <div class="table-scroll">
        <table style="table-layout: fixed; width: 100%;">
            <colgroup>
                <?php foreach ($colonnes as $col): ?>
                    <col class="col-<?= strtolower($col) ?>">
                <?php endforeach; ?>
                <col class="col-actions">
            </colgroup>
            <tbody>
            <?php foreach ($defmodbus as $row): ?>
                <tr>
                    <?php foreach ($colonnes as $col): ?>
                        <td class="<?= strtolower($col) ?>" data-label="<?= htmlspecialchars($col) ?>">
                            <span>
                                <?php if ($col === 'Nom'): ?>
                                    <?= htmlspecialchars(mb_strimwidth($row[$col], 0, 20, '...')) ?>
                                <?php elseif ($col === 'Port'): ?>
                                    <?= (int)$row[$col] ?>
                                <?php else: ?>
                                    <?= htmlspecialchars($row[$col]) ?>
                                <?php endif; ?>
                            </span>
                        </td>
                    <?php endforeach; ?>
                    <td data-label="Actions">
                        <button class="btn btn-warning" onclick='openModal(<?= json_encode($row) ?>)'>Modifier</button>
                        <button class="btn btn-danger" onclick="confirmDelete(<?= $row['Id'] ?>, '<?= htmlspecialchars($row['Nom'], ENT_QUOTES) ?>')">Supprimer</button>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal principal -->
<div class="modal" id="defmodbusModal">
    <div class="modal-content">
        <div class="modal-header">
            <span class="modal-title" id="defmodbusModalLabel">Gérer Modbus</span>
            <button type="button" class="btn-close" onclick="closeModal('defmodbusModal')">&times;</button>
        </div>
        <form method="post" id="defmodbusForm">
            <div class="modal-body" id="modalBody">
                <!-- Les champs du formulaire seront générés en JS -->
            </div>
            <div class="modal-footer">
                <button type="submit" class="btn btn-success">Enregistrer</button>
                <button type="button" class="btn btn-secondary" onclick="closeModal('defmodbusModal')">Annuler</button>
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
            <p>Tous les champs sont obligatoires.</p>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-primary" onclick="closeModal('errorModal')">Compris</button>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const columns = <?= json_encode($colonnes) ?>;
    let deleteId = null;

    window.openModal = function(data = null) {
        let html = '';
        if (data && data.Id !== undefined) {
            html += `<input type="hidden" name="Id" value="${data.Id}">`;
        }
        columns.forEach(col => {
            let value = data ? data[col] : '';
            if (col === 'Port' && value !== '') value = parseInt(value, 10) || '';
            let attrs = '';
            if (col === 'Nom') attrs = ' maxlength="20"';
            if (col === 'Addresse') attrs = ' pattern="(?:\\d{1,3}\\.){3}\\d{1,3}" placeholder="xxx.xxx.xxx.xxx"';
            if (col === 'Port') attrs = ' maxlength="6" pattern="\\d*" placeholder="port"';
            html += `
                <div class="mb-3">
                    <label class="form-label">${col}</label>
                    <input type="text" class="form-control" name="${col}" value="${value}"${attrs}>
                </div>
            `;
        });
        document.getElementById('modalBody').innerHTML = html;
        document.getElementById('defmodbusModal').style.display = 'block';
        document.getElementById('defmodbusForm').onsubmit = function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            // Validation : aucun champ ne doit être vide
            let valid = true;
            columns.forEach(col => {
                if (!formData.get(col) || formData.get(col).toString().trim() === '') valid = false;
            });
            if (!valid) {
                document.getElementById('errorModal').style.display = 'block';
                return;
            }
            // Conversion JS : Port en nombre avant envoi
            if (formData.has('Port')) {
                let portVal = formData.get('Port');
                formData.set('Port', portVal ? parseInt(portVal, 10) || 0 : 0);
            }
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(r => r.text())
            .then(msg => {
                location.reload();
            });
            closeModal('defmodbusModal');
        };
    }

    window.closeModal = function(id) {
        document.getElementById(id).style.display = 'none';
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
            .then(msg => { location.reload(); });
            closeModal('deleteConfirmModal');
        }
    };

    window.onclick = function(event) {
        ['defmodbusModal', 'deleteConfirmModal'].forEach(function(id) {
            let modal = document.getElementById(id);
            if (event.target === modal) {
                modal.style.display = 'none';
            }
        });
    };
});
</script>
</body>
</html>