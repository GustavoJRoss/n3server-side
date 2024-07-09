<?php
$requestBody = file_get_contents('php://input');
if (empty($requestBody)) {
    die("Nenhum conteúdo recebido na requisição SOAP.");
}

$wsdl = 'http://10.1.76.54:84/integration/ProdDeleteMaterialDataStorageByMaterialInternalID/ProdDeleteMaterialDataStorageByMaterialInternalIDDefinition.wsdl';
$options = [
    'location' => 'http://10.1.76.54:84/integration/ProdDeleteMaterialDataStorageByMaterialInternalID/MySoapServer.php',
    'uri' => 'http://10.1.76.54:84/integration/ProdDeleteMaterialDataStorageByMaterialInternalID/MySoapServer.php',
    'trace' => 1,
];

require_once 'SOAPRequestHandler.php';

try {
    $soapHandler = new SOAPRequestHandler($wsdl, $options);

    $soapHandler->processSOAPRequest($requestBody);

} catch (SoapFault $e) {
    die("Erro ao inicializar o cliente SOAP: " . $e->getMessage());
} catch (Exception $e) {
    die("Erro geral ao processar a requisição SOAP: " . $e->getMessage());
}
?>
