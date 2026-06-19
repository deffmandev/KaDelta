<?php

function log_detect_unc_server_name($path)
{
    $normalizedPath = str_replace('/', '\\', (string)$path);
    if (strpos($normalizedPath, '\\\\') !== 0) {
        return null;
    }

    $parts = explode('\\', trim($normalizedPath, '\\'));
    if (!isset($parts[0]) || trim((string)$parts[0]) === '') {
        return null;
    }

    return trim((string)$parts[0]);
}

$detectedServerHost = log_detect_unc_server_name(__DIR__);
$serverName = getenv('KA_LOG_DB_SERVER') ?: getenv('KA_DB_SERVER') ?: ($detectedServerHost ? ($detectedServerHost . ',1433') : 'localhost,1433');
$connectionOptions = [
    "Uid"                       => getenv('KA_LOG_DB_USER') ?: getenv('KA_DB_USER') ?: "BaseKa",
    "PWD"                       => getenv('KA_LOG_DB_PASS') ?: getenv('KA_DB_PASS') ?: "deffdeff",
    "CharacterSet"              => "UTF-8",
    "ConnectionPooling"         => true,
    "MultipleActiveResultSets"  => false,
];

$connLog = false;
$connLogDatabaseName = null;
$logConnectionCache = [];
$logTransactionLogCapCache = [];
$logSchemaCache = [];
$logWideSchemaCache = [];

define('LOG_DATABASE_LEGACY_NAME', 'KaLog');
define('LOG_DATABASE_PREFIX', 'KaLog_');
define('LOG_DATABASE_V2_PREFIX', 'V2-');
define('LOG_MIGRATION_REGISTRY_TABLE', 'dbo.LogMonthlyMigration');
define('LOG_MIGRATION_REGISTRY_FILE', __DIR__ . '/kalog_monthly_migration_registry.json');
define('LOG_AUTOGROW_FILE_NAME', 'KaLog_AutoExt1');
define('LOG_LICENSE_LIMIT_MB', 10240); // SQL Server Express: 10 Go par base de donnees (hors log)
define('LOG_AUTOGROW_INITIAL_MB', 256);
define('LOG_AUTOGROW_STEP_MB', 1024); // 1 Go
define('LOG_TRANSACTION_LOG_MAX_KB', 184320); // 180 Mo
define('LOG_TRANSACTION_LOG_GROWTH_KB', 4096); // 4 Mo
define('LOG_DELETE_PROTECTION_ENABLED', true);

function log_report_sql_error($context = '')
{
    if (function_exists('logSqlError')) {
        logSqlError($context);
        return;
    }

    $errors = sqlsrv_errors(SQLSRV_ERR_ERRORS);
    if (!is_array($errors)) {
        error_log('[LOGSQL] ' . $context . ' - erreur SQL inconnue');
        return;
    }

    foreach ($errors as $err) {
        $message = (string)($err['message'] ?? 'Erreur SQL inconnue');
        $code = (string)($err['code'] ?? '');
        $state = (string)($err['SQLSTATE'] ?? '');
        error_log('[LOGSQL] ' . $context . ' - SQLSTATE: ' . $state . ', Code: ' . $code . ', Message: ' . $message);
    }
}

function log_get_last_sql_error_summary()
{
    $errors = sqlsrv_errors(SQLSRV_ERR_ERRORS);
    if (!is_array($errors) || count($errors) === 0) {
        return '';
    }

    $parts = [];
    foreach ($errors as $err) {
        $parts[] = 'SQLSTATE: ' . (string)($err['SQLSTATE'] ?? '')
            . ', Code: ' . (string)($err['code'] ?? '')
            . ', Message: ' . (string)($err['message'] ?? 'Erreur SQL inconnue');
    }

    return implode(' | ', $parts);
}

function log_quote_identifier($value)
{
    return '[' . str_replace(']', ']]', (string)$value) . ']';
}

