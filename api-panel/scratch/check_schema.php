<?php

$sqlFile = 'c:/xampp/htdocs/farmacia_misalud/farmacia2026/db_farma.sql';
$content = file_get_contents($sqlFile);

$pos = strpos($content, "CREATE TABLE `producto`");
if ($pos !== false) {
    $end = strpos($content, ";", $pos);
    echo substr($content, $pos, $end - $pos + 1);
} else {
    echo "No se encontro la definicion de la tabla producto.";
}
