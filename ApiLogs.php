<?php

declare(strict_types=1);

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
	$bDevice = isset($_GET['device']) ? trim((string) $_GET['device']) : '';
	$bPointsRaw = isset($_GET['points']) ? trim((string) $_GET['points']) : '';
	$bDate = isset($_GET['date']) ? apiLogsNormalizeDate((string) $_GET['date']) : null;

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
	$rawPts = array_unique(array_map('intval', array_map('trim', explode(',', $bPointsRaw))));
	$needsIndex = in_array(-1, $rawPts, true);
	$dataPts = array_values(array_filter($rawPts, static fn(int $p): bool => $p !== -1));

	$bResult = [];

	// Une seule requête SQL : Point=1 (Heure) + tous les points de données
	$allPts = $dataPts;
	if ($needsIndex) {
		$bResult['-1'] = [];
		if (!in_array(1, $allPts, true)) {
			array_unshift($allPts, 1);
		}
	}
	foreach ($dataPts as $pt) {
		$bResult[(string) $pt] = [];
	}

	if (!empty($allPts)) {
		$placeholders = implode(',', array_fill(0, count($allPts), '?'));
		$sql  = 'SELECT CAST(Point AS int) AS Pt, CAST(Valeur AS nvarchar(50)) AS Valeur, CAST(Heure AS nvarchar(10)) AS Heure FROM Log WITH (NOLOCK) WHERE CAST(Device AS int) = ? AND CAST(Point AS int) IN (' . $placeholders . ') AND CAST(Date AS nvarchar(20)) = ? ORDER BY CAST(Point AS int), Id ASC';
		$params = array_merge([$bDeviceInt], $allPts, [$bDate]);
		$stmt = sqlsrv_query($connLog, $sql, $params);
		if ($stmt !== false) {
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
	}

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
	$sql    = 'SELECT CAST(Heure AS nvarchar(10)) AS Valeur FROM Log WITH (NOLOCK) WHERE CAST(Device AS int) = ? AND CAST(Point AS int) = 1 AND CAST(Date AS nvarchar(20)) = ? ORDER BY Id ASC';
	$params = [$deviceInt, $date];
} else {
	$sql    = 'SELECT CAST(Valeur AS nvarchar(50)) AS Valeur FROM Log WITH (NOLOCK) WHERE CAST(Device AS int) = ? AND CAST(Point AS int) = ? AND CAST(Date AS nvarchar(20)) = ? ORDER BY Id ASC';
	$params = [$deviceInt, $pointInt, $date];
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
