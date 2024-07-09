<?php

class SOAPRequestHandler {
    private $client;
    private $wsdl;
    private $options;
    private $dbConnection;

    public function __construct($wsdl, $options) {
        $this->wsdl = $wsdl;
        $this->options = $options;

        try {
            $this->client = new SoapClient($this->wsdl, $this->options);
        } catch (SoapFault $e) {
            die("Erro ao inicializar o cliente SOAP: " . $e->getMessage());
        } catch (Exception $e) {
            die("Erro geral ao inicializar o cliente SOAP: " . $e->getMessage());
        }

        $dbPath = '../users.db';
        $this->dbConnection = new SQLite3($dbPath);

        if (!$this->dbConnection) {
            die("Erro ao conectar ao banco de dados SQLite.");
        }

        $this->createUsersTable();
    }

    private function createUsersTable() {
        $query = 'CREATE TABLE IF NOT EXISTS users (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    name TEXT,
                    level INTEGER,
                    password TEXT,
                    shift INTEGER,
                    registration INTEGER UNIQUE,
                    status INTEGER CHECK(status IN (0, 1))
                )';

        $result = $this->dbConnection->exec($query);

        if (!$result) {
            die("Erro ao criar tabela 'users': " . $this->dbConnection->lastErrorMsg());
        }
    }

    public function processarRequisicaoSOAP($requestBody) {
        try {
            $xml = simplexml_load_string($requestBody);
            if ($xml === false) {
                throw new Exception("Erro ao processar o XML recebido.");
            }

            $registration = (string) ($xml->xpath('//registration')[0] ?? null);

            if ($registration === null) {
                throw new Exception("Campo 'registration' é obrigatório.");
            }

            if (!$this->checkRegistrationExists($registration)) {
                throw new Exception("Registro com 'registration' {$registration} não encontrado no banco de dados.");
            }

            $name = $this->getFieldValue($xml, '//name');
            $level = $this->getFieldValue($xml, '//level');
            $password = $this->getFieldValue($xml, '//password');
            $shift = $this->getFieldValue($xml, '//shift');
            $status = $this->getFieldValue($xml, '//status');

            if ($status !== null) {
                $status = $status == 0 ? 0 : 1;
            }

            $currentStatus = $this->getCurrentStatus($registration);

            $this->updateUserData(
                $name,
                $level,
                $password !== null ? hash('sha256', $password) : null,
                $shift,
                $registration,
                $status !== null ? $status : $currentStatus
            );

            $updatedDataXML = "<updated_data>\n";
            if ($name !== null) {
                $updatedDataXML .= "  <name>{$name}</name>\n";
            }
            if ($level !== null) {
                $updatedDataXML .= "  <level>{$level}</level>\n";
            }
            if ($shift !== null) {
                $updatedDataXML .= "  <shift>{$shift}</shift>\n";
            }
            if ($registration !== null) {
                $updatedDataXML .= "  <registration>{$registration}</registration>\n";
            }
            if ($status !== null) {
                $updatedDataXML .= "  <status>{$status}</status>\n";
            }
            $updatedDataXML .= "</updated_data>\n";
            $xmlResponse = "<response>\n";
            $xmlResponse .= "  <status>success</status>\n";
            $xmlResponse .= "  <message>Dados atualizados com sucesso.</message>\n";
            $xmlResponse .= $updatedDataXML;
            $xmlResponse .= "</response>\n";

            $this->enviarRespostaXML($xmlResponse);

            $params = [];
            if ($name !== null) {
                $params['name'] = $name;
            }
            if ($level !== null) {
                $params['level'] = (int) $level;
            }
            if ($password !== null) {
                $params['password'] = $password;
            }
            if ($shift !== null) {
                $params['shift'] = (int) $shift;
            }
            if ($registration !== null) {
                $params['registration'] = (int) $registration;
            }
            if ($status !== null) {
                $params['status'] = (int) $status;
            }

            if (!empty($params)) {
                $response = $this->client->editUser($params);

                if ($response !== false) {
                    $soapResponse = "<soap_response>" . htmlspecialchars($response) . "</soap_response>";
                    $xmlResponse = "<response>\n";
                    $xmlResponse .= "  <status>success</status>\n";
                    $xmlResponse .= "  <message>Resposta do serviço SOAP:</message>\n";
                    $xmlResponse .= "  {$soapResponse}\n";
                    $xmlResponse .= "</response>\n";

                    $this->enviarRespostaXML($xmlResponse);
                } else {
                    throw new Exception("Erro ao chamar o serviço SOAP.");
                }
            } else {
                $xmlResponse = "<response>\n";
                $xmlResponse .= "  <status>success</status>\n";
                $xmlResponse .= "  <message>Nenhum dado foi enviado para atualização no serviço SOAP.</message>\n";
                $xmlResponse .= "</response>\n";
                $this->enviarRespostaXML($xmlResponse);
            }

        } catch (Exception $e) {
            $xmlResponse .= "<status>HTTP</status>\n";
            $xmlResponse .= "<message>200 OK</message>\n";
            $this->enviarRespostaXML($xmlResponse);
        }
    }

    private function enviarRespostaXML($xmlResponse) {
        header('Content-Type: application/xml');
        echo $xmlResponse;
    }

    private function updateUserData($name, $level, $password, $shift, $registration, $status) {
        try {
            $query = 'UPDATE users SET ';
            $params = [];
            $updateSet = [];

            if ($name !== null) {
                $updateSet[] = 'name = :name';
                $params[':name'] = $name;
            }
            if ($level !== null) {
                $updateSet[] = 'level = :level';
                $params[':level'] = $level;
            }
            if ($password !== null) {
                $updateSet[] = 'password = :password';
                $params[':password'] = $password;
            }
            if ($shift !== null) {
                $updateSet[] = 'shift = :shift';
                $params[':shift'] = $shift;
            }
            if ($status !== null) {
                $updateSet[] = 'status = :status';
                $params[':status'] = $status;
            }

            if (empty($updateSet)) {
                return;
            }

            $query .= implode(', ', $updateSet);
            $query .= ' WHERE registration = :registration';
            $params[':registration'] = $registration;

            $stmt = $this->dbConnection->prepare($query);

            foreach ($params as $param => $value) {
                if ($param === ':status') {
                    $stmt->bindValue($param, $value, SQLITE3_INTEGER);
                } else {
                    $stmt->bindValue($param, $value, SQLITE3_TEXT);
                }
            }

            $result = $stmt->execute();

            if (!$result) {
                throw new Exception("Erro ao atualizar dados do usuário: " . $this->dbConnection->lastErrorMsg());
            }
        } catch (Exception $e) {
            throw new Exception("Erro ao atualizar dados do usuário: " . $e->getMessage());
        }
    }

    private function getCurrentStatus($registration) {
        $query = 'SELECT status FROM users WHERE registration = :registration';
        $stmt = $this->dbConnection->prepare($query);
        $stmt->bindValue(':registration', $registration, SQLITE3_INTEGER);
        $result = $stmt->execute();
        $row = $result->fetchArray(SQLITE3_ASSOC);

        if ($row) {
            return $row['status'];
        }

        return null;
    }

    private function getFieldValue($xml, $xpath) {
        $value = (string) ($xml->xpath($xpath)[0] ?? null);
    
        if ($value === '--' || $value === '') {
            return null;
        }
    
        // Convertendo explicitamente para inteiro se necessário
        if (is_numeric($value)) {
            return (int) $value;
        }
    
        return $value;
    }
    

    private function checkRegistrationExists($registration) {
        $query = 'SELECT COUNT(*) as count FROM users WHERE registration = :registration';
        $stmt = $this->dbConnection->prepare($query);
        $stmt->bindValue(':registration', $registration, SQLITE3_INTEGER);
        $result = $stmt->execute();
        $row = $result->fetchArray(SQLITE3_ASSOC);

        return $row['count'] > 0;
    }
}

?>
