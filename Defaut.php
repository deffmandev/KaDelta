<?php
include "base.php";

// Ajout d'un mode check pour le rafraîchissement automatique (avec crc32)
if (isset($_GET['check'])) {
    // Recharge la liste des défauts pour le check (hors cache navigateur)
    $defauts = [];
    $res = mssql("SELECT id, date, heure, unite, code, etat FROM defauts order by etat,id ");
    while ($row = sqlnext($res)) $defauts[] = $row;
    header('Content-Type: text/html; charset=utf-8');
    $crc = crc32(json_encode($defauts));
    echo '<div class="container" data-defaut-crc="' . $crc . '"></div>';
    exit;
}




// Charger les noms des unités en mémoire
$unites = [];
$resUnites = mssql("SELECT Id, Name FROM DefUnites");
while ($row = sqlnext($resUnites)) {
    $unites[$row['Id']] = $row['Name'];
}

// Traitement acquittement
if (isset($_POST['acquitte']) && isset($_POST['id'])) {
    $id = intval($_POST['id']);
    mssql("UPDATE defauts SET etat=3 WHERE id=$id");
    header('Location: Defaut.php');
    exit;
}


// Traitement acquittement total
if (isset($_POST['acquitte_all'])) {
    mssql("UPDATE defauts SET etat=3 WHERE etat<>3");
    header('Location: Defaut.php');
    exit;
}
// Traitement effacement total (seulement si etat=3)
if (isset($_POST['delete_all'])) {
    mssql("DELETE FROM defauts WHERE etat=3");
    header('Location: Defaut.php');
    exit;
}

// Récupération des défauts
$defauts = [];
$res = mssql("SELECT id, date, heure, unite, code, etat FROM defauts order by etat,id ");
while ($row = sqlnext($res)) $defauts[] = $row;
?>



<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Liste des défauts</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background: #f3f4f6;
            margin: 0;
            padding: 0;
        }
        .container {
            width: 1100px;
            background: #fff;
            border-radius: 18px;
            box-shadow: 0 4px 24px #0002;
            padding: 2vw 3vw;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            margin: 0;
        }
        h2 {
            margin-bottom: 2vw;
            font-size: 2em;
            color: #2c3e50;
            text-align: center;
        }
        .defaut-list {
            width: 100%;
            border-collapse: collapse;
            display: block;
            max-height: 540px;
            overflow-y: auto;
        }
        .defaut-list thead, .defaut-list tbody {
            display: table;
            width: 100%;
            table-layout: fixed;
        }
        .defaut-list tbody {
            max-height: 480px;
            overflow-y: auto;
            display: block;
        }
        .defaut-list tr { width: 100%; display: table; table-layout: fixed; }
        .defaut-list th, .defaut-list td {
            padding: 0.3em 0.7em;
            text-align: left;
            font-size: 1.1em;
        }
        .defaut-list th {
            background: #e0e7ef;
            color: #34495e;
            font-weight: 600;
        }
        .etat-1 { background: #fee2e2; color: #b91c1c; }
        .etat-2 { background: #fef9c3; color: #b45309; }
        .etat-3 { background: #fff; color: #222; }
        .defaut-list tr { transition: background 0.2s; }
        .defaut-list tr:hover { filter: brightness(0.97); }
        .btn-aquitte {
            background: #2563eb;
            color: #fff;
            border: none;
            border-radius: 8px;
            padding: 0.38em 1.5em;
            font-size: 0.8em;
            min-height: 26px;
            min-width: 90px;
            cursor: pointer;
            transition: background 0.2s;
        }
        /* Boutons plus grands dans la modale de confirmation */
        .modal-confirm-buttons .btn-aquitte {
            font-size: 1.18em;
            min-width: 180px;
            min-height: 48px;
            padding: 0.7em 2.5em;
        }
        .modal-confirm {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0; top: 0; width: 100vw; height: 100vh;
            background: rgba(44,62,80,0.18);
            align-items: center;
            justify-content: center;
        }
        .modal-confirm.show { display: flex; }
        .modal-confirm-content {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 8px 32px #0003;
            padding: 2em 2.5em 1.5em 2.5em;
            min-width: 320px;
            max-width: 90vw;
            text-align: center;
        }
        .modal-confirm-buttons {
            display: flex;
            justify-content: flex-end;
            gap: 1em;
            margin-top: 2em;
        }
        .modal-confirm-title { font-size: 1.2em; color: #2563eb; margin-bottom: 1em; }
    </style>
    <script>
    function showConfirmModal(message, callback) {
        let modal = document.getElementById('modalConfirm');
        if (!modal) {
            modal = document.createElement('div');
            modal.id = 'modalConfirm';
            modal.className = 'modal-confirm';
            document.body.appendChild(modal);
        }
        modal.innerHTML = `
            <div class="modal-confirm-content">
                <div class="modal-confirm-title">Confirmation</div>
                <div id="modalConfirmMsg"></div>
                <div class="modal-confirm-buttons">
                    <button id="modalConfirmNo" type="button" class="btn-aquitte" style="background:#64748b;">Annuler</button>
                    <button id="modalConfirmYes" type="button" class="btn-aquitte" style="background:#2563eb;">Oui</button>
                </div>
            </div>
        `;
        document.getElementById('modalConfirmMsg').textContent = message;
        modal.classList.add('show');
        setTimeout(function() {
            document.getElementById('modalConfirmNo').onclick = function() {
                modal.classList.remove('show');
                callback(false);
            };
            document.getElementById('modalConfirmYes').onclick = function() {
                modal.classList.remove('show');
                // Utiliser requestSubmit pour garantir l'envoi du POST même si le bouton n'est pas de type submit
                callback(true);
            };
        }, 10);
    }
    window.addEventListener('DOMContentLoaded', function() {
        // Pour les boutons d'action globaux
        document.querySelectorAll('form button[name="acquitte_all"], form button[name="delete_all"]').forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                let form = btn.closest('form');
                let msg = btn.name === 'acquitte_all' ? 'Acquitter tous les défauts ?' : 'Effacer tous les défauts acquittés ?';
                showConfirmModal(msg, function(ok) {
                    if (ok) {
                        // Utiliser requestSubmit pour garantir le POST natif
                        if (form.requestSubmit) {
                            form.requestSubmit(btn);
                        } else {
                            form.submit();
                        }
                    }
                });
                return false;
            });
        });
    });
    // Rafraîchissement automatique de la liste si changement détecté dans la table defauts (via crc32)
    let lastDefautCrc = <?php echo crc32(json_encode($defauts)); ?>;
    setInterval(function() {
        fetch('Defaut.php?check=1')
            .then(r => r.text())
            .then(txt => {
                let match = txt.match(/data-defaut-crc="(\d+)"/);
                if (match && parseInt(match[1]) !== lastDefautCrc) {
                    location.reload();
                }
            });
    }, 4000);
    </script>
