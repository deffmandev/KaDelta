<?php

function connectModbusTcp($ip, $port = 502, $timeout = 0.20) 
{
    $socket = @fsockopen($ip, $port, $errno, $errstr, $timeout);
    return $socket;
}

function CloseModbusTcp($socket) 
{
    if ($socket) {
        fclose($socket);
    }
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

function writeModbusCoil($socket, $unitId, $coilAddress, $value)
{
    if (!$socket) return "1";
    $transactionId = rand(0, 0xFFFF);
    $protocolId = 0x0000;
    $length = 6;
    $functionCode = 0x05;

    // La valeur doit être 0xFF00 pour ON, 0x0000 pour OFF
    $coilValue = ($value) ? 0xFF00 : 0x0000;

    $adu = pack('nnnC', $transactionId, $protocolId, $length, $unitId);
    $adu .= pack('Cnn', $functionCode, $coilAddress, $coilValue);

    fwrite($socket, $adu);

    $response = fread($socket, 12);
    if (strlen($response) < 12) {
        throw new Exception("Réponse Modbus TCP incomplète reçue (écriture coil)");
    }

    // Vérification de la réponse (optionnel)
    $resp = unpack('ntransactionId/nprotocolId/nlength/CunitId/CfunctionCode/ncoilAddress/ncoilValue', $response);
    if ($resp['functionCode'] != $functionCode) {
        throw new Exception("Exception Modbus ou code fonction inattendu (écriture coil)");
    }

    return true;
}

// Fonction pour écrire dans un registre Modbus (fonction 0x06 Write Single Register)
function writeModbusRegister($socket, $unitId, $registerAddress, $value) 
{
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


function ModbusWrite($socket,$Unite,$StartAddress,$type,$valeur)
{
                                if ($valeur === null || $valeur === '') return;

                                if ($type=='1') {
                                    try {
                                        writeModbusCoil($socket, $Unite, $StartAddress, $valeur);
                                        }
                                    catch (Exception $e) 
                                        {
                                    echo "Erreur lors de l'écriture de la bobine Modbus : " . $e->getMessage();
                                    return;
                                        }
                                            }

                                if ($type=='3') {
                                    try {
                                        writeModbusRegister($socket, $Unite, $StartAddress, $valeur);
                                        }
                                    catch (Exception $e) {
                                    echo "Erreur lors de l'écriture du registre Modbus : " . $e->getMessage();
                                    return;
                                        }
                                
                                if ($type>299)
                                        {
                                            $Bit = $type - 300; // Calculer le bit à partir du type
                                            $Valeur=readModbusRegisters($socket, $Unite, $StartAddress, 1)[0]; // Lire la valeur actuelle du registre

                                            if ($valeur) 
                                                $NewValeur = $Valeur | (1 << $Bit);
                                            else 
                                                $NewValeur = $Valeur & ~(1 << $Bit);

                                            
                                            try {
                                                writeModbusRegister($socket, $Unite, $StartAddress, $NewValeur);
                                                }
                                                catch (Exception $e) {
                                                    echo "Erreur lors de l'écriture du registre Modbus : " . $e->getMessage();
                                                return;
                                            }
                                        }
                                                                    
                                        } 


}


 
?>