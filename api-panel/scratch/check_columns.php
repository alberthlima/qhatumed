<?php

try {
    $pdo = new PDO('mysql:host=127.0.0.1;dbname=farmacia_2026;charset=utf8', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $pdo->query("DESCRIBE products");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    print_r($columns);

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
