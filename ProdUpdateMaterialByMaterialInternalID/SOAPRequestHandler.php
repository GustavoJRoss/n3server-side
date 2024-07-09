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
            echo "Tabela 'storage' criada com sucesso.\n";
        } else {
            die("Erro ao criar tabela 'storage': " . $this->dbConnection->lastErrorMsg());
        }
    }

    public function processarRequisicaoSOAP($requestBody) {
        try {
            $xml = simplexml_load_string($requestBody);
            if ($xml === false) {
                throw new Exception("Erro ao processar o XML recebido.");
            }

            $MaterialName = $this->extractValueFromXml($xml, '//MaterialName');
            $MaterialInternalID = $this->extractValueFromXml($xml, '//MaterialInternalID');
            $Location = $this->extractValueFromXml($xml, '//Location');
            $Quantity = $this->extractValueFromXml($xml, '//Quantity');

            $this->atualizarMaterial(
                $MaterialName,
                $MaterialInternalID,
                $Location,
                $Quantity
            );

            $xmlResponse = "<response>\n";
            $xmlResponse .= "  <status>success</status>\n";
            $xmlResponse .= "  <message>Dados atualizados com sucesso.</message>\n";
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

    private function extractValueFromXml($xml, $xpathExpression) {
        $nodes = $xml->xpath($xpathExpression);

        if (count($nodes) > 0) {
            $value = (string) $nodes[0];

            // Verifica se o valor é nulo ou comentado
            if ($value === '' || (strpos($value, '<!--') === 0 && strpos($value, '-->') !== false)) {
                return null; // Retorna nulo se estiver vazio ou comentado
            }

            return $value;
        }

        return null;
    }

    private function enviarRespostaXML($xmlResponse) {
        header('Content-Type: application/xml');
        echo $xmlResponse;
    }

    private function atualizarMaterial($MaterialName, $MaterialInternalID, $Location, $Quantity) {
        try {
            // Verifica se pelo menos um campo não é nulo
            if ($MaterialName !== null || $MaterialInternalID !== null || $Location !== null || $Quantity !== null) {
                $setStatements = [];
                $params = [];

                if ($MaterialName !== null) {
                    $setStatements[] = "MaterialName = :MaterialName";
                    $params['MaterialName'] = $MaterialName;
                }
                if ($MaterialInternalID !== null) {
                    $setStatements[] = "MaterialInternalID = :MaterialInternalID";
                    $params['MaterialInternalID'] = $MaterialInternalID;
                }
                if ($Location !== null) {
                    $setStatements[] = "Location = :Location";
                    $params['Location'] = $Location;
                }
                if ($Quantity !== null) {
                    $setStatements[] = "Quantity = :Quantity";
                    $params['Quantity'] = $Quantity;
                }

                if (empty($setStatements)) {
                    throw new Exception("Nenhum campo válido para atualização foi especificado.");
                }

                $setClause = implode(", ", $setStatements);

                $query = "UPDATE storage 
                          SET {$setClause} 
                          WHERE MaterialInternalID = :MaterialInternalID";

                $stmt = $this->dbConnection->prepare($query);

                foreach ($params as $paramName => $paramValue) {
                    $stmt->bindValue(":{$paramName}", $paramValue, SQLITE3_TEXT); // SQLite3 support binding types directly
                }

                $stmt->bindValue(':MaterialInternalID', $MaterialInternalID, SQLITE3_INTEGER);

                $result = $stmt->execute();

                if (!$result) {
                    throw new Exception("Erro ao atualizar dados no SQLite3.");
                }
            }
        } catch (Exception $e) {
            throw new Exception("Erro ao atualizar dados no SQLite3: " . $e->getMessage());
        }
    }
}
?>
