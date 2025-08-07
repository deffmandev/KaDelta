<?php

function erreurbus($message) 
{
    echo chr(10).chr(13)."\n\r";
    echo "Erreur Modbus : $message\n\r";
    echo chr(10).chr(13)."\n\r";
    //exit(0);
}

function connectModbusTcp($ip, $port = 502, $timeout = 1) 
{
    $socket = @fsockopen($ip, $port, $errno, $errstr, $timeout);
    if (!$socket) {
        erreurbus("Connexion échouée à $ip:$port - $errstr ($errno)");
        return false;
    }
    return $socket;
}

function CloseModbusTcp($socket) 
{
    if ($socket) 
        {
        try {
            @fclose($socket);
        } catch (Throwable $e) {
            // On ignore toute erreur de fermeture
        }
    }
}

function safe_fwrite($socket, $data, $timeout = 1) {
    if (!$socket) return false;
    stream_set_timeout($socket, $timeout);
    $result = @fwrite($socket, $data);
    $meta = stream_get_meta_data($socket);
    if ($meta['timed_out']) {
        erreurbus("Timeout lors de l'écriture Modbus (fwrite)");
        return false;
    }
    return $result;
}
function safe_fread($socket, $length, $timeout = 1) {
    if (!$socket) return false;
    stream_set_timeout($socket, $timeout);
    $data = @fread($socket, $length);
    $meta = stream_get_meta_data($socket);
    if ($meta['timed_out']) {
        erreurbus("Timeout lors de la lecture Modbus (fread)");
        return false;
    }
    return $data;
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

    safe_fwrite($socket, $adu);

    $header = safe_fread($socket, 7);

    if (strlen($header) < 7) {
        erreurbus("En-tête Modbus TCP reçu incomplet");
        return false;
    }
    $data = unpack('ntransactionId/nprotocolId/nlength/CunitId', $header);

    $remaining = $data['length']-1;
    $payload = safe_fread($socket, $remaining);

    if (strlen($payload) < $remaining) {
        erreurbus("Charge utile Modbus TCP reçue incomplète");
        return false;
    }

    $response = unpack('CfunctionCode/CbyteCount', substr($payload, 0, 2));
    if ($response['functionCode'] != $functionCode) {
        erreurbus("Exception Modbus ou code fonction inattendu");
        return false;
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

    safe_fwrite($socket, $adu);

    $response = safe_fread($socket, 12);
    if (strlen($response) < 12) {
        erreurbus("Réponse Modbus TCP incomplète reçue (écriture coil)");
        
        return false;
    }

    // Vérification de la réponse (optionnel)
    $resp = unpack('ntransactionId/nprotocolId/nlength/CunitId/CfunctionCode/ncoilAddress/ncoilValue', $response);
    if ($resp['functionCode'] != $functionCode) {
        erreurbus("Exception Modbus ou code fonction inattendu (écriture coil)");
        
        return false;
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
    safe_fwrite($socket, $adu);

    // Lecture de la réponse (12 octets attendus)
    $response = safe_fread($socket, 12);
    if (strlen($response) < 12) {
        erreurbus("Réponse Modbus TCP incomplète reçue");
        return false;
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

    safe_fwrite($socket, $adu);

    $header = safe_fread($socket, 7);
    if (strlen($header) < 7) {
        erreurbus("En-tête Modbus TCP reçu incomplet coil (fonction 1) ",strlen($header));
        return false;
    }
    
    $data = unpack('ntransactionId/nprotocolId/nlength/CunitId', $header);
    
    $remaining = $data['length']-1;
    $payload = safe_fread($socket, $remaining);
    if (strlen($payload) < $remaining) {
        erreurbus("Charge utile Modbus TCP reçue incomplète (fonction 1)");
        return false;
    }

    $response = unpack('CfunctionCode/CbyteCount', substr($payload, 0, 2));
    if ($response['functionCode'] != $functionCode) {
        erreurbus("Exception Modbus ou code fonction inattendu (fonction 1)");
        return false;
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

function LireModbus($socket, $unitId, $startAddress, $type) {
    if ($type == "1")   
    {
        $tempon=readModbusCoil($socket, $unitId, $startAddress, 1);
            if ($tempon !== false) $tempon=$tempon[0]; 
                return $tempon;
    } 
    if ($type == "3") 
    {
        $tempon = readModbusRegisters($socket, $unitId, $startAddress, 1);
            if ($tempon !== false) $tempon=$tempon[0];
                return $tempon;
    }

    if ($type > "299")   
    {

        $tempon = readModbusRegisters($socket, $unitId, $startAddress, 1);
        if ($tempon === false) return false;
        $valeur = $tempon[0];
        $ValBit=to16BitBinary($valeur);
        $bit=15-($type-300);
        if ($ValBit[$bit] === '1') {
            return 1; // Le bit est à 1
        } else {
            return 0; // Le bit est à 0
        }
    } 

    
}

 
?>