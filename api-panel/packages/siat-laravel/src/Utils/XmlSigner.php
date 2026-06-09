<?php

namespace Misalud\Siat\Utils;

use DOMDocument;
use Exception;

/**
 * XmlSigner
 *
 * Utilidad nativa de PHP para firmar digitalmente documentos XML
 * siguiendo el estándar XMLDSig (Enveloped Signature) requerido por el SIAT.
 * Usa openssl y DOMDocument sin dependencias externas.
 */
class XmlSigner
{
    /**
     * Firma digitalmente un documento XML de factura.
     *
     * @param string $xmlContent El XML original de la factura
     * @param string $certPath Ruta del certificado digital .p12
     * @param string $certPassword Contraseña del certificado
     * @return string El XML firmado con el nodo <Signature> inyectado
     * @throws Exception
     */
    public static function sign(string $xmlContent, string $certPath, string $certPassword): string
    {
        if (empty($certPath)) {
            throw new Exception("La ruta del certificado digital (.p12) no está configurada.");
        }

        if (!file_exists($certPath)) {
            throw new Exception("El certificado digital no existe en la ruta especificada: {$certPath}");
        }

        // 1. Leer Certificado .p12
        $pkcs12 = file_get_contents($certPath);
        $certs = [];
        if (!openssl_pkcs12_read($pkcs12, $certs, $certPassword)) {
            throw new Exception("No se pudo leer el certificado digital (.p12). Verifique la contraseña.");
        }

        $privateKey = $certs['pkey'];
        $publicCert = $certs['cert'];

        // Limpiar cabeceras del certificado PEM para el elemento <X509Certificate>
        $x509Certificate = str_replace(
            ["-----BEGIN CERTIFICATE-----", "-----END CERTIFICATE-----", "\r", "\n", " "],
            "",
            $publicCert
        );

        // 2. Cargar XML en DOMDocument
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = false;
        if (!$dom->loadXML($xmlContent)) {
            throw new Exception("El contenido XML de la factura no es válido.");
        }

        // 3. Canonicalización del XML (Exclusiva sin comentarios)
        $canonicalXml = $dom->C14N(true, false);
        $digestValue = base64_encode(hash('sha256', $canonicalXml, true));

        // 4. Construir el nodo <SignedInfo>
        $signedInfoXml = '<SignedInfo xmlns="http://www.w3.org/2000/09/xmldsig#">' .
            '<CanonicalizationMethod Algorithm="http://www.w3.org/2001/10/xml-exc-c14n#"/>' .
            '<SignatureMethod Algorithm="http://www.w3.org/2001/04/xmldsig-more#rsa-sha256"/>' .
            '<Reference URI="">' .
                '<Transforms>' .
                    '<Transform Algorithm="http://www.w3.org/2000/09/xmldsig#enveloped-signature"/>' .
                    '<Transform Algorithm="http://www.w3.org/2001/10/xml-exc-c14n#"/>' .
                '</Transforms>' .
                '<DigestMethod Algorithm="http://www.w3.org/2001/04/xmlenc#sha256"/>' .
                '<DigestValue>' . $digestValue . '</DigestValue>' .
            '</Reference>' .
        '</SignedInfo>';

        $signedInfoDom = new DOMDocument();
        $signedInfoDom->loadXML($signedInfoXml);
        $canonicalSignedInfo = $signedInfoDom->C14N(true, false);

        // 5. Firmar el nodo <SignedInfo> usando la clave privada (RSA-SHA256)
        $signature = '';
        if (!openssl_sign($canonicalSignedInfo, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
            throw new Exception("Error al realizar la firma digital con openssl_sign.");
        }
        $signatureValue = base64_encode($signature);

        // 6. Construir el nodo completo <Signature>
        $signatureNodeXml = '<Signature xmlns="http://www.w3.org/2000/09/xmldsig#">' .
            $signedInfoXml .
            '<SignatureValue>' . $signatureValue . '</SignatureValue>' .
            '<KeyInfo>' .
                '<X509Data>' .
                    '<X509Certificate>' . $x509Certificate . '</X509Certificate>' .
                '</X509Data>' .
            '</KeyInfo>' .
        '</Signature>';

        // Cargar el fragmento de la firma e importarlo al documento principal
        $signatureDom = new DOMDocument();
        $signatureDom->loadXML($signatureNodeXml);
        $importedSignature = $dom->importNode($signatureDom->documentElement, true);

        // Insertar al final del nodo raíz de la factura
        $dom->documentElement->appendChild($importedSignature);

        return $dom->saveXML();
    }
}
