<?php

declare(strict_types=1);
ob_start(); // capture tout output parasite (warnings BaseLog, etc.)

define('LOG_SKIP_AUTO_BOOTSTRAP', true);

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Private-Network: true');
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/BaseLog.php';

sqlsrv_configure('WarningsReturnAsErrors', 0);

error_reporting(E_ALL);

set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
	if (!(error_reporting() & $severity)) {
		return false;
	}

	throw new ErrorException($message, 0, $severity, $file, $line);
});

register_shutdown_function(static function (): void {
	$error = error_get_last();
	if (!is_array($error)) {
		return;
	}

	$fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR];
	if (!in_array((int)($error['type'] ?? 0), $fatalTypes, true)) {
		return;
	}

	if (!headers_sent()) {
		http_response_code(500);
		header('Content-Type: application/json; charset=utf-8');
	}

	if (ob_get_level()) {
		ob_clean();
	}

	echo apiLogsJsonEncode([
		'success' => false,
		'error' => 'Erreur fatale lors du traitement des logs',
	]);
});

function apiLogsJsonEncode(array $payload): string
{
	$json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK);
	if (is_string($json)) {
		return $json;
	}

	$fallback = json_encode([
		'success' => false,
		'error' => 'Impossible d\'encoder la réponse JSON',
	], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK);

	return is_string($fallback) ? $fallback : '{"success":false,"error":"Impossible d\'encoder la réponse JSON"}';
}

function apiLogsSqlErrorDetails(): string
{
	if (function_exists('log_get_last_sql_error_summary')) {
		$summary = trim((string)log_get_last_sql_error_summary());
		if ($summary !== '') {
			return $summary;
		}
	}

	$errors = sqlsrv_errors() ?: [];
	$messages = [];
	foreach ($errors as $err) {
		$messages[] = (string)($err['message'] ?? 'Erreur SQL inconnue');
	}

	return implode(' | ', $messages);
}

