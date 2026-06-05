<?php

declare(strict_types=1);

define('LOG_SKIP_AUTO_BOOTSTRAP', true);
require_once __DIR__ . '/BaseLog.php';

if (PHP_SAPI !== 'cli') {
    header('Content-Type: text/plain; charset=utf-8');
}

set_time_limit(300);

function maint_output(string $message): void
{
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
    if (PHP_SAPI === 'cli' && defined('STDOUT')) {
        fwrite(STDOUT, $line);
    } else {
        echo $line;
        flush();
    }
}

maint_output('=== Maintenance tempdb + cache ===');

// ── 1. Purge des fichiers cache PHP expires (> 7 jours) ──────────────────────
$cacheDir = __DIR__ . DIRECTORY_SEPARATOR . 'cache';
$cacheDeleted = 0;
$cacheSizeFreed = 0;

if (is_dir($cacheDir)) {
    $nowTs = time();
    $maxAge = 7 * 24 * 3600;
    foreach (glob($cacheDir . DIRECTORY_SEPARATOR . 'apilogs_*.json') ?: [] as $file) {
        $age = $nowTs - (int)@filemtime($file);
        if ($age > $maxAge) {
            $size = (int)@filesize($file);
            if (@unlink($file)) {
                $cacheDeleted++;
                $cacheSizeFreed += $size;
            }
        }
    }
}

maint_output('Cache PHP: ' . $cacheDeleted . ' fichier(s) supprimes, ' . round($cacheSizeFreed / 1024, 1) . ' Ko liberes.');

// ── 2. Nettoyage des vieux fichiers cache dans le dossier temp systeme ────────
// (fichiers apilogs_ laisses par l'ancienne version dans sys_get_temp_dir)
$sysTemp = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR);
$sysCacheDeleted = 0;
$sysCacheSizeFreed = 0;

if ($sysTemp !== '' && is_dir($sysTemp)) {
    foreach (glob($sysTemp . DIRECTORY_SEPARATOR . 'apilogs_*.json') ?: [] as $file) {
        $size = (int)@filesize($file);
        if (@unlink($file)) {
            $sysCacheDeleted++;
            $sysCacheSizeFreed += $size;
        }
    }
}

maint_output('Cache systeme (' . $sysTemp . '): ' . $sysCacheDeleted . ' fichier(s) supprimes, ' . round($sysCacheSizeFreed / 1024, 1) . ' Ko liberes.');

// ── 3. CHECKPOINT sur les bases mensuelles ────────────────────────────────────
// Libere le journal des transactions avant le shrink tempdb.
$masterConn = log_open_connection('master');
if ($masterConn === false) {
    maint_output('ERREUR: connexion master impossible, arret.');
    exit(1);
}

$dbsStmt = sqlsrv_query(
    $masterConn,
    "SELECT [name] FROM sys.databases WHERE [name] LIKE ? ORDER BY [name]",
    [LOG_DATABASE_PREFIX . '%']
);

$monthlyDbs = [];
if ($dbsStmt !== false) {
    while ($row = sqlsrv_fetch_array($dbsStmt, SQLSRV_FETCH_ASSOC)) {
        $name = trim((string)($row['name'] ?? ''));
        if ($name !== '') {
            $monthlyDbs[] = $name;
        }
    }
    sqlsrv_free_stmt($dbsStmt);
}

maint_output('Bases mensuelles trouvees: ' . implode(', ', $monthlyDbs));

foreach ($monthlyDbs as $dbName) {
    $conn = log_open_connection($dbName);
    if ($conn === false) {
        maint_output('  [' . $dbName . '] connexion impossible, ignore.');
        continue;
    }

    $stmt = sqlsrv_query($conn, 'CHECKPOINT;');
    if ($stmt !== false) {
        sqlsrv_free_stmt($stmt);
        maint_output('  [' . $dbName . '] CHECKPOINT OK');
    } else {
        log_report_sql_error('maint.checkpoint.' . $dbName);
        maint_output('  [' . $dbName . '] CHECKPOINT echec');
    }
}

// ── 4. Taille actuelle tempdb avant shrink ────────────────────────────────────
$tempdbConn = log_open_connection('tempdb');
if ($tempdbConn === false) {
    maint_output('ERREUR: connexion tempdb impossible.');
    exit(1);
}

$sizeStmt = sqlsrv_query(
    $tempdbConn,
    "SELECT [name], [size] * 8 / 1024 AS size_mb, physical_name FROM sys.database_files ORDER BY file_id"
);

$tempdbFiles = [];
if ($sizeStmt !== false) {
    while ($row = sqlsrv_fetch_array($sizeStmt, SQLSRV_FETCH_ASSOC)) {
        $tempdbFiles[] = $row;
    }
    sqlsrv_free_stmt($sizeStmt);
}

foreach ($tempdbFiles as $file) {
    maint_output('tempdb fichier [' . $file['name'] . ']: ' . $file['size_mb'] . ' Mo (' . $file['physical_name'] . ')');
}

// ── 5. SHRINKFILE sur les fichiers tempdb ─────────────────────────────────────
// SHRINKFILE tente de reduire le fichier physique au minimum utilisable.
// Sans parametre de taille cible : SQL Server determine le minimum.
$shrinkCount = 0;
foreach ($tempdbFiles as $file) {
    $logicalName = (string)$file['name'];
    $logicalNameSql = str_replace("'", "''", $logicalName);

    $shrinkStmt = sqlsrv_query(
        $tempdbConn,
        "DBCC SHRINKFILE (N'" . $logicalNameSql . "') WITH NO_INFOMSGS;"
    );

    if ($shrinkStmt !== false) {
        sqlsrv_free_stmt($shrinkStmt);
        $shrinkCount++;
        maint_output('  [' . $logicalName . '] SHRINKFILE execute');
    } else {
        log_report_sql_error('maint.shrinkfile.' . $logicalName);
        maint_output('  [' . $logicalName . '] SHRINKFILE echec');
    }
}

// ── 6. Taille tempdb apres shrink ─────────────────────────────────────────────
$sizeAfterStmt = sqlsrv_query(
    $tempdbConn,
    "SELECT [name], [size] * 8 / 1024 AS size_mb FROM sys.database_files ORDER BY file_id"
);

if ($sizeAfterStmt !== false) {
    maint_output('Taille tempdb apres shrink:');
    while ($row = sqlsrv_fetch_array($sizeAfterStmt, SQLSRV_FETCH_ASSOC)) {
        maint_output('  [' . $row['name'] . ']: ' . $row['size_mb'] . ' Mo');
    }
    sqlsrv_free_stmt($sizeAfterStmt);
}

maint_output('=== Maintenance terminee ===');
