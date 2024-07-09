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
            $Location = (string) ($xml->xpath('//Location')[0] ?? '');

            if ($MaterialInternalID === '' || $MaterialInternalID === '?' || $Location === '') {
                throw new Exception("Erro: MaterialInternalID e/ou Location não foram informados corretamente.");
            }

            $this->deleteRecord($MaterialInternalID, $Location);

            $xmlResponse = "<response>\n";
            $xmlResponse .= "  <status>success</status>\n";
            $xmlResponse .= "  <message>Registro deletado com sucesso.</message>\n";
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

    public function deleteRecord($MaterialInternalID, $Location) {
        if ($MaterialInternalID === '' || $Location === '') {
            throw new Exception("Erro: MaterialInternalID e/ou Location não foram informados corretamente para deletar o registro.");
        }

        $query = 'DELETE FROM storage WHERE MaterialInternalID = :MaterialInternalID AND Location = :Location';
        $stmt = $this->dbConnection->prepare($query);
        $stmt->bindValue(':MaterialInternalID', $MaterialInternalID, SQLITE3_INTEGER);
        $stmt->bindValue(':Location', $Location, SQLITE3_TEXT);
        
        $result = $stmt->execute();

        // Verifica se algum registro foi deletado
        $rowsAffected = $this->dbConnection->changes();
        if ($rowsAffected === 0) {
            throw new Exception("Combinação de MaterialInternalID '{$MaterialInternalID}' e Location '{$Location}' não encontrada na base de dados.");
        }
    }

    private function sendXMLResponse($xmlResponse) {
        header('Content-Type: application/xml');
        echo $xmlResponse;
    }
}
?>
