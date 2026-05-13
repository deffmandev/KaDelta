<?php

declare(strict_types=1);
ob_start(); // capture tout output parasite (warnings BaseLog, etc.)

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Private-Network: true');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/BaseLog.php';

sqlsrv_configure('WarningsReturnAsErrors', 0);

function apiLogsRespond(int $statusCode, array $payload): void
{
	if (ob_get_level()) { ob_clean(); } // supprime tout output parasite avant JSON
	http_response_code($statusCode);
	echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK);
	exit;
}

function apiLogsNormalizeDate(string $date): ?string
{
	$date = trim($date);
	if ($date === '') {
		return null;
	}

	$formats = ['d-m-Y', 'Y-m-d', 'd/m/Y'];
	foreach ($formats as $format) {
		$parsed = DateTime::createFromFormat($format, $date);
		if ($parsed instanceof DateTime && $parsed->format($format) === $date) {
			return $parsed->format('d-m-Y');
		}
	}

	return null;
}

// ── Mode batch ──────────────────────────────────────────────────────────────
// GET ?batch=1&device=X&points=-1,1,2,5&date=dd-mm-yyyy
// Retourne un objet JSON : { "-1": [...], "1": [...], "2": [...], "5": [...] }
// point=-1 → Heure (index X) via Point=1 ; autres points → Valeur
if (isset($_GET['batch']) && $_GET['batch'] === '1') {
	$bDevice    = isset($_GET['device'])    ? trim((string) $_GET['device'])    : '';
	$bPointsRaw = isset($_GET['points'])    ? trim((string) $_GET['points'])    : '';
	$bDate      = isset($_GET['date'])      ? apiLogsNormalizeDate((string) $_GET['date']) : null;
	$bStep      = isset($_GET['step'])      ? max(1, (int) $_GET['step'])       : 1;
	$bTop       = isset($_GET['top'])       ? max(1, (int) $_GET['top'])        : 0;
	$bEndpoints = isset($_GET['endpoints']) && $_GET['endpoints'] === '1';
	$bHeure     = isset($_GET['heure'])     ? preg_replace('/[^0-9:]/', '', (string) $_GET['heure']) : '';

	if ($bDevice === '' || $bPointsRaw === '' || $bDate === null) {
		apiLogsRespond(400, [
			'success' => false,
			'error' => 'Parametres requis (batch): device, points, date',
		]);
	}

	global $connLog;
	if (!$connLog) {
		apiLogsRespond(500, ['success' => false, 'error' => 'Connexion base logs indisponible']);
	}

	$bDeviceInt = (int) $bDevice;
	$rawPts     = array_unique(array_map('intval', array_map('trim', explode(',', $bPointsRaw))));
	$needsIndex = in_array(-1, $rawPts, true);
	$dataPts    = array_values(array_filter($rawPts, static fn(int $p): bool => $p !== -1));

	$bResult = [];
	$allPts  = $dataPts;
	if ($needsIndex) {
		$bResult['-1'] = [];
		if (!in_array(1, $allPts, true)) {
			array_unshift($allPts, 1);
		}
	}
	foreach ($dataPts as $pt) {
		$bResult[(string) $pt] = [];
	}

	// ── Cache fichier ────────────────────────────────────────────────────────
	// Jours passes : cache permanent (donnees immuables)
	// Aujourd'hui  : cache 90 secondes (donnees live)
	$sortedPts = $allPts; sort($sortedPts);
	$cacheKey  = 'apilogs_' . md5($bDeviceInt . '|' . implode(',', $sortedPts) . '|' . $bDate . '|s' . $bStep . 't' . $bTop . ($bEndpoints ? 'e1' : '') . ($bHeure !== '' ? 'h' . $bHeure : ''));
	$cacheFile = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $cacheKey . '.json';
	$isToday   = ($bDate === date('d-m-Y'));
	$cacheTtl  = $isToday ? 90 : PHP_INT_MAX;

	if (file_exists($cacheFile)) {
		$cacheAge = time() - filemtime($cacheFile);
		if ($cacheAge < $cacheTtl) {
			$cached = @json_decode((string) file_get_contents($cacheFile), true);
			if (is_array($cached)) {
				apiLogsRespond(200, $cached);
			}
		} else {
			@unlink($cacheFile);
		}
	}

	set_time_limit(0);

	if (!empty($allPts)) {
		$ptLiterals = implode(', ', array_map('intval', $allPts));

		if ($bEndpoints) {
			// ─ ENDPOINTS : 1er + dernier par point ────────────────────────
			// CROSS APPLY first+last => 2 lignes PHP/point => ~20ms total
			$ptUnion = implode(' UNION ALL ', array_map(fn(int $p) => 'SELECT ' . $p, $allPts));
			$sql = 'SELECT src.Pt,'
				. ' CONVERT(nvarchar(50), t.Valeur) AS Valeur,'
				. ' CONVERT(nvarchar(10), t.Heure) AS Heure'
				. ' FROM (' . $ptUnion . ') AS src(Pt)'
				. ' CROSS APPLY ('
				. '   SELECT TOP 1 Valeur, Heure, 0 AS _ord'
				. '   FROM Log WITH (NOLOCK)'
				. '   WHERE Device = ' . $bDeviceInt
				. '   AND Point = src.Pt AND DateNV = ? ORDER BY Id ASC'
				. '   UNION ALL'
				. '   SELECT TOP 1 Valeur, Heure, 1'
				. '   FROM Log WITH (NOLOCK)'
				. '   WHERE Device = ' . $bDeviceInt
				. '   AND Point = src.Pt AND DateNV = ? ORDER BY Id DESC'
				. ' ) AS t ORDER BY src.Pt, t._ord';
			$params = [$bDate, $bDate];

		} elseif ($bHeure !== '') {
			// ─ SINGLE TIME POINT : 1 point par serie au plus proche de $bHeure ─
			// CROSS APPLY TOP 1 WHERE CONVERT(Heure) >= ? ORDER BY Id
			// Heure est de type text => CONVERT obligatoire pour la comparaison
			// seek sur (Device, Point, DateNV) puis scan jusqu'a Heure >= cible
			// pour 0h30 : ~15 lignes scannees ; pour 12h00 : ~360 => rapide
			// PHP recoit 1 ligne par point => ~50ms total
			$ptUnion = implode(' UNION ALL ', array_map(fn(int $p) => 'SELECT ' . $p, $allPts));
			$sql = 'SELECT src.Pt,'
				. ' CONVERT(nvarchar(50), t.Valeur) AS Valeur,'
				. ' CONVERT(nvarchar(10), t.Heure) AS Heure'
				. ' FROM (' . $ptUnion . ') AS src(Pt)'
				. ' CROSS APPLY ('
				. '   SELECT TOP 1 Valeur, Heure'
				. '   FROM Log WITH (NOLOCK)'
				. '   WHERE Device = ' . $bDeviceInt
				. '   AND Point = src.Pt AND DateNV = ?'
				. '   AND CONVERT(nvarchar(10), Heure) >= ?'
				. '   ORDER BY Id ASC'
				. ' ) AS t'
				. ' ORDER BY src.Pt';
			$params = [$bDate, $bHeure];

		} elseif ($bStep > 1) {
			// ─ STEP : ROW_NUMBER SQL-side ─────────────────────────────────
			// PHP recoit seulement 720/$bStep lignes/point (pas 720)
			// Bottleneck mesure : ~10ms/appel ODBC => 720 = 7s, 3 = 30ms
			// step=240 => ~3 pts/courbe (ecart ~8h) => ~30ms
			// step=30  => ~24 pts/courbe (ecart ~1h) => ~240ms
			// % inligne comme int literal => aucun conflit driver sqlsrv
			$sql = 'SELECT Pt, Valeur, Heure FROM ('
				. ' SELECT CAST(Point AS int) AS Pt,'
				. '  CONVERT(nvarchar(50), Valeur) AS Valeur,'
				. '  CONVERT(nvarchar(10), Heure) AS Heure,'
				. '  ROW_NUMBER() OVER(PARTITION BY Point ORDER BY Id) AS _rn'
				. ' FROM Log WITH (NOLOCK)'
				. ' WHERE Device = ' . $bDeviceInt
				. ' AND Point IN (' . $ptLiterals . ')'
				. ' AND DateNV = ?'
				. ') AS _x WHERE (_rn - 1) % ' . $bStep . ' = 0'
				. ' ORDER BY Pt, _rn ASC';
			$params = [$bDate];

		} elseif ($bTop > 0) {
			// ─ CROSS APPLY TOP N (debut de journee) ──────────────────────
			$ptUnion = implode(' UNION ALL ', array_map(fn(int $p) => 'SELECT ' . $p, $allPts));
			$sql = 'SELECT src.Pt,'
				. ' CONVERT(nvarchar(50), t.Valeur) AS Valeur,'
				. ' CONVERT(nvarchar(10), t.Heure) AS Heure'
				. ' FROM (' . $ptUnion . ') AS src(Pt)'
				. ' CROSS APPLY ('
				. '   SELECT TOP ' . $bTop . ' Valeur, Heure'
				. '   FROM Log WITH (NOLOCK)'
				. '   WHERE Device = ' . $bDeviceInt
				. '   AND Point = src.Pt AND DateNV = ? ORDER BY Id'
				. ' ) AS t'
				. ' ORDER BY src.Pt';
			$params = [$bDate];

		} else {
			// ─ Chargement complet : UNION ALL ────────────────────────────
			// Branche 1 : lignes migrees (DateNV indexe)
			// Branche 2 : lignes post-migration (DateNV IS NULL)
			$sql = 'SELECT Pt, Valeur, Heure FROM ('
				. '  SELECT CAST(Point AS int) AS Pt,'
				. '   CONVERT(nvarchar(50), Valeur) AS Valeur,'
				. '   CONVERT(nvarchar(10), Heure) AS Heure,'
				. '   Id'
				. '  FROM Log WITH (NOLOCK)'
				. '  WHERE Device = ' . $bDeviceInt
				. '  AND Point IN (' . $ptLiterals . ')'
				. '  AND DateNV = ?'
				. '  UNION ALL'
				. '  SELECT CAST(Point AS int),'
				. '   CONVERT(nvarchar(50), Valeur),'
				. '   CONVERT(nvarchar(10), Heure),'
				. '   Id'
				. '  FROM Log WITH (NOLOCK)'
				. '  WHERE Device = ' . $bDeviceInt
				. '  AND Point IN (' . $ptLiterals . ')'
				. '  AND DateNV IS NULL'
				. '  AND CONVERT(nvarchar(20), Date) = ?'
				. ') AS _all ORDER BY Pt, Id ASC';
			$params = [$bDate, $bDate];
		}

		$stmt = sqlsrv_query($connLog, $sql, $params);
		if ($stmt === false) {
			$sqlErrs = sqlsrv_errors() ?: [];
			apiLogsRespond(500, [
				'success' => false,
				'error'   => 'Erreur SQL batch',
				'details' => implode(' | ', array_column($sqlErrs, 'message')),
			]);
		}
		while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
			$pt = (int) $row['Pt'];
			if ($pt === 1 && $needsIndex) {
				$bResult['-1'][] = $row['Heure'];
			}
			if (isset($bResult[(string) $pt])) {
				$bResult[(string) $pt][] = $row['Valeur'];
			}
		}
		sqlsrv_free_stmt($stmt);
	}

	// Stocker dans le cache
	@file_put_contents($cacheFile, json_encode($bResult, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK));

	apiLogsRespond(200, $bResult);
}

