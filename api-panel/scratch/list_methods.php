<?php

$wsdl = 'https://pilotosiatservicios.impuestos.gob.bo/v2/FacturacionSincronizacion?wsdl';
try {
    $client = new SoapClient($wsdl);
    echo "Metodos disponibles:\n";
    print_r($client->__getFunctions());
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
