<?php
$mysqli = new mysqli("127.0.0.1", "root", "");

$matchByName = 0;
$matchByCode = 0;

// Get names and codes from farma.producto
$mysqli->select_db("farma");
$farmaProducts = [];
$res = $mysqli->query("SELECT id_producto, codigo_producto, nombre_producto FROM producto");
while ($row = $res->fetch_assoc()) {
    $farmaProducts[$row['id_producto']] = [
        'code' => $row['codigo_producto'],
        'name' => $row['nombre_producto']
    ];
}

// Check farmacia_2026.products
$mysqli->select_db("farmacia_2026");
$mappedByName = [];
$mappedByCode = [];
$res = $mysqli->query("SELECT barcode, name, active_ingredient, therapeutic_action, indications FROM products");
while ($row = $res->fetch_assoc()) {
    $mappedByName[strtoupper(trim($row['name']))] = $row;
    if ($row['barcode']) {
        $mappedByCode[strtoupper(trim($row['barcode']))] = $row;
    }
}

foreach ($farmaProducts as $id => $fp) {
    $name = strtoupper(trim($fp['name']));
    $code = strtoupper(trim($fp['code']));
    
    if (isset($mappedByCode[$code])) {
        $matchByCode++;
    }
    if (isset($mappedByName[$name])) {
        $matchByName++;
    }
}

echo "Total farma.producto: " . count($farmaProducts) . "\n";
echo "Match by code (barcode vs codigo_producto): $matchByCode\n";
echo "Match by name: $matchByName\n";
