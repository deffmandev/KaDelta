<?php

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
auth_bootstrap();
auth_require_active_session();

require_once __DIR__ . '/modbus.php';
require_once __DIR__ . '/base.php';

header('Content-Type: application/json; charset=utf-8');

function lennoxSetConsigneRespondOk(array $payload): void
{
    http_response_code(200);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function lennoxSetConsigneDetachRequest(): void
{
    ignore_user_abort(true);
    @set_time_limit(0);

    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
        return;
    }

    @ob_end_flush();
    @flush();
}

function lennoxResolveTarget(int $id): array
{
    $configPath = __DIR__ . '/configurationlennox.json';
    if (!is_file($configPath)) {
        return ['ok' => false];
    }

    $config = json_decode((string)file_get_contents($configPath), true);
    if (!is_array($config)) {
        return ['ok' => false];
    }

    $modbusId = (int)($config['modbus'] ?? 0);
    $unit = (int)($config['device' . $id] ?? 0);
    if ($modbusId <= 0 || $unit <= 0) {
        return ['ok' => false];
    }

    $Ip = '';
    $Port = 502;
    $q = mssql('SELECT Addresse, Port FROM DefModBus WHERE Id = ' . $modbusId);
    if ($q) {
        $row = sqlnext($q);
        if ($row) {
            $Ip = (string)($row['Addresse'] ?? '');
            $Port = (int)($row['Port'] ?? 502);
        }
    }

    if ($Ip === '' || $Port <= 0) {
        return ['ok' => false];
    }

    return [
        'ok' => true,
        'ip' => $Ip,
        'port' => $Port,
        'unit' => $unit,
    ];
}

function lennoxWriteJsonConsigne(int $id, int $valueRaw): bool
{
    $jsonPath = __DIR__ . '/Lennox' . $id . '.json';
    $payload = array_fill(0, 200, 0);

    if (is_file($jsonPath)) {
        $content = file_get_contents($jsonPath);
        if ($content !== false) {
            $decoded = json_decode($content, true);
            if (is_array($decoded)) {
                $payload = $decoded;
            }
        }
    }

    $payload[34] = $valueRaw;
    $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($encoded)) {
        return false;
    }

    return file_put_contents($jsonPath, $encoded, LOCK_EX) !== false;
}

function lennoxSendUntilSuccess(int $id, int $register, int $valueRaw): void
{
    while (true) {
        $target = lennoxResolveTarget($id);
        if (!($target['ok'] ?? false)) {
            usleep(2000000);
            continue;
        }

        $socket = connectModbusTcp((string)$target['ip'], (int)$target['port']);
        if (!$socket) {
            usleep(2000000);
            continue;
        }

        $unit = (int)$target['unit'];
        $ok = writeModbusRegister($socket, $unit, $register, $valueRaw);
        if ($ok !== false) {
            $deadline = microtime(true) + 2.0;
            while (microtime(true) < $deadline) {
                writeModbusRegister($socket, $unit, $register, $valueRaw);
                usleep(200000);
            }
            CloseModbusTcp($socket);
            return;
        }

        CloseModbusTcp($socket);
        usleep(2000000);
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    lennoxSetConsigneRespondOk([
        'success' => true,
        'queued' => false,
        'ignored' => true,
    ]);
    exit;
}

$id = isset($_POST['Id']) ? (int)$_POST['Id'] : 1;
$register = isset($_POST['register']) ? (int)$_POST['register'] : 34;
$valueC = isset($_POST['valeur']) ? (int)$_POST['valeur'] : 20;

if ($id < 1) {
    $id = 1;
}
if ($id > 3) {
    $id = 3;
}

if ($register < 0 || $register > 65535) {
    $register = 34;
}

if ($valueC < 8) {
    $valueC = 8;
}
if ($valueC > 32) {
    $valueC = 32;
}

// Le registre Lennox est lu en dixiemes (lennoxdata divise par 10).
$valueRaw = $valueC * 10;
$jsonUpdated = lennoxWriteJsonConsigne($id, $valueRaw);

lennoxSetConsigneRespondOk([
    'success' => true,
    'queued' => true,
    'Id' => $id,
    'register' => $register,
    'value' => $valueC,
    'raw' => $valueRaw,
    'jsonUpdated' => $jsonUpdated,
]);

lennoxSetConsigneDetachRequest();
lennoxSendUntilSuccess($id, $register, $valueRaw);

