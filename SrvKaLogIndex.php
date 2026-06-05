<?php

declare(strict_types=1);

/*
 * SrvKaLogIndex.php
 * Appele par Srv.php toutes les ~60 secondes via file_get_contents.
 *
 * Responsabilites :
 *   1. Index de la base du mois courant (cree une seule fois par base)
 *   2. UPDATE STATISTICS sur la journee courante (une fois par heure)
 *   3. Nettoyage des tables temporaires de migration dans toutes les bases KaLog_*
 *   4. Purge du cache PHP expire (wwwroot/cache/)
 *   5. Nettoyage des vieux fichiers cache systeme (heritage ancien repertoire)
 *   6. CHECKPOINT sur les bases mensuelles + SHRINKFILE tempdb (une fois par 6h)
 */

ini_set('display_errors', '0');
define('LOG_SKIP_AUTO_BOOTSTRAP', true);
require_once __DIR__ . '/BaseLog.php';

set_time_limit(120);
header('Content-Type: text/plain; charset=utf-8');

// ── Fichier d'etat ────────────────────────────────────────────────────────────
// Memorise les horodatages des dernieres executions pour eviter le travail redondant.
$stateFile = __DIR__ . DIRECTORY_SEPARATOR . 'SrvKaLogIndex.state.json';

