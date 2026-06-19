<?php
require_once __DIR__ . '/auth.php';
auth_bootstrap();
auth_require_active_session();
require "Base.php";

$Indexvue = isset($_GET['IV']) ? (int) $_GET['IV'] : 0;
$requestedDate = isset($_GET['date']) ? trim((string) $_GET['date']) : date('d-m-Y');
if (strtotime($requestedDate) > strtotime(date('d-m-Y'))) {
    $requestedDate = date('d-m-Y');
}
$requestedDevice = isset($_GET['device']) ? (int) $_GET['device'] : 502;

$NomVue = 'Vue graphique';
$ConfigLigne = [];
$ConfigVueList = [];
$jsonOptions = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

function sendJsonResponse($payload, $statusCode = 200)
{
    global $jsonOptions;

    http_response_code($statusCode);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($payload, $jsonOptions);
    exit;
}

function fetchRows($sql)
{
    $rows = [];
    $result = mssql($sql);

    if ($result === false) {
        return $rows;
    }

    while ($row = sqlnext($result)) {
        $rows[] = $row;
    }

    return $rows;
}

function fetchSingleRow($sql)
{
    $result = mssql($sql);

    if ($result === false) {
        return null;
    }

    $row = sqlnext($result);
    return is_array($row) ? $row : null;
}

function escapeSqlIdentifier($identifier)
{
    return '[' . str_replace(']', ']]', (string) $identifier) . ']';
}

function getTableSchema($tableName)
{
    static $schemaCache = [];

    $safeTableName = preg_replace('/[^A-Za-z0-9_]/', '', (string) $tableName);

    if ($safeTableName === '') {
        return [];
    }

    if (isset($schemaCache[$safeTableName])) {
        return $schemaCache[$safeTableName];
    }

    $schemaRows = fetchRows(
        "SELECT COLUMN_NAME AS ColumnName, DATA_TYPE AS DataType, IS_NULLABLE AS IsNullable, " .
        "COLUMN_DEFAULT AS ColumnDefault, ORDINAL_POSITION AS OrdinalPosition " .
        "FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME='" . addslashes($safeTableName) . "' ORDER BY ORDINAL_POSITION"
    );

    $schema = [];

    foreach ($schemaRows as $schemaRow) {
        $columnName = isset($schemaRow['ColumnName']) ? (string) $schemaRow['ColumnName'] : '';

        if ($columnName === '') {
            continue;
        }

        $schema[$columnName] = [
            'name' => $columnName,
            'type' => strtolower((string) ($schemaRow['DataType'] ?? 'nvarchar')),
            'nullable' => strtoupper((string) ($schemaRow['IsNullable'] ?? 'YES')) === 'YES',
            'default' => $schemaRow['ColumnDefault'] ?? null,
            'position' => isset($schemaRow['OrdinalPosition']) ? (int) $schemaRow['OrdinalPosition'] : 0,
            'readonly' => strtolower($columnName) === 'id'
        ];
    }

    $schemaCache[$safeTableName] = $schema;
    return $schema;
}

function normalizeConfigVueRow($row)
{
    if (!is_array($row)) {
        return null;
    }

    $configVueId = isset($row['Id']) ? (int) $row['Id'] : 0;

    if ($configVueId <= 0) {
        return null;
    }

    $configVueName = isset($row['Nom']) && trim((string) $row['Nom']) !== ''
        ? (string) $row['Nom']
        : ('Graphique ' . $configVueId);

    return [
        'Id' => $configVueId,
        'Nom' => $configVueName
    ];
}

function fetchConfigVueList()
{
    $rows = fetchRows("SELECT Id, Nom FROM ConfigVue ORDER BY Id");
    $list = [];

    foreach ($rows as $row) {
        $normalizedRow = normalizeConfigVueRow($row);

        if ($normalizedRow !== null) {
            $list[] = $normalizedRow;
        }
    }

    return $list;
}

function fetchConfigVueById($configVueId)
{
    $configVueId = (int) $configVueId;

    if ($configVueId <= 0) {
        return null;
    }

    return fetchSingleRow("SELECT TOP 1 * FROM ConfigVue WHERE Id=" . $configVueId);
}

function fetchConfigVueLinesByViewId($configVueId)
{
    return fetchRows("SELECT * FROM ConfigVueLignes WHERE IdVue LIKE " . (int) $configVueId . " ORDER BY Id");
}

function isIntegerSqlType($dataType)
{
    return in_array($dataType, ['bigint', 'int', 'smallint', 'tinyint'], true);
}

function isDecimalSqlType($dataType)
{
    return in_array($dataType, ['decimal', 'numeric', 'float', 'real', 'money', 'smallmoney'], true);
}

function formatSqlLiteral($value, $columnMeta)
{
    $dataType = strtolower((string) ($columnMeta['type'] ?? 'nvarchar'));

    if ($value === null) {
        return ['ok' => true, 'sql' => 'NULL'];
    }

    $stringValue = trim((string) $value);

    if ($stringValue === '') {
        return ['ok' => true, 'sql' => 'NULL'];
    }

    if ($dataType === 'bit') {
        $normalizedValue = strtolower($stringValue);

        if (in_array($normalizedValue, ['1', 'true', 'oui', 'yes', 'on'], true)) {
            return ['ok' => true, 'sql' => '1'];
        }

        if (in_array($normalizedValue, ['0', 'false', 'non', 'no', 'off'], true)) {
            return ['ok' => true, 'sql' => '0'];
        }

        return ['ok' => false, 'message' => 'Valeur booleenne invalide pour ' . ($columnMeta['name'] ?? 'ce champ') . '.'];
    }

    if (isIntegerSqlType($dataType)) {
        if (!preg_match('/^-?\d+$/', $stringValue)) {
            return ['ok' => false, 'message' => 'Valeur entiere invalide pour ' . ($columnMeta['name'] ?? 'ce champ') . '.'];
        }

        return ['ok' => true, 'sql' => (string) ((int) $stringValue)];
    }

    if (isDecimalSqlType($dataType)) {
        $normalizedValue = str_replace(',', '.', $stringValue);

        if (!is_numeric($normalizedValue)) {
            return ['ok' => false, 'message' => 'Valeur numerique invalide pour ' . ($columnMeta['name'] ?? 'ce champ') . '.'];
        }

        return ['ok' => true, 'sql' => $normalizedValue];
    }

    return ['ok' => true, 'sql' => "'" . addslashes($stringValue) . "'"];
}

function buildDefaultSqlLiteral($columnMeta, $fallbackValue = null)
{
    if ($fallbackValue !== null) {
        return formatSqlLiteral($fallbackValue, $columnMeta);
    }

    $dataType = strtolower((string) ($columnMeta['type'] ?? 'nvarchar'));

    if ($dataType === 'bit' || isIntegerSqlType($dataType) || isDecimalSqlType($dataType)) {
        return ['ok' => true, 'sql' => '0'];
    }

    return ['ok' => true, 'sql' => "''"];
}

if (isset($_GET['config_vue_editor']) && isset($_GET['IdVue'])) {
    $configVueId = (int) $_GET['IdVue'];
    $configVue = fetchConfigVueById($configVueId);

    if (!is_array($configVue)) {
        sendJsonResponse(['ok' => false, 'message' => 'Graphique introuvable.'], 404);
    }

    sendJsonResponse([
        'ok' => true,
        'view' => $configVue,
        'lines' => fetchConfigVueLinesByViewId($configVueId),
        'lineSchema' => array_values(getTableSchema('ConfigVueLignes'))
    ]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = (string) $_POST['action'];

    if ($action === 'create_config_vue') {
        $nextName = isset($_POST['Nom']) ? trim((string) $_POST['Nom']) : '';

        if ($nextName === '') {
            sendJsonResponse(['ok' => false, 'message' => 'Le nom du graphique est obligatoire.'], 400);
        }

        $configVueSchema = getTableSchema('ConfigVue');
        $fields = [];
        $values = [];

        foreach ($configVueSchema as $columnMeta) {
            $columnName = (string) $columnMeta['name'];

            if (strtolower($columnName) === 'id') {
                continue;
            }

            $formattedValue = null;

            if ($columnName === 'Nom') {
                $formattedValue = formatSqlLiteral($nextName, $columnMeta);
            } elseif (in_array($columnName, ['Device', 'IdDevice', 'DeviceId'], true)) {
                $formattedValue = buildDefaultSqlLiteral($columnMeta, 502);
            } elseif (!$columnMeta['nullable'] && $columnMeta['default'] === null) {
                $formattedValue = buildDefaultSqlLiteral($columnMeta);
            }

            if ($formattedValue === null) {
                continue;
            }

            if ($formattedValue['ok'] !== true) {
                sendJsonResponse(['ok' => false, 'message' => $formattedValue['message']], 400);
            }

            $fields[] = escapeSqlIdentifier($columnName);
            $values[] = $formattedValue['sql'];
        }

        if (count($fields) === 0) {
            sendJsonResponse(['ok' => false, 'message' => 'Aucun champ exploitable pour creer le graphique.'], 500);
        }

        $insertResult = mssql(
            "INSERT INTO ConfigVue (" . implode(', ', $fields) . ") OUTPUT INSERTED.Id AS Id VALUES (" . implode(', ', $values) . ")"
        );

        if ($insertResult === false) {
            sendJsonResponse(['ok' => false, 'message' => 'La creation du graphique a echoue.'], 500);
        }

        $insertedRow = sqlnext($insertResult);
        $newConfigVueId = isset($insertedRow['Id']) ? (int) $insertedRow['Id'] : 0;
        $newConfigVue = fetchConfigVueById($newConfigVueId);

        if ($newConfigVueId <= 0 || !is_array($newConfigVue)) {
            sendJsonResponse(['ok' => false, 'message' => 'Le graphique a ete cree mais son identifiant reste introuvable.'], 500);
        }

        sendJsonResponse([
            'ok' => true,
            'id' => $newConfigVueId,
            'view' => $newConfigVue,
            'list' => fetchConfigVueList(),
            'message' => 'Graphique cree avec succes.'
        ]);
    }

    if ($action === 'rename_config_vue') {
        $configVueId = isset($_POST['Id']) ? (int) $_POST['Id'] : 0;
        $nextName = isset($_POST['Nom']) ? trim((string) $_POST['Nom']) : '';

        if ($configVueId <= 0) {
            sendJsonResponse(['ok' => false, 'message' => 'Identifiant de graphique invalide.'], 400);
        }

        if ($nextName === '') {
            sendJsonResponse(['ok' => false, 'message' => 'Le nom du graphique est obligatoire.'], 400);
        }

        $updateResult = mssql("UPDATE ConfigVue SET Nom='" . addslashes($nextName) . "' WHERE Id=" . $configVueId);

        if ($updateResult === false) {
            sendJsonResponse(['ok' => false, 'message' => 'La mise a jour du graphique a echoue.'], 500);
        }

        sendJsonResponse([
            'ok' => true,
            'id' => $configVueId,
            'nom' => $nextName,
            'list' => fetchConfigVueList(),
            'message' => 'Graphique mis a jour avec succes.'
        ]);
    }

    if ($action === 'delete_config_vue') {
        $configVueId = isset($_POST['Id']) ? (int) $_POST['Id'] : 0;

        if ($configVueId <= 0) {
            sendJsonResponse(['ok' => false, 'message' => 'Identifiant de graphique invalide.'], 400);
        }

        $deleteLinesResult = mssql("DELETE FROM ConfigVueLignes WHERE IdVue=" . $configVueId);

        if ($deleteLinesResult === false) {
            sendJsonResponse(['ok' => false, 'message' => 'La suppression des lignes du graphique a echoue.'], 500);
        }

        $deleteViewResult = mssql("DELETE FROM ConfigVue WHERE Id=" . $configVueId);

        if ($deleteViewResult === false) {
            sendJsonResponse(['ok' => false, 'message' => 'La suppression du graphique a echoue.'], 500);
        }

        sendJsonResponse([
            'ok' => true,
            'id' => $configVueId,
            'list' => fetchConfigVueList(),
            'message' => 'Graphique supprime avec succes.'
        ]);
    }

    if ($action === 'save_config_vue_line') {
        $configVueId = isset($_POST['IdVue']) ? (int) $_POST['IdVue'] : 0;
        $lineId = isset($_POST['LineId']) && trim((string) $_POST['LineId']) !== '' ? (int) $_POST['LineId'] : 0;
        $rowData = [];

        if ($configVueId <= 0) {
            sendJsonResponse(['ok' => false, 'message' => 'Identifiant de graphique invalide.'], 400);
        }

        if (isset($_POST['rowData'])) {
            $decodedRowData = json_decode((string) $_POST['rowData'], true);

            if (is_array($decodedRowData)) {
                $rowData = $decodedRowData;
            }
        }

        $lineSchema = getTableSchema('ConfigVueLignes');

        if (count($lineSchema) === 0) {
            sendJsonResponse(['ok' => false, 'message' => 'Impossible de lire le schema de ConfigVueLignes.'], 500);
        }

        $assignments = [];
        $fields = [];
        $values = [];
        $isInsert = $lineId <= 0;

        foreach ($lineSchema as $columnMeta) {
            $columnName = (string) $columnMeta['name'];
            $isRequiredColumn = !$columnMeta['nullable'] && $columnMeta['default'] === null && strtolower($columnName) !== 'id';

            if (strtolower($columnName) === 'id') {
                continue;
            }

            if (strtolower($columnName) === 'idvue') {
                $formattedValue = formatSqlLiteral($configVueId, $columnMeta);
            } else {
                if (!array_key_exists($columnName, $rowData)) {
                    if ($isInsert && $isRequiredColumn) {
                        $formattedValue = buildDefaultSqlLiteral($columnMeta);
                    } else {
                        if ($isInsert) {
                            continue;
                        }

                        continue;
                    }
                } else {
                    $rawValue = $rowData[$columnName];

                    if (trim((string) $rawValue) === '' && $isRequiredColumn) {
                        sendJsonResponse(['ok' => false, 'message' => 'Le champ ' . $columnName . ' est obligatoire.'], 400);
                    }

                    if ($isInsert && trim((string) $rawValue) === '') {
                        continue;
                    }

                    $formattedValue = formatSqlLiteral($rawValue, $columnMeta);
                }
            }

            if ($formattedValue['ok'] !== true) {
                sendJsonResponse(['ok' => false, 'message' => $formattedValue['message']], 400);
            }

            $escapedColumnName = escapeSqlIdentifier($columnName);

            if ($isInsert) {
                $fields[] = $escapedColumnName;
                $values[] = $formattedValue['sql'];
            } else {
                $assignments[] = $escapedColumnName . '=' . $formattedValue['sql'];
            }
        }

        if ($isInsert) {
            if (count($fields) === 0) {
                sendJsonResponse(['ok' => false, 'message' => 'Aucune valeur a enregistrer pour la nouvelle ligne.'], 400);
            }

            $insertSql = "INSERT INTO ConfigVueLignes (" . implode(', ', $fields) . ") OUTPUT INSERTED.Id AS Id VALUES (" . implode(', ', $values) . ")";
            $insertResult = mssql($insertSql);

            if ($insertResult === false) {
                sendJsonResponse(['ok' => false, 'message' => 'La creation de la ligne a echoue.'], 500);
            }

            $insertedLine = sqlnext($insertResult);
            $lineId = isset($insertedLine['Id']) ? (int) $insertedLine['Id'] : 0;

            sendJsonResponse([
                'ok' => true,
                'lineId' => $lineId,
                'message' => 'Ligne ajoutee avec succes.'
            ]);
        }

        if (count($assignments) === 0) {
            sendJsonResponse(['ok' => false, 'message' => 'Aucune modification a enregistrer pour cette ligne.'], 400);
        }

        $updateSql = "UPDATE ConfigVueLignes SET " . implode(', ', $assignments) . " WHERE Id=" . $lineId . " AND IdVue=" . $configVueId;
        $updateResult = mssql($updateSql);

        if ($updateResult === false) {
            sendJsonResponse(['ok' => false, 'message' => 'La mise a jour de la ligne a echoue.'], 500);
        }

        sendJsonResponse([
            'ok' => true,
            'lineId' => $lineId,
            'message' => 'Ligne mise a jour avec succes.'
        ]);
    }

    if ($action === 'delete_config_vue_line') {
        $configVueId = isset($_POST['IdVue']) ? (int) $_POST['IdVue'] : 0;
        $lineId = isset($_POST['LineId']) ? (int) $_POST['LineId'] : 0;

        if ($configVueId <= 0 || $lineId <= 0) {
            sendJsonResponse(['ok' => false, 'message' => 'Identifiant de ligne invalide.'], 400);
        }

        $deleteResult = mssql("DELETE FROM ConfigVueLignes WHERE Id=" . $lineId . " AND IdVue=" . $configVueId);

        if ($deleteResult === false) {
            sendJsonResponse(['ok' => false, 'message' => 'La suppression de la ligne a echoue.'], 500);
        }

        sendJsonResponse([
            'ok' => true,
            'lineId' => $lineId,
            'message' => 'Ligne supprimee avec succes.'
        ]);
    }
}

if ($Indexvue === 0) {
    $NomVue = 'Configuration des graphiques';
    $ConfigVueList = fetchConfigVueList();
} else {
    $sql = "SELECT * FROM ConfigVue WHERE Id LIKE " . $Indexvue;
    $baseVue = mssql($sql);

    if ($baseVue !== false) {
        $vueRow = sqlnext($baseVue);

        if (is_array($vueRow) && isset($vueRow['Nom']) && trim((string) $vueRow['Nom']) !== '') {
            $NomVue = (string) $vueRow['Nom'];
        }

        if (is_array($vueRow)) {
            foreach (['Device', 'IdDevice', 'DeviceId'] as $deviceField) {
                if (isset($vueRow[$deviceField]) && is_numeric($vueRow[$deviceField])) {
                    $requestedDevice = (int) $vueRow[$deviceField];
                    break;
                }
            }
        }
    }

    $sql = "SELECT * FROM ConfigVueLignes WHERE IdVue LIKE " . $Indexvue . " ORDER BY Id";
    $baseLignes = mssql($sql);

    if ($baseLignes !== false) {
        while ($ligne = sqlnext($baseLignes)) {
            $ConfigLigne[] = $ligne;
        }
    }
}

require "Style.php";
require "TopBar.php";

?>

<style>
.vg-shell {
    width: min(1280px, calc(100% - 36px));
    margin: 18px auto 36px auto;
    display: grid;
    gap: 18px;
    color: #e5eef9;
}

.vg-title {
    margin: 0;
    font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
    font-size: clamp(1.6rem, 2.2vw, 2.3rem);
}

.vg-card {
    background: linear-gradient(180deg, rgba(18, 28, 42, 0.95), rgba(10, 18, 30, 0.95));
    border: 1px solid rgba(114, 153, 204, 0.18);
    border-radius: 24px;
    box-shadow: 0 24px 60px rgba(0, 0, 0, 0.28);
    padding: 22px;
    backdrop-filter: blur(10px);
}

.vg-toolbar {
    display: flex;
    gap: 12px;
    justify-content: space-between;
    align-items: flex-start;
    flex-wrap: wrap;
}

.vg-toolbar-main {
    display: grid;
    gap: 8px;
}

.vg-toolbar-note {
    margin: 0;
    color: #9fb1c8;
    line-height: 1.5;
    max-width: 820px;
}

.vg-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}

