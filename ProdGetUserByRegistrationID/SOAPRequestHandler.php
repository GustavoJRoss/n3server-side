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
            echo "Valor recebido na requisição SOAP: \n";
            echo $requestBody . "\n\n";

            $xml = simplexml_load_string($requestBody);
            if ($xml === false) {
                throw new Exception("Erro ao processar o XML recebido.");
            }

            $registration = (string) ($xml->xpath('//registration')[0] ?? '');

            if ($registration === '' || $registration === '?') {
                throw new Exception("Erro: a variável registration do XML está vazia ou contém '?'.");
            }

            $userData = $this->getUserData($registration);

            if (!$userData) {
                throw new Exception("Registro não encontrado na base de dados.");
            }

            $xmlResponse = "<response>\n";
            $xmlResponse .= "  <status>success</status>\n";
            $xmlResponse .= "  <user>\n";
            $xmlResponse .= "    <name>{$userData['name']}</name>\n";
            $xmlResponse .= "    <level>{$userData['level']}</level>\n";
            $xmlResponse .= "    <shift>{$userData['shift']}</shift>\n";
            $xmlResponse .= "    <registration>{$userData['registration']}</registration>\n";
            $xmlResponse .= "  </user>\n";
            $xmlResponse .= "</response>\n";

            $this->enviarRespostaXML($xmlResponse);

        } catch (Exception $e) {
            $xmlResponse = "<response>\n";
            $xmlResponse .= "  <status>error</status>\n";
            $xmlResponse .= "  <message>" . htmlspecialchars($e->getMessage()) . "</message>\n";
            $xmlResponse .= "</response>\n";
            $this->enviarRespostaXML($xmlResponse);
        }
    }

    private function enviarRespostaXML($xmlResponse) {
        header('Content-Type: application/xml');
        echo $xmlResponse;
    }

    private function getUserData($registration) {
        $query = 'SELECT * FROM users WHERE registration = :registration';
        $stmt = $this->dbConnection->prepare($query);
        $stmt->bindValue(':registration', $registration, SQLITE3_INTEGER);
        $result = $stmt->execute();
        $userData = $result->fetchArray(SQLITE3_ASSOC);

        return $userData;
    }
}
?>
