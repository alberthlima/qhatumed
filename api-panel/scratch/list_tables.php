<?php

$sqlFile = 'c:/xampp/htdocs/farmacia_misalud/farmacia2026/db_farma.sql';
$content = file_get_contents($sqlFile);

preg_match_all('/CREATE TABLE `([^`]+)`/i', $content, $matches);
echo "Tablas encontradas en db_farma.sql:\n";
print_r(array_unique($matches[1]));
