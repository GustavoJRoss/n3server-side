<?php
require_once 'SOAPRequestHandler.php';

$wsdl = 'http://10.1.76.54:84/integration/ProdCreateUser/ProdCreateUserDefinition.wsdl';
$options = [
    'location' => 'http://10.1.76.54:84/integration/ProdCreateUser/MySoapServer.php',
    'uri' => 'http://10.1.76.54:84/integration/ProdCreateUser/MySoapServer.php',
    'trace' => 1,
];
$soapHandler = new SOAPRequestHandler($wsdl, $options);


$requestBody = file_get_contents('php://input');

if (!empty($requestBody)) {
    $soapHandler->processarRequisicaoSOAP($requestBody);
} else {
    echo "Nenhum conteúdo recebido na requisição.";
}
?>
