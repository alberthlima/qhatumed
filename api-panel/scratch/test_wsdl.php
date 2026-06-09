<?php

$wsdls = [
    'codigos' => 'https://pilotosiatservicios.impuestos.gob.bo/v2/FacturacionCodigos?wsdl',
    'sincronizacion' => 'https://pilotosiatservicios.impuestos.gob.bo/v2/FacturacionSincronizacion?wsdl',
    'operaciones' => 'https://pilotosiatservicios.impuestos.gob.bo/v2/FacturacionOperaciones?wsdl',
    'computarizada' => 'https://pilotosiatservicios.impuestos.gob.bo/v2/ServicioFacturacionComputarizada?wsdl',
    'electronica' => 'https://pilotosiatservicios.impuestos.gob.bo/v2/ServicioFacturacionElectronica?wsdl'
];

foreach ($wsdls as $name => $wsdl) {
    echo "=== WSDL: $name ($wsdl) ===\n";
    try {
        $client = new SoapClient($wsdl, [
            'stream_context' => stream_context_create([
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                ]
            ])
        ]);
        $functions = $client->__getFunctions();
        if (empty($functions)) {
            echo "No se encontraron funciones.\n";
        } else {
            foreach ($functions as $f) {
                echo " - " . trim($f) . "\n";
            }
        }
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
    echo "\n";
}
