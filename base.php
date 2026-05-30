<?php

date_default_timezone_set('Europe/Paris');

$serverName = getenv('KA_DB_SERVER') ?: "localhost,1433";
$connectionOptions = [
    "Database" => getenv('KA_DB_NAME') ?: "Ka",
    "Uid" => getenv('KA_DB_USER') ?: "BaseKa",
    "PWD" => getenv('KA_DB_PASS') ?: "deffdeff",
    "CharacterSet" => "UTF-8",
];


$conn = sqlsrv_connect($serverName, $connectionOptions);

if ($conn === false) {
    die(print_r(sqlsrv_errors(), true));
}

if (!isset($GLOBALS['__KA_BASE_PHP_LOADED'])) {
$GLOBALS['__KA_BASE_PHP_LOADED'] = true;

// Gestion d'erreur centralisée
function logSqlError($context = '') {
    $errors = sqlsrv_errors();
    $msg = "[ERREUR SQLSRV] ";
    if ($context) $msg .= "($context) ";
    foreach ($errors as $error) {
        $msg .= "SQLSTATE: " . $error['SQLSTATE'] . ", Code: " . $error['code'] . ", Message: " . $error['message'] . "; ";
    }
    error_log($msg);
}

function GetTable($tableName)
{
    global $conn;
    $sql = "SELECT * FROM " . $tableName;
    $stmt = sqlsrv_query($conn, $sql);

    if ($stmt === false) {
        logSqlError('GetTable');
        return;
    }

    echo "<table border='1' cellpadding='5'><tr>";
    // Afficher les noms des colonnes
    // Display column names
    if ($fields = sqlsrv_field_metadata($stmt)) {
        foreach ($fields as $field) {
            echo "<th>" . htmlspecialchars($field['Name']) . "</th>";
        }
    }
    echo "</tr>";

    // Afficher les lignes
    // Display rows
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        echo "<tr>";
        foreach ($row as $value) {
            echo "<td>" . htmlspecialchars((string)$value) . "</td>";
        }
        echo "</tr>";
    }
    echo "</table>";
}

function mssql($sql)
{
    global $conn;
    try {
        $stmt = sqlsrv_query($conn, $sql);
        if ($stmt === false) {
            logSqlError('mssql');
            return false;
        }
        return $stmt;
    } catch (Exception $e) {
        error_log("[EXCEPTION] " . $e->getMessage());
        echo "<div style='color:red;font-weight:bold;'>Erreur SQL. Consultez les logs.</div>";
        return false;
    }

}

function sqlnext($base)
{
    try {
        $Reponce=sqlsrv_fetch_array($base);
        }
    catch (Exception $e) 
        {
            $Reponce=null;
        }
    return $Reponce;
}

function mssqlinfo()
{
    global $conn;
    $sql = "SELECT @@VERSION AS Version";
    $stmt = sqlsrv_query($conn, $sql);

    if ($stmt === false) {
        logSqlError('mssqlinfo');
        return;
    }

    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    
    echo $row['Version'];
}



function afficherListeTables()
{
    global $conn;
    $sql = "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_TYPE = 'BASE TABLE'";
    $stmt = sqlsrv_query($conn, $sql);

    if ($stmt === false) {
        logSqlError('afficherListeTables');
        return;
    }

    echo "<ul>";
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        echo "<li>" . htmlspecialchars($row['TABLE_NAME']) . "</li>";
    }
    echo "</ul>";
}


/**
 * Met à jour une valeur spécifique dans une table de la base de données.
 *
 * @param string $table       Le nom de la table à mettre à jour.
 * @param string $field       Le champ à mettre à jour.
 * @param mixed  $value       La nouvelle valeur à affecter au champ.
 * @param string $whereField  Le champ utilisé dans la clause WHERE pour identifier la ligne à mettre à jour.
 * @param mixed  $whereValue  La valeur du champ utilisé dans la clause WHERE.
 *
 */
/* Exemple d'utilisation de updateTable pour changer le nom en 'bureau' pour l'id=1 dans la table DefUnites */

function updateTable($table, $field, $value, $whereField, $whereValue)
{
    global $conn;
    $safeTable = base_sql_identifier($table);
    $safeField = base_sql_identifier($field);
    $safeWhereField = base_sql_identifier($whereField);

    if ($safeTable === null || $safeField === null || $safeWhereField === null) {
        error_log('[SECURITY] updateTable rejected: invalid identifier(s).');
        return false;
    }

    if ($whereValue==-1)
    {
        $sql = "UPDATE [$safeTable] SET [$safeField] = ?";
        $params = [$value];
    }
    else
    {
        $sql = "UPDATE [$safeTable] SET [$safeField] = ? WHERE [$safeWhereField] = ?";
        $params = [$value, $whereValue];
    }
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt === false) {
        logSqlError('updateTable');
        return false;
    }
    return true;
}

