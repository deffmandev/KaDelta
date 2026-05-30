<?php
require_once __DIR__ . '/auth.php';

auth_bootstrap();
$currentUser = auth_require_active_session();

if (!auth_is_admin($currentUser)) {
    http_response_code(403);
    echo '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Acces refuse</title></head><body style="font-family:Segoe UI,Arial,sans-serif;background:#f1f5f9;color:#0f172a;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;"><div style="background:#fff;padding:24px 28px;border-radius:12px;box-shadow:0 8px 24px rgba(0,0,0,.12);max-width:520px;"><h2 style="margin:0 0 10px;">Acces refuse</h2><p style="margin:0;line-height:1.45;">Cette page est reservee aux administrateurs.</p></div></body></html>';
    exit;
}

$flash = ['type' => '', 'message' => ''];

function gu_set_flash(&$flash, $type, $message)
{
    $flash['type'] = $type;
    $flash['message'] = $message;
}

function gu_is_valid_identifiant($identifiant)
{
    if ($identifiant === '' || strlen($identifiant) < 3 || strlen($identifiant) > 60) {
        return false;
    }

    return (bool)preg_match('/^[A-Za-z0-9._-]+$/', $identifiant);
}

function gu_user_exists($identifiant)
{
    $stmt = auth_run_query('SELECT TOP 1 Id FROM dbo.Utilisateurs WHERE Identifiant = ?', [$identifiant]);
    if ($stmt === false) {
        return false;
    }
    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    return is_array($row);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim((string)($_POST['action'] ?? ''));

    if ($action === 'add_user') {
        $newIdentifiant = trim((string)($_POST['new_identifiant'] ?? ''));

        if (!gu_is_valid_identifiant($newIdentifiant)) {
            gu_set_flash($flash, 'error', 'Identifiant invalide (3-60 caracteres, lettres/chiffres/._-).');
        } elseif (strtolower($newIdentifiant) === strtolower(AUTH_BYPASS_USERNAME)) {
            gu_set_flash($flash, 'error', 'L\'identifiant admin est reserve.');
        } elseif (gu_user_exists($newIdentifiant)) {
            gu_set_flash($flash, 'error', 'Cet identifiant existe deja.');
        } else {
            $ok = auth_create_user_if_not_exists($newIdentifiant, AUTH_DEFAULT_PASSWORD);
            gu_set_flash($flash, $ok ? 'ok' : 'error', $ok ? 'Utilisateur ajoute. Mot de passe initialise a 12345.' : 'Erreur lors de la creation utilisateur.');
        }
    }

    if ($action === 'rename_user') {
        $userId = (int)($_POST['user_id'] ?? 0);
        $newIdentifiant = trim((string)($_POST['rename_identifiant'] ?? ''));

        if ($userId <= 0) {
            gu_set_flash($flash, 'error', 'Utilisateur invalide.');
        } elseif (!gu_is_valid_identifiant($newIdentifiant)) {
            gu_set_flash($flash, 'error', 'Nouvel identifiant invalide.');
        } elseif (strtolower($newIdentifiant) === strtolower(AUTH_BYPASS_USERNAME)) {
            gu_set_flash($flash, 'error', 'L\'identifiant admin est reserve.');
        } else {
            $stmt = auth_run_query('SELECT TOP 1 Id FROM dbo.Utilisateurs WHERE Identifiant = ? AND Id <> ?', [$newIdentifiant, $userId]);
            $exists = false;
            if ($stmt !== false) {
                $exists = (bool)sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
            }

            if ($exists) {
                gu_set_flash($flash, 'error', 'Ce nouvel identifiant est deja utilise.');
            } else {
                $ok = auth_run_query('UPDATE dbo.Utilisateurs SET Identifiant = ?, DateMaj = SYSUTCDATETIME() WHERE Id = ?', [$newIdentifiant, $userId]) !== false;
                gu_set_flash($flash, $ok ? 'ok' : 'error', $ok ? 'Identifiant modifie.' : 'Erreur pendant la modification.');
            }
        }
    }

    if ($action === 'change_role') {
        $userId = (int)($_POST['user_id'] ?? 0);
        $newRole = auth_normalize_role($_POST['new_role'] ?? AUTH_ROLE_USER);

        if ($userId <= 0) {
            gu_set_flash($flash, 'error', 'Utilisateur invalide.');
        } else {
            $ok = auth_run_query('UPDATE dbo.Utilisateurs SET [Role] = ?, DateMaj = SYSUTCDATETIME() WHERE Id = ?', [$newRole, $userId]) !== false;
            gu_set_flash($flash, $ok ? 'ok' : 'error', $ok ? 'Role utilisateur mis a jour.' : 'Erreur pendant la mise a jour du role.');
        }
    }

    if ($action === 'reset_password') {
        $userId = (int)($_POST['user_id'] ?? 0);

        if ($userId <= 0) {
            gu_set_flash($flash, 'error', 'Utilisateur invalide.');
        } else {
            $ok = auth_reset_password_to_default($userId);
            gu_set_flash($flash, $ok ? 'ok' : 'error', $ok ? 'Mot de passe reinitialise a 12345 (changement obligatoire).' : 'Erreur pendant la reinitialisation.');
        }
    }

    if ($action === 'delete_user') {
        $userId = (int)($_POST['user_id'] ?? 0);

        if ($userId <= 0) {
            gu_set_flash($flash, 'error', 'Utilisateur invalide.');
        } else {
            $ok = auth_run_query('DELETE FROM dbo.Utilisateurs WHERE Id = ?', [$userId]) !== false;
            if ($ok && (int)($currentUser['Id'] ?? 0) === $userId) {
                auth_logout();
                auth_redirect('loginin.php');
            }

            gu_set_flash($flash, $ok ? 'ok' : 'error', $ok ? 'Utilisateur supprime.' : 'Erreur pendant la suppression.');
        }
    }
}

