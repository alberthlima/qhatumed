<?php

require __DIR__ . '/../vendor/autoload.php';

use Misalud\Siat\Utils\XmlSigner;

echo "=== INICIANDO PRUEBA DE FIRMA DIGITAL (XMLDSig) ===\n\n";

$certPath = __DIR__ . '/temp_cert.p12';
$certPassword = 'password123';

try {
    // 1. Generar claves y certificado de prueba
    echo "1. Generando certificado de prueba autofirmado...\n";
    $pkey = openssl_pkey_new([
        "private_key_bits" => 2048,
        "private_key_type" => OPENSSL_KEYTYPE_RSA,
        "config" => "C:\\xampp\\apache\\conf\\openssl.cnf"
    ]);

    if (!$pkey) {
        throw new Exception("No se pudo generar la clave privada: " . openssl_error_string());
    }

    $dn = [
        "countryName" => "BO",
        "stateOrProvinceName" => "Santa Cruz",
        "localityName" => "Santa Cruz",
        "organizationName" => "FARMACIA MISALUD SRL",
        "commonName" => "Certificado de Pruebas SIAT",
    ];

    $csr = openssl_csr_new($dn, $pkey, [
        'digest_alg' => 'sha256',
        'config' => "C:\\xampp\\apache\\conf\\openssl.cnf"
    ]);
    $x509 = openssl_csr_sign($csr, null, $pkey, 365, [
        'digest_alg' => 'sha256',
        'config' => "C:\\xampp\\apache\\conf\\openssl.cnf"
    ]);

    $p12out = '';
    if (!openssl_pkcs12_export($x509, $p12out, $pkey, $certPassword)) {
        throw new Exception("No se pudo exportar a formato PKCS12: " . openssl_error_string());
    }

    file_put_contents($certPath, $p12out);
    echo "   [OK] Certificado guardado en: $certPath\n\n";

    // 2. XML de prueba
    echo "2. Cargando XML de prueba para la factura...\n";
    $xmlContent = '<?xml version="1.0" encoding="UTF-8"?>
<facturaElectronicaCompraVenta xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:noNamespaceSchemaLocation="facturaElectronicaCompraVenta.xsd">
    <cabecera>
        <nitEmisor>6789406016</nitEmisor>
        <razonSocialEmisor>FARMACIA MISALUD SRL</razonSocialEmisor>
        <municipio>Santa Cruz</municipio>
        <telefono>33333333</telefono>
        <numeroFactura>1</numeroFactura>
        <cuf>1234567890ABCDEF</cuf>
        <cufd>CUFD-TEST-123456</cufd>
        <codigoSucursal>0</codigoSucursal>
        <direccion>Av. Banzer #456</direccion>
        <codigoPuntoVenta>0</codigoPuntoVenta>
        <fechaEmision>2026-06-05T15:00:00.000</fechaEmision>
        <nombreRazonSocial>JUAN PEREZ</nombreRazonSocial>
        <codigoTipoDocumentoIdentidad>1</codigoTipoDocumentoIdentidad>
        <numeroDocumento>1234567</numeroDocumento>
        <codigoCliente>CLI-001</codigoCliente>
        <codigoMetodoPago>1</codigoMetodoPago>
        <montoTotal>150.00</montoTotal>
        <montoTotalSujetoIva>150.00</montoTotalSujetoIva>
        <codigoMoneda>1</codigoMoneda>
        <tipoCambio>1.00</tipoCambio>
        <montoTotalMoneda>150.00</montoTotalMoneda>
        <leyenda>Ley N° 453: El proveedor debe informar sobre el producto.</leyenda>
        <usuario>cajero1</usuario>
        <codigoDocumentoSector>1</codigoDocumentoSector>
    </cabecera>
    <detalle>
        <actividadEconomica>477210</actividadEconomica>
        <codigoProductoSin>99100</codigoProductoSin>
        <codigoProducto>P-001</codigoProducto>
        <descripcion>PARACETAMOL 500MG</descripcion>
        <cantidad>10.00</cantidad>
        <unidadMedida>57</unidadMedida>
        <precioUnitario>15.00</precioUnitario>
        <montoDescuento>0.00</montoDescuento>
        <subTotal>150.00</subTotal>
    </detalle>
</facturaElectronicaCompraVenta>';

    // 3. Firmar XML
    echo "3. Firmando XML digitalmente...\n";
    $signedXml = XmlSigner::sign($xmlContent, $certPath, $certPassword);
    echo "   [OK] XML firmado correctamente.\n\n";

    // 4. Validar XML firmado
    echo "4. Validando estructura del XML firmado...\n";
    $dom = new DOMDocument();
    $dom->preserveWhiteSpace = false;
    $dom->formatOutput = true;
    
    if (!$dom->loadXML($signedXml)) {
        throw new Exception("El XML generado no se pudo cargar en DOMDocument.");
    }

    $xpath = new DOMXPath($dom);
    $xpath->registerNamespace('ds', 'http://www.w3.org/2000/09/xmldsig#');

    $signatureNode = $xpath->query('//ds:Signature');
    if ($signatureNode->length === 0) {
        throw new Exception("No se encontró el nodo <Signature> en el XML firmado.");
    }
    echo "   [OK] Nodo <Signature> presente.\n";

    $digestValueNode = $xpath->query('//ds:Reference/ds:DigestValue');
    if ($digestValueNode->length === 0 || empty($digestValueNode->item(0)->nodeValue)) {
        throw new Exception("No se encontró el nodo <DigestValue> o está vacío.");
    }
    echo "   [OK] Nodo <DigestValue> presente. Valor: " . $digestValueNode->item(0)->nodeValue . "\n";

    $signatureValueNode = $xpath->query('//ds:SignatureValue');
    if ($signatureValueNode->length === 0 || empty($signatureValueNode->item(0)->nodeValue)) {
        throw new Exception("No se encontró el nodo <SignatureValue> o está vacío.");
    }
    echo "   [OK] Nodo <SignatureValue> presente. Valor (truncado): " . substr($signatureValueNode->item(0)->nodeValue, 0, 30) . "...\n";

    $certNode = $xpath->query('//ds:KeyInfo/ds:X509Data/ds:X509Certificate');
    if ($certNode->length === 0 || empty($certNode->item(0)->nodeValue)) {
        throw new Exception("No se encontró el nodo <X509Certificate> o está vacío.");
    }
    echo "   [OK] Nodo <X509Certificate> presente.\n\n";

    echo "=== RESULTADO DE LA PRUEBA: ÉXITO TOTAL ===\n";

} catch (Exception $e) {
    echo "\n[ERROR] Prueba fallida: " . $e->getMessage() . "\n";
} finally {
    // Limpieza
    if (file_exists($certPath)) {
        unlink($certPath);
        echo "   [CLEANUP] Certificado temporal eliminado.\n";
    }
}