function log_connection_options($databaseName)
{
    global $connectionOptions;

    $options = $connectionOptions;
    $options['Database'] = (string)$databaseName;
    return $options;
}

function log_normalize_date_string($dateValue = null)
{
    if ($dateValue === null) {
        return date('d-m-Y');
    }

    $date = trim((string)$dateValue);
    if ($date === '') {
        return date('d-m-Y');
    }

    foreach (['d-m-Y', 'Y-m-d', 'd/m/Y'] as $format) {
        $parsed = DateTime::createFromFormat($format, $date);
        if ($parsed instanceof DateTime && $parsed->format($format) === $date) {
            return $parsed->format('d-m-Y');
        }
    }

    return date('d-m-Y');
}

function log_month_database_name($dateValue = null)
{
    $normalized = log_normalize_date_string($dateValue);
    $parsed = DateTime::createFromFormat('d-m-Y', $normalized);
    if (!($parsed instanceof DateTime)) {
        $parsed = new DateTime('now');
    }

    return LOG_DATABASE_PREFIX . $parsed->format('Ym');
}

function log_month_v2_database_name($dateValue = null)
{
    $normalized = log_normalize_date_string($dateValue);
    $parsed = DateTime::createFromFormat('d-m-Y', $normalized);
    if (!($parsed instanceof DateTime)) {
        $parsed = new DateTime('now');
    }

    return LOG_DATABASE_V2_PREFIX . $parsed->format('Ym');
}

function log_month_key_from_date($dateValue = null)
{
    $normalized = log_normalize_date_string($dateValue);
    $parsed = DateTime::createFromFormat('d-m-Y', $normalized);
    if (!($parsed instanceof DateTime)) {
        $parsed = new DateTime('now');
    }

    return $parsed->format('Ym');
}

function log_open_connection($databaseName)
{
    global $serverName, $logConnectionCache;

    $databaseName = (string)$databaseName;
    if (isset($logConnectionCache[$databaseName]) && $logConnectionCache[$databaseName]) {
        return $logConnectionCache[$databaseName];
    }

    $conn = sqlsrv_connect($serverName, log_connection_options($databaseName));
    if ($conn !== false) {
        $logConnectionCache[$databaseName] = $conn;
    }

    return $conn;
}

function log_set_default_connection($databaseName, $conn)
{
    global $connLog, $connLogDatabaseName;

    $connLog = $conn;
    $connLogDatabaseName = (string)$databaseName;
    return $conn;
}

function log_database_exists($databaseName)
{
    $masterConn = log_open_connection('master');
    if ($masterConn === false) {
        log_report_sql_error('log_database_exists.connect_master');
        return false;
    }

    $stmt = sqlsrv_query($masterConn, 'SELECT 1 FROM sys.databases WHERE [name] = ?', [(string)$databaseName]);
    if ($stmt === false) {
        log_report_sql_error('log_database_exists.query');
        return false;
    }

    $exists = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_NUMERIC) !== null;
    sqlsrv_free_stmt($stmt);

    return $exists;
}