.vg-button,
.vg-date-button {
    border: 0;
    border-radius: 999px;
    padding: 11px 18px;
    font: inherit;
    font-weight: 700;
    color: #07111b;
    background: linear-gradient(135deg, #ffb066, #ff8e3c);
    cursor: pointer;
    box-shadow: 0 14px 30px rgba(255, 142, 60, 0.22);
    transition: transform 160ms ease, box-shadow 160ms ease, opacity 160ms ease;
}

.vg-button:hover:not(:disabled),
.vg-date-button:hover,
.vg-date-button:focus-visible {
    transform: translateY(-1px);
    box-shadow: 0 18px 34px rgba(255, 142, 60, 0.28);
}

.vg-button:disabled {
    opacity: 0.6;
    cursor: wait;
}

.vg-date-button {
    color: #07111b;
}

.vg-date-button strong {
    font-weight: 800;
}

.vg-date-nav {
    display: flex;
    align-items: center;
    gap: 4px;
}

.vg-date-nav-step {
    border: 0;
    border-radius: 999px;
    width: 40px;
    height: 40px;
    padding: 0;
    font-size: 1.25rem;
    font-weight: 900;
    line-height: 1;
    color: #07111b;
    background: linear-gradient(135deg, #ffb066, #ff8e3c);
    cursor: pointer;
    box-shadow: 0 14px 30px rgba(255, 142, 60, 0.22);
    transition: transform 160ms ease, box-shadow 160ms ease, opacity 160ms ease;
    flex-shrink: 0;
}

.vg-date-nav-step:hover {
    transform: translateY(-1px);
    box-shadow: 0 18px 34px rgba(255, 142, 60, 0.28);
}

.vg-date-input-hidden {
    position: absolute;
    opacity: 0;
    pointer-events: none;
    width: 0;
    height: 0;
    overflow: hidden;
}

.vg-status {
    margin-top: 18px;
    padding: 14px 16px;
    border-radius: 16px;
    background: rgba(109, 211, 160, 0.08);
    border: 1px solid rgba(109, 211, 160, 0.2);
}

.vg-status.error {
    background: rgba(255, 107, 107, 0.1);
    border-color: rgba(255, 107, 107, 0.28);
}

.vg-loading-overlay {
    display: none;
    position: fixed;
    inset: 0;
    z-index: 9999;
    background: rgba(6, 12, 22, 0.72);
    backdrop-filter: blur(4px);
    -webkit-backdrop-filter: blur(4px);
    align-items: center;
    justify-content: center;
    flex-direction: column;
    gap: 18px;
}

.vg-loading-overlay.active {
    display: flex;
}

.vg-loading-overlay-ring {
    width: 56px;
    height: 56px;
    border: 4px solid rgba(73, 163, 255, 0.2);
    border-top-color: #49a3ff;
    border-radius: 50%;
    animation: vg-spin 0.75s linear infinite;
}

@keyframes vg-spin {
    to { transform: rotate(360deg); }
}

.vg-summary {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 12px;
}

.vg-chip {
    padding: 16px;
    border-radius: 18px;
    background: rgba(9, 18, 31, 0.65);
    border: 1px solid rgba(114, 153, 204, 0.12);
}

.vg-chip span {
    display: block;
    color: #94a8c1;
    font-size: 0.82rem;
    margin-bottom: 7px;
    text-transform: uppercase;
    letter-spacing: 0.08em;
}

.vg-chip strong {
    display: block;
    font-size: 1.15rem;
    line-height: 1.3;
}

.vg-admin-shell {
    padding: 0 0 36px 0;
}

.vg-admin-container {
    width: min(1184px, calc(100% - 36px));
    margin: 18px auto 36px auto;
    background: #ffffff;
    border-radius: 18px;
    box-shadow: 0 4px 24px rgba(0, 0, 0, 0.14);
    padding: 28px 32px;
    color: #2c3e50;
}

.vg-admin-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 18px;
    margin-bottom: 18px;
    flex-wrap: wrap;
}

.vg-admin-title {
    margin: 0;
    font-size: 2rem;
    color: #2c3e50;
}

.vg-admin-note {
    margin: 8px 0 0 0;
    color: #64748b;
    line-height: 1.5;
}

.vg-admin-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 0.9em 1.4em;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-size: 1rem;
    font-weight: 600;
    transition: background 0.2s ease, color 0.2s ease, transform 0.2s ease;
}

.vg-admin-btn:hover:not(:disabled),
.vg-admin-btn:focus-visible:not(:disabled) {
    transform: translateY(-1px);
}

.vg-admin-btn:disabled {
    opacity: 0.65;
    cursor: wait;
}

.vg-admin-btn-primary {
    background: #2563eb;
    color: #ffffff;
}

.vg-admin-btn-primary:hover:not(:disabled),
.vg-admin-btn-primary:focus-visible:not(:disabled) {
    background: #1741a6;
}

.vg-admin-btn-warning {
    background: #ffe082;
    color: #7c5c00;
}

.vg-admin-btn-warning:hover:not(:disabled),
.vg-admin-btn-warning:focus-visible:not(:disabled) {
    background: #ffd54f;
}

.vg-admin-btn-danger {
    background: #ef4444;
    color: #ffffff;
}

.vg-admin-btn-danger:hover:not(:disabled),
.vg-admin-btn-danger:focus-visible:not(:disabled) {
    background: #b91c1c;
}

.vg-admin-btn-secondary {
    background: #64748b;
    color: #ffffff;
}

.vg-admin-btn-secondary:hover:not(:disabled),
.vg-admin-btn-secondary:focus-visible:not(:disabled) {
    background: #334155;
}

.vg-admin-btn-link {
    padding: 0.7em 1em;
    background: #f8fafc;
    color: #2563eb;
    border: 1px solid #dbe4ef;
}

.vg-admin-table-shell {
    width: 100%;
    overflow: hidden;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
    background: #fafbfc;
}

.vg-admin-table-scroll {
    max-height: 30em;
    overflow-y: auto;
}

.vg-admin-table {
    width: 100%;
    border-collapse: collapse;
    table-layout: fixed;
    background: #fafbfc;
}

.vg-admin-table th,
.vg-admin-table td {
    border: none;
    padding: 1em 0.7em;
    text-align: left;
    vertical-align: middle;
}

.vg-admin-table th {
    position: sticky;
    top: 0;
    z-index: 2;
    background: #e0e7ef;
    color: #34495e;
    font-weight: 600;
}

.vg-admin-table tr:nth-child(even) {
    background: #f6f8fa;
}

.vg-admin-table tr:hover {
    background: #e3e9f7;
}

.vg-admin-table td strong {
    color: #1e293b;
}

.vg-admin-row-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.vg-admin-open-link {
    color: #1e293b;
    font-weight: 600;
    text-decoration: none;
}

.vg-admin-open-link:hover,
.vg-admin-open-link:focus-visible {
    color: #2563eb;
}

.vg-admin-empty {
    margin: 0;
    padding: 1.2em;
    color: #64748b;
}

.vg-admin-status {
    margin: 18px 0;
    padding: 14px 16px;
    border-radius: 12px;
    background: #eff6ff;
    border: 1px solid #bfdbfe;
    color: #1d4ed8;
}

.vg-admin-status.error {
    background: #fef2f2;
    border-color: #fecaca;
    color: #b91c1c;
}

.vg-admin-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 34px;
    padding: 0 12px;
    border-radius: 999px;
    background: #eff6ff;
    color: #1d4ed8;
    font-weight: 600;
    white-space: nowrap;
}

.vg-admin-modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100vw;
    height: 100vh;
    overflow: auto;
    background: rgba(44, 62, 80, 0.18);
    opacity: 0;
    transition: opacity 0.35s cubic-bezier(.4,0,.2,1);
    pointer-events: none;
}

.vg-admin-modal.show {
    display: block;
    opacity: 1;
    pointer-events: auto;
}

.vg-admin-modal-content {
    background: #ffffff;
    margin: 6vh auto;
    padding: 24px 28px 22px 28px;
    border-radius: 16px;
    width: min(1260px, calc(100vw - 36px));
    min-width: 280px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
    position: relative;
    color: #2c3e50;
}

.vg-admin-modal-content.vg-admin-modal-content-sm {
    width: min(560px, calc(100vw - 36px));
}

.vg-admin-modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 16px;
}

.vg-admin-modal-title {
    margin: 0;
    font-size: 1.3em;
    color: #2563eb;
}

.vg-admin-close {
    background: none;
    border: none;
    font-size: 1.5em;
    cursor: pointer;
    color: #64748b;
}

.vg-admin-close:hover,
.vg-admin-close:focus-visible {
    color: #ef4444;
}

.vg-admin-form {
    display: grid;
    gap: 16px;
}

.vg-admin-form-row {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    gap: 16px;
    align-items: end;
}

.vg-admin-inline-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}

.vg-admin-field {
    display: grid;
    gap: 8px;
}

.vg-admin-field label {
    margin: 0;
    color: #34495e;
    font-weight: 500;
}

.vg-admin-field input,
.vg-admin-field select,
.vg-admin-field textarea {
    width: 100%;
    padding: 0.75em;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    font: inherit;
    background: #f8fafc;
    color: #1e293b;
    transition: border 0.2s ease, background 0.2s ease;
}

.vg-admin-field input:focus,
.vg-admin-field select:focus,
.vg-admin-field textarea:focus {
    border: 1.5px solid #2563eb;
    outline: none;
    background: #ffffff;
}

.vg-admin-field textarea {
    min-height: 92px;
    resize: vertical;
}

.vg-admin-modal-grid {
    display: grid;
    grid-template-columns: minmax(0, 1.8fr) minmax(320px, 1fr);
    gap: 24px;
    align-items: start;
}

.vg-admin-panel {
    display: grid;
    gap: 14px;
}

.vg-admin-section-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    flex-wrap: wrap;
}

.vg-admin-section-title {
    margin: 0;
    font-size: 1.1rem;
    color: #2c3e50;
}

.vg-admin-line-form {
    display: grid;
    gap: 14px;
    padding: 18px;
    border-radius: 12px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
}

.vg-admin-form-actions {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    flex-wrap: wrap;
}

.vg-admin-subtle {
    margin: 0;
    color: #64748b;
    line-height: 1.5;
}

.vg-admin-inline-lines {
    display: grid;
    gap: 12px;
}

.vg-admin-points-wrap {
    width: 100%;
    overflow: auto;
    max-height: 24em;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
    background: #fafbfc;
}

.vg-admin-points-table {
    width: 100%;
    table-layout: fixed;
    font-size: 0.98em;
    border-collapse: collapse;
    background: #fafbfc;
}

.vg-admin-points-table th,
.vg-admin-points-table td {
    padding: 0.55em 0.45em;
    vertical-align: middle;
    text-align: left;
    color: #111827;
}

.vg-admin-points-table th {
    position: sticky;
    top: 0;
    z-index: 2;
    background: #e0e7ef;
    color: #34495e;
    font-weight: 600;
}

.vg-admin-points-table tr:nth-child(even) {
    background: #f6f8fa;
}

.vg-admin-points-table tr:hover {
    background: #e3e9f7;
}

.vg-admin-points-table input,
.vg-admin-points-table select {
    width: 100%;
    padding: 0.35em 0.3em;
    font-size: 1em;
    border: 1px solid #cbd5e1;
    border-radius: 4px;
    background: #ffffff;
    color: #111827;
}

.vg-admin-points-table input:focus,
.vg-admin-points-table select:focus {
    outline: none;
    border-color: #2563eb;
}

.vg-admin-inline-row-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    align-items: center;
    justify-content: flex-start;
}

.vg-admin-inline-row-actions .vg-admin-btn {
    white-space: nowrap;
    padding: 0.55em 0.9em;
}

.vg-admin-icon-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 36px;
    min-height: 36px;
    padding: 0.35em;
    line-height: 1;
}

.vg-admin-icon-btn svg {
    width: 18px;
    height: 18px;
    pointer-events: none;
}

.vg-list-table {
    display: grid;
    gap: 12px;
}

.vg-list-row {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    gap: 14px;
    align-items: center;
    padding: 16px 18px;
    border-radius: 18px;
    background: rgba(9, 18, 31, 0.65);
    border: 1px solid rgba(114, 153, 204, 0.12);
}

.vg-list-link {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    min-width: 0;
    color: #e5eef9;
    text-decoration: none;
    font-weight: 700;
}

.vg-list-link:hover,
.vg-list-link:focus-visible {
    color: #ffb066;
}

.vg-list-link strong {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.vg-list-meta {
    color: #94a8c1;
    font-size: 0.92rem;
}

.vg-list-actions {
    display: flex;
    align-items: center;
    gap: 10px;
}

.vg-icon-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 44px;
    height: 44px;
    border: 1px solid rgba(114, 153, 204, 0.24);
    border-radius: 14px;
    background: rgba(114, 153, 204, 0.08);
    color: #e5eef9;
    cursor: pointer;
    transition: transform 160ms ease, border-color 160ms ease, background 160ms ease;
}

.vg-icon-button:hover,
.vg-icon-button:focus-visible {
    transform: translateY(-1px);
    border-color: rgba(255, 176, 102, 0.45);
    background: rgba(255, 176, 102, 0.14);
}

.vg-icon-button svg {
    width: 20px;
    height: 20px;
}

.vg-section-stack {
    display: grid;
    gap: 16px;
}

.vg-config-form {
    display: grid;
    gap: 16px;
}

.vg-config-header {
    display: grid;
    gap: 6px;
}

.vg-config-subtitle {
    margin: 0;
    color: #94a8c1;
}

.vg-empty-note {
    margin: 0;
    color: #94a8c1;
    line-height: 1.5;
}

.vg-lines-table-wrap {
    overflow-x: auto;
    border-radius: 18px;
    border: 1px solid rgba(114, 153, 204, 0.16);
    background: rgba(7, 14, 24, 0.9);
}

.vg-lines-table {
    width: 100%;
    border-collapse: collapse;
    min-width: 640px;
}

.vg-lines-table th,
.vg-lines-table td {
    padding: 10px 12px;
    border-bottom: 1px solid rgba(114, 153, 204, 0.1);
    text-align: left;
    vertical-align: top;
}

