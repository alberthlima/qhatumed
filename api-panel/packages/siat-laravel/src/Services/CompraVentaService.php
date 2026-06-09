<?php

namespace Misalud\Siat\Services;

use Misalud\Siat\DTOs\FacturaCabeceraDTO;
use Misalud\Siat\DTOs\FacturaDetalleDTO;
use Misalud\Siat\DTOs\FacturaEmitidaDTO;
use Misalud\Siat\Exceptions\SiatException;
use Misalud\Siat\Utils\CufGenerator;
use Misalud\Siat\Utils\SoapWrapper;
use Misalud\Siat\Utils\XmlSigner;

/**
 * CompraVentaService
 *
 * Maneja la emisión y anulación de facturas del Sector 1 (Compra-Venta).
 * Este es el sector estándar para comercios: farmacias, tiendas, etc.
 *
 * Flujo de emisión:
 *   1. Construir XML de la factura
 *   2. (Solo Electrónica) Firmar XML con certificado digital
 *   3. Gzip + SHA256 + Base64
 *   4. Enviar via SOAP → recepcionFactura
 *   5. Retornar CUF + codigoRecepcion + URL del QR
 */
class CompraVentaService
{
    protected SoapWrapper $soap;
    protected CodigosService $codigos;
    protected CufGenerator $cufGen;
    protected array $config;

    public function __construct(array $config, CodigosService $codigos)
    {
        $this->config  = $config;
        $this->codigos = $codigos;
        $this->cufGen  = new CufGenerator();

        $modalidad = (int) ($config['modalidad'] ?? 2);
        if ($modalidad === 1) {
            $wsdl = $config['wsdl']['electronica'] ?? $config['wsdl']['compra_venta'];
        } else {
            $wsdl = $config['wsdl']['computarizada'] ?? $config['wsdl']['compra_venta'];
        }

        $this->soap    = new SoapWrapper($wsdl, $config);
    }

    /**
     * Emite una factura de Compra-Venta (Sector 1).
     *
     * @param FacturaCabeceraDTO $cabecera  Datos del cliente y totales
     * @param FacturaDetalleDTO[] $detalles Líneas de detalle de la factura
     * @return FacturaEmitidaDTO             Resultado con CUF, estado y QR
     * @throws SiatException
     */
    public function emitir(FacturaCabeceraDTO $cabecera, array $detalles): FacturaEmitidaDTO
    {
        // 1. Obtener CUFD del día (cacheado automáticamente)
        $cufd = $this->codigos->obtenerCufd();

        // 2. Generar CUF único para esta factura
        $cuf = $this->cufGen->generar(
            nit:               (int) $this->config['nit'],
            fechaEmision:      $cabecera->fechaEmision,
            codigoSucursal:    $this->config['sucursal'],
            codigoModalidad:   $this->config['modalidad'],
            codigoEmision:     1, // 1 = Online
            codigoDocSector:   1, // 1 = Compra-Venta
            numeroFactura:     $cabecera->numeroFactura,
            codigoPuntoVenta:  $this->config['punto_venta'],
            tipoFactura:       1, // 1 = Factura con Derecho a Crédito Fiscal
            codigoControl:     $cufd['codigoControl']
        );

        $cabecera->cuf  = $cuf;
        $cabecera->cufd = $cufd['codigo'];

        // 3. Construir XML de la factura
        $xml = $this->construirXml($cabecera, $detalles);

        // Si es modalidad electrónica (1), firmamos digitalmente el XML
        if ((int) ($this->config['modalidad'] ?? 2) === 1) {
            $xml = XmlSigner::sign(
                $xml,
                $this->config['certificado']['ruta'] ?? '',
                $this->config['certificado']['password'] ?? ''
            );
        }

        // 4. Preparar el archivo (Gzip + SHA256)
        [$hash, $archivoBinary] = $this->prepararArchivo($xml);

        // 5. Enviar al SIAT
        $codigoRecepcion = $this->enviarAlSiat($hash, $archivoBinary, $cufd['codigo'], $cabecera);

        // 6. Construir y retornar el resultado
        return new FacturaEmitidaDTO(
            cuf:              $cuf,
            cufd:             $cufd['codigo'],
            codigoRecepcion:  $codigoRecepcion,
            numeroFactura:    $cabecera->numeroFactura,
            fechaEmision:     $cabecera->fechaEmision,
            estado:           'VALIDA',
            qrUrl:            $this->generarUrlQr($cuf),
        );
    }