// ── Mode point unique (compatibilité) ───────────────────────────────────────
$device = isset($_GET['device']) ? trim((string) $_GET['device']) : '';
$point = isset($_GET['point']) ? trim((string) $_GET['point']) : '';
$date = isset($_GET['date']) ? apiLogsNormalizeDate((string) $_GET['date']) : null;


if ($device === '' || $point === '' || $date === null) 
{
	apiLogsRespond(400, [
		'success' => false,
		'error' => 'Parametres requis: device, point, date',
		'expected_date_formats' => ['d-m-Y', 'Y-m-d', 'd/m/Y'],
	]);
}

global $connLog;

if (!$connLog) {
	apiLogsRespond(500, [
		'success' => false,
		'error' => 'Connexion base logs indisponible',
	]);
}

$deviceInt = (int) $device;
$pointInt  = (int) $point;

if ($point === "-1") {
	$sql    = 'SELECT CAST(Heure AS nvarchar(10)) AS Valeur FROM Log WITH (NOLOCK) WHERE Device = ? AND Point = ? AND Date = ? ORDER BY Id ASC';
	$params = [(string) $deviceInt, '1', $date];
} else {
	$sql    = 'SELECT CAST(Valeur AS nvarchar(50)) AS Valeur FROM Log WITH (NOLOCK) WHERE Device = ? AND Point = ? AND Date = ? ORDER BY Id ASC';
	$params = [(string) $deviceInt, (string) $pointInt, $date];
}

$stmt = sqlsrv_query($connLog, $sql, $params);

if ($stmt === false) {
	apiLogsRespond(500, [
		'success' => false,
		'error' => 'Erreur SQL',
		'details' => sqlsrv_errors() ?: [],
	]);
}

$values = [];
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_NUMERIC)) {
	$values[] = $row[0];
}

sqlsrv_free_stmt($stmt);

apiLogsRespond(200, $values);
