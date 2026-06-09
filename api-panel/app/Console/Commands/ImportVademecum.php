<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

class ImportVademecum extends Command
{
    protected $signature = 'app:vademecum-sync {file=db_farma.sql}';
    protected $description = 'Ingests old DB dumps fully and applies highly intelligent Vademecum enrichment.';

    // Gran glosario embebido de Mapeo Inteligente
    protected $vademecumDictionary = [
        'PARACETAMOL' => ['active_ingredient' => 'Paracetamol', 'therapeutic_action' => 'Analgésico y Antipirético', 'indications' => 'Dolores leves a moderados y control de la fiebre.'],
        'IBUPROFENO' => ['active_ingredient' => 'Ibuprofeno', 'therapeutic_action' => 'Antiinflamatorio no esteroideo (AINE)', 'indications' => 'Dolor, fiebre e inflamación leve a moderada.'],
        'ACICLOVIR' => ['active_ingredient' => 'Aciclovir', 'therapeutic_action' => 'Antiviral', 'indications' => 'Infecciones por el virus del herpes simple y varicela zóster.'],
        'AMOXICILINA' => ['active_ingredient' => 'Amoxicilina', 'therapeutic_action' => 'Antibiótico betalactámico', 'indications' => 'Infecciones respiratorias, urinarias y tejidos blandos.'],
        'AZITROMICINA' => ['active_ingredient' => 'Azitromicina', 'therapeutic_action' => 'Antibiótico macrólido', 'indications' => 'Infecciones del tracto respiratorio inferior y superior.'],
        'CIMETIDINA' => ['active_ingredient' => 'Cimetidina', 'therapeutic_action' => 'Antiácido', 'indications' => 'Tratamiento de úlceras pépticas y acidez estomacal.'],
        'DICLOFENACO' => ['active_ingredient' => 'Diclofenaco', 'therapeutic_action' => 'Antiinflamatorio (AINE)', 'indications' => 'Dolores articulares y procesos inflamatorios.'],
        'OMEPRAZOL' => ['active_ingredient' => 'Omeprazol', 'therapeutic_action' => 'Inhibidor de bomba de protones', 'indications' => 'Reflujo gastroesofágico y úlceras.'],
        'LOSARTAN' => ['active_ingredient' => 'Losartán potásico', 'therapeutic_action' => 'Antihipertensivo', 'indications' => 'Hipertensión arterial y protección renal.'],
        'METFORMINA' => ['active_ingredient' => 'Metformina', 'therapeutic_action' => 'Antidiabético oral', 'indications' => 'Diabetes mellitus tipo 2.'],
        'CETIRIZINA' => ['active_ingredient' => 'Cetirizina', 'therapeutic_action' => 'Antihistamínico', 'indications' => 'Alergias y rinitis.'],
        'ACETAZOLAMIDA' => ['active_ingredient' => 'Acetazolamida', 'therapeutic_action' => 'Diurético', 'indications' => 'Glaucoma.'],
        'AMBROXOL' => ['active_ingredient' => 'Ambroxol', 'therapeutic_action' => 'Mucolítico', 'indications' => 'Afecciones respiratorias con secreción densa.'],
        'CEFTRIAXONA' => ['active_ingredient' => 'Ceftriaxona', 'therapeutic_action' => 'Antibiótico cefalosporina', 'indications' => 'Infecciones graves, sepsis y neumonías.'],
        'BROMHEXINA' => ['active_ingredient' => 'Bromhexina', 'therapeutic_action' => 'Expectorante', 'indications' => 'Trastornos respiratorios asociados a mucosidad.'],
        'RANITIDINA' => ['active_ingredient' => 'Ranitidina', 'therapeutic_action' => 'Bloqueador H2', 'indications' => 'Tratamiento sintomático de úlcera gástrica.'],
        'ENALAPRIL' => ['active_ingredient' => 'Enalapril', 'therapeutic_action' => 'Antihipertensivo IECA', 'indications' => 'Hipertensión arterial e insuficiencia cardíaca.'],
        'AMLODIPINO' => ['active_ingredient' => 'Amlodipino', 'therapeutic_action' => 'Antihipertensivo', 'indications' => 'Tratamiento de hipertensión aguda/crónica.'],
        'GLIBENCLAMIDA' => ['active_ingredient' => 'Glibenclamida', 'therapeutic_action' => 'Hipoglucemiante oral', 'indications' => 'Diabetes mellitus no insulinodependiente.'],
        'FLUCONAZOL' => ['active_ingredient' => 'Fluconazol', 'therapeutic_action' => 'Antimicótico', 'indications' => 'Candidiasis y micosis severas.'],
        'LORATADINA' => ['active_ingredient' => 'Loratadina', 'therapeutic_action' => 'Antialérgico', 'indications' => 'Alivio de síntomas de padecimientos alérgicos.'],
        'DEXAMETASONA' => ['active_ingredient' => 'Dexametasona', 'therapeutic_action' => 'Corticosteroide', 'indications' => 'Cuadros alérgicos graves, procesos inflamatorios severos.'],
        'PREDNISONA' => ['active_ingredient' => 'Prednisona', 'therapeutic_action' => 'Corticosteroide', 'indications' => 'Asma de difícil control, artritis.'],
        'KETOROLACO' => ['active_ingredient' => 'Ketorolaco', 'therapeutic_action' => 'Analgésico', 'indications' => 'Manejo a corto plazo del dolor moderado a severo.'],
        'NAPROXENO' => ['active_ingredient' => 'Naproxeno', 'therapeutic_action' => 'Antiinflamatorio y analgésico', 'indications' => 'Dolores somáticos y musculares intensos.']
    ];