    /**
     * Anula una factura previamente emitida.
     *
     * @param string $cuf           CUF de la factura a anular
     * @param int    $codigoMotivo  Motivo de anulación (catálogo SIAT)
     * @throws SiatException
     */
    public function anular(string $cuf, int $codigoMotivo = 1): bool
    {
        $cufd = $this->codigos->obtenerCufd();
        $cuis = $this->codigos->obtenerCuis();

        $params = [
            'SolicitudServicioAnulacionFactura' => [
                'codigoAmbiente'       => $this->config['ambiente'],
                'codigoDocumentoSector'=> 1,
                'codigoEmision'        => 1,
                'codigoModalidad'      => $this->config['modalidad'],
                'codigoPuntoVenta'     => $this->config['punto_venta'],
                'codigoSistema'        => $this->config['codigo_sistema'],
                'codigoSucursal'       => $this->config['sucursal'],
                'cufd'                 => $cufd['codigo'],
                'cuis'                 => $cuis,
                'nit'                  => $this->config['nit'],
                'cuf'                  => $cuf,
                'codigoMotivo'         => $codigoMotivo,
                'tipoFacturaDocumento' => 1,
            ],
        ];

        $response  = $this->soap->call('anulacionFactura', $params);
        $resultado = $response['RespuestaAnulacionFactura'] ?? [];

        if (empty($resultado['transaccion'])) {
            throw SiatException::transaccionRechazada('Anulación rechazada por el SIAT');
        }

        return true;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Métodos privados
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Construye el XML de la factura Compra-Venta según el esquema del SIN.
     */
    protected function construirXml(FacturaCabeceraDTO $cabecera, array $detalles): string
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = false;

        $rootTag = (int) ($this->config['modalidad'] ?? 2) === 1
            ? 'facturaElectronicaCompraVenta'
            : 'facturaComputarizadaCompraVenta';

        // Namespace del esquema compraVenta del SIN
        $facturaFactura = $dom->createElementNS(
            'https://siat.impuestos.gob.bo/siat/api-v1/factura/compra-venta',
            $rootTag
        );
        $dom->appendChild($facturaFactura);

        // ── CABECERA ──
        $cabeceraNodo = $dom->createElement('cabecera');
        $facturaFactura->appendChild($cabeceraNodo);

        $campos = [
            'nitEmisor'                 => $this->config['nit'],
            'razonSocialEmisor'         => $cabecera->razonSocialEmisor,
            'municipio'                 => $cabecera->municipio,
            'telefono'                  => $cabecera->telefono ?? '',
            'numeroFactura'             => $cabecera->numeroFactura,
            'cuf'                       => $cabecera->cuf,
            'cufd'                      => $cabecera->cufd,
            'codigoSucursal'            => $this->config['sucursal'],
            'direccion'                 => $cabecera->direccion,
            'codigoPuntoVenta'          => $this->config['punto_venta'],
            'fechaEmision'              => $cabecera->fechaEmision->format('Y-m-d\TH:i:s.v'),
            'nombreRazonSocial'         => $cabecera->clienteNombre ?? 'SIN NOMBRE',
            'codigoTipoDocumentoIdentidad' => $cabecera->codigoTipoDocumento ?? 5, // 5=Sin doc
            'numeroDocumento'           => $cabecera->clienteDocumento ?? '0',
            'complemento'               => '',
            'codigoCliente'             => $cabecera->codigoCliente ?? '0',
            'codigoMetodoPago'          => $cabecera->codigoMetodoPago,
            'numeroTarjeta'             => '',
            'montoTotal'                => number_format($cabecera->montoTotal, 2, '.', ''),
            'montoTotalSujetoIva'       => number_format($cabecera->montoTotalSujetoIva ?? $cabecera->montoTotal, 2, '.', ''),
            'codigoMoneda'              => $cabecera->codigoMoneda ?? 1,  // 1 = BOB
            'tipoCambio'                => $cabecera->tipoCambio ?? 1,
            'montoTotalMoneda'          => number_format($cabecera->montoTotal, 2, '.', ''),
            'montoGiftCard'             => 0,
            'descuentoAdicional'        => number_format($cabecera->descuento ?? 0, 2, '.', ''),
            'codigoExcepcion'           => 0,
            'cafc'                      => '',
            'leyenda'                   => $cabecera->leyenda ?? 'Ley N° 453: El proveedor de servicios debe prioritariamente...',
            'usuario'                   => 'cajero',
            'codigoDocumentoSector'     => 1,
        ];

        foreach ($campos as $nombre => $valor) {
            $nodo = $dom->createElement($nombre, htmlspecialchars((string) $valor, ENT_XML1));
            $cabeceraNodo->appendChild($nodo);
        }

        // ── DETALLES ──
        foreach ($detalles as $detalle) {
            $detalleNodo = $dom->createElement('detalle');
            $facturaFactura->appendChild($detalleNodo);

            $camposDetalle = [
                'actividadEconomica'   => $detalle->actividadEconomica,
                'codigoProductoSin'    => $detalle->codigoProductoSin,
                'codigoProducto'       => $detalle->codigoProducto,
                'descripcion'          => $detalle->descripcion,
                'cantidad'             => $detalle->cantidad,
                'unidadMedida'         => $detalle->unidadMedida ?? 1,
                'precioUnitario'       => number_format($detalle->precioUnitario, 2, '.', ''),
                'montoDescuento'       => number_format($detalle->descuento ?? 0, 2, '.', ''),
                'subTotal'             => number_format($detalle->subTotal, 2, '.', ''),
                'codigoDetalleExento'  => '1',
            ];

            foreach ($camposDetalle as $nombre => $valor) {
                $nodo = $dom->createElement($nombre, htmlspecialchars((string) $valor, ENT_XML1));
                $detalleNodo->appendChild($nodo);
            }
        }

        return $dom->saveXML();
    }