function log_load_migration_registry()
{
    if (!is_file(LOG_MIGRATION_REGISTRY_FILE)) {
        return [];
    }

    $raw = @file_get_contents(LOG_MIGRATION_REGISTRY_FILE);
    if ($raw === false || trim($raw) === '') {
        return [];
    }

    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function log_save_migration_registry($registry)
{
    if (!is_array($registry)) {
        $registry = [];
    }

    $encoded = json_encode($registry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    if (!is_string($encoded)) {
        error_log('[LOG] Impossible d\'encoder le registre de migration mensuelle.');
        return false;
    }

    return @file_put_contents(LOG_MIGRATION_REGISTRY_FILE, $encoded . PHP_EOL) !== false;
}

function log_ensure_migration_registry()
{
    if (is_file(LOG_MIGRATION_REGISTRY_FILE)) {
        return true;
    }

    return log_save_migration_registry([]);
}

function log_is_month_migrated($dateValue = null)
{
    if (!log_ensure_migration_registry()) {
        return false;
    }

    $monthKey = log_month_key_from_date($dateValue);
    $registry = log_load_migration_registry();
    $entry = $registry[$monthKey] ?? null;

    return is_array($entry) && !empty($entry['IsComplete']);
}

function log_record_month_migration($monthKey, $targetDatabase, $sourceCount, $targetCount, $isComplete)
{
    if (!log_ensure_migration_registry()) {
        return false;
    }

    $registry = log_load_migration_registry();
    $registry[(string)$monthKey] = [
        'MonthKey' => (string)$monthKey,
        'TargetDatabase' => (string)$targetDatabase,
        'SourceCount' => (int)$sourceCount,
        'TargetCount' => (int)$targetCount,
        'IsComplete' => $isComplete ? 1 : 0,
        'UpdatedAt' => gmdate('c'),
    ];

    if (!log_save_migration_registry($registry)) {
        error_log('[LOG] Impossible d\'ecrire le registre de migration mensuelle.');
        return false;
    }

    return true;
}

function log_ensure_database_schema($databaseName)
{
    $conn = log_open_connection($databaseName);
    if ($conn === false) {
        log_report_sql_error('log_ensure_database_schema.connect');
        return false;
    }

    $sql = "
IF OBJECT_ID(N'dbo.Log', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.Log (
        Id INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        [Date] TEXT NULL,
        Heure TEXT NULL,
        Device INT NOT NULL,
        Point INT NOT NULL,
        Valeur TEXT NULL,
        DateNV NVARCHAR(10) NULL
    );
END

IF COL_LENGTH(N'dbo.Log', N'DateNV') IS NULL
BEGIN
    ALTER TABLE dbo.Log ADD DateNV NVARCHAR(10) NULL;
END

IF NOT EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE name = 'IX_Log_Device_Point_Id' AND object_id = OBJECT_ID(N'dbo.Log')
)
BEGIN
    CREATE INDEX IX_Log_Device_Point_Id ON dbo.Log (Device, Point, Id);
END

IF NOT EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE name = 'IX_Log_Device_Point_DateNV_Id' AND object_id = OBJECT_ID(N'dbo.Log')
)
BEGIN
    CREATE INDEX IX_Log_Device_Point_DateNV_Id ON dbo.Log (Device, Point, DateNV, Id);
END

IF NOT EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE name = 'IX_Log_DateNV_Device_Point_Id' AND object_id = OBJECT_ID(N'dbo.Log')
)
BEGIN
    CREATE INDEX IX_Log_DateNV_Device_Point_Id ON dbo.Log (DateNV, Device, Point, Id);
END

IF EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE name = 'IX_Log_Device_Point' AND object_id = OBJECT_ID(N'dbo.Log')
)
BEGIN
    DROP INDEX IX_Log_Device_Point ON dbo.Log;
END

IF EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE name = 'IX_Log_Device_Point_DateNV' AND object_id = OBJECT_ID(N'dbo.Log')
)
BEGIN
    DROP INDEX IX_Log_Device_Point_DateNV ON dbo.Log;
END
";

    $stmt = sqlsrv_query($conn, $sql);
    if ($stmt === false) {
        log_report_sql_error('log_ensure_database_schema.sql');
        return false;
    }

    sqlsrv_free_stmt($stmt);
    return true;
}

function log_ensure_database_schema_once($databaseName)
{
    global $logSchemaCache;

    $databaseName = (string)$databaseName;
    if (array_key_exists($databaseName, $logSchemaCache)) {
        return $logSchemaCache[$databaseName] === true;
    }

    $ok = log_ensure_database_schema($databaseName);
    $logSchemaCache[$databaseName] = $ok === true;
    return $ok;
}

