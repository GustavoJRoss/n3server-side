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

        $dbPath = '../MaterialData.db';
        $this->dbConnection = new SQLite3($dbPath);

        if (!$this->dbConnection) {
            die("Erro ao conectar ao banco de dados SQLite.");
        }

        $this->createStorageTable();
    }

    private function createStorageTable() {
        $query = 'CREATE TABLE IF NOT EXISTS storage (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    MaterialName TEXT NOT NULL,
                    MaterialInternalID INTEGER NOT NULL,
                    Location TEXT NOT NULL,
                    Quantity INTEGER NOT NULL
                )';

        $result = $this->dbConnection->exec($query);

        if ($result) {
            echo "";
        } else {
            die("Erro ao criar tabela 'storage': " . $this->dbConnection->lastErrorMsg());
        }
    }

    public function processSOAPRequest($requestBody) {
        try {
            echo "Valor recebido na requisição SOAP: \n";
            echo $requestBody . "\n\n";

            $xml = simplexml_load_string($requestBody);
            if ($xml === false) {
                throw new Exception("Erro ao processar o XML recebido.");
            }

            $MaterialInternalID = (string) ($xml->xpath('//MaterialInternalID')[0] ?? '');

            if ($MaterialInternalID === '' || $MaterialInternalID === '?') {
                throw new Exception("Erro: a variável MaterialInternalID do XML está vazia ou contém '?'.");
            }

            $userDataArray = $this->getUserData($MaterialInternalID);

            if (empty($userDataArray)) {
                throw new Exception("Registro não encontrado na base de dados.");
            }

            $xmlResponse = "<response>\n";
            $xmlResponse .= "  <status>success</status>\n";
            foreach ($userDataArray as $userData) {
                $xmlResponse .= "  <Information>\n";
                $xmlResponse .= "    <MaterialName>{$userData['MaterialName']}</MaterialName>\n";
                $xmlResponse .= "    <MaterialInternalID>{$userData['MaterialInternalID']}</MaterialInternalID>\n";
                $xmlResponse .= "    <Location>{$userData['Location']}</Location>\n";
                $xmlResponse .= "    <Quantity>{$userData['Quantity']}</Quantity>\n";
                $xmlResponse .= "  </Information>\n";
            }
            $xmlResponse .= "</response>\n";

            $this->sendXMLResponse($xmlResponse);

        } catch (Exception $e) {
            $xmlResponse = "<response>\n";
            $xmlResponse .= "  <status>error</status>\n";
            $xmlResponse .= "  <message>" . htmlspecialchars($e->getMessage()) . "</message>\n";
            $xmlResponse .= "</response>\n";
            $this->sendXMLResponse($xmlResponse);
        }
    }

    private function sendXMLResponse($xmlResponse) {
        header('Content-Type: application/xml');
        echo $xmlResponse;
    }

    private function getUserData($MaterialInternalID) {
        $query = 'SELECT * FROM storage WHERE MaterialInternalID = :MaterialInternalID';
        $stmt = $this->dbConnection->prepare($query);
        $stmt->bindValue(':MaterialInternalID', $MaterialInternalID, SQLITE3_INTEGER);
        $result = $stmt->execute();

        $userDataArray = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $userDataArray[] = $row;
        }

        return $userDataArray;
    }
}
?>