$users = [];
$stmtUsers = auth_run_query('SELECT Id, Identifiant, [Role], MotDePasseEnc, DateDerniereConnexion, DateCreation, DateMaj FROM dbo.Utilisateurs ORDER BY Identifiant');
if ($stmtUsers !== false) {
    while ($row = sqlsrv_fetch_array($stmtUsers, SQLSRV_FETCH_ASSOC)) {
        $users[] = $row;
    }
}

function gu_format_dt($value)
{
    if ($value instanceof DateTimeInterface) {
        return $value->format('d/m/Y H:i:s');
    }
    if (is_string($value) && trim($value) !== '') {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
    return '-';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des utilisateurs</title>
    <style>
        html { box-sizing: border-box; }
        *, *:before, *:after { box-sizing: inherit; }

        body {
            margin: 0;
            font-family: 'Segoe UI', Arial, sans-serif;
            background: rgba(86, 98, 114, 0.18);
            min-height: 100vh;
        }

        .container {
            max-width: 99vw;
            width: 1520px;
            min-width: 460px;
            margin: 0;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: #fff;
            padding: 1.6vw 1.8vw;
            border-radius: 18px;
            box-shadow: 0 4px 24px #0002;
            overflow: hidden;
        }

        h2 {
            margin: 0 0 1.1vw 0;
            font-size: 1.3em;
            color: #2c3e50;
        }

        .btn-close {
            position: absolute;
            top: 18px;
            right: 18px;
            background: none;
            border: none;
            font-size: 2em;
            cursor: pointer;
            color: #64748b;
            z-index: 10;
        }

        .btn-close:hover {
            color: #ef4444;
        }

        .flash {
            border-radius: 8px;
            padding: 0.8em 1em;
            margin-bottom: 1em;
            font-size: 1em;
        }

        .flash.ok {
            background: #e6f7eb;
            color: #166534;
            border: 1px solid #86efac;
        }

        .flash.error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fca5a5;
        }

        .toolbar {
            display: flex;
            align-items: end;
            gap: 0.8em;
            flex-wrap: wrap;
            margin-bottom: 1em;
        }

        .toolbar > * {
            margin: 0;
        }

        .toolbar label {
            display: flex;
            flex-direction: column;
            gap: 0.3em;
            color: #34495e;
            font-weight: 500;
            font-size: 0.95em;
        }

        input[type="text"] {
            min-width: 220px;
            padding: 0.7em;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            font-size: 1em;
            background: #f8fafc;
            transition: border .2s;
        }

        input[type="text"]:focus {
            border: 1.5px solid #2563eb;
            outline: none;
            background: #fff;
        }

        .hint {
            color: #64748b;
            font-size: 0.92em;
        }

        .btn {
            display: inline-block;
            padding: 0.8em 1.4em;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.95em;
            font-weight: 600;
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

        .table-header {
            border-radius: 12px 12px 0 0;
            box-shadow: 0 2px 8px #0001;
            background: #fafbfc;
            width: 100%;
            margin-bottom: 0;
            table-layout: fixed;
            border-collapse: separate;
            border-spacing: 0;
        }

        .table-header th {
            position: sticky;
            top: 0;
            z-index: 11;
            background: #e0e7ef;
            color: rgb(41, 59, 77);
            font-weight: 800;
            font-style: italic;
            padding: 1em 0.6em;
            text-align: left;
            background-clip: padding-box;
        }

        .table-header th:last-child {
            text-align: right;
            padding-right: 1.1em;
        }

        .table-scroll {
            max-height: 30em;
            overflow-y: auto;
            border-radius: 0 0 12px 12px;
            box-shadow: 0 2px 8px #0001;
            position: relative;
        }

        .table-scroll table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            background: #fafbfc;
            table-layout: fixed;
            font-size: 0.95em;
        }

        .table-scroll td {
            border: none;
            padding: 0.9em 0.6em;
            text-align: left;
            vertical-align: middle;
        }

        .table-scroll td:last-child {
            text-align: right;
            padding-right: 1.1em;
        }

        .table-scroll tr:nth-child(even) { background: #f6f8fa; }
        .table-scroll tr:hover { background: #e3e9f7; }

        .col-user { width: 16ch; }
        .col-date { width: 18ch; }
        .col-state { width: 14ch; }
        .col-role { width: 14ch; }
        .col-actions { width: 34ch; }

        .actions {
            display: flex;
            gap: 0.45em;
            align-items: center;
            flex-wrap: wrap;
            justify-content: flex-end;
        }

        .actions form {
            margin: 0;
            display: inline-flex;
        }

        .role-form {
            justify-content: flex-start;
            flex-wrap: nowrap;
        }

        .role-select {
            width: 100%;
            min-width: 0;
            max-width: 130px;
            padding: 0.35em 0.45em;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            background: #fff;
            font-size: 0.9em;
        }

        .user-actions {
            flex-wrap: nowrap;
        }

        .rename-modal {
            display: none;
            position: absolute;
            z-index: 2000;
            left: 12px;
            top: 12px;
            right: 12px;
            bottom: 12px;
            overflow: auto;
            background: rgba(44, 62, 80, 0.22);
            border-radius: 12px;
            align-items: center;
            justify-content: center;
            padding: 28px 12px;
        }

        .action-modal {
            display: none;
            position: absolute;
            z-index: 2100;
            left: 12px;
            top: 12px;
            right: 12px;
            bottom: 12px;
            background: rgba(44, 62, 80, 0.28);
            border-radius: 12px;
            align-items: center;
            justify-content: center;
            padding: 28px 12px;
        }

        .action-modal-content {
            background: #fff;
            margin: 0;
            padding: 1.2em 1.3em 1.1em 1.3em;
            border-radius: 16px;
            width: 460px;
            max-width: 94vw;
            box-shadow: 0 8px 32px #0003;
            position: relative;
        }

        .action-modal-title {
            font-size: 1.15em;
            color: #2563eb;
            font-weight: 700;
            margin-bottom: 0.6em;
        }

        .action-modal-text {
            color: #334155;
            font-size: 0.98em;
            line-height: 1.45;
        }

        .action-modal-footer {
            margin-top: 1em;
            display: flex;
            justify-content: flex-end;
            gap: 0.6em;
        }

        .rename-modal-content {
            background: #fff;
            margin: 0;
            padding: 1.2em 1.3em 1em 1.3em;
            border-radius: 16px;
            max-width: 94vw;
            width: 460px;
            max-height: calc(100% - 56px);
            box-shadow: 0 8px 32px #0003;
            position: relative;
        }

        .rename-modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.8em;
        }

        .rename-modal-title {
            font-size: 1.15em;
            color: #2563eb;
            font-weight: 700;
        }

        .rename-modal-footer {
            margin-top: 1em;
            display: flex;
            justify-content: flex-end;
            gap: 0.6em;
        }

        .badge-reset {
            display: inline-block;
            padding: 0.36em 0.62em;
            border-radius: 999px;
            background: #fff7d6;
            border: 1px solid #facc15;
            color: #7c5c00;
            font-size: 0.83em;
            font-weight: 600;
        }

        @media (max-width: 980px) {
            .container {
                width: 96vw;
                min-width: 0;
                padding: 1em;
            }

            .table-header { display: none; }

            .table-scroll table,
            .table-scroll tbody,
            .table-scroll tr,
            .table-scroll td {
                display: block;
                width: 100%;
            }

            .table-scroll tr {
                margin-bottom: 0.9em;
                border-radius: 10px;
                box-shadow: 0 1px 4px #0001;
                padding: 0.3em 0;
            }

            .table-scroll td {
                padding: 0.55em 0.8em;
            }
        }
    </style>
</head>
<body>
<div class="container">
    <button class="btn-close" id="close-main-btn" title="Fermer">&times;</button>
    <h2>Gestion des utilisateurs</h2>

    <?php if ($flash['message'] !== ''): ?>
        <div class="flash <?php echo $flash['type'] === 'ok' ? 'ok' : 'error'; ?>">
            <?php echo htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8'); ?>
        </div>
    <?php endif; ?>

    <form method="post" class="toolbar" autocomplete="off">
        <input type="hidden" name="action" value="add_user">
        <label>
            Nouvel utilisateur
            <input type="text" name="new_identifiant" placeholder="identifiant" maxlength="60" required>
        </label>
        <button class="btn btn-primary" type="submit">Ajouter</button>
        <span class="hint">Creation avec mot de passe reinitialise a 12345 (changement obligatoire).</span>
    </form>

    <table class="table-header">
        <colgroup>
            <col class="col-user">
            <col class="col-date">
            <col class="col-date">
            <col class="col-date">
            <col class="col-state">
            <col class="col-role">
            <col class="col-actions">
        </colgroup>
        <thead>
            <tr>
                <th>Utilisateur</th>
                <th>Derniere connexion</th>
                <th>Date creation</th>
                <th>Date modification</th>
                <th>Etat MDP</th>
                <th>Role</th>
                <th>Actions</th>
            </tr>
        </thead>
    </table>

    <div class="table-scroll">
        <table>
            <colgroup>
                <col class="col-user">
                <col class="col-date">
                <col class="col-date">
                <col class="col-date">
                <col class="col-state">
                <col class="col-role">
                <col class="col-actions">
            </colgroup>
            <tbody>
            <?php foreach ($users as $u): ?>
                <?php
                $uid = (int)$u['Id'];
                $isReset = trim((string)($u['MotDePasseEnc'] ?? '')) === AUTH_DEFAULT_PASSWORD;
                ?>
                <tr>
                    <td><?php echo htmlspecialchars((string)$u['Identifiant'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo gu_format_dt($u['DateDerniereConnexion'] ?? null); ?></td>
                    <td><?php echo gu_format_dt($u['DateCreation'] ?? null); ?></td>
                    <td><?php echo gu_format_dt($u['DateMaj'] ?? null); ?></td>
                    <td>
                        <?php if ($isReset): ?>
                            <span class="badge-reset">Reset 12345</span>
                        <?php else: ?>
                            Normal
                        <?php endif; ?>
                    </td>
                    <td>
                        <form method="post" class="actions role-form">
                            <input type="hidden" name="action" value="change_role">
                            <input type="hidden" name="user_id" value="<?php echo $uid; ?>">
                            <select name="new_role" class="role-select">
                                <?php $roleValue = auth_normalize_role($u['Role'] ?? AUTH_ROLE_USER); ?>
                                <option value="<?php echo AUTH_ROLE_USER; ?>" <?php echo $roleValue === AUTH_ROLE_USER ? 'selected' : ''; ?>><?php echo AUTH_ROLE_USER; ?></option>
                                <option value="<?php echo AUTH_ROLE_ADMIN; ?>" <?php echo $roleValue === AUTH_ROLE_ADMIN ? 'selected' : ''; ?>><?php echo AUTH_ROLE_ADMIN; ?></option>
                            </select>
                        </form>
                    </td>
                    <td>
                        <div class="actions user-actions">
                            <button
                                class="btn btn-primary btn-rename-user"
                                type="button"
                                data-user-id="<?php echo $uid; ?>"
                                data-identifiant="<?php echo htmlspecialchars((string)$u['Identifiant'], ENT_QUOTES, 'UTF-8'); ?>"
                            >Modifier</button>

                            <form method="post" class="reset-form">
                                <input type="hidden" name="action" value="reset_password">
                                <input type="hidden" name="user_id" value="<?php echo $uid; ?>">
                                <input type="hidden" name="user_name" value="<?php echo htmlspecialchars((string)$u['Identifiant'], ENT_QUOTES, 'UTF-8'); ?>">
                                <button class="btn btn-warning btn-open-reset-modal" type="button">Reinitialiser MDP</button>
                            </form>

                            <form method="post" class="delete-form">
                                <input type="hidden" name="action" value="delete_user">
                                <input type="hidden" name="user_id" value="<?php echo $uid; ?>">
                                <input type="hidden" name="user_name" value="<?php echo htmlspecialchars((string)$u['Identifiant'], ENT_QUOTES, 'UTF-8'); ?>">
                                <button class="btn btn-danger btn-open-delete-modal" type="button" title="Supprimer" aria-label="Supprimer">&#128465;</button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>

            <?php if (count($users) === 0): ?>
                <tr>
                    <td colspan="7">Aucun utilisateur en base.</td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="rename-modal" id="renameModal">
        <div class="rename-modal-content">
            <div class="rename-modal-header">
                <span class="rename-modal-title">Modifier le nom utilisateur</span>
                <button type="button" class="btn-close" style="position:static;font-size:1.6em;" onclick="closeRenameModal()">&times;</button>
            </div>

            <form method="post" id="renameModalForm" autocomplete="off">
                <input type="hidden" name="action" value="rename_user">
                <input type="hidden" name="user_id" id="rename_user_id" value="0">

                <label for="rename_identifiant" style="display:block;margin-bottom:0.35em;color:#34495e;font-weight:600;">Nouvel identifiant</label>
                <input type="text" name="rename_identifiant" id="rename_identifiant" maxlength="60" required style="width:100%;min-width:0;">

                <div class="rename-modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeRenameModal()">Annuler</button>
                    <button type="submit" class="btn btn-primary">Enregistrer</button>
                </div>
            </form>
        </div>
    </div>

    <div class="action-modal" id="confirmResetModal">
        <div class="action-modal-content">
            <div class="action-modal-title">Confirmer la reinitialisation</div>
            <div class="action-modal-text" id="confirmResetText"></div>
            <div class="action-modal-footer">
                <button type="button" class="btn btn-secondary" id="cancelResetBtn">Annuler</button>
                <button type="button" class="btn btn-warning" id="confirmResetBtn">Reinitialiser</button>
            </div>
        </div>
    </div>

    <div class="action-modal" id="confirmDeleteModal">
        <div class="action-modal-content">
            <div class="action-modal-title">Confirmer la suppression</div>
            <div class="action-modal-text" id="confirmDeleteText"></div>
            <div class="action-modal-footer">
                <button type="button" class="btn btn-secondary" id="cancelDeleteBtn">Annuler</button>
                <button type="button" class="btn btn-danger" id="confirmDeleteBtn">Supprimer</button>
            </div>
        </div>
    </div>
</div>

<script>
    function closeMaintenanceOverlay() {
        try {
            if (window.parent && window.parent !== window) {
                if (window.parent.OverScreenWunites) {
                    window.parent.OverScreenWunites.style.display = 'none';
                }
                if (window.parent.OSWunite) {
                    window.parent.OSWunite.src = '';
                }
                window.parent.postMessage({ action: 'close_users_iframe' }, '*');
            }
        } catch (e) {
            // noop
        }
    }

    document.getElementById('close-main-btn').addEventListener('click', closeMaintenanceOverlay);

    let pendingResetForm = null;
    let pendingDeleteForm = null;

    function openActionModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.style.display = 'flex';
        }
    }

    function closeActionModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.style.display = 'none';
        }
    }

    function openRenameModal(userId, identifiant) {
        const modal = document.getElementById('renameModal');
        document.getElementById('rename_user_id').value = String(userId);
        const input = document.getElementById('rename_identifiant');
        input.value = identifiant || '';
        modal.style.display = 'flex';
        setTimeout(function() {
            input.focus();
            input.select();
        }, 30);
    }

    function closeRenameModal() {
        document.getElementById('renameModal').style.display = 'none';
    }

    document.addEventListener('click', function(e) {
        const btn = e.target.closest('.btn-rename-user');
        if (!btn) return;
        const uid = parseInt(btn.getAttribute('data-user-id') || '0', 10);
        const identifiant = btn.getAttribute('data-identifiant') || '';
        if (uid > 0) {
            openRenameModal(uid, identifiant);
        }
    });

    document.addEventListener('click', function(e) {
        const btn = e.target.closest('.btn-open-reset-modal');
        if (!btn) return;
        const form = btn.closest('form');
        if (!form) return;
        const unameField = form.querySelector('input[name="user_name"]');
        const uname = unameField ? unameField.value : 'cet utilisateur';
        document.getElementById('confirmResetText').textContent = 'Reinitialiser le mot de passe de ' + uname + ' a 12345 ?';
        pendingResetForm = form;
        openActionModal('confirmResetModal');
    });

    document.addEventListener('click', function(e) {
        const btn = e.target.closest('.btn-open-delete-modal');
        if (!btn) return;
        const form = btn.closest('form');
        if (!form) return;
        const unameField = form.querySelector('input[name="user_name"]');
        const uname = unameField ? unameField.value : 'cet utilisateur';
        document.getElementById('confirmDeleteText').textContent = 'Supprimer l\'utilisateur ' + uname + ' ?';
        pendingDeleteForm = form;
        openActionModal('confirmDeleteModal');
    });

    document.addEventListener('change', function(e) {
        const roleSelect = e.target.closest('.role-select');
        if (!roleSelect) return;
        const form = roleSelect.closest('form.role-form');
        if (!form) return;
        form.submit();
    });

    document.getElementById('cancelResetBtn').addEventListener('click', function() {
        pendingResetForm = null;
        closeActionModal('confirmResetModal');
    });

    document.getElementById('confirmResetBtn').addEventListener('click', function() {
        if (pendingResetForm) {
            pendingResetForm.submit();
        }
    });

    document.getElementById('cancelDeleteBtn').addEventListener('click', function() {
        pendingDeleteForm = null;
        closeActionModal('confirmDeleteModal');
    });

    document.getElementById('confirmDeleteBtn').addEventListener('click', function() {
        if (pendingDeleteForm) {
            pendingDeleteForm.submit();
        }
    });

    document.getElementById('renameModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeRenameModal();
        }
    });

    document.getElementById('confirmResetModal').addEventListener('click', function(e) {
        if (e.target === this) {
            pendingResetForm = null;
            closeActionModal('confirmResetModal');
        }
    });

    document.getElementById('confirmDeleteModal').addEventListener('click', function(e) {
        if (e.target === this) {
            pendingDeleteForm = null;
            closeActionModal('confirmDeleteModal');
        }
    });

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            if (document.getElementById('confirmResetModal').style.display === 'flex') {
                pendingResetForm = null;
                closeActionModal('confirmResetModal');
                return;
            }

            if (document.getElementById('confirmDeleteModal').style.display === 'flex') {
                pendingDeleteForm = null;
                closeActionModal('confirmDeleteModal');
                return;
            }

            if (document.getElementById('renameModal').style.display === 'flex') {
                closeRenameModal();
                return;
            }
            closeMaintenanceOverlay();
        }
    });
</script>
</body>
</html>
