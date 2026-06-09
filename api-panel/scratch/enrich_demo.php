<?php

echo "=== DEMO DE ENRIQUECIMIENTO DINÁMICO DE VADEMECUM ===\n\n";

$sampleDrugs = ['ACIDO NALIDIXICO', 'METRONIDAZOL', 'CLOTRIMAZOL'];

foreach ($sampleDrugs as $drugName) {
    echo "Consultando en API CIMA para: '$drugName'...\n";
    
    $url = "https://cima.aemps.es/cima/rest/medicamentos?nombre=" . urlencode($drugName);
    
    $ctx = stream_context_create([
        'http' => ['timeout' => 5],
        'ssl' => ['verify_peer' => false, 'verify_peer_name' => false]
    ]);
    
    $response = @file_get_contents($url, false, $ctx);
    
    if (!$response) {
        echo "   [ERROR] No se pudo obtener respuesta del servidor.\n\n";
        continue;
    }
    
    $data = json_decode($response, true);
    
    if (empty($data['resultados'])) {
        echo "   [INFO] No se encontraron resultados específicos para este principio activo.\n\n";
        continue;
    }
    
    // Extraer datos del primer resultado
    $firstResult = $data['resultados'][0];
    $nombreOficial = $firstResult['nombre'] ?? '';
    $principioActivo = $firstResult['vtm']['nombre'] ?? 'No especificado';
    $formaFarmaceutica = $firstResult['formaFarmaceutica']['nombre'] ?? 'No especificada';
    
    // Buscar la URL de la ficha técnica (HTML) para indicaciones y dosis
    $fichaTecnicaUrl = '';
    if (!empty($firstResult['docs'])) {
        foreach ($firstResult['docs'] as $doc) {
            if ($doc['tipo'] === 1) { // 1 = Ficha técnica
                $fichaTecnicaUrl = $doc['urlHtml'] ?? $doc['url'] ?? '';
                break;
            }
        }
    }
    
    echo "   [RESULTADO ENCONTRADO]:\n";
    echo "   - Nombre Oficial: $nombreOficial\n";
    echo "   - Principio Activo extraído (VTM): $principioActivo\n";
    echo "   - Forma Farmacéutica: $formaFarmaceutica\n";
    if ($fichaTecnicaUrl) {
        echo "   - Enlace Ficha Técnica / Indicaciones: $fichaTecnicaUrl\n";
    }
    echo "\n";
}
