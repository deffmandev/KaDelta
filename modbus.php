<?php

function connectModbusTcp($ip, $port = 502, $timeout = 0.02) {
    $socket = @fsockopen($ip, $port, $errno, $errstr, $timeout);
    return $socket;
}

function readModbusRegisters($socket, $unitId, $startAddress, $quantity) 
{
    if (!$socket) return "1";
    // Construction de la trame Modbus TCP (ADU)
    $transactionId = rand(0, 0xFFFF);
    $protocolId = 0x0000;
    $length = 6; // Nombre d'octets après ce champ
    $functionCode = 0x03; // Lire les registres de maintien

    $adu = pack('nnnC', $transactionId, $protocolId, $length, $unitId);
    $adu .= pack('Cnn', $functionCode, $startAddress, $quantity);

    // Envoi de la requête
    fwrite($socket, $adu);

    // Lecture de l'en-tête de réponse (7 octets)
    $header = fread($socket, 7);

    if (strlen($header) < 7) {
        throw new Exception("En-tête Modbus TCP reçu incomplet");
    }
    $data = unpack('ntransactionId/nprotocolId/nlength/CunitId', $header);

    // Lecture des octets restants
    $remaining = $data['length']-1;
    $payload = fread($socket, $remaining);

    if (strlen($payload) < $remaining) {
        throw new Exception("Charge utile Modbus TCP reçue incomplète");
    }

    $response = unpack('CfunctionCode/CbyteCount', substr($payload, 0, 2));
    if ($response['functionCode'] != $functionCode) {
        throw new Exception("Exception Modbus ou code fonction inattendu");
    }

    $registers = [];
    for ($i = 0; $i < $response['byteCount'] / 2; $i++) {
        $registers[] = unpack('n', substr($payload, 2 + $i * 2, 2))[1];
    }
    return $registers;
}

// Fonction pour écrire dans un registre Modbus (fonction 0x06 Write Single Register)
function writeModbusRegister($socket, $unitId, $registerAddress, $value) {
    if (!$socket) return "1";
    // Génère un identifiant de transaction aléatoire
    $transactionId = rand(0, 0xFFFF);
    $protocolId = 0x0000;
    $length = 6; // Nombre d'octets après ce champ
    $functionCode = 0x06; // Code fonction pour écrire un registre

    // Construction de la trame Modbus TCP (ADU)
    $adu = pack('nnnC', $transactionId, $protocolId, $length, $unitId);
    $adu .= pack('Cnn', $functionCode, $registerAddress, $value);

    // Envoi de la requête au serveur Modbus
    fwrite($socket, $adu);

    // Lecture de la réponse (12 octets attendus)
    $response = fread($socket, 12);
    if (strlen($response) < 12) {
        throw new Exception("Réponse Modbus TCP incomplète reçue");
    }

    // Si tout s'est bien passé, retourne vrai
    return true;
}

function to16BitBinary($value) 
{
    return str_pad(decbin($value), 16, '0', STR_PAD_LEFT);
}


function readModbusCoil($socket, $unitId, $startAddress, $quantity = 1) 
{
    if (!$socket) return "1";

    $transactionId = rand(0, 0xFFFF);
    $protocolId = 0x0000;
    $length = 6;
    $functionCode = 0x01;

    $adu = pack('nnnC', $transactionId, $protocolId, $length, $unitId);
    $adu .= pack('Cnn', $functionCode, $startAddress, $quantity);

    fwrite($socket, $adu);

    $header = fread($socket, 7);
    if (strlen($header) < 7) {
        fclose($socket);
        throw new Exception("En-tête Modbus TCP reçu incomplet (fonction 1)");
    }
    $data = unpack('ntransactionId/nprotocolId/nlength/CunitId', $header);

    $remaining = $data['length']-1;
    $payload = fread($socket, $remaining);
    if (strlen($payload) < $remaining) {
        fclose($socket);
        throw new Exception("Charge utile Modbus TCP reçue incomplète (fonction 1)");
    }

    $response = unpack('CfunctionCode/CbyteCount', substr($payload, 0, 2));
    if ($response['functionCode'] != $functionCode) {
        fclose($socket);
        throw new Exception("Exception Modbus ou code fonction inattendu (fonction 1)");
    }

    $coilByte = ord($payload[2]);
    $coils = [];
    for ($i = 0; $i < $quantity; $i++) {
        $coils[] = ($coilByte >> $i) & 0x01;
    }

    return $coils;
}

// Exemple d'utilisation :
/*
 * Fonctions Modbus TCP en PHP
 * connectModbusTcp     : se connecte à un serveur Modbus TCP
 * readModbusRegisters  : lit des registres Modbus
 * writeModbusRegister  : écrit dans un registre Modbus
try {
    $ip = '192.168.1.19'; // Adresse IP de l'automate Modbus
    $port = 502; // Port Modbus TCP par défaut
    $unitId = 1; // Identifiant de l'unité Modbus
    $startAddress = 0; // Adresse de départ des registres
    $quantity = 30; // Nombre de registres à lire

    $socket = connectModbusTcp($ip, $port);
    $registers = readModbusRegisters($socket, $unitId, $startAddress, $quantity);
        writeModbusRegister($socket, $unitId, 11, 60);

    fclose($socket);

    //convertie les donnees en signed
    foreach ($registers as &$reg) 
        if ($reg >= 0x8000) $reg -= 0x10000;
    unset($reg);
    
    echo "Valeurs lues : " . implode(', ', $registers);
} catch (Exception $e) {
    echo "Erreur : " . $e->getMessage();
}

try {
    $ip = '192.168.1.109';
    $port = 8234;
    $unitId = 1;
    $registerAddress = 11;
    $value = 50; // Remplacez par la valeur à écrire

    $socket = connectModbusTcp($ip, $port);
    writeModbusRegister($socket, $unitId, $registerAddress, $value);
    fclose($socket);

    echo "<br>Valeur $value écrite dans le registre $registerAddress.";
} catch (Exception $e) {
    echo "<br>Erreur écriture : " . $e->getMessage();
}




// Exemple d'utilisation :
$valeur = $registers[0];
$ValBit=to16BitBinary($valeur);
echo "<br>Valeur $valeur en binaire 16 bits : " . $ValBit;

if ($ValBit[16-2] === '1') {
    echo "<br>Le bit 2 de \$ValBit est à 1.";
} else {
    echo "<br>Le bit 2 de \$ValBit est a 0.";
}


 */


 
?>