.vg-lines-table th {
    position: sticky;
    top: 0;
    background: rgba(18, 28, 42, 0.98);
    color: #94a8c1;
    font-size: 0.83rem;
    text-transform: uppercase;
    letter-spacing: 0.06em;
}

.vg-lines-table td {
    color: #e5eef9;
    white-space: nowrap;
}

.vg-panel-title {
    margin: 0 0 6px 0;
    font-size: 1.35rem;
}

.vg-panel-note {
    margin: 0 0 18px 0;
    color: #94a8c1;
}

.vg-chart-wrap {
    position: relative;
    height: min(68vh, 520px);
    border-radius: 22px;
    padding: 14px;
    background: linear-gradient(180deg, rgba(9, 18, 31, 0.95), rgba(7, 14, 24, 0.95));
    border: 1px solid rgba(114, 153, 204, 0.14);
}

.vg-chart-wrap.compact {
    height: var(--vg-onoff-chart-height, 220px);
}

.vg-chart-wrap canvas {
    cursor: crosshair;
}

.vg-chart-wrap canvas.is-right-panning {
    cursor: grabbing;
}

.vg-bg-progress {
    height: 4px;
    margin-top: 10px;
    border-radius: 2px;
    background: linear-gradient(90deg, rgba(73, 163, 255, 0.15) 0%, #49a3ff 50%, rgba(73, 163, 255, 0.15) 100%);
    background-size: 300% 100%;
    animation: vg-bg-slide 1.4s linear infinite;
}

@keyframes vg-bg-slide {
    0%   { background-position: 200% 0; }
    100% { background-position: -200% 0; }
}

.vg-hidden {
    display: none;
}

.vg-dialog {
    width: min(420px, calc(100% - 24px));
    border: 1px solid rgba(114, 153, 204, 0.22);
    border-radius: 24px;
    padding: 0;
    background: rgba(9, 18, 31, 0.98);
    color: #e5eef9;
    box-shadow: 0 24px 60px rgba(0, 0, 0, 0.35);
}

.vg-dialog::backdrop {
    background: rgba(2, 8, 14, 0.72);
    backdrop-filter: blur(6px);
}

.vg-dialog form {
    display: grid;
    gap: 16px;
    padding: 24px;
}

.vg-dialog p {
    margin: 0;
    color: #94a8c1;
    line-height: 1.45;
}

.vg-dialog label {
    display: grid;
    gap: 8px;
    font-weight: 600;
}

.vg-dialog input[type="date"],
.vg-dialog input[type="text"] {
    width: 100%;
    border: 1px solid rgba(114, 153, 204, 0.28);
    border-radius: 14px;
    padding: 12px 14px;
    font: inherit;
    color: #e5eef9;
    background: rgba(7, 14, 24, 0.9);
}

.vg-dialog-actions {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    flex-wrap: wrap;
}

.vg-dialog-actions .vg-cancel {
    color: #e5eef9;
    background: rgba(114, 153, 204, 0.16);
    box-shadow: none;
}

@media (max-width: 720px) {
    .vg-admin-container {
        width: min(100% - 18px, 1184px);
        padding: 20px 18px;
    }

    .vg-admin-form-row,
    .vg-admin-modal-grid {
        grid-template-columns: 1fr;
    }

    .vg-admin-modal-content {
        padding: 18px;
        margin: 3vh auto;
        width: min(calc(100vw - 18px), 1260px);
    }

    .vg-shell {
        width: min(100% - 18px, 1280px);
        margin-bottom: 24px;
    }

    .vg-card {
        padding: 18px;
        border-radius: 20px;
    }

    .vg-chart-wrap {
        height: 360px;
    }
}
</style>

<?php if ($Indexvue === 0): ?>
<main class="vg-admin-shell">
    <section class="vg-admin-container">
        <div class="vg-admin-header">
            <div>
                <h1 class="vg-admin-title"><?php echo htmlspecialchars($NomVue, ENT_QUOTES, 'UTF-8'); ?></h1>
                <p class="vg-admin-note">
                    Cliquez sur un nom pour ouvrir le graphique. Le bouton Configurer ouvre une fenetre de gestion,
                    permet de renommer la vue, d'ajouter des lignes, de les modifier et de les supprimer.
                </p>
            </div>
            <button type="button" class="vg-admin-btn vg-admin-btn-primary" id="vgCreateConfigButton">Ajouter un graphique</button>
        </div>

        <div class="vg-admin-table-shell">
            <table class="vg-admin-table">
                <colgroup>
                    <col style="width:70%">
                    <col style="width:30%">
                </colgroup>
                <thead>
                    <tr>
                        <th>Nom</th>
                        <th>Actions</th>
                    </tr>
                </thead>
            </table>
            <div class="vg-admin-table-scroll">
                <table class="vg-admin-table">
                    <colgroup>
                        <col style="width:70%">
                        <col style="width:30%">
                    </colgroup>
                    <tbody id="vgGraphListBody"></tbody>
                </table>
            </div>
        </div>
    </section>
</main>

<div class="vg-admin-modal" id="vgConfigModal">
    <div class="vg-admin-modal-content">
        <div class="vg-admin-modal-header">
            <div>
                <h2 class="vg-admin-modal-title" id="vgConfigDialogTitle">Configuration du graphique</h2>
            </div>
            <button type="button" class="vg-admin-close" id="vgCloseConfigDialogButton" aria-label="Fermer">&times;</button>
        </div>

        <div class="vg-admin-status" id="vgConfigDialogStatus">Selectionnez un graphique ou creez-en un nouveau.</div>

        <form id="vgConfigForm" class="vg-admin-form">
            <div class="vg-admin-form-row">
                <div class="vg-admin-field">
                    <label for="vgConfigNameInput">Nom du graphique</label>
                    <input type="text" id="vgConfigNameInput" name="Nom" maxlength="120" required>
                </div>
                <div class="vg-admin-inline-actions">
                    <button type="submit" class="vg-admin-btn vg-admin-btn-primary" id="vgSaveConfigButton">Enregistrer le graphique</button>
                    <button type="button" class="vg-admin-btn vg-admin-btn-warning" id="vgAddLineButton" disabled>Ajouter une ligne</button>
                </div>
            </div>
        </form>

        <section class="vg-admin-panel">
            <div id="vgConfigLinesContainer">
                <p class="vg-admin-empty">Aucune ligne chargee.</p>
            </div>
        </section>
    </div>
</div>

<div class="vg-admin-modal" id="vgConfirmModal">
    <div class="vg-admin-modal-content vg-admin-modal-content-sm">
        <div class="vg-admin-modal-header">
            <h2 class="vg-admin-modal-title" style="color:#ef4444;">Confirmation</h2>
            <button type="button" class="vg-admin-close" id="vgCloseConfirmDialogButton" aria-label="Fermer">&times;</button>
        </div>
        <p class="vg-admin-subtle" id="vgConfirmDialogMessage">Voulez-vous continuer ?</p>
        <div class="vg-admin-form-actions" style="margin-top:18px;">
            <button type="button" class="vg-admin-btn vg-admin-btn-secondary" id="vgCancelConfirmButton">Annuler</button>
            <button type="button" class="vg-admin-btn vg-admin-btn-danger" id="vgConfirmActionButton">Confirmer</button>
        </div>
    </div>
</div>

<script>
const initialConfigVueList = <?php echo json_encode($ConfigVueList, $jsonOptions); ?>;
const graphListBody = document.getElementById('vgGraphListBody');
const createConfigButton = document.getElementById('vgCreateConfigButton');
const configModal = document.getElementById('vgConfigModal');
const configDialogTitle = document.getElementById('vgConfigDialogTitle');
const configDialogStatus = document.getElementById('vgConfigDialogStatus');
const closeConfigDialogButton = document.getElementById('vgCloseConfigDialogButton');
const configForm = document.getElementById('vgConfigForm');
const configNameInput = document.getElementById('vgConfigNameInput');
const saveConfigButton = document.getElementById('vgSaveConfigButton');
const addLineButton = document.getElementById('vgAddLineButton');
const configLinesContainer = document.getElementById('vgConfigLinesContainer');
const confirmModal = document.getElementById('vgConfirmModal');
const confirmDialogMessage = document.getElementById('vgConfirmDialogMessage');
const closeConfirmDialogButton = document.getElementById('vgCloseConfirmDialogButton');
const cancelConfirmButton = document.getElementById('vgCancelConfirmButton');
const confirmActionButton = document.getElementById('vgConfirmActionButton');

const preferredLineColumns = [
    { label: 'Nom', aliases: ['Nom'] },
    { label: 'Device', aliases: ['Device', 'IdDevice', 'DeviceId'] },
    { label: 'Point', aliases: ['Point', 'IdPoint', 'PointId', 'NumPoint', 'ApiPoint'] },
    { label: 'Filtres', aliases: ['Filtres', 'Filtre', 'Filters', 'Filter'] },
    { label: 'Signe', aliases: ['Signe'] },
    { label: 'Couleur', aliases: ['Couleur', 'Color', 'BorderColor'] }
];

let configViewList = [];
let activeConfigViewId = 0;
let activeConfigState = {
    view: null,
    lineSchema: [],
    lines: []
};
let activeLineDraft = null;
let confirmCallback = null;

function coalesceValue(value, fallbackValue) {
    return value === null || value === undefined ? fallbackValue : value;
}

function copyObject(source) {
    const target = {};

    Object.keys(source || {}).forEach((key) => {
        target[key] = source[key];
    });

    return target;
}

function escapeHtml(value) {
    return String(coalesceValue(value, '')).replace(/[&<>"']/g, (character) => {
        const entities = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };

        return entities[character] || character;
    });
}

function findMatchingSchemaColumn(aliases) {
    const schemaColumns = Array.isArray(activeConfigState.lineSchema) ? activeConfigState.lineSchema : [];

    for (const alias of aliases) {
        const matchingColumn = schemaColumns.find((columnMeta) => String(columnMeta.name || '').toLowerCase() === String(alias).toLowerCase());

        if (matchingColumn) {
            return matchingColumn;
        }
    }

    return null;
}

function normalizeConfigViewList(rows) {
    return (Array.isArray(rows) ? rows : []).map((row) => {
        const nextId = Number(row && row.Id !== undefined ? row.Id : 0);
        const nextName = row && row.Nom !== undefined && String(row.Nom).trim() !== ''
            ? String(row.Nom).trim()
            : ('Graphique ' + nextId);

        return {
            Id: nextId,
            Nom: nextName
        };
    }).filter((row) => row.Id > 0);
}

function normalizeLineRows(rows, lineSchema) {
    return (Array.isArray(rows) ? rows : []).map((row) => {
        const normalizedRow = {};

        (Array.isArray(lineSchema) ? lineSchema : []).forEach((columnMeta) => {
            const columnName = String(columnMeta.name || '');
            normalizedRow[columnName] = row && row[columnName] !== undefined && row[columnName] !== null
                ? String(row[columnName])
                : '';
        });

        return normalizedRow;
    });
}

function getVisibleLineColumns() {
    return preferredLineColumns.map((definition) => {
        const matchingColumn = findMatchingSchemaColumn(definition.aliases);

        if (!matchingColumn) {
            return null;
        }

        const nextColumn = copyObject(matchingColumn);
        nextColumn.displayLabel = definition.label;

        return nextColumn;
    }).filter(Boolean);
}

function setConfigDialogStatus(message, isError) {
    configDialogStatus.textContent = message;
    configDialogStatus.classList.toggle('error', Boolean(isError));
}

function openModal(modalElement) {
    modalElement.style.display = 'block';
    window.requestAnimationFrame(() => {
        modalElement.classList.add('show');
    });
}

function closeModal(modalElement) {
    modalElement.classList.remove('show');
    window.setTimeout(() => {
        modalElement.style.display = 'none';
    }, 350);
}

function renderConfigList() {
    if (!configViewList.length) {
        graphListBody.innerHTML = '<tr><td colspan="2"><p class="vg-admin-empty">Aucun graphique disponible dans ConfigVue.</p></td></tr>';
        return;
    }

    graphListBody.innerHTML = configViewList.map((row) => {
        return '' +
            '<tr data-view-id="' + escapeHtml(row.Id) + '">' +
                '<td><a class="vg-admin-open-link" href="?IV=' + encodeURIComponent(row.Id) + '">' + escapeHtml(row.Nom) + '</a></td>' +
                '<td>' +
                    '<div class="vg-admin-row-actions">' +
                        '<button type="button" class="vg-admin-btn vg-admin-btn-warning" data-action="config" data-view-id="' + escapeHtml(row.Id) + '">Configurer</button>' +
                        '<button type="button" class="vg-admin-btn vg-admin-btn-danger" data-action="delete-view" data-view-id="' + escapeHtml(row.Id) + '" data-view-name="' + escapeHtml(row.Nom) + '">Supprimer</button>' +
                    '</div>' +
                '</td>' +
            '</tr>';
    }).join('');
}

function buildEmptyLineDraft() {
    const draft = { Id: '', IdVue: String(activeConfigViewId || '') };

    getVisibleLineColumns().forEach((columnMeta) => {
        const columnName = String(columnMeta.name || '');

        if (columnName === '' || columnName === 'Id' || columnName === 'IdVue') {
            return;
        }

        if (['couleur', 'color', 'bordercolor'].indexOf(columnName.toLowerCase()) !== -1) {
            draft[columnName] = 'FF0000';
        } else if (['filtres', 'filtre', 'filters', 'filter'].indexOf(columnName.toLowerCase()) !== -1) {
            draft[columnName] = '1';
        } else {
            draft[columnName] = '';
        }
    });

    return draft;
}

function buildInputField(columnMeta, currentValue) {
    const columnName = String(columnMeta.name || '');
    const value = coalesceValue(currentValue, '');
    const fieldLabel = escapeHtml(columnMeta.displayLabel || columnName);
    const fieldName = escapeHtml(columnName);
    const isRequired = columnMeta && columnMeta.nullable === false && (columnMeta.default === null || columnMeta.default === undefined);
    const requiredAttribute = isRequired ? ' required' : '';
    const labelSuffix = isRequired ? ' *' : '';

    if (String(columnMeta.type || '') === 'bit') {
        const normalizedValue = String(value).trim().toLowerCase();
        return '' +
            '<div class="vg-admin-field">' +
                '<label for="line-field-' + fieldName + '">' + fieldLabel + labelSuffix + '</label>' +
                '<select id="line-field-' + fieldName + '" name="' + fieldName + '"' + requiredAttribute + '>' +
                    '<option value=""></option>' +
                    '<option value="0"' + (normalizedValue === '0' ? ' selected' : '') + '>0</option>' +
                    '<option value="1"' + (normalizedValue === '1' ? ' selected' : '') + '>1</option>' +
                '</select>' +
            '</div>';
    }

    return '' +
        '<div class="vg-admin-field">' +
            '<label for="line-field-' + fieldName + '">' + fieldLabel + labelSuffix + '</label>' +
            '<input type="text" id="line-field-' + fieldName + '" name="' + fieldName + '" value="' + escapeHtml(value) + '"' + requiredAttribute + '>' +
        '</div>';
}

function applySigneRule(signeSelect) {
    var tr = signeSelect.closest('tr');
    if (!tr) { return; }
    var isOnOff = signeSelect.value === 'onoff';
    // couleur
    var tds = tr.querySelectorAll('td');
    for (var i = 0; i < tds.length; i++) {
        var picker = tds[i].querySelector('input[type="color"]');
        if (picker) {
            var textInput = tds[i].querySelector('input[data-col]');
            if (isOnOff) {
                picker.disabled = true;
                picker.style.opacity = '0.35';
                if (textInput) { textInput.value = ''; textInput.disabled = true; }
            } else {
                picker.disabled = false;
                picker.style.opacity = '';
                if (textInput) { textInput.disabled = false; }
            }
            break;
        }
    }
    // filtres
    var allSelects = tr.querySelectorAll('select[data-col]');
    for (var j = 0; j < allSelects.length; j++) {
        var colName = (allSelects[j].dataset.col || '').toLowerCase();
        if (colName === 'filtres' || colName === 'filtre' || colName === 'filters' || colName === 'filter') {
            if (isOnOff) {
                allSelects[j].value = '1';
                allSelects[j].disabled = true;
                allSelects[j].style.opacity = '0.35';
            } else {
                allSelects[j].disabled = false;
                allSelects[j].style.opacity = '';
            }
            break;
        }
    }
}

function applySigneConstraints() {
    var selects = configLinesContainer.querySelectorAll('select[data-col]');
    for (var i = 0; i < selects.length; i++) {
        if (selects[i].dataset && selects[i].dataset.col && selects[i].dataset.col.toLowerCase() === 'signe') {
            applySigneRule(selects[i]);
        }
    }
}