</head>
<body>
<div class="container" data-defaut-count="<?php echo count($defauts); ?>">
    <h2 style="display:flex;align-items:center;justify-content:space-between;">
        Liste des défauts
        <span style="display:flex;gap:1em;">
            <form method="post" style="display:inline;">
                <button type="submit" name="acquitte_all" class="btn-aquitte" style="background:#f59e42;">Acquittement total</button>
            </form>
            <form method="post" style="display:inline;">
                <button type="submit" name="delete_all" class="btn-aquitte" style="background:#ef4444;">Effacer la liste</button>
            </form>
        </span>
    </h2>
    <table class="defaut-list">
        <colgroup>
            <col style="width:120px">
            <col style="width:90px">
            <col style="width:120px">
            <col style="width:90px">
            <col style="width:120px">
        </colgroup>
        <thead>
            <tr>
                <th>Date</th>
                <th>Heure</th>
                <th>Unité</th>
                <th>Code</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($defauts)): ?>
            <tr>
                <td colspan="5" style="text-align:center; color:#64748b; font-size:1.3em; padding:2em 0; background:#f3f4f6; border-radius:12px; font-style:italic;">Aucun défaut trouvé</td>
            </tr>
        <?php else: ?>
        <?php foreach ($defauts as $d): ?>
            <tr class="etat-<?= (int)$d['etat'] ?>">
                <td><?= htmlspecialchars($d['date']) ?></td>
                <td><?= htmlspecialchars($d['heure']) ?></td>
                <td><?= htmlspecialchars($d['unite']) ?><?php if(isset($unites[$d['unite']])): ?> - <span style="color:#2563eb; font-weight:500;"><?= htmlspecialchars($unites[$d['unite']]) ?></span><?php endif; ?></td>
                <td><?= htmlspecialchars($d['code']) ?></td>
                <td>
                <?php if ((int)$d['etat'] !== 3): ?>
                    <form method="post" style="margin:0;display:inline;">
                        <input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
                        <button type="submit" name="acquitte" class="btn-aquitte">Acquitter</button>
                    </form>
                <?php else: ?>
                    <span style="color:#888;">Acquitté</span>
                <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>
</body>
</html>
