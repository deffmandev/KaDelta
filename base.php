<?php

date_default_timezone_set('Europe/Paris');

$serverName = "localhost,1433";
$connectionOptions = [
    "Database" => "Ka",
    "Uid" => "BaseKa",
    "PWD" => "deffdeff",
    "CharacterSet" => "UTF-8",
];


$conn = sqlsrv_connect($serverName, $connectionOptions);

if ($conn === false) {
    die(print_r(sqlsrv_errors(), true));
}

// Gestion d'erreur centralisée
function logSqlError($context = '') {
    $errors = sqlsrv_errors();
    $msg = "[SQLSRV ERROR] ";
    if ($context) $msg .= "($context) ";
    foreach ($errors as $error) {
        $msg .= "SQLSTATE: " . $error['SQLSTATE'] . ", Code: " . $error['code'] . ", Message: " . $error['message'] . "; ";
    }
    error_log($msg);
    echo "<div style='color:red;font-weight:bold;'>Erreur base de données. Consultez les logs.</div>";
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
    // Display column names
    if ($fields = sqlsrv_field_metadata($stmt)) {
        foreach ($fields as $field) {
            echo "<th>" . htmlspecialchars($field['Name']) . "</th>";
        }
    }
    echo "</tr>";

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
    return sqlsrv_fetch_array($base);
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
    if ($whereValue==-1)
    {
        $sql = "UPDATE [$table] SET [$field] = ?";
        $params = [$value];
    }
    else
    {
        $sql = "UPDATE [$table] SET [$field] = ? WHERE [$whereField] = ?";
        $params = [$value, $whereValue];
    }
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt === false) {
        logSqlError('updateTable');
        return false;
    }
    return true;
}

function mssql_insert($table, $data) 
{
    $fields = [];
    $values = [];
    foreach ($data as $key => $value) 
        {
                $fields[] = "[" . addslashes($key) . "]";
                $values[] = "'" . addslashes($value) . "'";
        }
            $sql = "INSERT INTO [$table] (" . implode(',', $fields) . ") VALUES (" . implode(',', $values) . ")";
            return mssql($sql);
}



if (isset($_GET['table'])) 
{
    $table = $_GET['table'];
    $field = $_GET['field'] ?? 'Name';
    $value = $_GET['value'] ?? 'bureau';
    $whereField = $_GET['whereField'] ?? 'Id';
    $whereValue = $_GET['whereValue'] ?? 1;
    updateTable($table, $field, $value, $whereField, $whereValue);
}


?>