function buildTableCellInput(columnMeta, currentValue) {
    const columnName = String(columnMeta.name || '');
    const value = coalesceValue(currentValue, '');
    const fieldName = escapeHtml(columnName);
    const fieldLabel = escapeHtml(columnMeta.displayLabel || columnName);
    const isRequired = columnMeta && columnMeta.nullable === false && (columnMeta.default === null || columnMeta.default === undefined);
    const requiredAttribute = isRequired ? ' required' : '';

    if (String(columnMeta.type || '') === 'bit') {
        const normalizedValue = String(value).trim().toLowerCase();
        return '' +
            '<select data-col="' + fieldName + '" aria-label="' + fieldLabel + '"' + requiredAttribute + '>' +
                '<option value=""></option>' +
                '<option value="0"' + (normalizedValue === '0' ? ' selected' : '') + '>0</option>' +
                '<option value="1"' + (normalizedValue === '1' ? ' selected' : '') + '>1</option>' +
            '</select>';
    }

    if (['filtres', 'filtre', 'filters', 'filter'].indexOf(columnName.toLowerCase()) !== -1) {
        const filtresOptions = ['0.001', '0.01', '0.1', '1', '10', '100'];
        const currentFiltre = String(value).trim() === '' ? '1' : String(value).trim();
        let filtresHtml = '<select data-col="' + fieldName + '" aria-label="' + fieldLabel + '"' + requiredAttribute + '>';
        filtresOptions.forEach(function(opt) {
            filtresHtml += '<option value="' + opt + '"' + (currentFiltre === opt ? ' selected' : '') + '>' + opt + '</option>';
        });
        filtresHtml += '</select>';
        return filtresHtml;
    }

    if (columnName.toLowerCase() === 'signe') {
        const signeOptions = ['c', 'onoff', 'H', '%', 'P'];
        let signeHtml = '<select data-col="' + fieldName + '" aria-label="' + fieldLabel + '" onchange="applySigneRule(this)"' + requiredAttribute + '>';
        signeOptions.forEach(function(opt) {
            signeHtml += '<option value="' + opt + '"' + (value === opt ? ' selected' : '') + '>' + (opt === '' ? '—' : opt) + '</option>';
        });
        signeHtml += '</select>';
        return signeHtml;
    }

    if (['device', 'iddevice', 'deviceid', 'point', 'apipoint'].indexOf(columnName.toLowerCase()) !== -1) {
        return '<input type="number" step="1" data-col="' + fieldName + '" aria-label="' + fieldLabel + '" value="' + escapeHtml(value) + '" style="width:80px;"' + requiredAttribute + '>';
    }

    if (['couleur', 'color', 'bordercolor'].indexOf(columnName.toLowerCase()) !== -1) {
        const rawHex = String(value).replace('#', '').trim();
        const pickerVal = /^[0-9a-fA-F]{6}$/.test(rawHex) ? ('#' + rawHex) : '#7dd3a7';
        const uid = 'cp-' + fieldName + '-' + Math.floor(Math.random() * 9999);
        return '' +
            '<div style="display:flex;align-items:center;gap:6px;">' +
                '<input type="color" id="' + uid + '" value="' + escapeHtml(pickerVal) + '" style="width:36px;height:28px;padding:2px;border:1px solid #cbd5e1;border-radius:4px;cursor:pointer;" ' +
                    'oninput="(function(p){var h=p.value.replace(\'#\',\'\');var t=document.getElementById(\'t' + uid + '\');if(t){t.value=h;}var inp=p.closest(\'td\').querySelector(\'[data-col]\');if(inp){inp.value=h;}})(this)">' +
                '<input type="text" id="t' + uid + '" data-col="' + fieldName + '" aria-label="' + fieldLabel + '" value="' + escapeHtml(rawHex) + '" maxlength="6" ' +
                    'style="width:72px;" ' +
                    'oninput="(function(t){var h=t.value.replace(\'#\',\'\');if(/^[0-9a-fA-F]{6}$/.test(h)){var p=document.getElementById(\'' + uid + '\');if(p){p.value=\'#\'+h;}}})(this)"' +
                    requiredAttribute + '>' +
            '</div>';
    }

    return '<input type="text" data-col="' + fieldName + '" aria-label="' + fieldLabel + '" value="' + escapeHtml(value) + '"' + requiredAttribute + '>';
}

function buildInlineLineRow(line, visibleColumns, isDraft) {
    let html = '<tr data-line-row="1" data-line-id="' + escapeHtml(line.Id || '') + '" data-is-draft="' + (isDraft ? '1' : '0') + '">';

    visibleColumns.forEach((columnMeta) => {
        html += '<td>' + buildTableCellInput(columnMeta, coalesceValue(line[columnMeta.name], '')) + '</td>';
    });

    const trashSvg = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6h18"></path><path d="M8 6V4h8v2"></path><path d="M19 6l-1 14H6L5 6"></path><path d="M10 11v6"></path><path d="M14 11v6"></path></svg>';
    const actionBtn = isDraft
        ? '<button type="button" class="vg-admin-btn vg-admin-btn-primary" data-action="save-line">Ajouter</button>' +
          '<button type="button" class="vg-admin-btn vg-admin-btn-danger vg-admin-icon-btn" data-action="cancel-draft" title="Annuler" aria-label="Annuler">' + trashSvg + '</button>'
        : '<button type="button" class="vg-admin-btn vg-admin-btn-danger vg-admin-icon-btn" data-action="delete-line" data-line-id="' + escapeHtml(line.Id) + '" title="Supprimer la ligne" aria-label="Supprimer la ligne">' + trashSvg + '</button>';
    html += '' +
        '<td>' +
            '<div class="vg-admin-inline-row-actions">' + actionBtn + '</div>' +
        '</td>' +
    '</tr>';

    return html;
}

function renderLinesTable() {
    const visibleColumns = getVisibleLineColumns();

    if (activeConfigViewId <= 0) {
        configLinesContainer.innerHTML = '<p class="vg-admin-empty">Enregistrez d\'abord le graphique pour gerer ses lignes.</p>';
        return;
    }

    if (!visibleColumns.length) {
        configLinesContainer.innerHTML = '<p class="vg-admin-empty">Schema ConfigVueLignes indisponible.</p>';
        return;
    }

    if (!activeConfigState.lines.length && !activeLineDraft) {
        configLinesContainer.innerHTML = '<p class="vg-admin-empty">Aucune ligne associee. Utilisez Ajouter une ligne pour commencer.</p>';
        return;
    }

    let html = '<div class="vg-admin-points-wrap"><table class="vg-admin-points-table">';
    html += '<colgroup>';

    visibleColumns.forEach(() => {
        html += '<col style="width:14%">';
    });

    html += '<col style="width:16%"></colgroup>';
    html += '<thead><tr>';

    visibleColumns.forEach((columnMeta) => {
        html += '<th>' + escapeHtml(columnMeta.displayLabel || columnMeta.name) + '</th>';
    });

    html += '<th>Actions</th></tr></thead><tbody>';

    activeConfigState.lines.forEach((line) => {
        html += buildInlineLineRow(line, visibleColumns, false);
    });

    if (activeLineDraft) {
        html += buildInlineLineRow(activeLineDraft, visibleColumns, true);
    }

    html += '</tbody></table></div>';
    configLinesContainer.innerHTML = html;
    applySigneConstraints();
}

function applyEditorPayload(payload) {
    activeConfigState.view = payload && payload.view ? payload.view : null;
    activeConfigState.lineSchema = Array.isArray(payload && payload.lineSchema) ? payload.lineSchema : [];
    activeConfigState.lines = normalizeLineRows(payload && payload.lines ? payload.lines : [], activeConfigState.lineSchema);

    configNameInput.value = activeConfigState.view && activeConfigState.view.Nom ? String(activeConfigState.view.Nom) : '';
    addLineButton.disabled = activeConfigViewId <= 0;

    renderLinesTable();
}

async function loadConfigEditor(configViewId) {
    setConfigDialogStatus('Chargement du graphique et de ses lignes...', false);
    addLineButton.disabled = true;
    configLinesContainer.innerHTML = '<p class="vg-admin-empty">Chargement en cours...</p>';

    const response = await fetch('?IV=0&config_vue_editor=1&IdVue=' + encodeURIComponent(configViewId), {
        headers: {
            Accept: 'application/json'
        }
    });

    let payload = null;

    try {
        payload = await response.json();
    } catch (error) {
        throw new Error('Reponse invalide lors du chargement de la configuration.');
    }

    if (!response.ok || !payload || payload.ok !== true) {
        throw new Error(payload && payload.message ? payload.message : 'Chargement impossible.');
    }

    applyEditorPayload(payload);
    addLineButton.disabled = false;
    setConfigDialogStatus('', false);
}

function openConfigDialogForCreate() {
    activeConfigViewId = 0;
    activeConfigState = {
        view: null,
        lineSchema: [],
        lines: []
    };
    activeLineDraft = null;
    configDialogTitle.textContent = 'Ajouter un graphique';
    configNameInput.value = '';
    addLineButton.disabled = true;
    configLinesContainer.innerHTML = '<p class="vg-admin-empty">Enregistrez d\'abord le graphique pour commencer la configuration.</p>';
    setConfigDialogStatus('Renseignez le nom du nouveau graphique.', false);
    openModal(configModal);
    configNameInput.focus();
}

async function openConfigDialogForExisting(configViewId) {
    const listItem = configViewList.find((row) => Number(row.Id) === Number(configViewId));

    activeConfigViewId = Number(configViewId || 0);
    activeLineDraft = null;
    configDialogTitle.textContent = 'Configuration du graphique';
    openModal(configModal);

    try {
        await loadConfigEditor(activeConfigViewId);
        configNameInput.focus();
    } catch (error) {
        setConfigDialogStatus(error instanceof Error ? error.message : 'Chargement impossible.', true);
        configLinesContainer.innerHTML = '<p class="vg-admin-empty">Impossible de charger la configuration.</p>';
    }
}

function openConfirmDialog(message, callback) {
    confirmCallback = typeof callback === 'function' ? callback : null;
    confirmDialogMessage.textContent = message;
    openModal(confirmModal);
}

function closeConfirmDialog() {
    confirmCallback = null;
    closeModal(confirmModal);
}

async function saveLineRow(lineRow, reloadAfter) {
    if (!lineRow || activeConfigViewId <= 0) {
        setConfigDialogStatus('Aucune ligne selectionnee.', true);
        return;
    }

    const rowData = {};
    const isDraft = lineRow.dataset.isDraft === '1';
    const lineId = isDraft ? 0 : Number(lineRow.dataset.lineId || 0);

    lineRow.querySelectorAll('[data-col]').forEach((inputElement) => {
        rowData[inputElement.dataset.col] = coalesceValue(inputElement.value, '');
    });

    const requestData = new FormData();
    requestData.append('action', 'save_config_vue_line');
    requestData.append('IdVue', String(activeConfigViewId));
    requestData.append('LineId', String(lineId));
    requestData.append('rowData', JSON.stringify(rowData));

    const actionButtons = Array.from(lineRow.querySelectorAll('button'));
    actionButtons.forEach((buttonElement) => {
        buttonElement.disabled = true;
    });

    setConfigDialogStatus(isDraft ? 'Ajout de la ligne...' : 'Enregistrement automatique de la ligne...', false);

    try {
        const response = await fetch('?IV=0', {
            method: 'POST',
            body: requestData,
            headers: {
                Accept: 'application/json'
            }
        });

        const payload = await response.json();

        if (!response.ok || !payload || payload.ok !== true) {
            throw new Error(payload && payload.message ? payload.message : 'Enregistrement impossible.');
        }

        if (isDraft) {
            activeLineDraft = null;
            if (reloadAfter) {
                await loadConfigEditor(activeConfigViewId);
            } else {
                const newId = payload.lineId || 0;
                lineRow.dataset.isDraft = '0';
                lineRow.dataset.lineId = String(newId);
                const actionDiv = lineRow.querySelector('.vg-admin-inline-row-actions');
                if (actionDiv) {
                    const trashSvg = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6h18"></path><path d="M8 6V4h8v2"></path><path d="M19 6l-1 14H6L5 6"></path><path d="M10 11v6"></path><path d="M14 11v6"></path></svg>';
                    actionDiv.innerHTML = '<button type="button" class="vg-admin-btn vg-admin-btn-danger vg-admin-icon-btn" data-action="delete-line" data-line-id="' + newId + '" title="Supprimer la ligne" aria-label="Supprimer la ligne">' + trashSvg + '</button>';
                }
            }
        }

        setConfigDialogStatus(payload.message || 'Ligne enregistree avec succes.', false);
    } catch (error) {
        setConfigDialogStatus(error instanceof Error ? error.message : 'Erreur lors de l\'enregistrement de la ligne.', true);
    } finally {
        actionButtons.forEach((buttonElement) => {
            buttonElement.disabled = false;
        });
    }
}

function requestLineDeletion(lineId) {
    if (!lineId || activeConfigViewId <= 0) {
        return;
    }

    openConfirmDialog('Voulez-vous vraiment supprimer cette ligne ?', async () => {
        closeConfirmDialog();
        setConfigDialogStatus('Suppression de la ligne...', false);

        const requestData = new FormData();
        requestData.append('action', 'delete_config_vue_line');
        requestData.append('IdVue', String(activeConfigViewId));
        requestData.append('LineId', String(lineId));

        try {
            const response = await fetch('?IV=0', {
                method: 'POST',
                body: requestData,
                headers: {
                    Accept: 'application/json'
                }
            });

            const payload = await response.json();

            if (!response.ok || !payload || payload.ok !== true) {
                throw new Error(payload && payload.message ? payload.message : 'Suppression impossible.');
            }

            activeLineDraft = null;
            await loadConfigEditor(activeConfigViewId);
            setConfigDialogStatus(payload.message || 'Ligne supprimee avec succes.', false);
        } catch (error) {
            setConfigDialogStatus(error instanceof Error ? error.message : 'Erreur lors de la suppression.', true);
        }
    });
}

function requestViewDeletion(viewId, viewName) {
    if (!viewId) {
        return;
    }

    openConfirmDialog('Voulez-vous vraiment supprimer le graphique ' + viewName + ' et toutes ses lignes ?', async () => {
        closeConfirmDialog();

        const requestData = new FormData();
        requestData.append('action', 'delete_config_vue');
        requestData.append('Id', String(viewId));

        try {
            const response = await fetch('?IV=0', {
                method: 'POST',
                body: requestData,
                headers: {
                    Accept: 'application/json'
                }
            });

            const payload = await response.json();

            if (!response.ok || !payload || payload.ok !== true) {
                throw new Error(payload && payload.message ? payload.message : 'Suppression impossible.');
            }

            configViewList = normalizeConfigViewList(payload.list || []);
            renderConfigList();

            if (activeConfigViewId === Number(viewId)) {
                activeConfigViewId = 0;
                activeLineDraft = null;
                closeModal(configModal);
            }
        } catch (error) {
            setConfigDialogStatus(error instanceof Error ? error.message : 'Erreur lors de la suppression du graphique.', true);
        }
    });
}

graphListBody.addEventListener('click', (event) => {
    const configButton = event.target.closest('button[data-action="config"]');
    const deleteViewButton = event.target.closest('button[data-action="delete-view"]');

    if (configButton) {
        openConfigDialogForExisting(Number(configButton.dataset.viewId || 0));
        return;
    }

    if (deleteViewButton) {
        requestViewDeletion(Number(deleteViewButton.dataset.viewId || 0), String(deleteViewButton.dataset.viewName || 'ce graphique'));
    }
});

configLinesContainer.addEventListener('click', (event) => {
    const saveLineButton = event.target.closest('button[data-action="save-line"]');
    const deleteLineButton = event.target.closest('button[data-action="delete-line"]');
    const cancelDraftButton = event.target.closest('button[data-action="cancel-draft"]');

    if (saveLineButton) {
        const lineRow = saveLineButton.closest('tr[data-line-row="1"]');
        saveLineRow(lineRow, true);
        return;
    }

    if (cancelDraftButton) {
        activeLineDraft = null;
        renderLinesTable();
        return;
    }

    if (deleteLineButton) {
        requestLineDeletion(Number(deleteLineButton.dataset.lineId || 0));
    }
});

configLinesContainer.addEventListener('change', (event) => {
    const inputElement = event.target.closest('[data-col]');

    if (!inputElement) {
        return;
    }

    const lineRow = inputElement.closest('tr[data-line-row="1"]');

    if (!lineRow) {
        return;
    }

    saveLineRow(lineRow);
});

configLinesContainer.addEventListener('input', (event) => {
    if (!event.target || event.target.type !== 'color') {
        return;
    }

    const lineRow = event.target.closest('tr[data-line-row="1"]');

    if (!lineRow) {
        return;
    }

    saveLineRow(lineRow);
});