function log_wide_point_columns()
{
    $columns = [];
    for ($point = 0; $point <= 500; $point++) {
        $columns[] = '[P' . $point . '] FLOAT NULL';
    }

    return implode(",\n        ", $columns);
}

function log_wide_point_column_list()
{
    $columns = [];
    for ($point = 0; $point <= 500; $point++) {
        $columns[] = '[P' . $point . ']';
    }

    return $columns;
}

function log_ensure_wide_schema($databaseName)
{
    $conn = log_open_connection($databaseName);
    if ($conn === false) {
        log_report_sql_error('log_ensure_wide_schema.connect');
        return false;
    }

    $pointColumnsSql = log_wide_point_columns();
    $sql = "
IF OBJECT_ID(N'dbo.LogWide', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.LogWide (
        Id BIGINT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        DateNV NVARCHAR(10) NOT NULL,
        Heure CHAR(5) NOT NULL,
        Device INT NOT NULL,
        " . $pointColumnsSql . "
    );
END

IF COL_LENGTH(N'dbo.LogWide', N'CreatedAt') IS NOT NULL
BEGIN
    DECLARE @dfCreatedAt sysname;
    SELECT @dfCreatedAt = dc.name
    FROM sys.default_constraints dc
    INNER JOIN sys.columns c ON c.default_object_id = dc.object_id
    INNER JOIN sys.tables t ON t.object_id = c.object_id
    INNER JOIN sys.schemas s ON s.schema_id = t.schema_id
    WHERE s.name = N'dbo' AND t.name = N'LogWide' AND c.name = N'CreatedAt';

    IF @dfCreatedAt IS NOT NULL
    BEGIN
        DECLARE @dropCreatedAtSql NVARCHAR(4000);
        SET @dropCreatedAtSql = N'ALTER TABLE dbo.LogWide DROP CONSTRAINT ' + QUOTENAME(@dfCreatedAt) + N';';
        EXEC(@dropCreatedAtSql);
    END

    ALTER TABLE dbo.LogWide DROP COLUMN CreatedAt;
END

IF COL_LENGTH(N'dbo.LogWide', N'UpdatedAt') IS NOT NULL
BEGIN
    DECLARE @dfUpdatedAt sysname;
    SELECT @dfUpdatedAt = dc.name
    FROM sys.default_constraints dc
    INNER JOIN sys.columns c ON c.default_object_id = dc.object_id
    INNER JOIN sys.tables t ON t.object_id = c.object_id
    INNER JOIN sys.schemas s ON s.schema_id = t.schema_id
    WHERE s.name = N'dbo' AND t.name = N'LogWide' AND c.name = N'UpdatedAt';

    IF @dfUpdatedAt IS NOT NULL
    BEGIN
        DECLARE @dropUpdatedAtSql NVARCHAR(4000);
        SET @dropUpdatedAtSql = N'ALTER TABLE dbo.LogWide DROP CONSTRAINT ' + QUOTENAME(@dfUpdatedAt) + N';';
        EXEC(@dropUpdatedAtSql);
    END

    ALTER TABLE dbo.LogWide DROP COLUMN UpdatedAt;
END

IF NOT EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE name = 'UX_LogWide_DateNV_Heure_Device' AND object_id = OBJECT_ID(N'dbo.LogWide')
)
BEGIN
    CREATE UNIQUE INDEX UX_LogWide_DateNV_Heure_Device ON dbo.LogWide (DateNV, Heure, Device);
END

IF NOT EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE name = 'IX_LogWide_DateNV_Heure' AND object_id = OBJECT_ID(N'dbo.LogWide')
)
BEGIN
    CREATE INDEX IX_LogWide_DateNV_Heure ON dbo.LogWide (DateNV, Heure) INCLUDE (Device);
END

IF NOT EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE name = 'IX_LogWide_Device_DateNV_Heure' AND object_id = OBJECT_ID(N'dbo.LogWide')
)
BEGIN
    CREATE INDEX IX_LogWide_Device_DateNV_Heure ON dbo.LogWide (Device, DateNV, Heure);
