<?php
$token = "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzUxMiJ9.eyJzdWIiOiJtYXlyb24ubGltYUBvdXRsb29rLmVzIiwiY29kaWdvU2lzdGVtYSI6IjIyNzZGMDk4QjVCNkI1QzgwNEY4RiIsIm5pdCI6Ikg0c0lBQUFBQUFBQUFETXp0N0EwTVRBek1EUURBS0N5NG1FS0FBQUEiLCJpZCI6NTQ0MTY3MCwiZXhwIjoxNzgyODU3NTI2LCJpYXQiOjE3NzU2MTQyOTYsIm5pdERlbGVnYWRvIjo2Nzg5NDA2MDE2LCJzdWJzaXN0ZW1hIjoiU0ZFIn0.RwF-XH0b8HhPn-2i1UaZmGvCGPkCpnF9Ndgrf11EadG49BbdmR9uykaRrvSm3WTwaDhEmGNpXRtjCWuzxx8ZXA";
$url = "https://pilotosiatservicios.impuestos.gob.bo/v2/FacturacionCodigos";

$xml = '<?xml version="1.0" encoding="UTF-8"?>
<SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ns1="https://siat.impuestos.gob.bo/">
  <SOAP-ENV:Body>
    <ns1:verificarComunicacion/>
  </SOAP-ENV:Body>
</SOAP-ENV:Envelope>';

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: text/xml; charset=utf-8',
    'apikey: TokenDelegado ' . $token,
]);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$response = curl_exec($ch);
$info = curl_getinfo($ch);
curl_close($ch);

echo "HTTP Code: " . $info['http_code'] . "\n";
echo "Response:\n" . $response . "\n";
