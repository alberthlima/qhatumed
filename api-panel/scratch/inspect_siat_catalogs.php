<?php
$mysqli = new mysqli("127.0.0.1", "root", "");
$mysqli->select_db("farmacia_2026");

echo "=== siat_catalogos ===\n";
$res = $mysqli->query("SELECT * FROM siat_catalogos");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        echo "Catalogo: {$row['tipo']} | Count: " . strlen($row['datos']) . " bytes\n";
        // Let's decode a bit to see the format
        $data = json_decode($row['datos'], true);
        if ($data) {
            echo "  Sample keys: " . implode(', ', array_slice(array_keys($data), 0, 5)) . "\n";
            if (isset($data['listaActividadesDocumentoSector'])) {
                echo "  listaActividadesDocumentoSector sample: " . json_encode(array_slice($data['listaActividadesDocumentoSector'], 0, 2)) . "\n";
            }
            if (isset($data['listaProductosServicios'])) {
                echo "  listaProductosServicios sample: " . json_encode(array_slice($data['listaProductosServicios'], 0, 2)) . "\n";
            }
            if (isset($data['listaParametricasUnidadMedida'])) {
                echo "  listaParametricasUnidadMedida sample: " . json_encode(array_slice($data['listaParametricasUnidadMedida'], 0, 2)) . "\n";
            }
        }
    }
}
