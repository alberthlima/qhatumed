<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

class EnrichVademecum extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:vademecum-enrich';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Enriquece de forma masiva y sincronizada las bases de datos farma y farmacia_2026 con vademecum localizado de Bolivia';

    /**
     * Diccionario maestro de mapeo de marcas y principios activos a textos de vademecum
     */
    protected $dictionary = [
        // Marcas y combinaciones comerciales bolivianas populares
        'MIGRADOL' => [
            'active_ingredient' => 'Clonixinato de Lisina / Ergotamina Tartrato',
            'therapeutic_action' => 'Antimigrañoso y Analgésico',
            'indications' => 'Tratamiento de las crisis agudas de migraña (con o sin aura) y cefaleas vasculares.',
            'dosage' => 'Tomar 1 a 2 comprimidos al inicio del ataque. Si es necesario, 1 comprimido cada 6 a 8 horas. Máximo 6 comprimidos al día.'
        ],
        'DELTAGEL' => [
            'active_ingredient' => 'Hidróxido de Aluminio / Hidróxido de Magnesio',
            'therapeutic_action' => 'Antiácido y Protector Gástrico',
            'indications' => 'Tratamiento sintomático de hiperacidez gástrica, acidez estomacal, gastritis y reflujo gastroesofágico.',
            'dosage' => 'Masticar 1 o 2 comprimidos (o tomar 10 ml de suspensión) entre 1 y 2 horas después de las comidas principales y al acostarse.'
        ],
        'CHAKITABS' => [
            'active_ingredient' => 'Paracetamol / Ácido Acetilsalicílico / Cafeína',
            'therapeutic_action' => 'Analgésico y Antipirético contra la resaca',
            'indications' => 'Alivio sintomático de dolores de cabeza, malestar general y fatiga causados por el consumo excesivo de alcohol (resaca).',
            'dosage' => 'Adultos: Tomar 1 o 2 tabletas con un vaso de agua al presentarse los síntomas. No exceder de 6 tabletas en 24 horas.'
        ],
        'DEXTROAMIN' => [
            'active_ingredient' => 'Dextrosa / Complejo B / Vitamina C',
            'therapeutic_action' => 'Reconstituyente Energético y Nutritivo',
            'indications' => 'Prevención y tratamiento de estados de deshidratación leve, fatiga física, agotamiento y deficiencia de vitaminas del complejo B.',
            'dosage' => 'Tomar el contenido de 1 sobre disuelto en agua, de 1 a 3 veces al día según el nivel de fatiga física.'
        ],
        'ESPASMODOL' => [
            'active_ingredient' => 'Butilbromuro de Hioscina',
            'therapeutic_action' => 'Espasmolítico y Anticolinérgico',
            'indications' => 'Espasmos agudos de las vías gastrointestinales, biliares y genitourinarias. Cólicos renales y hepáticos.',
            'dosage' => 'Adultos y niños mayores de 6 años: 10 mg a 20 mg (1 o 2 comprimidos) 3 a 5 veces al día. Dosis máxima 100 mg/día.'
        ],
        'FIXIM' => [
            'active_ingredient' => 'Cefixima',
            'therapeutic_action' => 'Antibiótico Cefalosporina de Tercera Generación',
            'indications' => 'Infecciones del tracto respiratorio superior e inferior, otitis media, infecciones urinarias no complicadas y gonorrea.',
            'dosage' => 'Adultos: 400 mg al día (en dosis única o divididos en dos tomas de 200 mg cada 12 horas). Niños: 8 mg/kg al día.'
        ],
        'AMOXIMIL' => [
            'active_ingredient' => 'Amoxicilina',
            'therapeutic_action' => 'Antibiótico Bactericida de Amplio Espectro',
            'indications' => 'Infecciones de las vías respiratorias superiores e inferiores, del tracto genitourinario, de la piel y tejidos blandos causadas por bacterias sensibles.',
            'dosage' => 'Adultos: 500 mg a 1 g cada 8 horas. Niños: 40 a 90 mg/kg/día divididos en tres dosis diarias por 7 a 10 días.'
        ],
        'AMPIMIL' => [
            'active_ingredient' => 'Ampicilina',
            'therapeutic_action' => 'Antibiótico Penicilánico de Amplio Espectro',
            'indications' => 'Tratamiento de infecciones respiratorias, urinarias, gastrointestinales y ginecológicas causadas por gérmenes sensibles.',
            'dosage' => 'Adultos: 250 mg a 500 mg cada 6 horas vía oral. Tomar 30 minutos antes o 2 horas después de las comidas.'
        ],
        'MENTISAN' => [
            'active_ingredient' => 'Alcanfor / Mentol / Aceite de Eucalipto',
            'therapeutic_action' => 'Analgésico Local, Descongestionante y Antiséptico',
            'indications' => 'Alivio sintomático del resfriado común (congestión nasal, tos), dolores musculares, picaduras de insectos y quemaduras leves.',
            'dosage' => 'Aplicar una pequeña cantidad friccionando sobre el pecho, cuello y espalda, o en las zonas adoloridas 3 a 4 veces al día.'
        ],

        // Principios activos genéricos y marcas directas
        'PARACETAMOL' => [
            'active_ingredient' => 'Paracetamol',
            'therapeutic_action' => 'Analgésico y Antipirético',
            'indications' => 'Alivio del dolor de intensidad leve a moderada y reducción de la fiebre.',
            'dosage' => 'Adultos: 500 mg a 1 g cada 6 u 8 horas según necesidad. No exceder de 4 g al día. Niños: 10-15 mg/kg cada 6 horas.'
        ],
        'IBUPROFENO' => [
            'active_ingredient' => 'Ibuprofeno',
            'therapeutic_action' => 'Antiinflamatorio No Esteroideo (AINE)',
            'indications' => 'Tratamiento de dolor e inflamación osteoarticular, dolores de cabeza, dolores menstruales, fiebre y dolor muscular.',
            'dosage' => 'Adultos: 400 mg a 600 mg cada 6 u 8 horas con las comidas. Dosis máxima diaria recomendada de 2400 mg.'
        ],
        'DICLOFENACO' => [
            'active_ingredient' => 'Diclofenaco',
            'therapeutic_action' => 'Antiinflamatorio, Analgésico y Antirreumático',
            'indications' => 'Dolor e inflamación en reumatismos crónicos, artritis, gota aguda, dolores postraumáticos y postoperatorios.',
            'dosage' => 'Adultos: 75 mg a 150 mg al día, distribuidos en 2 o 3 tomas. Se recomienda ingerir con abundante líquido durante o después de las comidas.'
        ],
        'OMEPRAZOL' => [
            'active_ingredient' => 'Omeprazol',
            'therapeutic_action' => 'Inhibidor de la Bomba de Protones (Antiulceroso)',
            'indications' => 'Tratamiento de reflujo gastroesofágico, úlcera gástrica y duodenal, y esofagitis erosiva.',
            'dosage' => 'Tomar 20 mg o 40 mg una vez al día por la mañana en ayunas, tragando el comprimido entero con agua.'
        ],
        'LOSARTAN' => [
            'active_ingredient' => 'Losartán Potásico',
            'therapeutic_action' => 'Antihipertensivo (Antagonista de Receptores AT1)',
            'indications' => 'Tratamiento de la hipertensión arterial esencial y protección renal en pacientes hipertensos con diabetes mellitus tipo 2.',
            'dosage' => 'Tomar 50 mg a 100 mg una vez al día, preferiblemente a la misma hora todos los días.'
        ],
        'METFORMINA' => [
            'active_ingredient' => 'Metformina Hidrocloruro',
            'therapeutic_action' => 'Antidiabético Oral (Biguanida)',
            'indications' => 'Tratamiento de la diabetes mellitus tipo 2, especialmente en pacientes con sobrepeso, cuando la dieta y el ejercicio no son suficientes.',
            'dosage' => 'Iniciar con 500 mg u 850 mg dos o tres veces al día junto con o después de las comidas. Dosis máxima recomendada de 2550 mg/día.'
        ],
        'CETIRIZINA' => [
            'active_ingredient' => 'Cetirizina Hidrocloruro',
            'therapeutic_action' => 'Antihistamínico de Segunda Generación',
            'indications' => 'Alivio de los síntomas nasales y oculares de la rinitis alérgica estacional y crónica, y urticaria crónica.',
            'dosage' => 'Adultos y adolescentes mayores de 12 años: 10 mg (1 comprimido o 10 ml de jarabe) una vez al día.'
        ],
        'AMBROXOL' => [
            'active_ingredient' => 'Ambroxol Hidrocloruro',
            'therapeutic_action' => 'Mucolítico y Expectorante',
            'indications' => 'Facilita la eliminación de exceso de moco y secreciones bronquiales en afecciones respiratorias agudas y crónicas.',
            'dosage' => 'Adultos: 30 mg (1 comprimido o 10 ml de jarabe al 0.3%) tres veces al día. Niños: 15 mg (5 ml de jarabe) dos o tres veces al día.'
        ],
        'CEFTRIAXONA' => [
            'active_ingredient' => 'Ceftriaxona',
            'therapeutic_action' => 'Antibiótico Cefalosporina de Amplio Espectro',
            'indications' => 'Infecciones graves del tracto respiratorio inferior, meningitis bacteriana, sepsis abdominal y de tejidos blandos.',
            'dosage' => 'Adultos: 1 g a 2 g cada 24 horas vía intramuscular o intravenosa lenta. Niños: 50 a 75 mg/kg al día.'
        ],
        'BROMHEXINA' => [
            'active_ingredient' => 'Bromhexina Hidrocloruro',
            'therapeutic_action' => 'Expectorante y Secretolítico',
            'indications' => 'Tratamiento de trastornos bronquiales con secreción mucosa aumentada o viscosa.',
            'dosage' => 'Adultos: 8 mg a 16 mg (1 o 2 comprimidos) tres veces al día. Niños mayores de 6 años: 4 mg a 8 mg tres veces al día.'
        ],
        'RANITIDINA' => [
            'active_ingredient' => 'Ranitidina',
            'therapeutic_action' => 'Antagonista H2 (Antiácido)',
            'indications' => 'Úlcera duodenal, úlcera gástrica benigna, reflujo gastroesofágico y prevención de úlceras por estrés.',
            'dosage' => 'Adultos: 150 mg dos veces al día (mañana y noche) o 300 mg antes de acostarse. Tratar durante 4 a 8 semanas.'
        ],
        'ENALAPRIL' => [
            'active_ingredient' => 'Enalapril Maleato',
            'therapeutic_action' => 'Antihipertensivo (Inhibidor de la ECA)',
            'indications' => 'Tratamiento de la hipertensión arterial y de la insuficiencia cardíaca congestiva sintomática.',
            'dosage' => 'Iniciar con 5 mg al día. Dosis habitual de mantenimiento de 10 mg a 20 mg en una sola toma al día. Máximo 40 mg/día.'
        ],
        'AMLODIPINO' => [
            'active_ingredient' => 'Amlodipino',
            'therapeutic_action' => 'Antihipertensivo (Bloqueador de Canales de Calcio)',
            'indications' => 'Tratamiento de la hipertensión arterial y de la angina de pecho de esfuerzo o vasoespástica.',
            'dosage' => 'Iniciar con 5 mg una vez al día. Dosis máxima de 10 mg una vez al día según la respuesta del paciente.'
        ],
        'GLIBENCLAMIDA' => [
            'active_ingredient' => 'Glibenclamida',
            'therapeutic_action' => 'Hipoglucemiante Oral (Sulfonilurea)',
            'indications' => 'Tratamiento de la diabetes mellitus tipo 2 en pacientes estables cuya hiperglucemia no se controla con dieta ni ejercicio.',
            'dosage' => 'Iniciar con 2.5 mg a 5 mg al día con el desayuno. Ajustar de acuerdo al control de la glucemia. Máximo 15 mg/día.'
        ],
        'FLUCONAZOL' => [
            'active_ingredient' => 'Fluconazol',
            'therapeutic_action' => 'Antifúngico Sistémico',
            'indications' => 'Candidiasis vaginal aguda o recurrente, candidiasis mucosa y micosis cutáneas (pie de atleta, tiñas).',
            'dosage' => 'Candidiasis vaginal: 150 mg dosis única oral. Micosis cutáneas: 150 mg una vez a la semana por 2 a 4 semanas.'
        ],
        'LORATADINA' => [
            'active_ingredient' => 'Loratadina',
            'therapeutic_action' => 'Antihistamínico H1 No Sedante',
            'indications' => 'Alivio sintomático de alergias como rinitis alérgica estacional, estornudos, lagrimeo y prurito ocular o nasal.',
            'dosage' => 'Adultos y niños mayores de 12 años: 10 mg una vez al día. Niños de 2 a 12 años: 5 mg a 10 mg al día según peso.'
        ],
        'DEXAMETASONA' => [
            'active_ingredient' => 'Dexametasona',
            'therapeutic_action' => 'Corticosteroide Sistémico Antiinflamatorio',
            'indications' => 'Procesos inflamatorios agudos y severos, alergias graves, asma, shock y como terapia adyuvante en edema cerebral.',
            'dosage' => 'Dosis inicial de 0.75 mg a 9 mg al día según la gravedad del cuadro clínico. Administrar bajo estricto control médico.'
        ],
        'PREDNISONA' => [
            'active_ingredient' => 'Prednisona',
            'therapeutic_action' => 'Corticosteroide Inmunosupresor y Antiinflamatorio',
            'indications' => 'Tratamiento de enfermedades autoinmunes, asma severo, alergias rebeldes, trastornos reumáticos y nefropatías.',
            'dosage' => 'Dosis variable de 5 mg a 60 mg al día vía oral, ajustando gradualmente hasta la dosis mínima de mantenimiento eficaz.'
        ],
        'KETOROLACO' => [
            'active_ingredient' => 'Ketorolaco Trometamol',
            'therapeutic_action' => 'Analgésico Potente No Esteroideo',
            'indications' => 'Manejo a corto plazo del dolor agudo de moderado a severo (ej. postoperatorio o postraumático).',
            'dosage' => 'Tomar 10 mg cada 4 a 6 horas. Dosis máxima de 40 mg al día. No exceder los 5 días de tratamiento.'
        ],
        'NAPROXENO' => [
            'active_ingredient' => 'Naproxeno',
            'therapeutic_action' => 'Antiinflamatorio, Analgésico y Antipirético',
            'indications' => 'Alivio del dolor leve a moderado, dolor dental, dolor menstrual, dolores musculares, fiebre y artritis.',
            'dosage' => 'Adultos: 275 mg o 550 mg cada 12 horas. Ingerir los comprimidos enteros con agua y con alimentos para evitar acidez.'
        ],
        'METRONIDAZOL' => [
            'active_ingredient' => 'Metronidazol',
            'therapeutic_action' => 'Antiparasitario y Antibacteriano Anaeróbico',
            'indications' => 'Tratamiento de amebiasis intestinal o hepática, giardiasis, tricomoniasis urogenital e infecciones por anaerobios.',
            'dosage' => 'Amebiasis: 500 mg a 750 mg tres veces al día por 5 a 10 días. Tricomoniasis: 2 g en dosis única o 250 mg cada 8 horas por 7 días.'
        ],
        'CLOTRIMAZOL' => [
            'active_ingredient' => 'Clotrimazol',
            'therapeutic_action' => 'Antimicótico de Uso Tópico y Vaginal',
            'indications' => 'Tratamiento de micosis cutáneas (dermatomicosis) y candidiasis vulvovaginal.',
            'dosage' => 'Crema vaginal: aplicar 1 aplicador lleno al acostarse por 3 a 6 días. Crema dérmica: aplicar 2 a 3 veces al día en la zona afectada.'
        ],
        'METOCLOPRAMIDA' => [
            'active_ingredient' => 'Metoclopramida Hidrocloruro',
            'therapeutic_action' => 'Antiemético y Procinético Gastrointestinal',
            'indications' => 'Prevención y tratamiento de náuseas, vómitos, reflujo y trastornos de la motilidad gástrica.',
            'dosage' => 'Adultos: 10 mg hasta tres veces al día. Tomar 30 minutos antes de las comidas. Dosis diaria máxima de 30 mg.'
        ],
        'AZITROMICINA' => [
            'active_ingredient' => 'Azitromicina',
            'therapeutic_action' => 'Antibiótico Macrólido de Acción Prolongada',
            'indications' => 'Infecciones de vías respiratorias altas y bajas, infecciones de piel y tejidos blandos, y enfermedades de transmisión sexual no complicadas.',
            'dosage' => 'Tomar 500 mg una vez al día durante 3 días consecutivos (dosis total de 1.5 g). Tomar al menos 1 hora antes o 2 horas después de las comidas.'
        ],
        'EUTIROX' => [
            'active_ingredient' => 'Levotiroxina Sódica',
            'therapeutic_action' => 'Hormona Tiroidea de Reemplazo',
            'indications' => 'Tratamiento de hipotiroidismo primario, secundario o terciario de cualquier etiología.',
            'dosage' => 'Dosis inicial de 25 a 50 mcg al día, con incrementos según perfil de TSH. Tomar en ayunas, 30-60 minutos antes del desayuno.'
        ],
        'POVIDONA' => [
            'active_ingredient' => 'Povidona Yodada',
            'therapeutic_action' => 'Antiséptico y Desinfectante',
            'indications' => 'Desinfección de la piel sana antes de intervenciones quirúrgicas o punciones, y lavado higiénico de heridas menores.',
            'dosage' => 'Aplicar sobre la zona afectada bien limpia y seca de 1 a 3 veces al día, utilizando una gasa estéril si es necesario.'
        ],
        'ALCOHOL' => [
            'active_ingredient' => 'Alcohol Etílico 70%',
            'therapeutic_action' => 'Antiséptico y Desinfectante de la Piel',
            'indications' => 'Antisepsia de la piel antes de inyecciones y desinfección de superficies inertes.',
            'dosage' => 'Aplicar sobre la piel limpia utilizando un algodón o gasa estéril. No aplicar sobre heridas abiertas o mucosas.'
        ],
        'SUERO' => [
            'active_ingredient' => 'Sodio Cloruro',
            'therapeutic_action' => 'Solución Electrolítica Isotónica',
            'indications' => 'Restauración de fluidos corporales, dilución de medicamentos inyectables e irrigación/lavado ocular o nasal.',
            'dosage' => 'Dosis variable según la necesidad clínica del paciente, administrada por infusión intravenosa lenta o para lavados tópicos.'
        ],
        'CALCIO' => [
            'active_ingredient' => 'Carbonato de Calcio',
            'therapeutic_action' => 'Suplemento de Calcio y Antiácido',
            'indications' => 'Prevención y tratamiento de estados de deficiencia de calcio (osteoporosis, hipocalcemia, embarazo, lactancia) y como antiácido.',
            'dosage' => 'Tomar 500 mg a 1500 mg de calcio elemental al día, repartidos en 2 o 3 tomas junto con las comidas principales.'
        ],
        'CIPROFLOXACINO' => [
            'active_ingredient' => 'Ciprofloxacino',
            'therapeutic_action' => 'Antibiótico Fluoroquinolona Bactericida',
            'indications' => 'Infecciones de vías urinarias complicadas y no complicadas, infecciones respiratorias, osteomielitis y otitis media crónica.',
            'dosage' => 'Adultos: 250 mg a 750 mg cada 12 horas vía oral por 7 a 14 días. Tomar con abundante agua.'
        ],
        'CEFALEXINA' => [
            'active_ingredient' => 'Cefalexina',
            'therapeutic_action' => 'Antibiótico Cefalosporina de Primera Generación',
            'indications' => 'Infecciones respiratorias, urinarias, óseas, de piel y tejidos blandos causadas por gérmenes sensibles.',
            'dosage' => 'Adultos: 250 mg a 500 mg cada 6 horas vía oral. Niños: 25 a 50 mg/kg/día divididos en 4 tomas.'
        ],
        'ATORVASTATINA' => [
            'active_ingredient' => 'Atorvastatina',
            'therapeutic_action' => 'Hipolipemiante (Inhibidor de la HMG-CoA Reductasa)',
            'indications' => 'Reducción del colesterol total y triglicéridos elevados en hipercolesterolemia primaria. Prevención de infartos.',
            'dosage' => 'Adultos: Iniciar con 10 mg o 20 mg una vez al día. Dosis de mantenimiento de 10 mg a 80 mg al día vía oral.'
        ],
        'CAPTOPRIL' => [
            'active_ingredient' => 'Captopril',
            'therapeutic_action' => 'Antihipertensivo (Inhibidor de la ECA)',
            'indications' => 'Hipertensión arterial sistémica, insuficiencia cardíaca congestiva, infarto de miocardio y nefropatía diabética.',
            'dosage' => 'Iniciar con 25 mg dos o tres veces al día. Tomar 1 hora antes de las comidas principales.'
        ],
        'ACETAZOLAMIDA' => [
            'active_ingredient' => 'Acetazolamida',
            'therapeutic_action' => 'Antiglaucomatoso y Diurético',
            'indications' => 'Tratamiento del glaucoma de ángulo abierto, glaucoma secundario y preoperatorio de glaucoma de ángulo cerrado.',
            'dosage' => 'Tomar 250 mg (1 comprimido) de 1 a 4 veces al día según indicación médica, distribuidos a lo largo del día.'
        ]
    ];

    /**
     * Palabras clave no farmacológicas (cosméticos e insumos)
     */
    protected $exclusionsCosmetic = [
        'NIVEA', 'BIBERON', 'FORAMEN', 'JOHNSONS', 'EUCERIN', 'CREMA', 'DOCTOR', 'COLGATE', 'TETINA', 
        'HIGEEN', 'WISDOM', 'FAJA', 'PROTEX', 'LADY', 'BABYLAND', 'NOSOTRAS', 'HUGGIES', 'ASEPXIA', 
        'SET', 'PALMOLIVE', 'BAHIA', 'JABON', 'JABÓN', 'MORDEDOR', 'SPEED', 'CHUPON', 'SUNLESS', 
        'SHAMPOO', 'CEPILLO', 'TOALLA', 'PAÑAL', 'TALCO', 'DESODORANTE', 'GEL', 'LOCION', 'MASCARILLA', 
        'PLENITUD', 'MASCULAN', 'NUTRILON', 'LISTERINE', 'FAMILAND', 'LECHE', 'POMADA', 'DOVE', 'VASO', 
        'SEPTONA', 'KOTEX', 'COLORANTE', 'MIEL', 'FORMOL', 'POWER', 'NAN', 'PLAYTEX', 'COCO', 'TORTA', 
        'GALLETA', 'MAMADERA'
    ];

    protected $exclusionsInsumo = [
        'VENDA', 'HILO', 'GUANTE', 'GUANTES', 'ALGODON', 'TELA', 'JERINGA', 'TEGADERM', 'INTROCAN', 
        'VENDITAS', 'TINTURA', 'TERMOMETRO', 'CLOREX', 'GASA', 'TRANSPORE', 'POVIDONA', 'COMPRESA', 
        'CABESTRILLO', 'RODILLERA', 'TUBO', 'SUTURA'
    ];

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info("Iniciando proceso de enriquecimiento masivo sincronizado...");

        // 1. Obtener todos los productos de farmacia_2026.products
        $products = Product::all();
        $total = $products->count();

        if ($total === 0) {
            $this->error("No se encontraron productos en la tabla farmacia_2026.products.");
            return 1;
        }

        $this->info("Procesando {$total} productos...");
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $enriquecidos = 0;
        $omitidos = 0;
        $fallidos = 0;

        foreach ($products as $product) {
            $rawName = trim(preg_replace('/\s+/', ' ', $product->name));
            $parts = explode(' ', $rawName);
            $firstWord = strtoupper($parts[0] ?? '');
            
            // Quitar caracteres especiales para limpiar el prefijo
            $firstWordClean = preg_replace('/[^A-Z]/', '', $firstWord);

            if ($firstWordClean === 'ACIDO' && isset($parts[1])) {
                $secondWord = strtoupper($parts[1]);
                $secondWordClean = preg_replace('/[^A-Z]/', '', $secondWord);
                $prefix = $firstWordClean . ' ' . $secondWordClean;
            } else {
                $prefix = $firstWordClean;
            }

            // Datos que aplicaremos
            $activeIngredient = '';
            $therapeuticAction = '';
            $indications = '';
            $dosage = '';

            // 1. Comprobar si es un producto cosmético o no farmacológico
            if (in_array($prefix, $this->exclusionsCosmetic)) {
                $activeIngredient = 'No farmacológico / Cuidado personal';
                $therapeuticAction = 'Higiene / Cuidado de la Piel';
                $indications = 'Producto de consumo general para higiene, hidratación o cuidado de la piel.';
                $dosage = 'Aplicar o usar sobre la zona correspondiente según las indicaciones de uso del fabricante.';
                $omitidos++;
            } 
            // 2. Comprobar si es un insumo médico
            elseif (in_array($prefix, $this->exclusionsInsumo)) {
                $activeIngredient = 'Insumo médico / Material de curación';
                $therapeuticAction = 'Insumo para el cuidado de la salud';
                $indications = 'Material para curación, inyección, sutura, protección o inmovilización física en procedimientos médicos.';
                $dosage = 'Utilizar bajo medidas higiénicas o de esterilidad adecuadas. Descartar después de su uso en recipientes aptos.';
                $omitidos++;
            }
            // 3. Comprobar si está en nuestro diccionario explícito
            elseif (isset($this->dictionary[$prefix])) {
                $mapped = $this->dictionary[$prefix];
                $activeIngredient = $mapped['active_ingredient'];
                $therapeuticAction = $mapped['therapeutic_action'];
                $indications = $mapped['indications'];
                $dosage = $mapped['dosage'];
                $enriquecidos++;
            }
            // 4. Si ya tiene un principio activo válido asignado previamente por CIMA, lo conservamos y generamos fallbacks legibles
            elseif ($product->active_ingredient && !in_array($product->active_ingredient, ['', 'NINGUNA', 'NINGUNO', 'No encontrado en CIMA', 'No farmacológico/Cosmético'])) {
                $activeIngredient = $product->active_ingredient;
                $therapeuticAction = $product->therapeutic_action ?: 'Fármaco de Acción Específica';
                $indications = $product->indications && !str_starts_with($product->indications, 'http') ? $product->indications : "Tratamiento sintomático de afecciones relacionadas con el principio activo: {$product->active_ingredient}.";
                $dosage = "Administrar según la pauta recomendada para {$product->active_ingredient} y de acuerdo con el criterio del médico tratante.";
                $enriquecidos++;
            }
            // 5. Fallback dinámico si no coincide
            else {
                // Intentar usar el prefijo como principio activo genérico
                $activeIngredient = ucwords(strtolower($prefix ?: 'General'));
                $therapeuticAction = 'Acción Terapéutica General';
                $indications = "Tratamiento sintomático y soporte clínico bajo indicación médica.";
                $dosage = "La dosificación debe ser establecida por el médico tratante de acuerdo con la edad y condición del paciente.";
                $enriquecidos++;
            }

            // Sanitizar a mayúsculas para la base de datos legado (el legacy usa mayúsculas en la visualización)
            $activeIngredientUpper = mb_strtoupper($activeIngredient, 'UTF-8');
            $therapeuticActionUpper = mb_strtoupper($therapeuticAction, 'UTF-8');
            $indicationsUpper = mb_strtoupper($indications, 'UTF-8');
            $dosageUpper = mb_strtoupper($dosage, 'UTF-8');

            try {
                // A. Actualizar farmacia_2026.products
                $product->update([
                    'active_ingredient' => $activeIngredient,
                    'therapeutic_action' => $therapeuticAction,
                    'indications' => $indications,
                    'dosage' => $dosage
                ]);

                // B. Actualizar farma.producto (Sincronización por barcode -> codigo_producto)
                if ($product->barcode) {
                    DB::statement("
                        UPDATE farma.producto 
                        SET principio_activo_producto = ?, 
                            accion_terapeutica_producto = ?, 
                            indicaciones_producto = ?, 
                            dosificacion_producto = ?
                        WHERE codigo_producto = ?
                    ", [
                        $activeIngredientUpper,
                        $therapeuticActionUpper,
                        $indicationsUpper,
                        $dosageUpper,
                        $product->barcode
                    ]);
                }

            } catch (\Exception $e) {
                $fallidos++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("¡Proceso finalizado con éxito!");
        $this->info("- Registros enriquecidos: {$enriquecidos}");
        $this->info("- Registros omitidos/no-farmacos: {$omitidos}");
        $this->info("- Registros fallidos: {$fallidos}");

        return 0;
    }
}
