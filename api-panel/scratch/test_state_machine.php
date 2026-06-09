<?php

$sqlFile = 'c:/xampp/htdocs/farmacia_misalud/farmacia2026/db_farma.sql';
echo "Cargando archivo...\n";
$sql = file_get_contents($sqlFile);
$length = strlen($sql);

echo "Buscando inserciones de productos...\n";
$offset = 0;
$rows = [];

while (($valuesStart = strpos($sql, "INSERT INTO `producto` (", $offset)) !== false) {
    $pos = strpos($sql, "VALUES", $valuesStart);
    if ($pos === false) break;
    $pos += 6;
    
    $inString = false;
    $escape = false;
    $inRow = false;
    $currentRow = "";
    
    for ($i = $pos; $i < $length; $i++) {
        $char = $sql[$i];
        
        if ($inRow) {
            if ($inString) {
                if ($escape) {
                    $currentRow .= $char;
                    $escape = false;
                } elseif ($char === '\\') {
                    $currentRow .= $char;
                    $escape = true;
                } elseif ($char === "'") {
                    $currentRow .= $char;
                    $inString = false;
                } else {
                    $currentRow .= $char;
                }
            } else {
                if ($char === "'") {
                    $currentRow .= $char;
                    $inString = true;
                } elseif ($char === ')') {
                    $rows[] = $currentRow;
                    $currentRow = "";
                    $inRow = false;
                } else {
                    $currentRow .= $char;
                }
            }
        } else {
            if ($char === '(') {
                $inRow = true;
            } elseif ($char === ';') {
                $offset = $i;
                break;
            }
        }
    }
}

echo "Total filas extraidas: " . count($rows) . "\n";

// Mostrar las primeras 5
for ($k = 0; $k < min(5, count($rows)); $k++) {
    $fields = str_getcsv($rows[$k], ",", "'");
    echo "Fila $k: " . count($fields) . " campos.\n";
    if (count($fields) >= 11) {
        echo "   Barcode: {$fields[2]} | Nombre: {$fields[3]}\n";
        echo "   Accion: {$fields[9]} | Principio: {$fields[10]}\n";
    }
}

// Contar cuántos tienen principio activo válido (distinto de NINGUNA, NINGUNO, etc.)
$validActiveCount = 0;
foreach ($rows as $r) {
    $fields = str_getcsv($r, ",", "'");
    if (count($fields) >= 11) {
        $val = strtoupper(trim($fields[10]));
        if (!empty($val) && $val !== 'NINGUNA' && $val !== 'NINGUNO' && $val !== 'NINGUN' && $val !== 'NO TIENE') {
            $validActiveCount++;
        }
    }
}
echo "Total con Principio Activo útil: $validActiveCount\n";