END
";

    $stmt = sqlsrv_query($conn, $sql);
    if ($stmt === false) {
        log_report_sql_error('log_ensure_wide_schema.sql');
        return false;
    }

    sqlsrv_free_stmt($stmt);
    return true;
}

function log_ensure_wide_schema_once($databaseName)
{
    global $logWideSchemaCache;

    $databaseName = (string)$databaseName;
    if (array_key_exists($databaseName, $logWideSchemaCache)) {
        return $logWideSchemaCache[$databaseName] === true;
    }

    $ok = log_ensure_wide_schema($databaseName);
    $logWideSchemaCache[$databaseName] = $ok === true;
    return $ok;
}

function log_create_month_database($databaseName)
{
    if (log_database_exists($databaseName)) {
        if (!log_ensure_database_schema_once($databaseName)) {
            return false;
        }

        log_enforce_transaction_log_limit($databaseName);
        return true;
    }

    $masterConn = log_open_connection('master');
    if ($masterConn === false) {
        log_report_sql_error('log_create_month_database.connect_master');
        return false;
    }

    $createSql = 'CREATE DATABASE ' . log_quote_identifier($databaseName);
    $stmt = sqlsrv_query($masterConn, $createSql);
    if ($stmt === false) {
        log_report_sql_error('log_create_month_database.create_db');
        return false;
    }
    sqlsrv_free_stmt($stmt);

    if (!log_database_exists($databaseName)) {
        error_log('[LOG] Creation de base non confirmee pour ' . $databaseName . '.');
        return false;
    }

    if (!log_ensure_database_schema_once($databaseName)) {
        return false;
    }

    log_enforce_transaction_log_limit($databaseName);
    return true;
}

function log_create_v2_database($databaseName)
{
    if (log_database_exists($databaseName)) {
        if (!log_ensure_wide_schema_once($databaseName)) {
            return false;
        }

        log_enforce_transaction_log_limit($databaseName);
        return true;
    }

    $masterConn = log_open_connection('master');
    if ($masterConn === false) {
        log_report_sql_error('log_create_v2_database.connect_master');
        return false;
    }

    $createSql = 'CREATE DATABASE ' . log_quote_identifier($databaseName);
    $stmt = sqlsrv_query($masterConn, $createSql);
    if ($stmt === false) {
        log_report_sql_error('log_create_v2_database.create_db');
        return false;
    }
    sqlsrv_free_stmt($stmt);

    if (!log_database_exists($databaseName)) {
        error_log('[LOG] Creation de base non confirmee pour ' . $databaseName . '.');
        return false;
    }

    if (!log_ensure_wide_schema_once($databaseName)) {
        return false;
    }

    log_enforce_transaction_log_limit($databaseName);
    return true;
}

