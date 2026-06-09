<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Entorno de Operación
    |--------------------------------------------------------------------------
    | 1 = Producción (facturas reales con validez tributaria)
    | 2 = Piloto/Pruebas (desarrollo y testing, sin efecto fiscal)
    */
    'ambiente' => env('SIAT_CODIGO_AMBIENTE', 2),

    /*
    |--------------------------------------------------------------------------
    | Modalidad de Facturación
    |--------------------------------------------------------------------------
    | 1 = Electrónica  (requiere certificado digital .p12 / .pfx)
    | 2 = Computarizada (usa código de control, sin firma digital)
    |
    | FARMACIA MISALUD  => SIAT_CODIGO_MODALIDAD=2 (Computarizada)
    | EMPRESA SERVICIOS => SIAT_CODIGO_MODALIDAD=1 (Electrónica)
    */
    'modalidad' => env('SIAT_CODIGO_MODALIDAD', 2),

    /*
    |--------------------------------------------------------------------------
    | Datos de Identificación del Emisor
    |--------------------------------------------------------------------------
    */
    'nit' => env('SIAT_NIT'),
    'token' => env('SIAT_TOKEN'),
    'codigo_sistema' => env('SIAT_CODIGO_SISTEMA'),
    'sucursal' => env('SIAT_CODIGO_SUCURSAL', 0),
    'punto_venta' => env('SIAT_CODIGO_PUNTO_VENTA', 0),

    /*
    |--------------------------------------------------------------------------
    | URL Base del Servicio SIAT
    |--------------------------------------------------------------------------
    | Piloto:     https://pilotosiatservicios.impuestos.gob.bo/v2
    | Producción: https://siatservicios.impuestos.gob.bo/v2
    */
    'url' => env('SIAT_URL', 'https://pilotosiatservicios.impuestos.gob.bo/v2'),

    /*
    |--------------------------------------------------------------------------
    | WSDL — Endpoints de cada Servicio SIAT
    |--------------------------------------------------------------------------
    | Se construyen dinámicamente desde la URL base.
    | Solo cambia si el SIN modifica su estructura.
    */
    'wsdl' => [
        'codigos' => env('SIAT_URL', 'https://pilotosiatservicios.impuestos.gob.bo/v2') . '/FacturacionCodigos?wsdl',
        'sincronizacion' => env('SIAT_URL', 'https://pilotosiatservicios.impuestos.gob.bo/v2') . '/FacturacionSincronizacion?wsdl',
        'operaciones' => env('SIAT_URL', 'https://pilotosiatservicios.impuestos.gob.bo/v2') . '/FacturacionOperaciones?wsdl',
        'computarizada' => env('SIAT_URL', 'https://pilotosiatservicios.impuestos.gob.bo/v2') . '/ServicioFacturacionComputarizada?wsdl',
        'compra_venta' => env('SIAT_URL', 'https://pilotosiatservicios.impuestos.gob.bo/v2') . '/FacturacionCompraVenta?wsdl',
        'electronica' => env('SIAT_URL', 'https://pilotosiatservicios.impuestos.gob.bo/v2') . '/ServicioFacturacionElectronica?wsdl',
    ],

    /*
    |--------------------------------------------------------------------------
    | Certificado Digital (Solo para Modalidad Electrónica)
    |--------------------------------------------------------------------------
    | Ruta al archivo .p12 o .pfx del certificado digital emitido por el SIN.
    | Dejar vacío si usa Modalidad Computarizada.
    */
    'certificado' => [
        'ruta' => env('SIAT_CERT_PATH'),
        'password' => env('SIAT_CERT_PASSWORD'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Caché de Códigos CUIS / CUFD
    |--------------------------------------------------------------------------
    | El CUIS no expira frecuentemente; el CUFD debe renovarse cada 24 horas.
    | Se almacenan en caché para evitar requests innecesarios al SIAT.
    */
    'cache' => [
        'driver' => env('SIAT_CACHE_DRIVER', 'database'),
        'ttl_cuis' => 60 * 24 * 30, // 30 días en minutos
        'ttl_cufd' => 60 * 23,      // 23 horas en minutos (CUFD válido 24h)
    ],

    /*
    |--------------------------------------------------------------------------
    | Opciones de Timeout SOAP
    |--------------------------------------------------------------------------
    */
    'timeout' => [
        'connection' => 15,
        'response' => 45,
    ],
    /*
    |--------------------------------------------------------------------------
    | Modo Debug
    |--------------------------------------------------------------------------
    | Si está en true, registra todas las peticiones y respuestas SOAP en
    | storage/logs/siat.log para auditoría y corrección de errores.
    */
    'debug' => env('SIAT_DEBUG', true),

    /*
    |--------------------------------------------------------------------------
    | Datos de la Empresa (Para Cabecera de Factura)
    |--------------------------------------------------------------------------
    */
    'razon_social' => 'FARMACIA MISALUD',
    'municipio'    => 'Santa Cruz',
    'direccion'    => 'Av. Principal #123 (Zona Norte)',
    'actividad_economica' => '4772100', // Código de actividad farmacéutica
];