    /**
     * Comprime (gzip) y hashea (SHA256) el XML.
     *
     * @return array [hash_sha256, archivo_binary]
     */
    protected function prepararArchivo(string $xml): array
    {
        $compressed  = gzencode($xml, 9);
        $hash        = hash('sha256', $compressed);

        return [$hash, $compressed];
    }

    /**
     * Envía la factura comprimida y codificada al SIAT vía SOAP.
     */
    protected function enviarAlSiat(
        string              $hash,
        string              $archivoBinary,
        string              $cufd,
        FacturaCabeceraDTO  $cabecera
    ): string {
        $cuis = $this->codigos->obtenerCuis();

        $params = [
            'SolicitudServicioRecepcionFactura' => [
                'codigoAmbiente'       => $this->config['ambiente'],
                'codigoDocumentoSector'=> 1,
                'codigoEmision'        => 1,
                'codigoModalidad'      => $this->config['modalidad'],
                'codigoPuntoVenta'     => $this->config['punto_venta'],
                'codigoSistema'        => $this->config['codigo_sistema'],
                'codigoSucursal'       => $this->config['sucursal'],
                'cufd'                 => $cufd,
                'cuis'                 => $cuis,
                'nit'                  => $this->config['nit'],
                'tipoFacturaDocumento' => 1,
                'archivo'              => $archivoBinary,
                'fechaEnvio'           => $cabecera->fechaEmision->format('Y-m-d\TH:i:s.v'),
                'hashArchivo'          => $hash,
            ],
        ];

        $response  = $this->soap->call('recepcionFactura', $params);
        $resultado = $response['RespuestaServicioFacturacion'] ?? [];

        if (empty($resultado['transaccion'])) {
            $mensajes = $this->formatMensajesList($resultado['mensajesList'] ?? []);
            throw SiatException::transaccionRechazada(
                "Factura rechazada por el SIAT: {$mensajes}"
            );
        }

        return $resultado['codigoRecepcion'] ?? '';
    }

