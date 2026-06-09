<?php

try {
    $pdo = new PDO('mysql:host=127.0.0.1;dbname=farmacia_2026;charset=utf8', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("UPDATE products SET active_ingredient = NULL, therapeutic_action = NULL, indications = NULL WHERE id = 6");
    echo "Reset OK!\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
