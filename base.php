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

function GetTable($tableName)
{
    global $conn;
    $sql = "SELECT * FROM " . $tableName;
    $stmt = sqlsrv_query($conn, $sql);

    if ($stmt === false) {
        die(print_r(sqlsrv_errors(), true));
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
            throw new Exception(print_r(sqlsrv_errors(), true));
        }   
        return $stmt;
    } catch (Exception $e) {
        echo "Error executing query: " . $e->getMessage();
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
        die(print_r(sqlsrv_errors(), true));
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
        die(print_r(sqlsrv_errors(), true));
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
        die(print_r(sqlsrv_errors(), true));
    }
    return true;
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