<?php
include 'modbus.php';
include 'base.php';

echo "Aquitement defaut sur groupe Lennox";

$Id = isset($_GET['Id']) ? (int)$_GET['Id'] : 0;

// Remplace ###aquite : écrire la valeur 1 dans le registre Modbus 85

$configPath = __DIR__ . '/configurationlennox.json';
$Ip = '127.0.0.1';
$Port = 502;
$unit = 1;

if (file_exists($configPath)) {
	$config = json_decode(file_get_contents($configPath), true);
	if ($config) {
		$modbusId = (int)($config['modbus'] ?? 0);
		// Récupérer l'unité/device à cibler : device1/device2/device3
		if (isset($config['device' . $Id])) {
			$unit = (int)$config['device' . $Id];
		} elseif (isset($config['device1'])) {
			$unit = (int)$config['device1'];
		}

		if ($modbusId > 0) {
			$q = mssql("SELECT Addresse, Port FROM DefModBus WHERE id = $modbusId");
			if ($q) {
				$row = sqlnext($q);
				if ($row) {
					$Ip = $row['Addresse'];
					$Port = $row['Port'];
				}
			}
		}
	}
}

$socket = connectModbusTcp($Ip, $Port);
if (!$socket) {
	echo "\nErreur : impossible de se connecter au Modbus $Ip:$Port";
	exit;
}

$register = 85;
$value = 1;
$ok = writeModbusRegister($socket, $unit, $register, $value);
if ($ok === false) {
	echo "\nÉchec de l'écriture du registre $register sur l'unité $unit";
} else {
	echo "\nRegistre $register mis à 1 sur l'unité $unit (IP $Ip:$Port)";
}

CloseModbusTcp($socket);

?>