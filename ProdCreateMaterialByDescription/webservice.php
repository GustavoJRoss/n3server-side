<?php
require_once 'SOAPRequestHandler.php';

$wsdl = 'http://10.1.76.54:84/integration/ProdCreateMaterialByDescription/ProdCreateMaterialByDescriptionDefinition.wsdl';
$options = [
    'location' => 'http://10.1.76.54:84/integration/ProdCreateMaterialByDescription/MySoapServer.php',
    'uri' => 'http://10.1.76.54:84/integration/ProdCreateMaterialByDescription/MySoapServer.php',
    'trace' => 1,
];

// Instanciando o manipulador de requisiçao
$soapHandler = new MaterialSOAPRequestHandler($wsdl, $options);

// Recebendo o corpo da requisição
$requestBody = file_get_contents('php://input');

if (!empty($requestBody)) {
    // Processando a requisição SOAP
    $soapHandler->processarRequisicaoSOAP($requestBody);
} else {
    echo "Nenhum conteúdo recebido na requisição.";
}
?>
