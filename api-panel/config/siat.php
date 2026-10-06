<?php

return [
    /*
    |--------------------------------------------------------------------------
    | SIAT API Service Microservice Configuration
    |--------------------------------------------------------------------------
    | URL y credenciales de acceso al microservicio independiente siat-api-service.
    */
    'api_url' => env('SIAT_API_SERVICE_URL', 'http://127.0.0.1:8000/api/v1'),
    'api_key' => env('SIAT_API_KEY', ''),
    'timeout' => env('SIAT_API_TIMEOUT', 30),

    /*
    | Sucursal y Punto de Venta por defecto
    */
    'sucursal' => env('SIAT_CODIGO_SUCURSAL', 0),
    'punto_venta' => env('SIAT_CODIGO_PUNTO_VENTA', 0),

    /*
    | Datos de cabecera y actividad de la empresa
    */
    'nit'                 => env('SIAT_NIT', '6789406016'),
    'razon_social'        => env('SIAT_RAZON_SOCIAL', 'FARMACIA MISALUD'),
    'municipio'           => env('SIAT_MUNICIPIO', 'Santa Cruz'),
    'direccion'           => env('SIAT_DIRECCION', 'Av. Principal #123 (Zona Norte)'),
    'actividad_economica' => env('SIAT_ACTIVIDAD_ECONOMICA', '474110'),
    'debug'               => env('SIAT_DEBUG', true),
];