    /**
     * Genera la URL del código QR para imprimir en la factura.
     * El QR debe apuntar al portal de verificación del SIN.
     */
    protected function generarUrlQr(string $cuf): string
    {
        $ambiente = $this->config['ambiente'] === 2 ? 'piloto' : 'www';
        return "https://{$ambiente}.impuestos.gob.bo/siat/api-v1/boleta-pdf?cuf={$cuf}";
    }

    /**
     * Genera y firma la factura fuera de línea (Contingencia).
     *
     * @param FacturaCabeceraDTO $cabecera
     * @param FacturaDetalleDTO[] $detalles
     * @return array Datos de la factura generada
     */
    public function emitirFueraDeLinea(FacturaCabeceraDTO $cabecera, array $detalles): array
    {
        $cufd = $this->codigos->obtenerCufd();

        $cuf = $this->cufGen->generar(
            nit:               (int) $this->config['nit'],
            fechaEmision:      $cabecera->fechaEmision,
            codigoSucursal:    $this->config['sucursal'],
            codigoModalidad:   $this->config['modalidad'],
            codigoEmision:     2, // 2 = Fuera de línea / Contingencia
            codigoDocSector:   1, // 1 = Compra-Venta
            numeroFactura:     $cabecera->numeroFactura,
            codigoPuntoVenta:  $this->config['punto_venta'],
            tipoFactura:       1,
            codigoControl:     $cufd['codigoControl']
        );

        $cabecera->cuf  = $cuf;
        $cabecera->cufd = $cufd['codigo'];

        $xml = $this->construirXml($cabecera, $detalles);

        if ((int) ($this->config['modalidad'] ?? 2) === 1) {
            $xml = XmlSigner::sign(
                $xml,
                $this->config['certificado']['ruta'] ?? '',
                $this->config['certificado']['password'] ?? ''
            );
        }

        return [
            'cuf' => $cuf,
            'cufd' => $cufd['codigo'],
            'xml' => $xml,
            'numeroFactura' => $cabecera->numeroFactura,
            'fechaEmision' => $cabecera->fechaEmision,
            'qrUrl' => $this->generarUrlQr($cuf)
        ];
    }

