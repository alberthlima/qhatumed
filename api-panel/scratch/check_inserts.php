<?php

$sqlFile = 'c:/xampp/htdocs/farmacia_misalud/farmacia2026/db_farma.sql';
$content = file_get_contents($sqlFile);

$pos = strpos($content, "INSERT INTO `producto`");
if ($pos !== false) {
    echo substr($content, $pos, 500);
} else {
    $pos2 = strpos(strtolower($content), "insert into `producto`");
    if ($pos2 !== false) {
        echo "Found lowercase: " . substr($content, $pos2, 500);
    } else {
        echo "Not found at all.";
    }
}
