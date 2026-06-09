<?php
$mysqli = new mysqli("127.0.0.1", "root", "");

$res = $mysqli->query("SHOW DATABASES");
while ($row = $res->fetch_row()) {
    $db = $row[0];
    if ($mysqli->select_db($db)) {
        $tablesRes = $mysqli->query("SHOW TABLES");
        if ($tablesRes) {
            while ($tRow = $tablesRes->fetch_row()) {
                $table = $tRow[0];
                if (in_array(strtolower($table), ['producto', 'productos', 'product', 'products'])) {
                    $countRes = $mysqli->query("SELECT COUNT(*) FROM `$table`");
                    $count = $countRes ? $countRes->fetch_row()[0] : 'N/A';
                    if ($count > 100) {
                        echo "Database: $db | Table: $table | Rows: $count\n";
                    }
                }
            }
        }
    }
}
