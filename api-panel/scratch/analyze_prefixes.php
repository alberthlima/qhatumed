<?php
$mysqli = new mysqli("127.0.0.1", "root", "");
$mysqli->select_db("farma");

$res = $mysqli->query("SELECT nombre_producto FROM producto");
$prefixes = [];
while ($row = $res->fetch_assoc()) {
    $name = trim(preg_replace('/\s+/', ' ', $row['nombre_producto']));
    $parts = explode(' ', $name);
    $firstWord = strtoupper($parts[0] ?? '');
    $firstWordClean = preg_replace('/[^A-Z]/', '', $firstWord);
    if (strlen($firstWordClean) >= 3) {
        if ($firstWordClean === 'ACIDO' && isset($parts[1])) {
            $secondWord = strtoupper($parts[1]);
            $secondWordClean = preg_replace('/[^A-Z]/', '', $secondWord);
            $prefix = $firstWordClean . ' ' . $secondWordClean;
        } else {
            $prefix = $firstWordClean;
        }
        $prefixes[$prefix] = ($prefixes[$prefix] ?? 0) + 1;
    }
}

arsort($prefixes);
echo "Total unique prefixes: " . count($prefixes) . "\n";
echo "Top 100 prefixes:\n";
$i = 0;
foreach ($prefixes as $pref => $count) {
    echo "  $pref: $count\n";
    if (++$i >= 100) break;
}
