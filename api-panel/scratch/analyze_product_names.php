<?php

try {
    $pdo = new PDO('mysql:host=127.0.0.1;dbname=farmacia_2026;charset=utf8', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $pdo->query("SELECT name FROM products");
    $names = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $firstWords = [];
    foreach ($names as $name) {
        // Limpiar espacios y obtener la primera palabra
        $cleanName = trim(preg_replace('/\s+/', ' ', $name));
        $parts = explode(' ', $cleanName);
        $firstWord = strtoupper($parts[0]);
        // Quitar caracteres especiales
        $firstWord = preg_replace('/[^A-Z]/', '', $firstWord);
        if (!empty($firstWord)) {
            $firstWords[$firstWord] = ($firstWords[$firstWord] ?? 0) + 1;
        }
    }

    arsort($firstWords);
    echo "Total palabras clave (primeras palabras) únicas: " . count($firstWords) . "\n\n";
    echo "=== TOP 50 PALABRAS CLAVE ===\n";
    print_r(array_slice($firstWords, 0, 50, true));

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