createConfigButton.addEventListener('click', openConfigDialogForCreate);
closeConfigDialogButton.addEventListener('click', () => closeModal(configModal));
addLineButton.addEventListener('click', async () => {
    if (activeConfigViewId <= 0) { return; }

    addLineButton.disabled = true;
    setConfigDialogStatus('Ajout de la ligne...', false);

    const draft = buildEmptyLineDraft();
    const rowData = {};
    Object.keys(draft).forEach(function(k) {
        if (k !== 'Id') { rowData[k] = draft[k]; }
    });

    const requestData = new FormData();
    requestData.append('action', 'save_config_vue_line');
    requestData.append('IdVue', String(activeConfigViewId));
    requestData.append('LineId', '0');
    requestData.append('rowData', JSON.stringify(rowData));

    try {
        const response = await fetch('?IV=0', {
            method: 'POST',
            body: requestData,
            headers: { Accept: 'application/json' }
        });

        const payload = await response.json();

        if (!response.ok || !payload || payload.ok !== true) {
            throw new Error(payload && payload.message ? payload.message : 'Ajout impossible.');
        }

        activeLineDraft = null;
        await loadConfigEditor(activeConfigViewId);
        setConfigDialogStatus(payload.message || 'Ligne ajoutee avec succes.', false);
    } catch (error) {
        setConfigDialogStatus(error instanceof Error ? error.message : 'Erreur lors de l\'ajout.', true);
        addLineButton.disabled = false;
    }
});

configForm.addEventListener('submit', async (event) => {
    event.preventDefault();

    const nextName = configNameInput.value.trim();

    if (nextName === '') {
        setConfigDialogStatus('Le nom du graphique est obligatoire.', true);
        configNameInput.focus();
        return;
    }

    const requestData = new FormData();
    const isCreate = activeConfigViewId <= 0;
    requestData.append('action', isCreate ? 'create_config_vue' : 'rename_config_vue');
    requestData.append('Nom', nextName);

    if (!isCreate) {
        requestData.append('Id', String(activeConfigViewId));
    }

    saveConfigButton.disabled = true;
    setConfigDialogStatus(isCreate ? 'Creation du graphique...' : 'Enregistrement du graphique...', false);

    try {
        const response = await fetch('?IV=0', {
            method: 'POST',
            body: requestData,
            headers: {
                Accept: 'application/json'
            }
        });

        const payload = await response.json();

        if (!response.ok || !payload || payload.ok !== true) {
            throw new Error(payload && payload.message ? payload.message : 'Enregistrement impossible.');
        }

        configViewList = normalizeConfigViewList(payload.list || configViewList);
        renderConfigList();

        if (isCreate) {
            activeConfigViewId = Number(payload.id || 0);
            configDialogTitle.textContent = 'Configuration du graphique';
            await loadConfigEditor(activeConfigViewId);
        }

        setConfigDialogStatus(payload.message || 'Graphique enregistre avec succes.', false);
    } catch (error) {
        setConfigDialogStatus(error instanceof Error ? error.message : 'Erreur lors de l\'enregistrement du graphique.', true);
    } finally {
        saveConfigButton.disabled = false;
    }
});

closeConfirmDialogButton.addEventListener('click', closeConfirmDialog);
cancelConfirmButton.addEventListener('click', closeConfirmDialog);
confirmActionButton.addEventListener('click', () => {
    if (typeof confirmCallback === 'function') {
        const callback = confirmCallback;
        confirmCallback = null;
        callback();
        return;
    }

    closeConfirmDialog();
});

configModal.addEventListener('click', (event) => {
    if (event.target === configModal) {
        closeModal(configModal);
    }
});

confirmModal.addEventListener('click', (event) => {
    if (event.target === confirmModal) {
        closeConfirmDialog();
    }
});

window.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
        if (confirmModal.classList.contains('show')) {
            closeConfirmDialog();
            return;
        }

        if (configModal.classList.contains('show')) {
            closeModal(configModal);
        }
    }
});

configViewList = normalizeConfigViewList(initialConfigVueList);
renderConfigList();
</script>
<?php return; endif; ?>

<main class="vg-shell">
    <section class="vg-card">
        <div class="vg-toolbar">
            <div class="vg-toolbar-main">
                <h1 class="vg-title"><?php echo htmlspecialchars($NomVue, ENT_QUOTES, 'UTF-8'); ?></h1>
            </div>
            <div class="vg-actions">
                <div class="vg-date-nav">
                    <button type="button" class="vg-date-nav-step" id="vgPrevDayButton" title="Jour precedent">&#8249;</button>
                    <button type="button" class="vg-date-button" id="vgOpenDateDialogButton">
                        Date : <strong id="vgDescriptionDateValue">-</strong>
                    </button>
                    <input type="date" id="vgDateInput" class="vg-date-input-hidden" aria-hidden="true">
                    <button type="button" class="vg-date-nav-step" id="vgNextDayButton" title="Jour suivant">&#8250;</button>
                </div>
                <button type="button" class="vg-button" id="vgReloadButton">Recharger</button>
                <button type="button" class="vg-button" id="vgResetZoomButton">Reinitialiser le zoom</button>
            </div>
        </div>

        <div id="vgStatusBox" class="vg-status"><span id="vgStatusText">Analyse de la configuration...</span><div id="vgBgProgress" class="vg-bg-progress vg-hidden"></div></div>
    </section>

    <section class="vg-card vg-hidden" id="vgAnalogPanel">
        <h2 class="vg-panel-title">Courbes analogiques</h2>
        <div class="vg-chart-wrap">
            <canvas id="vgAnalogChart"></canvas>
        </div>
    </section>

    <section class="vg-card vg-hidden" id="vgPressurePanel">
        <h2 class="vg-panel-title">Pression</h2>
        <div class="vg-chart-wrap">
            <canvas id="vgPressureChart"></canvas>
        </div>
    </section>

    <section class="vg-card vg-hidden" id="vgOnOffPanel">
        <h2 class="vg-panel-title">Courbes ON/OFF</h2>
        <div class="vg-chart-wrap compact" style="--vg-onoff-chart-height: 112px;">
            <canvas id="vgOnOffChart"></canvas>
        </div>
    </section>
</main>

<div class="vg-loading-overlay" id="vgLoadingOverlay">
    <div class="vg-loading-overlay-ring"></div>
</div>



<script src="libs/chartjs/chart.umd.min.js"></script>
<script src="libs/hammerjs/hammer.min.js"></script>
<script src="libs/chartjs-plugin-zoom/chartjs-plugin-zoom.min.js"></script>
<script>
const rawGraphLines = <?php echo json_encode($ConfigLigne, $jsonOptions); ?>;
const graphContext = {
    viewId: <?php echo json_encode($Indexvue, $jsonOptions); ?>,
    title: <?php echo json_encode($NomVue, $jsonOptions); ?>,
    defaultDevice: <?php echo json_encode($requestedDevice, $jsonOptions); ?>,
    date: <?php echo json_encode($requestedDate, $jsonOptions); ?>,
    apiBaseUrl: 'ApiLogs.php'
};

const colorPalette = [
    '#7dd3a7',
    '#49a3ff',
    '#ff8e3c',
    '#ff6b6b',
    '#ffd166',
    '#8be9fd',
    '#c084fc',
    '#38bdf8',
    '#fb7185',
    '#4ade80'
];

const analogPanel = document.getElementById('vgAnalogPanel');
const pressurePanel = document.getElementById('vgPressurePanel');
const onOffPanel = document.getElementById('vgOnOffPanel');
const onOffChartWrap = onOffPanel ? onOffPanel.querySelector('.vg-chart-wrap.compact') : null;
const statusBox = document.getElementById('vgStatusBox');
const dateInput = document.getElementById('vgDateInput');
const openDateDialogButton = document.getElementById('vgOpenDateDialogButton');
const prevDayButton = document.getElementById('vgPrevDayButton');
const nextDayButton = document.getElementById('vgNextDayButton');
const reloadButton = document.getElementById('vgReloadButton');
const resetZoomButton = document.getElementById('vgResetZoomButton');
const descriptionDateValue = document.getElementById('vgDescriptionDateValue');

const sharedYAxisWidth = 124;
const sharedY2AxisWidth = 50;
const onOffRowStep = 3;
const onOffTickFontSize = 5;
const onOffChartBaseHeight = 56;
const onOffChartRowVisualHeight = 82;
const onOffChartSingleRowHeight = 112;

let activeCrosshairIndex = null;
let isSyncingZoom = false;
let rightMousePanState = null;
let pendingRightMousePanClientX = null;
let rightMousePanFrameId = null;
let analogSeriesConfig = [];
let pressureSeriesConfig = [];
let onOffSeriesConfig = [];
let syncedCharts = [];
let analogChart = null;
let pressureChart = null;
let onOffChart = null;

function coalesceGraphValue(value, fallbackValue) {
    return value === null || value === undefined ? fallbackValue : value;
}

function copyGraphObject(source) {
    const target = {};

    Object.keys(source || {}).forEach((key) => {
        target[key] = source[key];
    });

    return target;
}

function toFieldMap(row) {
    const map = {};

    Object.keys(row || {}).forEach((key) => {
        map[String(key).toLowerCase()] = row[key];
    });

    return map;
}

function getConfiguredValue(fieldMap, aliases) {
    for (const alias of aliases) {
        const value = fieldMap[String(alias).toLowerCase()];

        if (value !== undefined && value !== null && String(value).trim() !== '') {
            return value;
        }
    }

    return null;
}

function toNumberOrNull(value) {
    if (value === null || value === undefined || String(value).trim() === '') {
        return null;
    }

    const normalizedValue = String(value).replace(',', '.');
    const parsedValue = Number(normalizedValue);

    return Number.isFinite(parsedValue) ? parsedValue : null;
}

function toNumericValueOrNull(value) {
    if (value === null || value === undefined || String(value).trim() === '') {
        return null;
    }

    const normalizedValue = Number(String(value).replace(',', '.'));
    return Number.isFinite(normalizedValue) ? normalizedValue : null;
}

function toBooleanFlag(value, fallbackValue) {
    if (value === null || value === undefined || String(value).trim() === '') {
        return fallbackValue;
    }

    const normalizedValue = String(value).trim().toLowerCase();

    if (['0', 'false', 'non', 'no', 'off', 'hide', 'hidden'].includes(normalizedValue)) {
        return false;
    }

    if (['1', 'true', 'oui', 'yes', 'on', 'show', 'visible'].includes(normalizedValue)) {
        return true;
    }

    return fallbackValue;
}

function alphaColor(hexColor, alpha) {
    const safeHex = String(hexColor || '').replace('#', '').trim();

    if (safeHex.length !== 6) {
        return 'rgba(73, 163, 255, ' + alpha + ')';
    }

    const red = Number.parseInt(safeHex.slice(0, 2), 16);
    const green = Number.parseInt(safeHex.slice(2, 4), 16);
    const blue = Number.parseInt(safeHex.slice(4, 6), 16);

    return 'rgba(' + red + ', ' + green + ', ' + blue + ', ' + alpha + ')';
}

function normalizeSeriesColor(rawColor, fallbackColor) {
    const color = String(rawColor || '').trim();

    if (color === '') {
        return fallbackColor;
    }

    if (/^0x[0-9a-f]{6}$/i.test(color)) {
        return '#' + color.slice(2);
    }

    if (/^#?[0-9a-f]{6}$/i.test(color) || /^#?[0-9a-f]{3}$/i.test(color)) {
        return color.startsWith('#') ? color : ('#' + color);
    }

    return color;
}

function parseSingleFilterDefinition(rawToken) {
    const token = String(rawToken || '').trim();

    if (token === '') {
        return null;
    }

    const compactToken = token.replace(/\s+/g, '');
    const comparisonMatch = compactToken.match(/^(<=|>=|<|>|==|=|!=)(-?\d+(?:[.,]\d+)?)$/);

    if (comparisonMatch) {
        const operatorMap = {
            '>': 'gt',
            '>=': 'gte',
            '<': 'lt',
            '<=': 'lte',
            '=': 'eq',
            '==': 'eq',
            '!=': 'neq'
        };

        return {
            op: operatorMap[comparisonMatch[1]],
            value: Number(comparisonMatch[2].replace(',', '.')),
            raw: token
        };
    }

    const unaryMatch = compactToken.match(/^(abs|floor|ceil)$/i);

    if (unaryMatch) {
        return {
            op: unaryMatch[1].toLowerCase(),
            raw: token
        };
    }

    const singleValueMatch = compactToken.match(/^(min|max|gte|lte|gt|lt|eq|neq|offset|add|scale|multiply|factor|divide|round)[:=](-?\d+(?:[.,]\d+)?)$/i);

    if (singleValueMatch) {
        return {
            op: singleValueMatch[1].toLowerCase(),
            value: Number(singleValueMatch[2].replace(',', '.')),
            raw: token
        };
    }

    const rangeMatch = compactToken.match(/^(between|range|clamp|clip)[:=](-?\d+(?:[.,]\d+)?)[,:;](-?\d+(?:[.,]\d+)?)$/i);

    if (rangeMatch) {
        return {
            op: rangeMatch[1].toLowerCase(),
            min: Number(rangeMatch[2].replace(',', '.')),
            max: Number(rangeMatch[3].replace(',', '.')),
            raw: token
        };
    }

    const numericScaleMatch = compactToken.match(/^-?\d+(?:[.,]\d+)?$/);

    if (numericScaleMatch) {
        // Valeur purement numerique : utilisee comme multiplicateur (champ Filtres),
        // ne pas aussi la traiter comme un filtre scale pour eviter la double application.
        return null;
    }

    return {
        op: 'unsupported',
        raw: token
    };
}

function parseSeriesFilters(rawFilters) {
    const filterText = String(rawFilters || '').trim();

    if (filterText === '') {
        return {
            filters: [],
            invalidTokens: []
        };
    }

    let candidateTokens = [];

    if ((filterText.startsWith('[') && filterText.endsWith(']')) || (filterText.startsWith('{') && filterText.endsWith('}'))) {
        try {
            const parsedConfig = JSON.parse(filterText);
            const parsedList = Array.isArray(parsedConfig) ? parsedConfig : [parsedConfig];

            parsedList.forEach((item) => {
                if (typeof item === 'string') {
                    candidateTokens.push(item);
                    return;
                }

                if (!item || typeof item !== 'object') {
                    return;
                }

                const op = String(item.op || item.type || item.name || '').trim().toLowerCase();

                if (op === '') {
                    return;
                }

                if (['between', 'range', 'clamp', 'clip'].includes(op)) {
                    candidateTokens.push(op + ':' + item.min + ':' + item.max);
                    return;
                }

                if (item.value !== undefined) {
                    candidateTokens.push(op + ':' + item.value);
                    return;
                }

                candidateTokens.push(op);
            });
        } catch (error) {
            candidateTokens = filterText.split(/[\n;|]+/);
        }
    } else {
        candidateTokens = filterText.split(/[\n;|]+/);
    }

    const filters = [];
    const invalidTokens = [];

    candidateTokens.forEach((token) => {
        const filterDefinition = parseSingleFilterDefinition(token);

        if (!filterDefinition) {
            return;
        }

        if (filterDefinition.op === 'unsupported') {
            invalidTokens.push(filterDefinition.raw);
            return;
        }

        filters.push(filterDefinition);
    });

    return { filters, invalidTokens };
}

function applyAnalogFilterValue(value, filterDefinition) {
    if (value === null || value === undefined) {
        return null;
    }

    switch (filterDefinition.op) {
        case 'min':
        case 'gte':
            return value >= filterDefinition.value ? value : null;
        case 'max':
        case 'lte':
            return value <= filterDefinition.value ? value : null;
        case 'gt':
            return value > filterDefinition.value ? value : null;
        case 'lt':
            return value < filterDefinition.value ? value : null;
        case 'eq':
            return value === filterDefinition.value ? value : null;
        case 'neq':
            return value !== filterDefinition.value ? value : null;
        case 'between':
        case 'range': {
            const minValue = Math.min(filterDefinition.min, filterDefinition.max);
            const maxValue = Math.max(filterDefinition.min, filterDefinition.max);
            return value >= minValue && value <= maxValue ? value : null;
        }
        case 'clamp':
        case 'clip': {
            const minValue = Math.min(filterDefinition.min, filterDefinition.max);
            const maxValue = Math.max(filterDefinition.min, filterDefinition.max);
            return Math.min(maxValue, Math.max(minValue, value));
        }
        case 'offset':
        case 'add':
            return value + filterDefinition.value;
        case 'scale':
        case 'multiply':
        case 'factor':
            return value * filterDefinition.value;
        case 'divide':
            return filterDefinition.value === 0 ? null : (value / filterDefinition.value);
        case 'round': {
            const decimals = Math.max(0, Math.round(filterDefinition.value));
            const precisionFactor = 10 ** decimals;
            return Math.round(value * precisionFactor) / precisionFactor;
        }
        case 'abs':
            return Math.abs(value);
        case 'floor':
            return Math.floor(value);
        case 'ceil':
            return Math.ceil(value);
        default:
            return value;
    }
}

