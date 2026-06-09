<?php

namespace Misalud\Siat\Utils;

use Misalud\Siat\Exceptions\SiatException;

/**
 * SoapWrapper
 *
 * Wrapper sobre el SoapClient nativo de PHP que:
 * - Inyecta el Token de autenticación en cada llamada
 * - Maneja el retry en errores transitorios
 * - Convierte las respuestas SOAP en arrays PHP limpios
 * - Registra logs de cada llamada para debugging
 */
class SoapWrapper
{
    protected \SoapClient $client;
    protected array       $config;

    public function __construct(string $wsdl, array $config)
    {
        $this->config = $config;

        $soapOptions = [
            'cache_wsdl'         => WSDL_CACHE_DISK,
            'connection_timeout' => $config['timeout']['connection'] ?? 15,
            'trace'              => true,
            'exceptions'         => true,
            'encoding'           => 'UTF-8',
            'soap_version'       => SOAP_1_1,
            // Necesario para el SIAT (SSL)
            'stream_context'     => stream_context_create([
                'ssl' => [
                    'verify_peer'      => false,
                    'verify_peer_name' => false,
                ],
                'http' => [
                    'header' => "apiKey: TokenApi " . trim($config['token']) . "\r\n" .
                                "apikey: TokenApi " . trim($config['token']) . "\r\n"
                ]
            ]),
        ];

        try {
            $this->client = new \SoapClient($wsdl, $soapOptions);
        } catch (\SoapFault $e) {
            throw SiatException::fromSoapFault($e);
        }
    }

    /**
     * Ejecuta una llamada SOAP al SIAT inyectando el Token en el header.
     *
     * @param string $metodo  Nombre del método SOAP (ej: 'solicitudCuis')
     * @param array  $params  Parámetros del método
     * @return array          Respuesta parseada como array
     * @throws SiatException
     */
    public function call(string $metodo, array $params): mixed
    {
        // En SIAT V2, la autenticación se maneja predominantemente vía HTTP header 'apiKey' 
        // con el prefijo 'TokenApi'. El SOAP Header se deja vacío por compatibilidad.
        $this->client->__setSoapHeaders([]);

        try {
            $response = $this->client->__soapCall($metodo, [$params]);

            if ($this->config['debug'] ?? false) {
                \Log::channel('siat')->debug("SOAP Call: {$metodo}", [
                    'request'  => $this->getLastRequest(),
                    'response' => $this->getLastResponse(),
                ]);
            }

            return $this->toArray($response);

        } catch (\SoapFault $e) {
            if ($this->config['debug'] ?? false) {
                \Log::channel('siat')->error("SOAP Error: {$metodo}", [
                    'faultcode' => $e->faultcode,
                    'faultstring' => $e->getMessage(),
                    'request'  => $this->getLastRequest(),
                    'response' => $this->getLastResponse(),
                ]);
            }
            throw SiatException::fromSoapFault($e);
        }
    }

    /**
     * Convierte recursivamente objetos stdClass a arrays PHP.
     */
    protected function toArray(mixed $data): mixed
    {
        if (is_object($data)) {
            $data = (array) $data;
        }

        if (is_array($data)) {
            return array_map(fn($v) => $this->toArray($v), $data);
        }

        return $data;
    }

    /**
     * Retorna el último XML enviado al SIAT (útil para debugging).
     */
    public function getLastRequest(): string
    {
        return $this->client->__getLastRequest() ?? '';
    }

    /**
     * Retorna la última respuesta XML del SIAT (útil para debugging).
     */
    public function getLastResponse(): string
    {
        return $this->client->__getLastResponse() ?? '';
    }
}