function base_sql_identifier($name)
{
    $value = trim((string)$name);
    if ($value === '' || !preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value)) {
        return null;
    }
    return $value;
}

function base_has_active_session()
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        if (headers_sent()) {
            return false;
        }
        session_name('KADELTASESSID');
        session_start();
    }

    return isset($_SESSION['user_id']);
}

function base_is_admin_session()
{
    if (!base_has_active_session()) {
        return false;
    }

    if (isset($_SESSION['auth_virtual']) && (int)$_SESSION['auth_virtual'] === 1) {
        return true;
    }

    return isset($_SESSION['user_role']) && (string)$_SESSION['user_role'] === 'Administrateur';
}

function base_is_allowed_update_request($table, $field, $whereField)
{
    $safeTable = base_sql_identifier($table);
    $safeField = base_sql_identifier($field);
    $safeWhereField = base_sql_identifier($whereField);

    if ($safeTable !== 'DefUnites') {
        return false;
    }

    if (!in_array($safeWhereField, ['Id', 'Gr'], true)) {
        return false;
    }

    $allowedFields = [
        'Prog', 'Name', 'Gr', 'ModbusId', 'Device',
        'OnOff', 'Type_OnOff', 'Mode', 'Type_Mode',
        'Fan', 'Type_Fan', 'Room', 'Type_Room',
        'SetRoom', 'Type_SetRoom', 'Alarm', 'Type_Alarm',
        'CodeErreur', 'Type_CodeErreur',
        'LimiteClimB', 'LimiteClimH', 'LimiteChaudB', 'LimiteChaudH'
    ];

    return in_array($safeField, $allowedFields, true);
}

function mssql_insert($table, $data) 
{
    global $conn;

    $safeTable = base_sql_identifier($table);
    if ($safeTable === null || !is_array($data) || count($data) === 0) {
        return false;
    }

    $fields = [];
    $placeholders = [];
    $params = [];

    foreach ($data as $key => $value) {
        $safeField = base_sql_identifier((string)$key);
        if ($safeField === null) {
            return false;
        }
        $fields[] = "[$safeField]";
        $placeholders[] = '?';
        $params[] = $value;
    }

    $sql = "INSERT INTO [$safeTable] (" . implode(',', $fields) . ") VALUES (" . implode(',', $placeholders) . ")";
    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt === false) {
        logSqlError('mssql_insert');
        return false;
    }
    return $stmt;
}

// Suppression générique d'une ligne dans une table
function sql_delete($table, $whereField, $whereValue) {
    global $conn;

    $safeTable = base_sql_identifier($table);
    $safeWhereField = base_sql_identifier($whereField);
    if ($safeTable === null || $safeWhereField === null) {
        return false;
    }

    $sql = "DELETE FROM [$safeTable] WHERE [$safeWhereField] = ?";
    $stmt = sqlsrv_query($conn, $sql, [$whereValue]);
    if ($stmt === false) {
        logSqlError('sql_delete');
        return false;
    }
    return $stmt;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['api_action'])) {
    header('Content-Type: text/plain; charset=UTF-8');

    if (!base_has_active_session()) {
        http_response_code(401);
        echo 'UNAUTHORIZED';
        exit;
    }

    $apiAction = (string)$_POST['api_action'];

    if ($apiAction === 'update_table') {
        $table = (string)($_POST['table'] ?? '');
        $field = (string)($_POST['field'] ?? '');
        $value = $_POST['value'] ?? '';
        $whereField = (string)($_POST['whereField'] ?? 'Id');
        $whereValue = $_POST['whereValue'] ?? 0;

        if (!base_is_allowed_update_request($table, $field, $whereField)) {
            http_response_code(403);
            echo 'FORBIDDEN';
            exit;
        }

        $ok = updateTable($table, $field, $value, $whereField, $whereValue);
        echo $ok ? 'OK' : 'ERR';
        exit;
    }

    if ($apiAction === 'delete_row') {
        $table = (string)($_POST['table'] ?? '');
        $id = $_POST['Id'] ?? 0;

        if (!base_is_admin_session()) {
            http_response_code(403);
            echo 'FORBIDDEN';
            exit;
        }

        if (base_sql_identifier($table) !== 'DefUnites') {
            http_response_code(403);
            echo 'FORBIDDEN';
            exit;
        }

        $ok = sql_delete($table, 'Id', $id);
        echo $ok ? 'OK' : 'ERR';
        exit;
    }

    http_response_code(400);
    echo 'BAD_REQUEST';
    exit;
}


}


?>