function srvkl_load_state(string $stateFile): array
{
    if (!is_file($stateFile)) {
        return [];
    }
    $raw = @file_get_contents($stateFile);
    if ($raw === false || trim($raw) === '') {
        return [];
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function srvkl_save_state(string $stateFile, array $state): void
{
    @file_put_contents(
        $stateFile,
        json_encode($state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL
    );
}

// ── Verrou anti-chevauchement ─────────────────────────────────────────────────
// Si une execution precedente tourne encore, on sort immediatement.
$lockFile = __DIR__ . DIRECTORY_SEPARATOR . 'SrvKaLogIndex.lock';
$lockTtl  = 90; // secondes avant de considerer le verrou comme mort

if (is_file($lockFile)) {
    $lockAge = time() - (int)@filemtime($lockFile);
    if ($lockAge < $lockTtl) {
        echo '[SrvKaLogIndex] deja en cours (verrou age=' . $lockAge . 's), sortie.' . PHP_EOL;
        exit(0);
    }
    @unlink($lockFile);
}

@file_put_contents($lockFile, (string)time());

function srvkl_unlock(string $lockFile): void
{
    @unlink($lockFile);
}

// ── Sortie courte pour Srv.php ────────────────────────────────────────────────
$out = [];

function srvkl_log(string $msg): void
{
    global $out;
    $out[] = '[' . date('H:i:s') . '] ' . $msg;
}

// ─────────────────────────────────────────────────────────────────────────────
// Connexion master
// ─────────────────────────────────────────────────────────────────────────────
$masterConn = log_open_connection('master');
if ($masterConn === false) {
    srvkl_log('ERREUR connexion master');
    srvkl_unlock($lockFile);
    echo implode(PHP_EOL, $out) . PHP_EOL;
    exit(1);
}

$now      = time();
$today    = date('d-m-Y');
$todayKey = date('Ymd');
$state    = srvkl_load_state($stateFile);
$stateChanged = false;

// ─────────────────────────────────────────────────────────────────────────────
// 1. Lister toutes les bases mensuelles existantes
// ─────────────────────────────────────────────────────────────────────────────
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

$currentMonthDb = LOG_DATABASE_PREFIX . date('Ym');

// ─────────────────────────────────────────────────────────────────────────────
// 2. Index des bases mensuelles (une fois par base, memorise dans l'etat)
// ─────────────────────────────────────────────────────────────────────────────
foreach ($monthlyDbs as $dbName) {
    $stateKey = 'index_done_' . $dbName;
    if (!empty($state[$stateKey])) {
        continue; // deja fait
    }

    $conn = log_open_connection($dbName);
    if ($conn === false) {
        srvkl_log('  [' . $dbName . '] connexion impossible pour index');
        continue;
    }

    $sql = "
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
    if ($stmt !== false) {
        sqlsrv_free_stmt($stmt);
        $state[$stateKey] = $now;
        $stateChanged = true;
        srvkl_log('[' . $dbName . '] index OK');
    } else {
        log_report_sql_error('SrvKaLogIndex.index.' . $dbName);
        srvkl_log('[' . $dbName . '] index ECHEC');
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// 3. UPDATE STATISTICS sur la journee courante (base du mois en cours)
//    Une fois par heure seulement — met a jour les stats pour les nouvelles
//    lignes inserees depuis le dernier passage, ameliore les plans de requete.
// ─────────────────────────────────────────────────────────────────────────────
$statsKey      = 'stats_date_' . $todayKey;
$statsInterval = 3600; // 1 heure

if (
    in_array($currentMonthDb, $monthlyDbs, true)
    && (empty($state[$statsKey]) || ($now - (int)$state[$statsKey]) >= $statsInterval)
) {
    $conn = log_open_connection($currentMonthDb);
    if ($conn !== false) {
        // UPDATE STATISTICS cible uniquement l'index de recherche par date.
        // Permet a SQL Server d'avoir des estimations precises pour les
        // requetes batch du jour sur IX_Log_Device_Point_DateNV_Id.
        $statStmt = sqlsrv_query(
            $conn,
            'UPDATE STATISTICS dbo.Log (IX_Log_Device_Point_DateNV_Id) WITH FULLSCAN;'
        );

        if ($statStmt !== false) {
            sqlsrv_free_stmt($statStmt);
            $state[$statsKey] = $now;
            $stateChanged = true;
            srvkl_log('[' . $currentMonthDb . '] UPDATE STATISTICS journee ' . $today . ' OK');
        } else {
            log_report_sql_error('SrvKaLogIndex.stats.' . $currentMonthDb);
            srvkl_log('[' . $currentMonthDb . '] UPDATE STATISTICS ECHEC');
        }
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// 4. Nettoyage des tables temporaires de migration dans toutes les bases
//    Rapide : simple requete systeme + DROP si trouve.
// ─────────────────────────────────────────────────────────────────────────────
$tempTablePatterns = "'LogMonthlyMigration'";
$tempLikePatterns  = [
    "t.name LIKE N'Tmp[_]%'",
    "t.name LIKE N'Temp[_]%'",
    "t.name LIKE N'MigrationTmp[_]%'",
    "t.name LIKE N'LogMigration[_]%'",
    "t.name LIKE N'Staging[_]%'",
    "t.name LIKE N'%[_]Tmp'",
    "t.name LIKE N'%[_]Temp'",
];
$tempWhereClause = "t.name = " . $tempTablePatterns . " OR " . implode(' OR ', $tempLikePatterns);

foreach ($monthlyDbs as $dbName) {
    $conn = log_open_connection($dbName);
    if ($conn === false) {
        continue;
    }

    $listStmt = sqlsrv_query(
        $conn,
        "SELECT s.name AS sn, t.name AS tn
         FROM sys.tables t
         INNER JOIN sys.schemas s ON s.schema_id = t.schema_id
         WHERE t.is_ms_shipped = 0 AND (" . $tempWhereClause . ");"
    );

    if ($listStmt === false) {
        continue;
    }

    $toDropList = [];
    while ($row = sqlsrv_fetch_array($listStmt, SQLSRV_FETCH_ASSOC)) {
        $toDropList[] = [$row['sn'], $row['tn']];
    }
    sqlsrv_free_stmt($listStmt);

    foreach ($toDropList as [$schema, $table]) {
        $dropStmt = sqlsrv_query(
            $conn,
            'DROP TABLE ' . log_quote_identifier($schema) . '.' . log_quote_identifier($table) . ';'
        );
        if ($dropStmt !== false) {
            sqlsrv_free_stmt($dropStmt);
            srvkl_log('[' . $dbName . '] DROP TABLE ' . $schema . '.' . $table . ' OK');
        } else {
            log_report_sql_error('SrvKaLogIndex.drop.' . $dbName . '.' . $table);
        }
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// 5. Purge du cache PHP expire dans wwwroot/cache/
//    Rapide, fait a chaque appel.
// ─────────────────────────────────────────────────────────────────────────────
$cacheDir     = __DIR__ . DIRECTORY_SEPARATOR . 'cache';
$cacheMaxAge  = 7 * 24 * 3600;
$cacheDeleted = 0;

if (is_dir($cacheDir)) {
    foreach (glob($cacheDir . DIRECTORY_SEPARATOR . 'apilogs_*.json') ?: [] as $file) {
        if (($now - (int)@filemtime($file)) > $cacheMaxAge) {
            if (@unlink($file)) {
                $cacheDeleted++;
            }
        }
    }
}

// Nettoyage de l'ancien repertoire temp systeme (heritage avant refactoring)
$sysTemp = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR);
if ($sysTemp !== '' && is_dir($sysTemp)) {
    foreach (glob($sysTemp . DIRECTORY_SEPARATOR . 'apilogs_*.json') ?: [] as $file) {
        @unlink($file);
    }
}

if ($cacheDeleted > 0) {
    srvkl_log('Cache: ' . $cacheDeleted . ' fichier(s) expire(s) supprimes');
}

// ─────────────────────────────────────────────────────────────────────────────
// 6. CHECKPOINT + SHRINKFILE tempdb  (une fois toutes les 6 heures)
// ─────────────────────────────────────────────────────────────────────────────
$shrinkKey      = 'tempdb_shrink';
$shrinkInterval = 6 * 3600;

if (empty($state[$shrinkKey]) || ($now - (int)$state[$shrinkKey]) >= $shrinkInterval) {
    // CHECKPOINT sur toutes les bases mensuelles pour liberer les journaux
    foreach ($monthlyDbs as $dbName) {
        $conn = log_open_connection($dbName);
        if ($conn === false) {
            continue;
        }
        $stmt = sqlsrv_query($conn, 'CHECKPOINT;');
        if ($stmt !== false) {
            sqlsrv_free_stmt($stmt);
        }
    }

    // Taille tempdb avant shrink
    $tempdbConn = log_open_connection('tempdb');
    if ($tempdbConn !== false) {
        $sizesBefore = [];
        $sizesStmt = sqlsrv_query(
            $tempdbConn,
            "SELECT [name], [size] * 8 / 1024 AS size_mb FROM sys.database_files ORDER BY file_id"
        );
        if ($sizesStmt !== false) {
            while ($row = sqlsrv_fetch_array($sizesStmt, SQLSRV_FETCH_ASSOC)) {
                $sizesBefore[(string)$row['name']] = (int)$row['size_mb'];
            }
            sqlsrv_free_stmt($sizesStmt);
        }

        // SHRINKFILE sur chaque fichier tempdb
        foreach (array_keys($sizesBefore) as $logicalName) {
            $logicalNameSql = str_replace("'", "''", $logicalName);
            $shrinkStmt = sqlsrv_query(
                $tempdbConn,
                "DBCC SHRINKFILE (N'" . $logicalNameSql . "') WITH NO_INFOMSGS;"
            );
            if ($shrinkStmt !== false) {
                sqlsrv_free_stmt($shrinkStmt);
            } else {
                log_report_sql_error('SrvKaLogIndex.shrink.' . $logicalName);
            }
        }

        // Taille apres shrink
        $sizesAfterStmt = sqlsrv_query(
            $tempdbConn,
            "SELECT [name], [size] * 8 / 1024 AS size_mb FROM sys.database_files ORDER BY file_id"
        );
        if ($sizesAfterStmt !== false) {
            $parts = [];
            while ($row = sqlsrv_fetch_array($sizesAfterStmt, SQLSRV_FETCH_ASSOC)) {
                $name   = (string)$row['name'];
                $before = $sizesBefore[$name] ?? 0;
                $after  = (int)$row['size_mb'];
                $parts[] = $name . ': ' . $before . '->' . $after . ' Mo';
            }
            sqlsrv_free_stmt($sizesAfterStmt);
            srvkl_log('tempdb SHRINK: ' . implode(', ', $parts));
        }
    }

    $state[$shrinkKey] = $now;
    $stateChanged = true;
}

// ─────────────────────────────────────────────────────────────────────────────
// Sauvegarde de l'etat + verrou
// ─────────────────────────────────────────────────────────────────────────────
if ($stateChanged) {
    srvkl_save_state($stateFile, $state);
}

srvkl_unlock($lockFile);

// Sortie compacte pour Srv.php
if (!empty($out)) {
    echo implode(PHP_EOL, $out) . PHP_EOL;
} else {
    echo '[SrvKaLogIndex] OK ' . date('H:i:s') . PHP_EOL;
}
