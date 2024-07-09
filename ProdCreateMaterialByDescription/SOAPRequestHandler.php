<?php
class MaterialSOAPRequestHandler {
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
                    MaterialName TEXT,
                    MaterialInternalID INTEGER NOT NULL,
                    Location TEXT NOT NULL,
                    Quantity INTEGER NOT NULL
                )';

        $result = $this->dbConnection->exec($query);

        if (!$result) {
            die("Erro ao criar tabela 'storage': " . $this->dbConnection->lastErrorMsg());
        }
    }

    public function processarRequisicaoSOAP($requestBody) {
        try {
            $xml = simplexml_load_string($requestBody);
            if ($xml === false) {
                throw new Exception("Erro ao processar o XML recebido.");
            }

            $MaterialName = (string) ($xml->xpath('//MaterialName')[0] ?? '');
            $MaterialInternalID = (string) ($xml->xpath('//MaterialInternalID')[0] ?? '');
            $Location = (string) ($xml->xpath('//Location')[0] ?? '');
            $Quantity = (string) ($xml->xpath('//Quantity')[0] ?? '');

            if ($MaterialInternalID === '' || $Location === '' || $Quantity === '') {
                throw new Exception("Erro: uma ou mais variáveis do XML estão vazias.");
            }

            if ($this->checkMaterialExists($MaterialInternalID)) {
                $this->updateMaterial($MaterialInternalID, $Location, $Quantity, $MaterialName);
            } else {
                if (!empty($MaterialName)) {
                    $this->inserirDadosMaterial($MaterialName, $MaterialInternalID, $Location, $Quantity);
                } else {
                    throw new Exception("Erro: MaterialName não pode ser nulo para inserção de novo registro.");
                }
            }

            $insertedDataXML = "<inserted_data>\n";
            $insertedDataXML .= "  <MaterialName>{$MaterialName}</MaterialName>\n";
            $insertedDataXML .= "  <MaterialInternalID>{$MaterialInternalID}</MaterialInternalID>\n";
            $insertedDataXML .= "  <Location>{$Location}</Location>\n";
            $insertedDataXML .= "  <Quantity>{$Quantity}</Quantity>\n";
            $insertedDataXML .= "</inserted_data>\n";
            $xmlResponse = "<response>\n";
            $xmlResponse .= "  <status>success</status>\n";
            $xmlResponse .= "  <message>Dados inseridos/atualizados com sucesso.</message>\n";
            $xmlResponse .= $insertedDataXML;
            $xmlResponse .= "</response>\n";

            $this->enviarRespostaXML($xmlResponse);

            $params = [
                'MaterialName' => $MaterialName,
                'MaterialInternalID' => (int) $MaterialInternalID,
                'Location' => $Location,
                'Quantity' => (int) $Quantity,
            ];

            $response = $this->client->registerMaterial($params);

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

    private function inserirDadosMaterial($MaterialName, $MaterialInternalID, $Location, $Quantity) {
        try {
            $query = 'INSERT INTO storage (MaterialName, MaterialInternalID, Location, Quantity)
                      VALUES (:MaterialName, :MaterialInternalID, :Location, :Quantity)';

            $stmt = $this->dbConnection->prepare($query);

            $stmt->bindValue(':MaterialName', $MaterialName, SQLITE3_TEXT);
            $stmt->bindValue(':MaterialInternalID', $MaterialInternalID, SQLITE3_INTEGER);
            $stmt->bindValue(':Location', $Location, SQLITE3_TEXT);
            $stmt->bindValue(':Quantity', $Quantity, SQLITE3_INTEGER);

            $result = $stmt->execute();

            if (!$result) {
                throw new Exception("Erro ao inserir dados no SQLite3.");
            }
        } catch (Exception $e) {
            throw new Exception("Erro ao inserir dados no SQLite3: " . $e->getMessage());
        }
    }

    private function checkMaterialExists($MaterialInternalID) {
        $query = 'SELECT COUNT(*) as count FROM storage WHERE MaterialInternalID = :MaterialInternalID';
        $stmt = $this->dbConnection->prepare($query);
        $stmt->bindValue(':MaterialInternalID', $MaterialInternalID, SQLITE3_INTEGER);
        $result = $stmt->execute();
        $row = $result->fetchArray(SQLITE3_ASSOC);

        return $row['count'] > 0;
    }

    private function updateMaterial($MaterialInternalID, $Location, $Quantity, $MaterialName) {
        try {
            if (!empty($MaterialName)) {
                $query = 'UPDATE storage SET MaterialName = :MaterialName, Quantity = Quantity + :Quantity WHERE MaterialInternalID = :MaterialInternalID AND Location = :Location';
            } else {
                $query = 'UPDATE storage SET Quantity = Quantity + :Quantity WHERE MaterialInternalID = :MaterialInternalID AND Location = :Location';
            }

            $stmt = $this->dbConnection->prepare($query);
            $stmt->bindValue(':MaterialInternalID', $MaterialInternalID, SQLITE3_INTEGER);
            $stmt->bindValue(':Location', $Location, SQLITE3_TEXT);
            $stmt->bindValue(':Quantity', $Quantity, SQLITE3_INTEGER);

            if (!empty($MaterialName)) {
                $stmt->bindValue(':MaterialName', $MaterialName, SQLITE3_TEXT);
            }

            $result = $stmt->execute();

            if (!$result) {
                throw new Exception("Erro ao atualizar quantidade do material.");
            }
        } catch (Exception $e) {
            throw new Exception("Erro ao atualizar quantidade do material: " . $e->getMessage());
        }
    }
}

?>

