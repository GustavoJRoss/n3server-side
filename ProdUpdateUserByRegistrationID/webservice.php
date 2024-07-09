<?php
require_once 'SOAPRequestHandler.php';

$wsdl = 'http://10.1.76.54:84/integration/ProdUpdateUserByRegistrationID/ProdUpdateUserByRegistrationIDDefinition.wsdl';
$options = [
    'location' => 'http://10.1.76.54:84/integration/ProdUpdateUserByRegistrationID/webservice.php',
    'uri' => 'http://10.1.76.54:84/integration/ProdUpdateUserByRegistrationID/webservice.php',
    'trace' => 1,
];

$requestBody = file_get_contents('php://input');

if (!empty($requestBody)) {
    $soapHandler = new SOAPRequestHandler($wsdl, $options);
    
    $soapHandler->processarRequisicaoSOAP($requestBody);
} else {
    echo "Nenhum conteúdo recebido na requisição.";
}
?>