function applyAnalogFilters(points, series) {
    const filters = Array.isArray(series.filters) ? series.filters : [];

    if (!filters.length) {
        return {
            points,
            filteredCount: 0
        };
    }

    let filteredCount = 0;
    const filteredPoints = points.map((value) => {
        let nextValue = value;

        for (const filterDefinition of filters) {
            nextValue = applyAnalogFilterValue(nextValue, filterDefinition);

            if (nextValue === null || nextValue === undefined || !Number.isFinite(nextValue)) {
                filteredCount += 1;
                return null;
            }
        }

        return nextValue;
    });

    return {
        points: filteredPoints,
        filteredCount
    };
}

function hasTransformFilter(filters) {
    return Array.isArray(filters) && filters.some((filterDefinition) => [
        'offset',
        'add',
        'scale',
        'multiply',
        'factor',
        'divide',
        'round',
        'abs',
        'floor',
        'ceil',
        'clamp',
        'clip'
    ].includes(filterDefinition.op));
}

function findLastDefinedValue(values) {
    for (let index = values.length - 1; index >= 0; index -= 1) {
        const value = values[index];

        if (value !== null && value !== undefined && !Number.isNaN(value)) {
            return value;
        }
    }

    return null;
}

function detectSeriesType(fieldMap, point) {
    const rawType = String(getConfiguredValue(fieldMap, [
        'TypeGraphique',
        'GraphType',
        'TypeCourbe',
        'Nature',
        'Type'
    ]) || '').trim().toLowerCase();
    const numericType = toNumberOrNull(rawType);

    if (numericType === 1) {
        return 'onoff';
    }

    if (rawType !== '') {
        const onOffMarkers = ['onoff', 'bool', 'boolean', 'digital', 'etat', 'state', 'binaire', 'switch', 'alarm', 'alarme'];

        if (onOffMarkers.some((marker) => rawType.includes(marker))) {
            return 'onoff';
        }
    }

    if (point === 137 || point === 140) {
        return 'onoff';
    }

    return 'analog';
}

function normalizeGraphLines() {
    const normalized = [];

    rawGraphLines.forEach((row, index) => {
        const fieldMap = toFieldMap(row);
        const point = toNumberOrNull(getConfiguredValue(fieldMap, [
            'Point',
            'IdPoint',
            'PointId',
            'NumPoint',
            'Id_Pt',
            'ApiPoint'
        ]));

        if (point === null) {
            return;
        }

        const rawDisplayOrder = toNumberOrNull(getConfiguredValue(fieldMap, ['Ordre', 'Order', 'Rang', 'Position', 'Id']));
        const displayOrder = rawDisplayOrder === null ? (index + 1) : rawDisplayOrder;
        const visible = toBooleanFlag(getConfiguredValue(fieldMap, ['Actif', 'Active', 'Visible', 'Afficher']), true);

        if (!visible) {
            return;
        }

        const rawDevice = toNumberOrNull(getConfiguredValue(fieldMap, ['Device', 'DeviceId', 'IdDevice', 'ApiDevice']));
        const device = rawDevice === null ? graphContext.defaultDevice : rawDevice;
        const divisor = toNumberOrNull(getConfiguredValue(fieldMap, ['Diviseur', 'Divider']));
        const factor = toNumberOrNull(getConfiguredValue(fieldMap, ['Facteur', 'Coefficient', 'Coef', 'Scale', 'Multiplier']));
        const precision = toNumberOrNull(getConfiguredValue(fieldMap, ['Precision', 'Decimales', 'Decimals']));
        const unit = String(getConfiguredValue(fieldMap, ['UniteMesure', 'Unite', 'Unit', 'Suffixe', 'Suffix']) || '').trim();
        const label = String(getConfiguredValue(fieldMap, ['Nom', 'Label', 'Libelle', 'Description', 'Titre']) || ('Point ' + point)).trim();
        const fallbackColor = colorPalette[index % colorPalette.length];
        const color = normalizeSeriesColor(getConfiguredValue(fieldMap, ['Color', 'Couleur', 'BorderColor']), fallbackColor);
        const type = detectSeriesType(fieldMap, point);
        const filtresRaw = getConfiguredValue(fieldMap, ['Filtres', 'Filtre', 'Filters', 'Filter']);
        const filtresMultiplier = toNumberOrNull(filtresRaw);
        const parsedFilters = parseSeriesFilters(filtresRaw);
        const signe = String(getConfiguredValue(fieldMap, ['Signe']) || '').trim();

        let multiplier = factor;

        if (divisor !== null && divisor !== 0) {
            multiplier = 1 / divisor;
        }

        if (multiplier === null) {
            if (type === 'onoff') {
                multiplier = 1;
            } else if (filtresMultiplier !== null && filtresMultiplier !== 0) {
                multiplier = filtresMultiplier;
            } else {
                multiplier = 1;
            }
        }

        normalized.push({
            id: index + 1,
            displayOrder,
            point,
            device,
            label,
            color,
            backgroundColor: alphaColor(color, type === 'onoff' ? 0.18 : 0.12),
            type,
            multiplier,
            precision: Number.isFinite(precision) ? Math.max(0, Math.round(precision)) : (type === 'onoff' ? 0 : 1),
            unit,
            filters: parsedFilters.filters,
            filterWarnings: parsedFilters.invalidTokens,
            signe
        });
    });

    normalized.sort((left, right) => left.displayOrder - right.displayOrder);
    return normalized;
}

function setStatus(message, isError) {
    const textEl = document.getElementById('vgStatusText');
    if (textEl) { textEl.textContent = message; } else { statusBox.textContent = message; }
    statusBox.classList.toggle('error', Boolean(isError));
}

function showLoadingOverlay() {
    const overlay = document.getElementById('vgLoadingOverlay');
    if (overlay) { overlay.classList.add('active'); }
}

function hideLoadingOverlay() {
    const overlay = document.getElementById('vgLoadingOverlay');
    if (overlay) { overlay.classList.remove('active'); }
}

function showBgProgress() {
    const el = document.getElementById('vgBgProgress');
    if (el) { el.classList.remove('vg-hidden'); }
}

function hideBgProgress() {
    const el = document.getElementById('vgBgProgress');
    if (el) { el.classList.add('vg-hidden'); }
}

function formatDateForInput(dateString) {
    const dateParts = String(dateString).split('-');

    if (dateParts.length !== 3) {
        return '';
    }

    return dateParts[2] + '-' + dateParts[1] + '-' + dateParts[0];
}

function formatDateForApi(dateString) {
    const dateParts = String(dateString).split('-');

    if (dateParts.length !== 3) {
        throw new Error('Format de date invalide.');
    }

    return dateParts[2] + '-' + dateParts[1] + '-' + dateParts[0];
}

function refreshDateDisplay() {
    descriptionDateValue.textContent = graphContext.date;
    dateInput.value = formatDateForInput(graphContext.date);
    document.title = graphContext.title + ' - ' + graphContext.date;
}

function getTodayInputValue() {
    const now = new Date();
    const yyyy = now.getFullYear();
    const mm = String(now.getMonth() + 1).padStart(2, '0');
    const dd = String(now.getDate()).padStart(2, '0');
    return yyyy + '-' + mm + '-' + dd;
}

function shiftDate(daysDelta) {
    const parts = graphContext.date.split('-');
    const d = new Date(Number(parts[2]), Number(parts[1]) - 1, Number(parts[0]));
    d.setDate(d.getDate() + daysDelta);
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    if (d > today) { return; }
    if (d < new Date(2026, 3, 4)) { return; }
    const yyyy = d.getFullYear();
    const mm = String(d.getMonth() + 1).padStart(2, '0');
    const dd = String(d.getDate()).padStart(2, '0');
    graphContext.date = dd + '-' + mm + '-' + yyyy;
    refreshDateDisplay();
    loadCurve(true);
}

function buildApiUrl(device, point) {
    return graphContext.apiBaseUrl + '?device=' + encodeURIComponent(device) + '&point=' + encodeURIComponent(point) + '&date=' + encodeURIComponent(graphContext.date);
}

function buildBatchApiUrl(device, points, step, top, endpoints, heure) {
    var url = graphContext.apiBaseUrl + '?batch=1&device=' + encodeURIComponent(device) + '&points=' + encodeURIComponent(points.join(',')) + '&date=' + encodeURIComponent(graphContext.date);
    if (step && step > 1)   { url += '&step='      + encodeURIComponent(step); }
    if (top  && top  > 0)   { url += '&top='       + encodeURIComponent(top);  }
    if (endpoints)          { url += '&endpoints=1'; }
    if (heure)              { url += '&heure='     + encodeURIComponent(heure); }
    return url;
}

function fetchJsonArray(url) {
    return fetch(url, {
        method: 'GET',
        headers: {
            Accept: 'application/json, text/plain, */*'
        }
    }).then((response) => {
        if (!response.ok) {
            throw new Error('HTTP ' + response.status + ' sur ' + url);
        }

        return response.text();
    }).then((rawText) => {
        let payload;

        try {
            payload = JSON.parse(rawText);
        } catch (error) {
            throw new Error('Reponse API invalide: ' + rawText.slice(0, 120));
        }

        if (!Array.isArray(payload)) {
            throw new Error('Le format attendu est un tableau JSON.');
        }

        return payload;
    });
}

function fetchJsonObject(url) {
    return fetch(url, {
        method: 'GET',
        headers: { Accept: 'application/json, text/plain, */*' }
    }).then((response) => {
        if (!response.ok) {
            throw new Error('HTTP ' + response.status + ' sur ' + url);
        }
        return response.text();
    }).then((rawText) => {
        let payload;
        try {
            payload = JSON.parse(rawText);
        } catch (error) {
            throw new Error('Reponse batch invalide: ' + rawText.slice(0, 120));
        }
        if (typeof payload !== 'object' || payload === null || Array.isArray(payload)) {
            throw new Error('Format batch attendu: objet JSON keye par point.');
        }
        return payload;
    });
}

function formatAnalogValue(value, series) {
    if (value === null || value === undefined || Number.isNaN(Number(value))) {
        return '-';
    }

    return Number(value).toLocaleString('fr-FR', {
        minimumFractionDigits: series.precision,
        maximumFractionDigits: series.precision
    }) + (series.unit ? ' ' + series.unit : '');
}

function formatIndexLabel(value) {
    const numericValue = Number(String(value).replace(',', '.'));

    if (!Number.isFinite(numericValue)) {
        return String(coalesceGraphValue(value, ''));
    }

    return numericValue.toLocaleString('fr-FR');
}

function normalizeUnitLabel(unit) {
    return String(unit || '')
        .trim()
        .toLowerCase()
        .replace(/\s+/g, '')
        .replace(/deg/g, '°');
}

function inferAnalogAxisTitle() {
    if (!analogSeriesConfig.length) {
        return 'Valeur';
    }

    const normalizedUnits = analogSeriesConfig
        .map((series) => ({
            raw: String(series.unit || '').trim(),
            normalized: normalizeUnitLabel(series.unit)
        }))
        .filter((unitInfo) => unitInfo.raw !== '');

    if (normalizedUnits.length > 0) {
        const hasTemperatureUnit = normalizedUnits.some((unitInfo) => ['°c', 'c'].includes(unitInfo.normalized));

        if (hasTemperatureUnit) {
            return '°C';
        }

        const uniqueUnits = Array.from(new Set(normalizedUnits.map((unitInfo) => unitInfo.raw)));

        if (uniqueUnits.length === 1) {
            return uniqueUnits[0];
        }
    }

    const hasTemperatureLabel = analogSeriesConfig.some((series) => {
        const normalizedLabel = String(series.label || '').trim().toLowerCase();
        return normalizedLabel.includes('temp');
    });

    if (hasTemperatureLabel) {
        return '°C';
    }

    return 'Valeur';
}

function formatOnOffValue(value) {
    if (value === null || value === undefined || Number.isNaN(Number(value))) {
        return '-';
    }

    return Number(value) >= 0.5 ? 'ON' : 'OFF';
}

function parseOnOffValue(value) {
    if (typeof value === 'string') {
        const normalizedValue = value.trim().toLowerCase();

        if (normalizedValue === 'on' || normalizedValue === 'true') {
            return 1;
        }

        if (normalizedValue === 'off' || normalizedValue === 'false') {
            return 0;
        }
    }

    const numericValue = Number(value);

    if (Number.isNaN(numericValue)) {
        return Number.NaN;
    }

    return numericValue >= 1 ? 1 : 0;
}

function normalizeAnalogSeries(indexes, values, series) {
    const labels = indexes.map((value) => String(value));
    const warnings = [];
    const points = indexes.map((_, index) => {
        const rawValue = index < values.length ? values[index] : null;
        const numericValue = toNumericValueOrNull(rawValue);

        if (numericValue === null) {
            if (rawValue !== null && rawValue !== undefined && String(rawValue).trim() !== '') {
                warnings.push('valeur invalide ignoree a l\'index ' + labels[index]);
            } else if (index >= values.length) {
                warnings.push('valeur manquante ignoree a l\'index ' + labels[index]);
            }

            return null;
        }

        return numericValue * series.multiplier;
    });

    if (indexes.length === 0) {
        warnings.push('aucun index exploitable renvoye par l\'API');
    }

    const filteredSeries = applyAnalogFilters(points, series);

    return {
        labels,
        points: filteredSeries.points,
        filteredCount: filteredSeries.filteredCount,
        warnings
    };
}

function normalizeOnOffSeries(indexes, values, series) {
    const labels = indexes.map((value) => String(value));
    const warnings = [];
    const points = indexes.map((_, index) => {
        const rawValue = index < values.length ? values[index] : null;

        if (rawValue === null || rawValue === undefined || String(rawValue).trim() === '') {
            if (index >= values.length) {
                warnings.push('valeur manquante ignoree a l\'index ' + labels[index]);
            }
            return null;
        }

        const parsedValue = parseOnOffValue(rawValue);
        if (Number.isNaN(parsedValue)) {
            warnings.push('valeur ON/OFF invalide ignoree a l\'index ' + labels[index]);
            return null;
        }

        return parsedValue;
    });

    if (indexes.length === 0) {
        warnings.push('aucun index exploitable renvoye par l\'API');
    }

    return { labels, points, warnings };
}

function setSharedYAxisWidth(scale) {
    scale.width = sharedYAxisWidth;
}

function getOnOffDisplayValue(seriesIndex, value) {
    return (seriesIndex * onOffRowStep) + Number(value);
}

function getOnOffAxisMax() {
    return ((onOffSeriesConfig.length - 1) * onOffRowStep) + 1;
}

function updateOnOffChartHeight() {
    if (!onOffChartWrap) {
        return;
    }

    const seriesCount = Math.max(onOffSeriesConfig.length, 1);
    const chartHeight = seriesCount === 1
        ? onOffChartSingleRowHeight
        : (onOffChartBaseHeight + (seriesCount * onOffChartRowVisualHeight));

    onOffChartWrap.style.setProperty('--vg-onoff-chart-height', chartHeight + 'px');
}

function formatOnOffTick(value) {
    const normalizedValue = Number(value);

    if (!Number.isInteger(normalizedValue)) {
        return '';
    }

    switch (normalizedValue % onOffRowStep) {
        case 0:
            return 'OFF';
        case 1:
            return 'ON';
        default:
            return '';
    }
}

const verticalHoverLinePlugin = {
    id: 'vgVerticalHoverLine',
    afterDatasetsDraw(chartInstance) {
        if (activeCrosshairIndex === null) {
            return;
        }

        const datasetMeta = chartInstance.getDatasetMeta(0);
        const pointElement = datasetMeta && datasetMeta.data ? datasetMeta.data[activeCrosshairIndex] : null;

        if (!pointElement) {
            return;
        }

        const chartArea = chartInstance.chartArea;
        const ctx = chartInstance.ctx;

        ctx.save();
        ctx.beginPath();
        ctx.lineWidth = 1;
        ctx.strokeStyle = 'rgba(255, 255, 255, 0.9)';
        ctx.moveTo(pointElement.x, chartArea.top);
        ctx.lineTo(pointElement.x, chartArea.bottom);
        ctx.stroke();
        ctx.restore();
    }
};

