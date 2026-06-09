<?php
$mysqli = new mysqli("127.0.0.1", "root", "");
$mysqli->select_db("farmacia_2026");

$res = $mysqli->query("SELECT active_ingredient, COUNT(*) as cnt FROM products GROUP BY active_ingredient ORDER BY cnt DESC");
echo "Total unique active ingredients in farmacia_2026: " . $res->num_rows . "\n";
echo "Top 50 active ingredients:\n";
$i = 0;
while ($row = $res->fetch_assoc()) {
    echo "  - " . ($row['active_ingredient'] ?: '[NULL]') . ": " . $row['cnt'] . " products\n";
    if (++$i >= 50) break;
}