function log_enforce_transaction_log_limit($databaseName)
{
    global $logTransactionLogCapCache;

    $databaseName = (string)$databaseName;
    $isMonthly = strpos($databaseName, LOG_DATABASE_PREFIX) === 0;
    $isV2 = strpos($databaseName, LOG_DATABASE_V2_PREFIX) === 0;
    if ($databaseName === '' || (!$isMonthly && !$isV2)) {
        return true;
    }

    if (array_key_exists($databaseName, $logTransactionLogCapCache)) {
        return $logTransactionLogCapCache[$databaseName] === true;
    }

    $conn = log_open_connection($databaseName);
    if ($conn === false) {
        log_report_sql_error('log_enforce_transaction_log_limit.connect');
        $logTransactionLogCapCache[$databaseName] = false;
        return false;
    }

    $fileStmt = sqlsrv_query(
        $conn,
        "SELECT TOP 1 [name], [size] * 8 AS size_kb FROM sys.database_files WHERE type_desc = 'LOG' ORDER BY file_id"
    );
    if ($fileStmt === false) {
        log_report_sql_error('log_enforce_transaction_log_limit.select_log_file');
        $logTransactionLogCapCache[$databaseName] = false;
        return false;
    }

    $fileRow = sqlsrv_fetch_array($fileStmt, SQLSRV_FETCH_ASSOC);
    sqlsrv_free_stmt($fileStmt);
    if (!is_array($fileRow) || trim((string)($fileRow['name'] ?? '')) === '') {
        error_log('[LOG] Impossible de trouver le fichier LOG pour ' . $databaseName . '.');
        $logTransactionLogCapCache[$databaseName] = false;
        return false;
    }

    $logicalName = (string)$fileRow['name'];
    $currentSizeKb = (int)ceil((float)($fileRow['size_kb'] ?? 0));
    $maxSizeKb = max(100, (int)LOG_TRANSACTION_LOG_MAX_KB);
    $growthKb = min($maxSizeKb, max(32, (int)LOG_TRANSACTION_LOG_GROWTH_KB));

    if ($currentSizeKb > $maxSizeKb) {
        error_log('[LOG] Limite LOG transaction deja depassee pour ' . $databaseName . ' (' . $currentSizeKb . ' Ko > ' . $maxSizeKb . ' Ko). Application de la limite ignorée pour cette base existante.');
        $logTransactionLogCapCache[$databaseName] = true;
        return true;
    }

    $logicalNameSql = str_replace("'", "''", $logicalName);
    $sql = "
ALTER DATABASE " . log_quote_identifier($databaseName) . " SET RECOVERY SIMPLE WITH NO_WAIT;
ALTER DATABASE " . log_quote_identifier($databaseName) . "
MODIFY FILE (NAME = N'{$logicalNameSql}', FILEGROWTH = {$growthKb}KB, MAXSIZE = {$maxSizeKb}KB);
";

    $stmt = sqlsrv_query($conn, $sql);
    if ($stmt === false) {
        log_report_sql_error('log_enforce_transaction_log_limit.alter');
        $logTransactionLogCapCache[$databaseName] = false;
        return false;
    }
    sqlsrv_free_stmt($stmt);

    $logTransactionLogCapCache[$databaseName] = true;
    return true;
}

function log_get_write_connection($dateValue = null)
{
    $databaseName = log_month_v2_database_name($dateValue);

    if (!log_database_exists($databaseName) && !log_create_v2_database($databaseName)) {
        return false;
    }

    $conn = log_open_connection($databaseName);
    if ($conn === false) {
        log_report_sql_error('log_get_write_connection.open');
        return false;
    }

    if (!log_ensure_wide_schema_once($databaseName)) {
        return false;
    }

    // Limitation non bloquante: on garde l'ecriture active meme si SQL refuse la contrainte.
    log_enforce_transaction_log_limit($databaseName);

    return log_set_default_connection($databaseName, $conn);
}

function log_upsert_wide_point($dateValue, $heureValue, $device, $point, $valeur)
{
    global $connLog;

    $pointInt = (int)$point;
    if ($pointInt < 0 || $pointInt > 500) {
        return true;
    }

    $date = trim((string)$dateValue);
    $heure = trim((string)$heureValue);
    if ($date === '' || $heure === '') {
        return false;
    }

    $v2DatabaseName = log_month_v2_database_name($date);
    if (!log_database_exists($v2DatabaseName) && !log_create_v2_database($v2DatabaseName)) {
        log_report_sql_error('log_upsert_wide_point.create_v2_db');
        return false;
    }

    $v2Conn = log_open_connection($v2DatabaseName);
    if ($v2Conn === false) {
        log_report_sql_error('log_upsert_wide_point.connect_v2');
        return false;
    }

    if (!log_ensure_wide_schema_once($v2DatabaseName)) {
        return false;
    }

    $column = '[P' . $pointInt . ']';
    $sql = "
MERGE dbo.LogWide WITH (HOLDLOCK) AS tgt
USING (
    SELECT
        CAST(? AS NVARCHAR(10)) AS DateNV,
        CAST(? AS CHAR(5)) AS Heure,
        CAST(? AS INT) AS Device,
        TRY_CONVERT(FLOAT, ?) AS PointValue
) AS src
ON tgt.DateNV = src.DateNV
AND tgt.Heure = src.Heure
AND tgt.Device = src.Device
WHEN MATCHED THEN
    UPDATE SET
        " . $column . " = src.PointValue
WHEN NOT MATCHED THEN
    INSERT (DateNV, Heure, Device, " . $column . ")
    VALUES (src.DateNV, src.Heure, src.Device, src.PointValue);
";

    $params = [(string)$date, (string)$heure, (int)$device, (string)$valeur];
    $stmt = sqlsrv_query($v2Conn, $sql, $params);
    if ($stmt === false) {
        log_report_sql_error('log_upsert_wide_point.merge');
        return false;
    }

    sqlsrv_free_stmt($stmt);
    return true;
}

