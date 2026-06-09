<?php
$mysqli = new mysqli("127.0.0.1", "root", "");

echo "=== VERIFYING farmacia_2026.products ===\n";
$mysqli->select_db("farmacia_2026");
$res = $mysqli->query("SELECT name, active_ingredient, therapeutic_action, indications, dosage FROM products WHERE barcode IN ('FPR-01024', 'FPR-04181', 'FPR-01008', 'FPR-01036', 'FPR-01058')");
while ($row = $res->fetch_assoc()) {
    echo "Product: {$row['name']}\n";
    echo "  Active: {$row['active_ingredient']}\n";
    echo "  Action: {$row['therapeutic_action']}\n";
    echo "  Indications: {$row['indications']}\n";
    echo "  Dosage: {$row['dosage']}\n\n";
}

echo "=== VERIFYING farma.producto ===\n";
$mysqli->select_db("farma");
$res = $mysqli->query("SELECT nombre_producto, principio_activo_producto, accion_terapeutica_producto, indicaciones_producto, dosificacion_producto FROM producto WHERE codigo_producto IN ('FPR-01024', 'FPR-04181', 'FPR-01008', 'FPR-01036', 'FPR-01058')");
while ($row = $res->fetch_assoc()) {
    echo "Product: {$row['nombre_producto']}\n";
    echo "  Active: {$row['principio_activo_producto']}\n";
    echo "  Action: {$row['accion_terapeutica_producto']}\n";
    echo "  Indications: {$row['indicaciones_producto']}\n";
    echo "  Dosage: {$row['dosificacion_producto']}\n\n";
}
