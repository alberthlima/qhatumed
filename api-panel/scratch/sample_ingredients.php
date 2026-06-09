<?php

$sqlFile = 'c:/xampp/htdocs/farmacia_misalud/farmacia2026/db_farma.sql';
$sql = file_get_contents($sqlFile);
$length = strlen($sql);

$offset = 0;
$rows = [];

while (($valuesStart = strpos($sql, "INSERT INTO `producto` (", $offset)) !== false) {
    $pos = strpos($sql, "VALUES", $valuesStart);
    if ($pos === false) break;
    $pos += 6;
    
    $inString = false;
    $escape = false;
    $inRow = false;
    $currentRow = "";
    
    for ($i = $pos; $i < $length; $i++) {
        $char = $sql[$i];
        
        if ($inRow) {
            if ($inString) {
                if ($escape) {
                    $currentRow .= $char;
                    $escape = false;
                } elseif ($char === '\\') {
                    $currentRow .= $char;
                    $escape = true;
                } elseif ($char === "'") {
                    $currentRow .= $char;
                    $inString = false;
                } else {
                    $currentRow .= $char;
                }
            } else {
                if ($char === "'") {
                    $currentRow .= $char;
                    $inString = true;
                } elseif ($char === ')') {
                    $rows[] = $currentRow;
                    $currentRow = "";
                    $inRow = false;
                } else {
                    $currentRow .= $char;
                }
            }
        } else {
            if ($char === '(') {
                $inRow = true;
            } elseif ($char === ';') {
                $offset = $i;
                break;
            }
        }
    }
}

$ingredients = [];
$actions = [];

foreach ($rows as $r) {
    $fields = str_getcsv($r, ",", "'");
    if (count($fields) >= 11) {
        $ing = trim($fields[10]);
        $act = trim($fields[9]);
        if (!empty($ing)) {
            $ingredients[$ing] = ($ingredients[$ing] ?? 0) + 1;
        }
        if (!empty($act)) {
            $actions[$act] = ($actions[$act] ?? 0) + 1;
        }
    }
}

arsort($ingredients);
arsort($actions);

echo "=== TOP PRINCIPIOS ACTIVOS ===\n";
print_r(array_slice($ingredients, 0, 30, true));

echo "\n=== TOP ACCIONES TERAPEUTICAS ===\n";
print_r(array_slice($actions, 0, 30, true));