const onOffRowLabelPlugin = {
    id: 'vgOnOffRowLabel',
    afterDraw(chartInstance, args, options) {
        if (chartInstance.canvas.id !== 'vgOnOffChart') {
            return;
        }

        const yScale = chartInstance.scales.y;

        if (!yScale) {
            return;
        }

        const ctx = chartInstance.ctx;

        ctx.save();
        ctx.fillStyle = options.color || '#94a8c1';
        ctx.font = '600 ' + (options.fontSize || 12) + 'px "Segoe UI", Tahoma, Geneva, Verdana, sans-serif';
        ctx.textAlign = 'left';
        ctx.textBaseline = 'middle';

        onOffSeriesConfig.forEach((series, seriesIndex) => {
            const baseValue = seriesIndex * onOffRowStep;
            const centerY = (yScale.getPixelForValue(baseValue) + yScale.getPixelForValue(baseValue + 1)) / 2;

            ctx.fillText(series.label, yScale.left + (options.offset || 8), centerY);
        });

        ctx.restore();
    }
};

Chart.register(verticalHoverLinePlugin, onOffRowLabelPlugin);

function syncZoomWindow(sourceChart) {
    if (isSyncingZoom || !sourceChart.scales || !sourceChart.scales.x) {
        return;
    }

    isSyncingZoom = true;

    try {
        syncedCharts.forEach((targetChart) => {
            if (targetChart === sourceChart || !targetChart.options.scales || !targetChart.options.scales.x) {
                return;
            }

            targetChart.options.scales.x.min = sourceChart.scales.x.min;
            targetChart.options.scales.x.max = sourceChart.scales.x.max;
            targetChart.update('none');
        });
    } finally {
        isSyncingZoom = false;
    }
}

function buildZoomOptions() {
    return {
        pan: {
            enabled: true,
            mode: 'x',
            modifierKey: 'shift',
            onPanComplete(eventContext) {
                syncZoomWindow(eventContext.chart);
            }
        },
        zoom: {
            wheel: {
                enabled: false
            },
            pinch: {
                enabled: true
            },
            drag: {
                enabled: true,
                backgroundColor: 'rgba(255, 255, 255, 0.08)',
                borderColor: 'rgba(255, 255, 255, 0.4)',
                borderWidth: 1
            },
            mode: 'x',
            onZoomComplete(eventContext) {
                syncZoomWindow(eventContext.chart);
            }
        }
    };
}

function redrawSyncedCharts() {
    syncedCharts.forEach((chartInstance) => {
        chartInstance.draw();
    });
}

function refreshAllCharts(updateMode) {
    syncedCharts.forEach((chartInstance) => {
        chartInstance.update(updateMode || 'none');
    });
}

function setCrosshairIndex(nextIndex) {
    if (activeCrosshairIndex === nextIndex) {
        return;
    }

    activeCrosshairIndex = nextIndex;
    redrawSyncedCharts();
}

function stopRightMousePan() {
    if (!rightMousePanState) {
        return;
    }

    if (rightMousePanFrameId !== null) {
        cancelAnimationFrame(rightMousePanFrameId);
        rightMousePanFrameId = null;
    }

    pendingRightMousePanClientX = null;
    rightMousePanState.chart.canvas.classList.remove('is-right-panning');
    rightMousePanState = null;
}

function applyRightMousePan(clientX) {
    if (!rightMousePanState || clientX === null) {
        return;
    }

    const sourceChart = rightMousePanState.chart;
    const deltaX = clientX - rightMousePanState.lastClientX;

    if (!deltaX || typeof sourceChart.pan !== 'function') {
        rightMousePanState.lastClientX = clientX;
        return;
    }

    if (!sourceChart.scales || !sourceChart.scales.x) {
        stopRightMousePan();
        return;
    }

    sourceChart.pan({ x: deltaX }, undefined, 'none');
    rightMousePanState.lastClientX = clientX;
    syncZoomWindow(sourceChart);
}

function flushRightMousePan() {
    rightMousePanFrameId = null;
    applyRightMousePan(pendingRightMousePanClientX);
}

function handleRightMousePanMove(event) {
    if (!rightMousePanState) {
        return;
    }

    if ((event.buttons & 2) !== 2) {
        stopRightMousePan();
        return;
    }

    pendingRightMousePanClientX = event.clientX;

    if (rightMousePanFrameId === null) {
        rightMousePanFrameId = requestAnimationFrame(flushRightMousePan);
    }
}

function bindRightMousePan(chartInstance) {
    chartInstance.canvas.addEventListener('contextmenu', (event) => {
        event.preventDefault();
    });

    chartInstance.canvas.addEventListener('mousedown', (event) => {
        if (event.button !== 2) {
            return;
        }

        if (!chartInstance.scales.x) {
            return;
        }

        event.preventDefault();
        rightMousePanState = {
            chart: chartInstance,
            lastClientX: event.clientX
        };
        pendingRightMousePanClientX = event.clientX;
        setCrosshairIndex(null);
        chartInstance.canvas.classList.add('is-right-panning');
    });
}

function bindCrosshair(chartInstance) {
    chartInstance.canvas.addEventListener('mousemove', (event) => {
        if (rightMousePanState) {
            return;
        }

        const points = chartInstance.getElementsAtEventForMode(event, 'index', { intersect: false }, false);
        setCrosshairIndex(points.length ? points[0].index : null);
    });

    chartInstance.canvas.addEventListener('mouseleave', () => {
        if (!rightMousePanState) {
            setCrosshairIndex(null);
        }
    });
}

function bindDoubleClickReset(chartInstance) {
    chartInstance.canvas.addEventListener('dblclick', (event) => {
        event.preventDefault();
        resetAllZoom();
    });
}

function destroyCharts() {
    syncedCharts.forEach((chartInstance) => {
        chartInstance.destroy();
    });

    syncedCharts = [];
    analogChart = null;
    pressureChart = null;
    onOffChart = null;
    activeCrosshairIndex = null;
}

function createCharts() {
    destroyCharts();

    const hasRightAxis = analogSeriesConfig.some((s) => s.signe === '%' || s.signe === 'H');

    if (analogSeriesConfig.length > 0) {
        analogPanel.classList.remove('vg-hidden');

        analogChart = new Chart(document.getElementById('vgAnalogChart'), {
            type: 'line',
            data: {
                labels: [],
                datasets: analogSeriesConfig.map((series) => ({
                    label: series.label,
                    data: [],
                    borderColor: series.color,
                    backgroundColor: series.backgroundColor,
                    fill: false,
                    borderWidth: 2.2,
                    pointRadius: 0,
                    pointHoverRadius: 4,
                    tension: 0,
                    yAxisID: (series.signe === '%' || series.signe === 'H') ? 'y2' : 'y'
                }))
            },
            options: {
                animation: false,
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false
                },
                plugins: {
                    legend: {
                        display: true,
                        labels: {
                            color: '#e5eef9',
                            usePointStyle: true
                        }
                    },
                    tooltip: {
                        callbacks: {
                            title(items) {
                                return items.length ? 'Index: ' + formatIndexLabel(items[0].label) : '';
                            },
                            label(context) {
                                const series = analogSeriesConfig[context.datasetIndex];
                                return context.dataset.label + ': ' + formatAnalogValue(context.parsed.y, series);
                            }
                        }
                    },
                    zoom: buildZoomOptions()
                },
                scales: Object.assign(
                    {
                        x: {
                            title: {
                                display: true,
                                text: 'Index',
                                color: '#e5eef9'
                            },
                            ticks: {
                                color: '#e5eef9',
                                display: true,
                                maxTicksLimit: 18,
                                maxRotation: 0,
                                callback(value) {
                                    return formatIndexLabel(this.getLabelForValue(value));
                                }
                            },
                            grid: {
                                color: 'rgba(148, 168, 193, 0.1)'
                            }
                        },
                        y: {
                            afterFit(scale) {
                                setSharedYAxisWidth(scale);
                            },
                            title: {
                                display: true,
                                text: inferAnalogAxisTitle(),
                                color: '#e5eef9'
                            },
                            ticks: {
                                color: '#e5eef9'
                            },
                            grid: {
                                color: 'rgba(148, 168, 193, 0.15)'
                            }
                        }
                    },
                    hasRightAxis ? {
                        y2: {
                            afterFit(scale) {
                                scale.width = sharedY2AxisWidth;
                            },
                            position: 'right',
                            min: 0,
                            max: 100,
                            title: {
                                display: true,
                                text: '%',
                                color: '#e5eef9'
                            },
                            ticks: {
                                color: '#e5eef9'
                            },
                            grid: {
                                drawOnChartArea: false
                            }
                        }
                    } : {}
                )
            }
        });

        syncedCharts.push(analogChart);
        bindCrosshair(analogChart);
        bindRightMousePan(analogChart);
        bindDoubleClickReset(analogChart);
    } else {
        analogPanel.classList.add('vg-hidden');
    }

    if (pressureSeriesConfig.length > 0) {
        pressurePanel.classList.remove('vg-hidden');

        pressureChart = new Chart(document.getElementById('vgPressureChart'), {
            type: 'line',
            data: {
                labels: [],
                datasets: pressureSeriesConfig.map((series) => ({
                    label: series.label,
                    data: [],
                    borderColor: series.color,
                    backgroundColor: series.backgroundColor,
                    fill: false,
                    borderWidth: 2.2,
                    pointRadius: 0,
                    pointHoverRadius: 4,
                    tension: 0,
                    yAxisID: 'y'
                }))
            },
            options: {
                animation: false,
                responsive: true,
                maintainAspectRatio: false,
                layout: hasRightAxis ? { padding: { right: sharedY2AxisWidth } } : {},
                interaction: {
                    mode: 'index',
                    intersect: false
                },
                plugins: {
                    legend: {
                        display: true,
                        labels: {
                            color: '#e5eef9',
                            usePointStyle: true
                        }
                    },
                    tooltip: {
                        callbacks: {
                            title(items) {
                                return items.length ? 'Index: ' + formatIndexLabel(items[0].label) : '';
                            },
                            label(context) {
                                const series = pressureSeriesConfig[context.datasetIndex];
                                return context.dataset.label + ': ' + formatAnalogValue(context.parsed.y, series);
                            }
                        }
                    },
                    zoom: buildZoomOptions()
                },
                scales: {
                    x: {
                        title: {
                            display: true,
                            text: 'Index',
                            color: '#e5eef9'
                        },
                        ticks: {
                            color: '#e5eef9',
                            display: true,
                            maxTicksLimit: 18,
                            maxRotation: 0,
                            callback(value) {
                                return formatIndexLabel(this.getLabelForValue(value));
                            }
                        },
                        grid: {
                            color: 'rgba(148, 168, 193, 0.1)'
                        }
                    },
                    y: {
                        afterFit(scale) {
                            setSharedYAxisWidth(scale);
                        },
                        min: 600,
                        max: 1150,
                        title: {
                            display: true,
                            text: 'kPa',
                            color: '#e5eef9'
                        },
                        ticks: {
                            color: '#e5eef9'
                        },
                        grid: {
                            color: 'rgba(148, 168, 193, 0.15)'
                        }
                    }
                }
            }
        });

        syncedCharts.push(pressureChart);
        bindCrosshair(pressureChart);
        bindRightMousePan(pressureChart);
        bindDoubleClickReset(pressureChart);
    } else {
        if (pressurePanel) { pressurePanel.classList.add('vg-hidden'); }
    }

    if (onOffSeriesConfig.length > 0) {
        if (onOffPanel) { onOffPanel.classList.remove('vg-hidden'); }
        updateOnOffChartHeight();
        onOffChart = new Chart(document.getElementById('vgOnOffChart'), {
            type: 'line',
            data: {
                labels: [],
                datasets: onOffSeriesConfig.map((series) => ({
                    label: series.label,
                    data: [],
                    rawStates: [],
                    borderColor: series.color,
                    backgroundColor: series.backgroundColor,
                    fill: false,
                    stepped: true,
                    borderWidth: 2.2,
                    pointRadius: 0,
                    pointHoverRadius: 4,
                    tension: 0
                }))
            },
            options: {
                animation: false,
                responsive: true,
                maintainAspectRatio: false,
                layout: hasRightAxis ? { padding: { right: sharedY2AxisWidth } } : {},
                interaction: {
                    mode: 'index',
                    intersect: false
                },
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            title(items) {
                                return items.length ? 'Index: ' + formatIndexLabel(items[0].label) : '';
                            },
                            label(context) {
                                return context.dataset.label + ': ' + formatOnOffValue(context.dataset.rawStates[context.dataIndex]);
                            }
                        }
                    },
                    zoom: buildZoomOptions(),
                    vgOnOffRowLabel: {
                        color: '#94a8c1',
                        fontSize: 12,
                        offset: 8
                    }
                },
                scales: {
                    x: {
                        title: {
                            display: true,
                            text: 'Index',
                            color: '#e5eef9'
                        },
                        ticks: {
                            color: '#e5eef9',
                            maxTicksLimit: 18,
                            maxRotation: 0,
                            callback(value) {
                                return formatIndexLabel(this.getLabelForValue(value));
                            }
                        },
                        grid: {
                            color: 'rgba(148, 168, 193, 0.1)'
                        }
                    },
                    y: {
                        afterFit(scale) {
                            setSharedYAxisWidth(scale);
                        },
                        min: 0,
                        max: getOnOffAxisMax(),
                        title: {
                            display: true,
                            text: 'ON/OFF',
                            color: '#e5eef9'
                        },
                        ticks: {
                            color: '#e5eef9',
                            stepSize: 1,
                            font: {
                                size: onOffTickFontSize
                            },
                            callback(value) {
                                return formatOnOffTick(value);
                            }
                        },
                        grid: {
                            color(context) {
                                return context.tick.value % onOffRowStep === 2
                                    ? 'rgba(148, 168, 193, 0.05)'
                                    : 'rgba(148, 168, 193, 0.15)';
                            }
                        }
                    }
                }
            }
        });

        syncedCharts.push(onOffChart);
        bindCrosshair(onOffChart);
        bindRightMousePan(onOffChart);
        bindDoubleClickReset(onOffChart);
    } else {
        if (onOffChartWrap) {
            onOffChartWrap.style.removeProperty('--vg-onoff-chart-height');
        }
        if (onOffPanel) { onOffPanel.classList.add('vg-hidden'); }
    }
}

function resetAllZoom() {
    stopRightMousePan();
    activeCrosshairIndex = null;
    isSyncingZoom = true;

    try {
        syncedCharts.forEach((chartInstance) => {
            if (chartInstance.options.scales && chartInstance.options.scales.x) {
                const labelCount = Array.isArray(chartInstance.data.labels) ? chartInstance.data.labels.length : 0;

                if (labelCount > 0) {
                    chartInstance.options.scales.x.min = 0;
                    chartInstance.options.scales.x.max = labelCount - 1;
                } else {
                    delete chartInstance.options.scales.x.min;
                    delete chartInstance.options.scales.x.max;
                }
            }

            chartInstance.update('none');
        });
    } finally {
        isSyncingZoom = false;
    }

    refreshAllCharts('none');
    redrawSyncedCharts();
}

function buildRequestPlan(seriesList) {
    const devices = Array.from(new Set(seriesList.map((series) => series.device)));
    const indexDevice = devices.length && devices[0] !== undefined && devices[0] !== null ? devices[0] : graphContext.defaultDevice;

    return {
        indexDevice,
        deviceWarning: devices.length > 1 ? 'plusieurs devices configures, les index X utilisent le premier device' : '',
        seriesRequests: seriesList.map((series) => ({
            series,
            url: buildApiUrl(series.device, series.point)
        }))
    };
}

async function fetchBatchData(seriesList, step, top, endpoints, heure) {
    const devicePointsMap = {};
    seriesList.forEach((series) => {
        const key = String(series.device);
        if (!devicePointsMap[key]) { devicePointsMap[key] = new Set(); }
        devicePointsMap[key].add(-1);
        devicePointsMap[key].add(series.point);
    });
    const deviceKeys = Object.keys(devicePointsMap);
    const dataByDevice = {};
    for (const device of deviceKeys) {
        const points = Array.from(devicePointsMap[device]);
        const url = buildBatchApiUrl(device, points, step, top || 0, endpoints || false, heure || '');
        const data = await fetchJsonObject(url);
        dataByDevice[device] = data;
    }
    return dataByDevice;
}

// ── Helpers chargement progressif ────────────────────────────────────────────────────

function heureToMinutes(h) {
    const parts = String(h).split(':');
    return parseInt(parts[0], 10) * 60 + parseInt(parts[1] || '0', 10);
}

function minutesToHeure(m) {
    const hh = String(Math.floor(m / 60)).padStart(2, '0');
    const mm = String(m % 60).padStart(2, '0');
    return hh + ':' + mm;
}

// Extrait la plage horaire (first, last) depuis les donnees endpoints
// dataByDevice[device]['-1'] est un tableau de strings "HH:MM"
function extractTimeRange(endpointsData, seriesList) {
    for (const series of seriesList) {
        const rows = endpointsData[String(series.device)]?.['-1'];
        if (Array.isArray(rows) && rows.length >= 2) {
            return { first: rows[0], last: rows[rows.length - 1] };
        }
    }
    return null;
}