function apiLogsRespond(int $statusCode, array $payload): void
{
	if (ob_get_level()) { ob_clean(); } // supprime tout output parasite avant JSON
	http_response_code($statusCode);
	echo apiLogsJsonEncode($payload);
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

function apiLogsQuoteIdentifier(string $value): string
{
	return '[' . str_replace(']', ']]', $value) . ']';
}

function apiLogsBuildWideSelectColumns(array $pointNumbers, bool $includeHeure = true): string
{
	$columns = [];
	if ($includeHeure) {
		$columns[] = 'Heure';
	}

	foreach (array_values(array_unique($pointNumbers)) as $pointNumber) {
		$pointInt = (int) $pointNumber;
		if ($pointInt < 0 || $pointInt > 500) {
			continue;
		}

		$columns[] = '[P' . $pointInt . ']';
	}

	return implode(', ', $columns);
}

function apiLogsHeureToSeconds(string $heure): ?int
{
	$value = trim($heure);
	if ($value === '') {
		return null;
	}

	if (!preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', $value, $matches)) {
		return null;
	}

	$h = (int) $matches[1];
	$m = (int) $matches[2];
	$s = isset($matches[3]) ? (int) $matches[3] : 0;

	if ($h < 0 || $h > 23 || $m < 0 || $m > 59 || $s < 0 || $s > 59) {
		return null;
	}

	return ($h * 3600) + ($m * 60) + $s;
}

function apiLogsSecondsToHeureLabel(int $seconds): string
{
	$h = intdiv($seconds, 3600);
	$m = intdiv($seconds % 3600, 60);

	return str_pad((string) $h, 2, '0', STR_PAD_LEFT) . ':' . str_pad((string) $m, 2, '0', STR_PAD_LEFT);
}

try {

// ── Mode batch ──────────────────────────────────────────────────────────────
// GET ?batch=1&device=X&points=-1,1,2,5&date=dd-mm-yyyy
// Retourne un objet JSON : { "-1": [...], "1": [...], "2": [...], "5": [...] }
// point=-1 → Heure (index X) ; autres points → Valeur (ou null si manquante)
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

	$connLog = log_get_read_connection($bDate);
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
	}
	foreach ($dataPts as $pt) {
		$bResult[(string) $pt] = [];
	}

	set_time_limit(0);

	if (count($allPts) === 0 && !$needsIndex) {
		apiLogsRespond(500, [
			'success' => false,
			'error' => 'Aucun point valide a interroger dans LogWide',
		]);
	}

	$selectColumns = apiLogsBuildWideSelectColumns($allPts, true);
	$sql = "
SELECT " . $selectColumns . "
FROM dbo.LogWide WITH (NOLOCK)
WHERE Device = ?
	AND DateNV = ?
	AND Heure IS NOT NULL
	AND LTRIM(RTRIM(CONVERT(NVARCHAR(16), Heure))) <> ''
ORDER BY TRY_CONVERT(time(0), Heure) ASC, Heure ASC;
";
	$stmt = sqlsrv_query($connLog, $sql, [$bDeviceInt, $bDate]);
	if ($stmt === false) {
		apiLogsRespond(500, [
			'success' => false,
			'error' => 'Erreur SQL batch',
			'details' => apiLogsSqlErrorDetails(),
		]);
	}

	$rows = [];
	while ($raw = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
		$heureRaw = trim((string)($raw['Heure'] ?? ''));
		$heureSeconds = apiLogsHeureToSeconds($heureRaw);
		if ($heureRaw === '' || $heureSeconds === null) {
			continue;
		}

		$row = [
			'Heure' => apiLogsSecondsToHeureLabel($heureSeconds),
			'_HeureSeconds' => $heureSeconds,
		];
		foreach ($allPts as $pt) {
			$key = 'P' . (int)$pt;
			if (array_key_exists($key, $raw)) {
				$row[$key] = $raw[$key] !== null ? (string)$raw[$key] : null;
			}
		}
		$rows[] = $row;
	}
	sqlsrv_free_stmt($stmt);

	usort($rows, static function (array $a, array $b): int {
		return ((int)($a['_HeureSeconds'] ?? 0)) <=> ((int)($b['_HeureSeconds'] ?? 0));
	});

	$rowSource = $rows;

	$appendRowToResult = static function (array $row) use (&$bResult, $dataPts, $needsIndex): void {
		if ($needsIndex) {
			$bResult['-1'][] = (string)($row['Heure'] ?? '');
		}

		foreach ($dataPts as $pt) {
			$key = 'P' . $pt;
			$bResult[(string)$pt][] = array_key_exists($key, $row) ? $row[$key] : null;
		}
	};

	if ($bEndpoints) {
		if (count($rowSource) > 0) {
			$appendRowToResult($rowSource[0]);
			if (count($rowSource) > 1) {
				$appendRowToResult($rowSource[count($rowSource) - 1]);
			}
		}
	} elseif ($bHeure !== '') {
		$targetSeconds = apiLogsHeureToSeconds($bHeure);
		if ($targetSeconds === null) {
			apiLogsRespond(400, [
				'success' => false,
				'error' => 'Parametre heure invalide (attendu HH:MM)',
			]);
		}

		$matchedRow = null;
		foreach ($rowSource as $row) {
			$rowSeconds = (int)($row['_HeureSeconds'] ?? -1);
			if ($rowSeconds >= $targetSeconds) {
				$matchedRow = $row;
				break;
			}
		}

		if (is_array($matchedRow)) {
			$appendRowToResult($matchedRow);
		}
	} elseif ($bTop > 0) {
		$limit = min($bTop, count($rowSource));
		for ($rowIndex = 0; $rowIndex < $limit; $rowIndex++) {
			$appendRowToResult($rowSource[$rowIndex]);
		}
	} else {
		$rowIndex = 0;
		foreach ($rowSource as $row) {
			if ($bStep > 1 && ($rowIndex % $bStep) !== 0) {
				$rowIndex++;
				continue;
			}

			$appendRowToResult($row);

			$rowIndex++;
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

$connLog = log_get_read_connection($date);

if (!$connLog) {
	apiLogsRespond(500, [
		'success' => false,
		'error' => 'Connexion base logs indisponible',
	]);
}

$deviceInt = (int) $device;
$pointInt  = (int) $point;

if ($point !== '-1' && ($pointInt < 0 || $pointInt > 500)) {
	apiLogsRespond(400, [
		'success' => false,
		'error' => 'Point hors plage supportee pour V2',
	]);
}

if ($point === "-1") {
	$sql    = "
SELECT Heure AS Valeur
FROM dbo.LogWide WITH (NOLOCK)
WHERE Device = ?
	AND DateNV = ?
	AND Heure IS NOT NULL
	AND LTRIM(RTRIM(CONVERT(NVARCHAR(16), Heure))) <> ''
ORDER BY TRY_CONVERT(time(0), Heure) ASC, Heure ASC;
";
	$params = [$deviceInt, $date];
} else {
	$pointColumn = apiLogsQuoteIdentifier('P' . $pointInt);
	$sql    = "
SELECT CONVERT(NVARCHAR(128), " . $pointColumn . ") AS Valeur
FROM dbo.LogWide WITH (NOLOCK)
WHERE Device = ?
	AND DateNV = ?
	AND " . $pointColumn . " IS NOT NULL
ORDER BY Heure ASC;
";
	$params = [$deviceInt, $date];
}

$stmt = sqlsrv_query($connLog, $sql, $params);

if ($stmt === false) {
	apiLogsRespond(500, [
		'success' => false,
		'error' => 'Erreur SQL',
		'details' => apiLogsSqlErrorDetails(),
	]);
}

$values = [];
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_NUMERIC)) {
	$values[] = $row[0];
}

sqlsrv_free_stmt($stmt);

apiLogsRespond(200, $values);

} catch (Throwable $e) {
	apiLogsRespond(500, [
		'success' => false,
		'error' => 'Erreur interne du service',
		'details' => $e->getMessage(),
	]);
}