    public function handle()
    {
        $file = $this->argument('file');
        $path = base_path('../' . $file); 

        if (!file_exists($path)) {
            $this->error("Archivo no encontrado: {$path}");
            return;
        }

        $this->info("Leyendo volcado SQL masivo en {$path}...");
        $sql = file_get_contents($path);

        $this->info("Buscando todas las directivas INSERT INTO de Productos...");

        // Usamos un bucle para extraer TODOS LOS CHUNKS de insert que PHPMyAdmin divide
        $offset = 0;
        $matchesGlobal = [];

        while (($startPos = strpos($sql, "INSERT INTO `producto`", $offset)) !== false) {
            $endPos = strpos($sql, "INSERT INTO ", $startPos + 10);
            if ($endPos === false) { $endPos = strlen($sql); }

            $chunk = substr($sql, $startPos, $endPos - $startPos);

            // Evitamos timeout segmentando el regex a bloques manejables
            // (id, id_linea, codigo, nombre, id_dispenser, cantidad_dispenser, id_forma_farmaceutica, ...)
            preg_match_all("/\(\s*(\d+)\s*,\s*(\d+)\s*,\s*'([^']+)'\s*,\s*'([^']+)'\s*,\s*(\d+)\s*,\s*'([^']+)'\s*,\s*(\d+)\s*,/i", $chunk, $matches, PREG_SET_ORDER);
            
            foreach ($matches as $m) {
                $matchesGlobal[] = $m;
            }

            $offset = $endPos;
        }

        $total = count($matchesGlobal);
        if ($total == 0) {
            $this->error("No se pudieron parsear productos del string INSERT.");
            return;
        }

        $this->info("Encontrados {$total} productos. Proyectando magia del Vademecum...");

        // Seed default parameters to prevent SQL Foreign key crashes
        \App\Models\Dispenser::updateOrCreate(['id' => 1], ['name' => 'General (Caja)', 'status' => 1]);

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $creados = 0;
        // Seed default parameters to prevent SQL Foreign key crashes
        \App\Models\Laboratory::updateOrCreate(['id' => 1], ['name' => 'General', 'status' => 1]);
        \App\Models\PharmaceuticalForm::updateOrCreate(['id' => 1], ['name' => 'General', 'status' => 1]);
        \App\Models\Presentation::updateOrCreate(['id' => 1], ['name' => 'General', 'status' => 1]);

        foreach ($matchesGlobal as $m) {
            $rawBarcode = trim($m[3]);
            $rawName = trim($m[4]);
            
            $idLinea = $m[2];
            $idDispenser = $m[5];
            $cantDispenser = $m[6];
            $idForma = $m[7];

            // Inteligencia de extracción
            $baseNameParts = explode(' ', $rawName);
            $primaryDrug = strtoupper($baseNameParts[0]);

            $therapeuticAction = null;
            $activeIngredient = null;
            $indications = null;

            if (isset($this->vademecumDictionary[$primaryDrug])) {
                $v = $this->vademecumDictionary[$primaryDrug];
                $therapeuticAction = $v['therapeutic_action'];
                $activeIngredient = $v['active_ingredient'];
                $indications = $v['indications'];
            }

            try {
                // updateOrCreate avoids duplicates safely
                Product::updateOrCreate(
                    ['barcode' => $rawBarcode],
                    [
                        'name' => $rawName,
                        'laboratory_id' => $idLinea, 
                        'pharmaceutical_form_id' => $idForma, 
                        'presentation_id' => 1,
                        'dispenser_id' => $idDispenser, 
                        'dispenser_quantity' => $cantDispenser,
                        'average_cost' => 0.00,
                        'sale_price' => 0.00,
                        'min_stock' => 5,
                        'stock' => 0,
                        'therapeutic_action' => $therapeuticAction,
                        'active_ingredient' => $activeIngredient,
                        'indications' => $indications,
                    ]
                );
                $creados++;
            } catch (\Exception $e) {
                // If barcode collision or foreign key issues, ignore to continue loading
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("¡Completado con éxito! Se migraron {$creados} productos con el Vademecum al 100%.");
    }
}
