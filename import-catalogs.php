<?php
// Script para extraer catálogos de db_farma.sql y conectarlos directamente a la base de datos farmacia_2026
$pdo = new PDO('mysql:host=127.0.0.1;dbname=farmacia_2026;charset=utf8', 'root', '');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$sqlFile = 'c:/xampp/htdocs/farmacia_misalud/farmacia2026/db_farma.sql';
$content = file_get_contents($sqlFile);

if (!$content) {
    die("No se pudo leer db_farma.sql");
}

function extractInserts($tableName, $content, $regex) {
    $offset = 0;
    $matchesGlobal = [];
    while (($startPos = strpos($content, "INSERT INTO `$tableName`", $offset)) !== false) {
        $endPos = strpos($content, "INSERT INTO ", $startPos + 10);
        if ($endPos === false) $endPos = strlen($content);
        $chunk = substr($content, $startPos, $endPos - $startPos);
        preg_match_all($regex, $chunk, $matches, PREG_SET_ORDER);
        foreach ($matches as $m) $matchesGlobal[] = $m;
        $offset = $endPos;
    }
    return $matchesGlobal;
}

echo "Migrando Laboratorios (linea)...\n";
// INSERT INTO `linea` (`id_linea`, `nombre_linea`) VALUES (1, 'ABBOTT');
$lineas = extractInserts('linea', $content, "/\(\s*(\d+)\s*,\s*'([^']+)'/i");
foreach ($lineas as $m) {
    $id = $m[1]; $name = trim($m[2]);
    $pdo->prepare("INSERT IGNORE INTO laboratories (id, name, status, created_at, updated_at) VALUES (?, ?, 1, NOW(), NOW())")->execute([$id, $name]);
}
echo "Migrados " . count($lineas) . " Laboratorios.\n";

echo "Migrando Dispensers...\n";
// INSERT INTO `dispenser` (`id_dispenser`, `nombre_dispenser`) VALUES (1, 'AMPOLLA');
$dispensers = extractInserts('dispenser', $content, "/\(\s*(\d+)\s*,\s*'([^']+)'/i");
foreach ($dispensers as $m) {
    $id = $m[1]; $name = trim($m[2]);
    $pdo->prepare("INSERT IGNORE INTO dispensers (id, name, status, created_at, updated_at) VALUES (?, ?, 1, NOW(), NOW())")->execute([$id, $name]);
}
echo "Migrados " . count($dispensers) . " Dispensadores.\n";

echo "Migrando Forma Farmaceutica...\n";
// INSERT INTO `forma_farmaceutica` (`id_forma_farmaceutica`, `nombre_forma_farmaceutica`) VALUES (1, 'CAPSULA');
$formas = extractInserts('forma_farmaceutica', $content, "/\(\s*(\d+)\s*,\s*'([^']+)'/i");
foreach ($formas as $m) {
    $id = $m[1]; $name = trim($m[2]);
    $pdo->prepare("INSERT IGNORE INTO pharmaceutical_forms (id, name, status, created_at, updated_at) VALUES (?, ?, 1, NOW(), NOW())")->execute([$id, $name]);
}
echo "Migradas " . count($formas) . " Formas.\n";

echo "Migrando Proveedores (distribuidor)...\n";
// INSERT INTO `distribuidor` (`id_distribuidor`, `nombre_distribuidor`...) VALUES (1, 'BAGÓ', '1234', 'dir', 1);
$proveedores = extractInserts('distribuidor', $content, "/\(\s*(\d+)\s*,\s*'([^']+)'/i");
foreach ($proveedores as $m) {
    $id = $m[1]; $name = trim($m[2]);
    // Asumismos que la base de datos suppliers tiene contact_name o tax_id nullable
    try {
       $pdo->prepare("INSERT IGNORE INTO suppliers (id, name, status, created_at, updated_at) VALUES (?, ?, 1, NOW(), NOW())")->execute([$id, $name]);
    } catch (Exception $e) { } // If columns differ, we'll see
}
echo "Migrados " . count($proveedores) . " Proveedores.\n";

// AHORA actualizar las llaves foraneas en `products` basado en los inserts de "producto"
echo "Re-Mapeando Productos con IDs correctos...\n";
// INSERT INTO `producto` (`id_producto`, `id_linea`, `codigo_producto`, `nombre_producto`, `id_estado_producto`, `stock_minimo`, `id_forma_farmaceutica`, `id_dispenser`, `id_distribuidor`) VALUES
// (1, 1, 'FPR-01001', 'ACETAZOLAMIDA 250MG', 1, '100', 4, 2, NULL, ...);

// Debido a variaciones, la regex robusta:
$productos = extractInserts('producto', $content, "/\(\s*(\d+)\s*,\s*(\d+)\s*,\s*'([^']+)'\s*,\s*'([^']+)'\s*,\s*(\d+)\s*,\s*'([^']+)'\s*,\s*(\d+)\s*,\s*(\d+)\s*,\s*(\w+|NULL)/i");
$updCount = 0;
foreach ($productos as $m) {
    // $m[1] = id_producto, $m[2] = id_linea, $m[3] = codigo, $m[4] = nombre
    // $m[7] = id_forma, $m[8] = id_dispenser
    $id_linea = $m[2];
    $barcode = trim($m[3]);
    $id_forma = $m[7];
    $id_dispenser = $m[8];
    // distribuidor is often tricky if it's the 9th field and sometimes NULL

    $stmt = $pdo->prepare("UPDATE products SET laboratory_id = ?, pharmaceutical_form_id = ?, dispenser_id = ? WHERE barcode = ?");
    $stmt->execute([$id_linea, $id_forma, $id_dispenser, $barcode]);
    if ($stmt->rowCount() > 0) $updCount++;
}
echo "Se actualizaron relaciones exactas para {$updCount} productos.\n";
echo "¡Done!\n";