function log_get_read_databases($dateValue = null)
{
    $monthDatabase = log_month_v2_database_name($dateValue);
    $databases = [];

    if (log_database_exists($monthDatabase)) {
        $databases[] = $monthDatabase;
    }

    return $databases;
}

function log_get_read_connection($dateValue = null)
{
    $databases = log_get_read_databases($dateValue);
    $preferredDatabase = end($databases);
    if ($preferredDatabase === false) {
        return false;
    }

    $conn = log_open_connection($preferredDatabase);
    if ($conn === false) {
        log_report_sql_error('log_get_read_connection.open');
        return false;
    }

    return log_set_default_connection($preferredDatabase, $conn);
}

function mssqllog($sql)
{
    global $connLog;
    try {
        $stmt = sqlsrv_query($connLog, $sql);
        if ($stmt === false) {
            log_report_sql_error('mssql');
            return false;
        }
        return $stmt;
    } catch (Exception $e) {
        error_log("[EXCEPTION] " . $e->getMessage());
        echo "<div style='color:red;font-weight:bold;'>Erreur SQL. Consultez les logs.</div>";
        return false;
    }

}

function log_is_db_full_error()
{
    $errors = sqlsrv_errors(SQLSRV_ERR_ERRORS);
    if (!is_array($errors)) {
        return false;
    }

    foreach ($errors as $err) {
        $code = (int)($err['code'] ?? 0);
        if ($code === 1105) {
            return true;
        }
    }

    return false;
}

function log_refuse_delete_operation($context = '')
{
    if (!LOG_DELETE_PROTECTION_ENABLED) {
        return true;
    }

    $label = $context !== '' ? $context : 'unknown';
    error_log('[LOG] Suppression interdite dans KaLog::Log (' . $label . ').');
    return false;
}