    /**
     * Empaqueta XMLs en ZIP y los envía como paquete de contingencia.
     */
    public function enviarPaqueteContingencia(array $facturasXml, string $codigoRecepcionEvento, string $cufd): string
    {
        $cuis = $this->codigos->obtenerCuis();

        $tempFile = tempnam(sys_get_temp_dir(), 'siat_pkg');
        $zip = new \ZipArchive();
        if ($zip->open($tempFile, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new SiatException("No se pudo crear el archivo ZIP temporal para el paquete de contingencia.");
        }

        foreach ($facturasXml as $filename => $xmlContent) {
            $zip->addFromString($filename, $xmlContent);
        }
        $zip->close();

        $compressed = file_get_contents($tempFile);
        unlink($tempFile);

        $hash = hash('sha256', $compressed);

        $params = [
            'SolicitudServicioRecepcionPaquete' => [
                'codigoAmbiente'       => $this->config['ambiente'],
                'codigoDocumentoSector'=> 1,
                'codigoEmision'        => 2,
                'codigoModalidad'      => $this->config['modalidad'],
                'codigoPuntoVenta'     => $this->config['punto_venta'],
                'codigoSistema'        => $this->config['codigo_sistema'],
                'codigoSucursal'       => $this->config['sucursal'],
                'cufd'                 => $cufd,
                'cuis'                 => $cuis,
                'nit'                  => $this->config['nit'],
                'tipoFacturaDocumento' => 1,
                'archivo'              => $compressed,
                'fechaEnvio'           => date('Y-m-d\TH:i:s.v'),
                'hashArchivo'          => $hash,
                'cantidadFacturas'     => count($facturasXml),
                'codigoRecepcionEventoSignificativo' => $codigoRecepcionEvento
            ],
        ];

        $response  = $this->soap->call('recepcionPaqueteFactura', $params);
        $resultado = $response['RespuestaServicioFacturacion'] ?? [];

        if (empty($resultado['transaccion'])) {
            $mensajes = $this->formatMensajesList($resultado['mensajesList'] ?? []);
            throw SiatException::transaccionRechazada(
                "Envío de paquete rechazado por el SIAT: {$mensajes}"
            );
        }

        return $resultado['codigoRecepcion'] ?? '';
    }

    /**
     * Valida un paquete de contingencia enviado.
     */
    public function validarPaqueteContingencia(string $codigoRecepcion, string $cufd): array
    {
        $cuis = $this->codigos->obtenerCuis();

        $params = [
            'SolicitudServicioValidacionRecepcionPaquete' => [
                'codigoAmbiente'       => $this->config['ambiente'],
                'codigoDocumentoSector'=> 1,
                'codigoEmision'        => 2,
                'codigoModalidad'      => $this->config['modalidad'],
                'codigoPuntoVenta'     => $this->config['punto_venta'],
                'codigoSistema'        => $this->config['codigo_sistema'],
                'codigoSucursal'       => $this->config['sucursal'],
                'cufd'                 => $cufd,
                'cuis'                 => $cuis,
                'nit'                  => $this->config['nit'],
                'tipoFacturaDocumento' => 1,
                'codigoRecepcion'      => $codigoRecepcion
            ],
        ];

        $response  = $this->soap->call('validacionRecepcionPaqueteFactura', $params);
        $resultado = $response['RespuestaServicioFacturacion'] ?? [];

        $codigoEstado = $resultado['codigoEstado'] ?? null;
        $estado = $codigoEstado == 908 ? 'VALIDA' : ($codigoEstado == 904 ? 'OBSERVADA' : 'RECHAZADA');

        $mensajesList = [];
        if (!empty($resultado['mensajesList'])) {
            $formatted = $this->formatMensajesList($resultado['mensajesList']);
            if ($formatted) {
                $mensajesList = explode(' | ', $formatted);
            }
        }

        return [
            'transaccion' => !empty($resultado['transaccion']),
            'codigoEstado' => $codigoEstado,
            'estado' => $estado,
            'mensajes' => $mensajesList
        ];
    }

    /**
     * Formatea la lista de mensajes devuelta por el SIAT de forma robusta.
     */
    protected function formatMensajesList(mixed $mensajesList): string
    {
        if (empty($mensajesList)) {
            return '';
        }

        $msgs = [];
        if (isset($mensajesList['codigo'])) {
            // Un solo mensaje directo (ej: ['codigo' => 995, 'descripcion' => '...'])
            $msgs[] = "[{$mensajesList['codigo']}] " . ($mensajesList['descripcion'] ?? '');
        } elseif (isset($mensajesList['MensajeServicio'])) {
            // Contiene una lista de mensajes bajo 'MensajeServicio'
            $rawMsgs = $mensajesList['MensajeServicio'];
            $rawMsgs = is_array($rawMsgs[0] ?? null) ? $rawMsgs : [$rawMsgs];
            foreach ($rawMsgs as $m) {
                if (isset($m['codigo'])) {
                    $msgs[] = "[{$m['codigo']}] " . ($m['descripcion'] ?? '');
                }
            }
        } elseif (is_array($mensajesList)) {
            // Una lista plana de mensajes o de arrays
            $rawMsgs = is_array($mensajesList[0] ?? null) ? $mensajesList : [$mensajesList];
            foreach ($rawMsgs as $m) {
                if (isset($m['codigo'])) {
                    $msgs[] = "[{$m['codigo']}] " . ($m['descripcion'] ?? '');
                }
            }
        }

        return implode(' | ', $msgs);
    }
}
