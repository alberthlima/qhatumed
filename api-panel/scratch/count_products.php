<?php

try {
    $pdo = new PDO('mysql:host=127.0.0.1;dbname=farmacia_2026;charset=utf8', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $total = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
    $withActive = $pdo->query("SELECT COUNT(*) FROM products WHERE active_ingredient IS NOT NULL AND active_ingredient != ''")->fetchColumn();
    $withAction = $pdo->query("SELECT COUNT(*) FROM products WHERE therapeutic_action IS NOT NULL AND therapeutic_action != ''")->fetchColumn();

    echo "Total productos en BD: $total\n";
    echo "Productos con Principio Activo: $withActive\n";
    echo "Productos con Accion Terapeutica: $withAction\n";

    // Mostrar 5 de ejemplo
    $stmt = $pdo->query("SELECT id, barcode, name, active_ingredient, therapeutic_action FROM products LIMIT 5");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    print_r($rows);

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