function log_ensure_database_space()
{
    global $connLog;
    $sizeStmt = sqlsrv_query(
        $connLog,
        "SELECT [name], [size] * 8 / 1024 AS size_mb FROM sys.database_files WHERE type_desc = 'ROWS' ORDER BY file_id"
    );

    if ($sizeStmt === false) {
        log_report_sql_error('log_ensure_database_space.select_sizes');
        return false;
    }

    $files = [];
    $totalMb = 0;
    while ($sizeRow = sqlsrv_fetch_array($sizeStmt, SQLSRV_FETCH_ASSOC)) {
        $fileName = (string)($sizeRow['name'] ?? '');
        $fileSizeMb = (int)ceil((float)($sizeRow['size_mb'] ?? 0));
        if ($fileName !== '') {
            $files[$fileName] = $fileSizeMb;
        }
        $totalMb += $fileSizeMb;
    }
    sqlsrv_free_stmt($sizeStmt);

    $remainingMb = max(0, LOG_LICENSE_LIMIT_MB - $totalMb);
    if ($remainingMb < 64) {
        error_log('[LOG] Extension KaLog impossible: limite SQL Server Express atteinte ou presque (' . $totalMb . ' Mo / ' . LOG_LICENSE_LIMIT_MB . ' Mo).');
        return false;
    }

    $stmt = sqlsrv_query(
        $connLog,
        "SELECT TOP 1 [name], [physical_name] FROM sys.database_files WHERE type_desc = 'ROWS' ORDER BY file_id"
    );

    if ($stmt === false) {
        log_report_sql_error('log_ensure_database_space.select_file');
        return false;
    }

    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    sqlsrv_free_stmt($stmt);
    if (!is_array($row)) {
        error_log('[LOG] Aucun fichier ROWS trouve pour KaLog.');
        return false;
    }

    $mainLogical = (string)$row['name'];
    $mainPhysical = (string)$row['physical_name'];
    $dir = str_replace('/', '\\', dirname($mainPhysical));
    $autoPhysical = $dir . '\\' . LOG_AUTOGROW_FILE_NAME . '.ndf';

    $autoPhysicalSql = str_replace("'", "''", $autoPhysical);
    $mainLogicalSql = str_replace("'", "''", $mainLogical);
    $autoLogicalSql = str_replace("'", "''", LOG_AUTOGROW_FILE_NAME);
    $initialMb = min(max(64, (int)LOG_AUTOGROW_INITIAL_MB), $remainingMb);
    $growthMb = min(max(64, (int)LOG_AUTOGROW_STEP_MB), $remainingMb);
    $autoCurrentMb = (int)($files[LOG_AUTOGROW_FILE_NAME] ?? 0);
    $autoMaxMb = $autoCurrentMb > 0 ? $autoCurrentMb + $remainingMb : $initialMb + max(0, $remainingMb - $initialMb);
    $mainCurrentMb = (int)($files[$mainLogical] ?? 0);
    $mainMaxMb = $mainCurrentMb + $remainingMb;

    $sql = "
IF NOT EXISTS (SELECT 1 FROM sys.database_files WHERE [name] = N'{$autoLogicalSql}')
BEGIN
    ALTER DATABASE [KaLog]
    ADD FILE (
        NAME = N'{$autoLogicalSql}',
        FILENAME = N'{$autoPhysicalSql}',
        SIZE = {$initialMb}MB,
        FILEGROWTH = {$growthMb}MB,
        MAXSIZE = {$autoMaxMb}MB
    );
END

ALTER DATABASE [KaLog]
MODIFY FILE (NAME = N'{$mainLogicalSql}', FILEGROWTH = {$growthMb}MB, MAXSIZE = {$mainMaxMb}MB);

IF EXISTS (SELECT 1 FROM sys.database_files WHERE [name] = N'{$autoLogicalSql}')
BEGIN
    ALTER DATABASE [KaLog]
    MODIFY FILE (NAME = N'{$autoLogicalSql}', FILEGROWTH = {$growthMb}MB, MAXSIZE = {$autoMaxMb}MB);
END
";

    $alterStmt = sqlsrv_query($connLog, $sql);
    if ($alterStmt === false) {
        log_report_sql_error('log_ensure_database_space.alter_db');
        return false;
    }
    sqlsrv_free_stmt($alterStmt);

    return true;
}

date_default_timezone_set('Europe/Paris');

if ($connLog === false && (!defined('LOG_SKIP_AUTO_BOOTSTRAP') || LOG_SKIP_AUTO_BOOTSTRAP !== true)) {
    log_get_write_connection();
}

function LogIn($device, $point, $valeur)
{
    $date  = (string) date('d-m-Y');
    $heure = (string) date('H:i');
    if (log_get_write_connection($date) === false) {
        log_report_sql_error('LogIn.connect');
        return;
    }

    if (!log_upsert_wide_point($date, $heure, $device, $point, $valeur)) {
        log_report_sql_error('LogIn.upsert_wide');
    }
}


?>