// ── Dictionnaire de l'apercu progressif ───────────────────────────────────
// Structure : previewMap[device][heure][pt] = valeur (string)
// Heure est la cle => pas d'alignement positionnel a gerer
// Ajout d'une valeur : previewMap[device][heure][pt] = val
// Conversion pour applyDataToCharts : previewMapToDataByDevice()

function previewAddData(previewMap, apiData) {
    // apiData : { device: { '-1': [heure,...], pt: [valeur,...] } }
    for (const device of Object.keys(apiData)) {
        if (!previewMap[device]) { previewMap[device] = {}; }
        const heures = apiData[device]['-1'];
        if (!Array.isArray(heures)) { continue; }
        for (const [pt, values] of Object.entries(apiData[device])) {
            if (pt === '-1') { continue; }
            if (!Array.isArray(values)) { continue; }
            for (let i = 0; i < values.length && i < heures.length; i++) {
                const h = heures[i];
                if (!previewMap[device][h]) { previewMap[device][h] = {}; }
                previewMap[device][h][pt] = values[i];
            }
        }
    }
}

function previewMapToDataByDevice(previewMap) {
    // Reconstruit { device: { '-1': [heures triees], pt: [valeurs alignees] } }
    const dataByDevice = {};
    for (const device of Object.keys(previewMap)) {
        const sortedHeures = Object.keys(previewMap[device]).sort();
        dataByDevice[device] = { '-1': sortedHeures };
        for (const h of sortedHeures) {
            for (const [pt, val] of Object.entries(previewMap[device][h])) {
                if (!dataByDevice[device][pt]) { dataByDevice[device][pt] = []; }
                dataByDevice[device][pt].push(val);
            }
        }
        // Aligner toutes les series a la meme longueur (null pour les trous)
        const size = sortedHeures.length;
        for (const pt of Object.keys(dataByDevice[device])) {
            if (pt === '-1') { continue; }
            while (dataByDevice[device][pt].length < size) {
                dataByDevice[device][pt].push(null);
            }
        }
    }
    return dataByDevice;
}

// Requetes paralleles par serie : 1 request par (device, point) au lieu de 1 gros batch
// Avantage : chaque PHP process fetche ~24 lignes (preview) ou ~720 lignes (full)
//            au lieu de N*720 lignes en sequentiel => wall-clock divise par ~N
async function fetchBatchDataParallel(seriesList, step) {
    const requests = seriesList.map((series) => {
        const device = String(series.device);
        // Chaque requete inclut -1 (index Heure) + le point de la serie
        const url = buildBatchApiUrl(device, [-1, series.point], step);
        return fetchJsonObject(url).then((data) => ({ device, point: String(series.point), data }));
    });
    const results = await Promise.all(requests);
    // Fusionner dans le format dataByDevice attendu par applyDataToCharts
    const dataByDevice = {};
    results.forEach(({ device, point, data }) => {
        if (!dataByDevice[device]) { dataByDevice[device] = {}; }
        // Index (-1) : prendre le premier resultat disponible pour ce device
        if (!dataByDevice[device]['-1'] && Array.isArray(data['-1']) && data['-1'].length > 0) {
            dataByDevice[device]['-1'] = data['-1'];
        }
        if (Array.isArray(data[point])) {
            dataByDevice[device][point] = data[point];
        }
    });
    return dataByDevice;
}

// Mise a jour directe des graphiques depuis le previewMap
// Lit previewMap[device][heure][pt], pas d'alignement positionnel a gerer
// Index X = heures triees du tableau, valeur = previewMap[device][heure][pt] * multiplier
function applyPreviewToCharts(previewMap, seriesList) {
    const firstDevice = String(seriesList[0].device || graphContext.defaultDevice);
    const sortedHeures = Object.keys(previewMap[firstDevice] || {}).sort();
    if (sortedHeures.length === 0) { return; }

    // Bati les donnees pour chaque serie a partir du tableau
    function buildSeriesData(series) {
        const device = String(series.device);
        const pt     = String(series.point);
        return sortedHeures.map((h) => {
            const raw = (previewMap[device]?.[h]?.[pt]);
            if (raw === undefined || raw === null) { return null; }
            const num = parseFloat(String(raw).replace(',', '.'));
            return Number.isFinite(num) ? num * series.multiplier : null;
        });
    }

    function buildOnOffData(series) {
        const device = String(series.device);
        const pt     = String(series.point);
        return sortedHeures.map((h) => {
            const raw = previewMap[device]?.[h]?.[pt];
            return raw !== undefined && raw !== null ? parseOnOffValue(raw) : null;
        });
    }

    // Supprime les bornes de zoom fixes pour que Chart.js affiche tous les labels
    function clearZoomBounds(chartInstance) {
        if (chartInstance && chartInstance.options.scales && chartInstance.options.scales.x) {
            delete chartInstance.options.scales.x.min;
            delete chartInstance.options.scales.x.max;
        }
    }

    if (analogChart && analogSeriesConfig.length) {
        analogChart.data.labels = sortedHeures.slice();
        analogSeriesConfig.forEach((series, i) => {
            const pts = buildSeriesData(series);
            analogChart.data.datasets[i].data = pts;
            analogSeriesConfig[i].lastValue = findLastDefinedValue(pts);
        });
        clearZoomBounds(analogChart);
        analogChart.update('none');
    }

    if (pressureChart && pressureSeriesConfig.length) {
        pressureChart.data.labels = sortedHeures.slice();
        pressureSeriesConfig.forEach((series, i) => {
            const pts = buildSeriesData(series);
            pressureChart.data.datasets[i].data = pts;
            pressureSeriesConfig[i].lastValue = findLastDefinedValue(pts);
        });
        clearZoomBounds(pressureChart);
        pressureChart.update('none');
    }

    if (onOffChart && onOffSeriesConfig.length) {
        onOffChart.data.labels = sortedHeures.slice();
        onOffSeriesConfig.forEach((series, i) => {
            const rawStates = buildOnOffData(series);
            onOffChart.data.datasets[i].rawStates = rawStates;
            onOffChart.data.datasets[i].data = rawStates.map((v, idx) => getOnOffDisplayValue(i, v));
            onOffSeriesConfig[i].lastValue = rawStates[rawStates.length - 1];
        });
        clearZoomBounds(onOffChart);
        onOffChart.update();
    }

    redrawSyncedCharts();
}

function applyDataToCharts(dataByDevice, seriesList, resetZoom) {
    const firstDevice = String(seriesList[0].device || graphContext.defaultDevice);
    const indexes = Array.isArray((dataByDevice[firstDevice] || {})['-1'])
        ? dataByDevice[firstDevice]['-1']
        : [];

    const normalizedAnalogSeries = analogSeriesConfig.map((series) => {
        const nextSeries = copyGraphObject(series);
        const values = ((dataByDevice[String(series.device)] || {})[String(series.point)]) || [];
        nextSeries.normalized = normalizeAnalogSeries(indexes, values, series);
        return nextSeries;
    });
    const normalizedPressureSeries = pressureSeriesConfig.map((series) => {
        const nextSeries = copyGraphObject(series);
        const values = ((dataByDevice[String(series.device)] || {})[String(series.point)]) || [];
        nextSeries.normalized = normalizeAnalogSeries(indexes, values, series);
        return nextSeries;
    });
    const normalizedOnOffSeries = onOffSeriesConfig.map((series) => {
        const nextSeries = copyGraphObject(series);
        const values = ((dataByDevice[String(series.device)] || {})[String(series.point)]) || [];
        nextSeries.normalized = normalizeOnOffSeries(indexes, values, series);
        return nextSeries;
    });

    let finalSize = indexes.length;
    normalizedAnalogSeries.forEach((series) => { finalSize = Math.min(finalSize, series.normalized.points.length); });
    normalizedPressureSeries.forEach((series) => { finalSize = Math.min(finalSize, series.normalized.points.length); });
    normalizedOnOffSeries.forEach((series) => { finalSize = Math.min(finalSize, series.normalized.points.length); });

    const warnings = [];
    normalizedAnalogSeries.forEach((series) => {
        if (Array.isArray(series.normalized.warnings) && series.normalized.warnings.length > 0) {
            warnings.push(series.label + ' valeurs signalees: ' + series.normalized.warnings.join(', '));
        }
    });
    normalizedPressureSeries.forEach((series) => {
        if (Array.isArray(series.normalized.warnings) && series.normalized.warnings.length > 0) {
            warnings.push(series.label + ' valeurs signalees: ' + series.normalized.warnings.join(', '));
        }
    });
    normalizedOnOffSeries.forEach((series) => {
        if (Array.isArray(series.normalized.warnings) && series.normalized.warnings.length > 0) {
            warnings.push(series.label + ' valeurs signalees: ' + series.normalized.warnings.join(', '));
        }
    });

    if (finalSize === 0) {
        warnings.push('aucun point commun exploitable entre les series configurees');
    }

    if (analogChart) {
        analogChart.data.labels = normalizedAnalogSeries[0].normalized.labels.slice(0, finalSize);
        normalizedAnalogSeries.forEach((series, datasetIndex) => {
            analogChart.data.datasets[datasetIndex].data = series.normalized.points.slice(0, finalSize);
            analogSeriesConfig[datasetIndex].lastValue = findLastDefinedValue(series.normalized.points.slice(0, finalSize));
        });
        analogChart.update('none');
    }

    if (pressureChart) {
        pressureChart.data.labels = normalizedPressureSeries[0].normalized.labels.slice(0, finalSize);
        normalizedPressureSeries.forEach((series, datasetIndex) => {
            pressureChart.data.datasets[datasetIndex].data = series.normalized.points.slice(0, finalSize);
            pressureSeriesConfig[datasetIndex].lastValue = findLastDefinedValue(series.normalized.points.slice(0, finalSize));
        });
        pressureChart.update('none');
    }

    if (onOffChart) {
        onOffChart.data.labels = normalizedOnOffSeries[0].normalized.labels.slice(0, finalSize);
        normalizedOnOffSeries.forEach((series, datasetIndex) => {
            const rawStates = series.normalized.points.slice(0, finalSize);
            onOffChart.data.datasets[datasetIndex].rawStates = rawStates;
            onOffChart.data.datasets[datasetIndex].data = rawStates.map((value) => getOnOffDisplayValue(datasetIndex, value));
            onOffSeriesConfig[datasetIndex].lastValue = rawStates[finalSize - 1];
        });
        onOffChart.update();
    }

    if (resetZoom) { resetAllZoom(); }

    return { normalizedAnalogSeries, normalizedPressureSeries, normalizedOnOffSeries, finalSize, indexes, warnings };
}

async function loadCurve(resetZoomOnSuccess) {
    const seriesList = analogSeriesConfig.concat(pressureSeriesConfig).concat(onOffSeriesConfig);

    if (!seriesList.length) {
        setStatus('Aucune ligne exploitable dans ConfigVueLignes pour cette vue.', true);
        return;
    }

    reloadButton.disabled = true;
    showLoadingOverlay();
    setStatus('Chargement de l\'apercu...', false);

    try {
        // ── Phase 0 : endpoints => graphique visible instantanement ─────────────────
        // 1 appel : CROSS APPLY first+last => 2 pts/serie => ~20ms
        const endpointsData = await fetchBatchData(seriesList, 1, 0, true);
        applyDataToCharts(endpointsData, seriesList, resetZoomOnSuccess !== false);
        hideLoadingOverlay();
        showBgProgress();
        setStatus('Apercu initial (debut + fin de journee)...', false);

        // ── Full load demarre maintenant en fond (concurrent avec la boucle apercu) ─
        // IIS peut traiter la longue requete full ET les courtes requetes heure en parallele
        let fullDone = false;
        const fullLoadPromise = fetchBatchData(seriesList, 1, 0, false);
        fullLoadPromise.then(() => { fullDone = true; }).catch(() => { fullDone = true; });

        // ── Boucle apercu progressif : 1 point par appel, toutes les 60 min ────────
        // Chaque appel : CROSS APPLY TOP 1 WHERE Heure >= cible => ~50ms (1 ligne/serie)
        // previewMap[device][heure][pt] = valeur => pas d'alignement positionnel a gerer
        const timeRange = extractTimeRange(endpointsData, seriesList);
        if (timeRange) {
            // Initialise le dictionnaire avec les endpoints deja affiches
            const previewMap = {};
            previewAddData(previewMap, endpointsData);

            const lastMin = heureToMinutes(timeRange.last);
            let   nextMin = heureToMinutes(timeRange.first) + 60; // premier point : t_first + 1h

            while (nextMin < lastMin && !fullDone) {
                const heure = minutesToHeure(nextMin);
                const pointData = await fetchBatchData(seriesList, 1, 0, false, heure);
                if (fullDone) { break; } // full load arrive pendant cet appel => inutile de continuer
                previewAddData(previewMap, pointData);
                try {
                    applyPreviewToCharts(previewMap, seriesList);

                    setStatus('Apercu ' + heure + '...', false);
                    // Cede le controle au navigateur pour qu'il peigne le graphique
                    await new Promise((resolve) => requestAnimationFrame(resolve));
                } catch (_e) {
                    console.warn('[Apercu] applyPreviewToCharts echoue a ' + heure + ' :', _e);
                }
                nextMin += 60;
            }
        }

        // ── Phase finale : attendre les donnees completes (deja en cours) ──────────
        const fullData = await fullLoadPromise;
        hideBgProgress();
        const fullResult = applyDataToCharts(fullData, seriesList, true);

        const hasMissingData =
            fullResult.normalizedAnalogSeries.some((series) => fullResult.indexes.length !== series.normalized.points.length)
            || fullResult.normalizedPressureSeries.some((series) => fullResult.indexes.length !== series.normalized.points.length)
            || fullResult.normalizedOnOffSeries.some((series) => fullResult.indexes.length !== series.normalized.points.length)
            || fullResult.normalizedAnalogSeries.some((series) => Number(series.normalized.filteredCount || 0) > 0)
            || fullResult.normalizedAnalogSeries.some((series) => Array.isArray(series.filterWarnings) && series.filterWarnings.length > 0)
            || (Array.isArray(fullResult.warnings) && fullResult.warnings.length > 0);

        if (hasMissingData) {
            setStatus('Donnees chargees (' + fullResult.finalSize + ' pts).', false);
        } else {
            setStatus('Donnees completes chargees (' + fullResult.finalSize + ' pts).', false);
        }
    } catch (error) {
        hideBgProgress();
        setStatus('Echec du chargement: ' + (error instanceof Error ? error.message : 'Erreur inconnue'), true);
    } finally {
        reloadButton.disabled = false;
        hideLoadingOverlay();
    }
}

function initGraphView() {
    // Date par defaut : aujourd'hui si non renseignee
    if (!graphContext.date || String(graphContext.date).trim() === '') {
        const now = new Date();
        const dd = String(now.getDate()).padStart(2, '0');
        const mm = String(now.getMonth() + 1).padStart(2, '0');
        const yyyy = now.getFullYear();
        graphContext.date = dd + '-' + mm + '-' + yyyy;
    }

    const normalizedSeries = normalizeGraphLines();
    const allAnalog = normalizedSeries.filter((series) => series.type === 'analog');
    analogSeriesConfig = allAnalog.filter((series) => series.signe !== 'P');
    pressureSeriesConfig = allAnalog.filter((series) => series.signe === 'P');
    onOffSeriesConfig = normalizedSeries.filter((series) => series.type === 'onoff');

    createCharts();
    refreshDateDisplay();

    if (!normalizedSeries.length) {
        setStatus('Aucune ligne exploitable dans ConfigVueLignes pour cette vue.', true);
        return;
    }

    loadCurve();
}

openDateDialogButton.addEventListener('click', () => {
    const todayVal = getTodayInputValue();
    dateInput.min = '2026-04-04';
    dateInput.max = todayVal;
    dateInput.value = formatDateForInput(graphContext.date);
    if (typeof dateInput.showPicker === 'function') {
        dateInput.showPicker();
    } else {
        dateInput.click();
    }
});
dateInput.addEventListener('change', () => {
    try {
        graphContext.date = formatDateForApi(dateInput.value);
    } catch (error) {
        setStatus(error instanceof Error ? error.message : 'Date invalide.', true);
        return;
    }
    refreshDateDisplay();
    loadCurve(true);
});
prevDayButton.addEventListener('click', () => shiftDate(-1));
nextDayButton.addEventListener('click', () => shiftDate(1));
reloadButton.addEventListener('click', loadCurve);
resetZoomButton.addEventListener('click', resetAllZoom);
window.addEventListener('mousemove', handleRightMousePanMove);
window.addEventListener('mouseup', stopRightMousePan);
window.addEventListener('blur', stopRightMousePan);

initGraphView();
</script>
