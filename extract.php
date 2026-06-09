<?php
$content = file_get_contents('c:/xampp/htdocs/farmacia_misalud/farmacia2026/db_farma.sql');
$out = "";

preg_match_all("/CREATE TABLE `(.*?)`/is", $content, $matches);
$out .= "TABLES:\n" . implode(", ", $matches[1]) . "\n\n";

// Find producto creation
if (preg_match("/CREATE TABLE `producto` \((.*?)\) ENGINE=/is", $content, $m)) {
    $out .= "SCHEMA PRODUCTO:\n" . $m[1] . "\n\n";
}

// Find producto inserts
$pos = strpos($content, "INSERT INTO `producto`");
if ($pos !== false) {
    $out .= "INSERTS:\n" . substr($content, $pos, 1000) . "...\n";
}

// Find how many products
if (preg_match_all("/INSERT INTO `producto` \([^\)]+\) VALUES\s*(.*?);/is", $content, $m)) {
    // each $m[1] contains multiple products like (1,...),(2,...)
    $total = 0;
    foreach ($m[1] as $valuesStr) {
        $total += substr_count($valuesStr, '(');
    }
    $out .= "TOTAL PRODUCTOS ESTIMADOS: $total\n";
}

file_put_contents('info.txt', $out);
