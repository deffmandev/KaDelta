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

function apiLogsReadJsonCache(string $path): ?array
{
	if (!is_file($path) || !is_readable($path)) {
		return null;
	}

	$content = @file_get_contents($path);
	if (!is_string($content) || trim($content) === '') {
		return null;
	}

	$decoded = json_decode($content, true);
	if (!is_array($decoded)) {
		@unlink($path);
		return null;
	}

	return $decoded;
}

function apiLogsWriteJsonCache(string $path, array $payload): bool
{
	$directory = dirname($path);
	if (!is_dir($directory) && !@mkdir($directory, 0750, true) && !is_dir($directory)) {
		return false;
	}

	$json = apiLogsJsonEncode($payload);
	return @file_put_contents($path, $json, LOCK_EX) !== false;
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

try {

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
		if (!in_array(1, $allPts, true)) {
			array_unshift($allPts, 1);
		}
	}
	$indexPts = count($dataPts) > 0 ? $dataPts : [1];
	$hasIndexForRow = static function (array $row) use ($indexPts): bool {
		foreach ($indexPts as $pt) {
			$key = 'P' . (int)$pt;
			if (!array_key_exists($key, $row) || $row[$key] === null) {
				continue;
			}

			if (trim((string)$row[$key]) !== '') {
				return true;
			}
		}

		return false;
	};
	foreach ($dataPts as $pt) {
		$bResult[(string) $pt] = [];
	}

	// ── Cache fichier ────────────────────────────────────────────────────────
	// Jours passes : cache 7 jours (donnees immuables)
	// Aujourd'hui  : cache 90 secondes (donnees live)
	$cacheDir = __DIR__ . DIRECTORY_SEPARATOR . 'cache';
	if (!is_dir($cacheDir) && !@mkdir($cacheDir, 0750, true) && !is_dir($cacheDir)) {
		apiLogsRespond(500, ['success' => false, 'error' => 'Impossible de creer le cache local']);
	}
	$sortedPts = $allPts; sort($sortedPts);
	$cacheKey  = 'apilogs_' . md5('v3|' . $bDeviceInt . '|' . implode(',', $sortedPts) . '|' . $bDate . '|s' . $bStep . 't' . $bTop . ($bEndpoints ? 'e1' : '') . ($bHeure !== '' ? 'h' . $bHeure : ''));
	$cacheFile = $cacheDir . DIRECTORY_SEPARATOR . $cacheKey . '.json';
	$isToday   = ($bDate === date('d-m-Y'));
	$cacheTtl  = $isToday ? 90 : (7 * 24 * 3600);

	// Nettoyage periodique : 1 chance sur 200 de purger les fichiers expires
	if (random_int(1, 200) === 1 && is_dir($cacheDir)) {
		$nowTs = time();
		foreach (glob($cacheDir . DIRECTORY_SEPARATOR . 'apilogs_*.json') ?: [] as $oldFile) {
			if ($nowTs - @filemtime($oldFile) > (7 * 24 * 3600)) {
				@unlink($oldFile);
			}
		}
	}

	if (is_file($cacheFile)) {
		$cacheAge = time() - filemtime($cacheFile);
		if ($cacheAge < $cacheTtl) {
			$cached = apiLogsReadJsonCache($cacheFile);
			if (is_array($cached)) {
				apiLogsRespond(200, $cached);
			}
		} else {
			@unlink($cacheFile);
		}
	}

	set_time_limit(0);

	if (count($allPts) === 0) {
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
ORDER BY Heure ASC;
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
		$heure = trim((string)($raw['Heure'] ?? ''));
		if ($heure === '') {
			continue;
		}

		$row = ['Heure' => $heure];
		foreach ($allPts as $pt) {
			$key = 'P' . (int)$pt;
			if (array_key_exists($key, $raw) && $raw[$key] !== null) {
				$row[$key] = (string)$raw[$key];
			}
		}
		$rows[] = $row;
	}
	sqlsrv_free_stmt($stmt);

	if ($bEndpoints) {
		$firstValues = [];
		$lastValues = [];
		$firstHeures = [];
		$lastHeures = [];
		$firstIndexHeure = null;
		$lastIndexHeure = null;
		foreach ($allPts as $pt) {
			$firstValues[$pt] = null;
			$lastValues[$pt] = null;
			$firstHeures[$pt] = null;
			$lastHeures[$pt] = null;
		}

		foreach ($rows as $row) {
			$heure = (string)($row['Heure'] ?? '');
			foreach ($allPts as $pt) {
				$key = 'P' . $pt;
				if (!array_key_exists($key, $row) || $row[$key] === null) {
					continue;
				}

				$value = (string) $row[$key];
				if ($firstValues[$pt] === null) {
					$firstValues[$pt] = $value;
					$firstHeures[$pt] = $heure;
				}
				$lastValues[$pt] = $value;
				$lastHeures[$pt] = $heure;
			}

			if ($hasIndexForRow($row)) {
				if ($firstIndexHeure === null) {
					$firstIndexHeure = $heure;
				}
				$lastIndexHeure = $heure;
			}
		}

		foreach ($dataPts as $pt) {
			$bResult[(string)$pt] = array_values(array_filter([
				$firstValues[$pt],
				$lastValues[$pt],
			], static fn($value): bool => $value !== null && $value !== ''));
		}

		if ($needsIndex) {
			$bResult['-1'] = array_values(array_filter([
				$firstIndexHeure,
				$lastIndexHeure,
			], static fn($value): bool => $value !== null && $value !== ''));
		}
	} elseif ($bHeure !== '') {
		$matchedRow = null;
		foreach ($rows as $row) {
			$heure = (string)($row['Heure'] ?? '');
			if ($heure >= $bHeure) {
				$matchedRow = $row;
				break;
			}
		}

		if (is_array($matchedRow)) {
			foreach ($dataPts as $pt) {
				$key = 'P' . $pt;
				if (!array_key_exists($key, $matchedRow) || $matchedRow[$key] === null) {
					continue;
				}

				$bResult[(string)$pt][] = (string) $matchedRow[$key];
			}

			if ($needsIndex && $hasIndexForRow($matchedRow)) {
				$bResult['-1'][] = (string)($matchedRow['Heure'] ?? '');
			}
		}
	} elseif ($bTop > 0) {
		$limit = min($bTop, count($rows));
		for ($rowIndex = 0; $rowIndex < $limit; $rowIndex++) {
			$row = $rows[$rowIndex];
			foreach ($dataPts as $pt) {
				$key = 'P' . $pt;
				if (!array_key_exists($key, $row) || $row[$key] === null) {
					continue;
				}

				$bResult[(string)$pt][] = (string) $row[$key];
			}

			if ($needsIndex && $hasIndexForRow($row)) {
				$bResult['-1'][] = (string)($row['Heure'] ?? '');
			}
		}
	} else {
		$rowIndex = 0;
		foreach ($rows as $row) {
			if ($bStep > 1 && ($rowIndex % $bStep) !== 0) {
				$rowIndex++;
				continue;
			}

			foreach ($dataPts as $pt) {
				$key = 'P' . $pt;
				if (!array_key_exists($key, $row) || $row[$key] === null) {
					continue;
				}

				$bResult[(string)$pt][] = (string) $row[$key];
			}

			if ($needsIndex && $hasIndexForRow($row)) {
				$bResult['-1'][] = (string)($row['Heure'] ?? '');
			}

			$rowIndex++;
		}
	}

	// Stocker dans le cache
	apiLogsWriteJsonCache($cacheFile, $bResult);

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
	AND [P1] IS NOT NULL
	AND Heure IS NOT NULL
	AND LTRIM(RTRIM(CONVERT(NVARCHAR(16), Heure))) <> ''
ORDER BY Heure ASC;
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
