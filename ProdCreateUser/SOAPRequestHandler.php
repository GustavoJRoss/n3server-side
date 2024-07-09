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
                    name TEXT NOT NULL,
                    level INTEGER NOT NULL,
                    password TEXT NOT NULL,
                    shift INTEGER NOT NULL,
                    registration INTEGER NOT NULL UNIQUE
                )';

        $result = $this->dbConnection->exec($query);

        if ($result) {
            echo "";
        } else {
            die("Erro ao criar tabela 'users': " . $this->dbConnection->lastErrorMsg());
        }
    }

    public function processarRequisicaoSOAP($requestBody) {
        try {
            $xml = simplexml_load_string($requestBody);
            if ($xml === false) {
                throw new Exception("Erro ao processar o XML recebido.");
            }

            $name = (string) ($xml->xpath('//name')[0] ?? '');
            $level = (string) ($xml->xpath('//level')[0] ?? '');
            $password = (string) ($xml->xpath('//password')[0] ?? '');
            $shift = (string) ($xml->xpath('//shift')[0] ?? '');
            $registration = (string) ($xml->xpath('//registration')[0] ?? '');
            $status = (string) ($xml->xpath('//status')[0] ?? '');

            if ($name === '' || $name === '?' ||
                $level === '' || $level === '?' ||
                $password === '' || $password === '?' ||
                $shift === '' || $shift === '?' ||
                $registration === '' || $registration === '?' ||
                $status === '' || $status === '?') {
                throw new Exception("Erro: uma ou mais variáveis do XML estão vazias, contêm '?' ou não possuem o formato esperado.");
            }

            $hashedPassword = hash('sha256', $password);

            if ($this->registroExistente($registration)) {
                $xmlResponse = "<response>\n";
                $xmlResponse .= "  <status>error</status>\n";
                $xmlResponse .= "  <message>Já existe um registro com o número de registro fornecido.</message>\n";
                $xmlResponse .= "</response>\n";
                $this->enviarRespostaXML($xmlResponse);
                return;
            }

            $this->inserirDadosUsuario(
                $name,
                $level,
                $hashedPassword,
                $shift,
                $registration,
                $status
            );

            $insertedDataXML = "<inserted_data>\n";
            $insertedDataXML .= "  <name>{$name}</name>\n";
            $insertedDataXML .= "  <level>{$level}</level>\n";
            $insertedDataXML .= "  <shift>{$shift}</shift>\n";
            $insertedDataXML .= "  <registration>{$registration}</registration>\n";
            $insertedDataXML .= "  <status>{$status}</status>\n";
            $insertedDataXML .= "</inserted_data>\n";
            $xmlResponse = "<response>\n";
            $xmlResponse .= "  <status>success</status>\n";
            $xmlResponse .= "  <message>Dados inseridos com sucesso.</message>\n";
            $xmlResponse .= $insertedDataXML;
            $xmlResponse .= "</response>\n";

            $this->enviarRespostaXML($xmlResponse);

            $params = [
                'name' => $name,
                'level' => (int)$level,
                'password' => $password,
                'shift' => (int)$shift,
                'registration' => (int)$registration,
                'status' => (int)$status
            ];

            $response = $this->client->registerUser($params);

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

        } catch (Exception $e) {
            $xmlResponse = "<response>\n";
            $xmlResponse .= "  <status>200</status>\n";
            $xmlResponse .= "  <message>OK</message>\n";
            $xmlResponse .= "</response>\n";
            $this->enviarRespostaXML($xmlResponse);
        }
    }

    private function enviarRespostaXML($xmlResponse) {
        header('Content-Type: application/xml');
        echo $xmlResponse;
    }

    private function inserirDadosUsuario($name, $level, $password, $shift, $registration, $status) {
        try {
            $query = 'INSERT INTO users (name, level, password, shift, registration, status)
                      VALUES (:name, :level, :password, :shift, :registration, :status)';

            $stmt = $this->dbConnection->prepare($query);

            $stmt->bindValue(':name', $name, SQLITE3_TEXT);
            $stmt->bindValue(':level', $level, SQLITE3_INTEGER);
            $stmt->bindValue(':password', $password, SQLITE3_TEXT);
            $stmt->bindValue(':shift', $shift, SQLITE3_INTEGER);
            $stmt->bindValue(':registration', $registration, SQLITE3_INTEGER);
            $stmt->bindValue(':status', $status, SQLITE3_INTEGER);

            $result = $stmt->execute();

            if (!$result) {
                throw new Exception("Erro ao inserir dados no SQLite3.");
            }
        } catch (Exception $e) {
            throw new Exception("Erro ao inserir dados no SQLite3: " . $e->getMessage());
        }
    }

    private function registroExistente($registration) {
        $query = 'SELECT COUNT(*) as count FROM users WHERE registration = :registration';
        $stmt = $this->dbConnection->prepare($query);
        $stmt->bindValue(':registration', $registration, SQLITE3_INTEGER);
        $result = $stmt->execute();
        $row = $result->fetchArray(SQLITE3_ASSOC);

        return $row['count'] > 0;
    }
}

?>
