<?php
$mysqli = new mysqli("127.0.0.1", "root", "");
$mysqli->select_db("farmacia_2026");

echo "=== farmacia_2026.products samples ===\n";
$res = $mysqli->query("SELECT id, name, active_ingredient, therapeutic_action, indications FROM products WHERE active_ingredient IS NOT NULL AND active_ingredient != 'NINGUNA' AND active_ingredient != 'No farmacológico/Cosmético' LIMIT 15");
while ($row = $res->fetch_assoc()) {
    echo "Product: {$row['name']}\n";
    echo "  Active Ingredient: {$row['active_ingredient']}\n";
    echo "  Therapeutic Action: {$row['therapeutic_action']}\n";
    echo "  Indications: {$row['indications']}\n\n";
}
