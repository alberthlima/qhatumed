<?php

$sqlFile = 'c:/xampp/htdocs/farmacia_misalud/farmacia2026/db_farma.sql';
echo "Cargando archivo...\n";
$content = file_get_contents($sqlFile);

echo "Buscando inserciones de productos...\n";
$offset = 0;
$allRecords = [];

while (($startPos = strpos($content, "INSERT INTO `producto` VALUES", $offset)) !== false) {
    $endPos = strpos($content, ";", $startPos);
    if ($endPos === false) break;
    $chunk = substr($content, $startPos, $endPos - $startPos);
    
    // Extraer todo lo que está entre paréntesis
    preg_match_all('/\(([^)]+)\)/', $chunk, $matches);
    foreach ($matches[1] as $match) {
        $allRecords[] = $match;
    }
    
    $offset = $endPos;
}

echo "Total registros encontrados en texto: " . count($allRecords) . "\n";

// Analizar un registro para ver cómo separar los campos respetando comillas
function parseSqlRow($rowText) {
    // Usamos str_getcsv con comilla simple como enclosure
    return str_getcsv($rowText, ",", "'");
}

// Mostrar los primeros 10 registros procesados
$count = 0;
foreach ($allRecords as $rowText) {
    $fields = parseSqlRow($rowText);
    if (count($fields) >= 11) {
        echo "ID: {$fields[0]} | Barcode: {$fields[2]} | Nombre: {$fields[3]}\n";
        echo "   Acción: {$fields[9]}\n";
        echo "   Principio: {$fields[10]}\n";
        echo "   Indicaciones: " . substr($fields[7], 0, 50) . "...\n";
        $count++;
        if ($count >= 10) break;
    }
}
