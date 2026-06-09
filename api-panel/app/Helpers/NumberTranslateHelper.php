<?php

namespace App\Helpers;

/**
 * NumberTranslateHelper
 * Convierte montos numéricos a su representación literal en español (Específicamente para facturas en Bolivia).
 */
class NumberTranslateHelper
{
    private static $unidades = ['', 'un', 'dos', 'tres', 'cuatro', 'cinco', 'seis', 'siete', 'ocho', 'nueve'];
    private static $dieces = ['diez', 'once', 'doce', 'trece', 'catorce', 'quince', 'dieciséis', 'diecisiete', 'dieciocho', 'diecinueve'];
    private static $decenas = ['', 'diez', 'veinte', 'treinta', 'cuarenta', 'cincuenta', 'sesenta', 'setenta', 'ochenta', 'noventa'];
    private static $centenas = ['', 'cien', 'doscientos', 'trescientos', 'cuatrocientos', 'quinientos', 'seiscientos', 'setecientos', 'ochocientos', 'novecientos'];

    public static function toLiteral($number)
    {
        $parts = explode('.', number_format($number, 2, '.', ''));
        $entero = (int)$parts[0];
        $decimal = $parts[1];

        if ($entero == 0) {
            $literal = 'cero';
        } else {
            $literal = self::convertir($entero);
        }

        return strtoupper($literal . ' ' . $decimal . '/100 BOLIVIANOS');
    }

    private static function convertir($n)
    {
        if ($n < 10) return self::$unidades[$n];
        if ($n < 20) return self::$dieces[$n - 10];
        if ($n < 100) {
            $unidades = $n % 10;
            $decena = (int)($n / 10);
            if ($unidades == 0) return self::$decenas[$decena];
            return self::$decenas[$decena] . ($decena == 2 ? 'i' : ' y ') . self::$unidades[$unidades];
        }
        if ($n < 1000) {
            $decenas = $n % 100;
            $centena = (int)($n / 100);
            if ($n == 100) return 'cien';
            $prefijo = ($centena == 1) ? 'ciento' : self::$centenas[$centena];
            if ($decenas == 0) return $prefijo;
            return $prefijo . ' ' . self::convertir($decenas);
        }
        if ($n < 1000000) {
            $miles = (int)($n / 1000);
            $resto = $n % 1000;
            $prefijo = ($miles == 1) ? 'mil' : self::convertir($miles) . ' mil';
            if ($resto == 0) return $prefijo;
            return $prefijo . ' ' . self::convertir($resto);
        }
        
        return 'monto demasiado grande';
    }
}
