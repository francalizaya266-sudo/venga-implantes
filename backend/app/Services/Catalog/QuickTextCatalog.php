<?php

namespace App\Services\Catalog;

use App\Enums\SnippetField;
use App\Enums\SnippetStatus;
use App\Models\TextSnippet;
use Illuminate\Support\Facades\DB;

/**
 * Las opciones rápidas que el sistema trae para todo el equipo: frases de la historia clínica y medicamentos
 * frecuentes, para que el doctor toque en vez de escribir. Cada doctor suma las suyas.
 *
 * Nunca pisa lo que el consultorio escribió: solo agrega las que faltan. Las frases que terminan en un espacio
 * ("… Pieza ") esperan que el doctor complete el dato.
 */
final class QuickTextCatalog
{
    /**
     * Con las que el sistema nació.
     *
     * @var list<array{0: SnippetField, 1: string, 2: string, 3: string|null}> Campo, nombre corto, texto y atajo.
     */
    public const ORIGINAL = [
        [SnippetField::ChiefComplaint, 'Dolor dental', 'Paciente refiere dolor en pieza ', null],
        [SnippetField::ChiefComplaint, 'Control y limpieza', 'Acude para control y limpieza dental.', null],
        [SnippetField::ChiefComplaint, 'Sensibilidad', 'Refiere sensibilidad al frío y al dulce.', null],
        [SnippetField::ClinicalExam, 'Examen sin alteraciones', 'Extraoral: sin alteraciones. Intraoral: mucosas hidratadas, de color rosado, sin lesiones.', 'normal'],
        [SnippetField::Procedures, 'Profilaxis completa', 'Se realizó profilaxis: destartraje supragingival con ultrasonido, pulido coronal y aplicación de flúor barniz.', 'limp'],
        [SnippetField::Procedures, 'Restauración con resina', 'Aislamiento relativo, remoción de caries, grabado ácido, adhesivo y restauración con resina fotocurable. Se ajusta oclusión y se pule.', 'res'],
        [SnippetField::Procedures, 'Extracción simple', 'Anestesia infiltrativa, sindesmotomía, luxación y avulsión de la pieza. Hemostasia con gasa. Se dan indicaciones.', 'ext'],
        [SnippetField::Indications, 'Indicaciones post-extracción', 'No enjuagarse ni escupir por 24 horas. Morder la gasa 30 minutos. Dieta blanda y fría. Hielo en la zona por intervalos de 15 minutos. No fumar ni usar sorbete.', 'postex'],
        [SnippetField::Indications, 'Indicaciones post-resina', 'Puede comer normalmente. Si siente la mordida alta o molestia que no cede en 2 días, comuníquese con el consultorio.', 'postres'],
        [SnippetField::Prescription, 'Ibuprofeno 400 mg', 'Ibuprofeno 400 mg: 1 tableta cada 8 horas por 3 días, después de los alimentos.', 'ibu'],
        [SnippetField::Prescription, 'Paracetamol 500 mg', 'Paracetamol 500 mg: 1 tableta cada 8 horas por 3 días, si hay dolor.', 'para'],
    ];

    /**
     * Las que se sumaron después, para cubrir las atenciones de todos los días. A una base que ya se usa llegan
     * solo estas: una de las originales que el consultorio borró no vuelve a aparecer.
     *
     * @var list<array{0: SnippetField, 1: string, 2: string, 3: string|null}> Campo, nombre corto, texto y atajo.
     */
    public const ADDED = [
        [SnippetField::ChiefComplaint, 'Evaluación general', 'Acude para evaluación general. No refiere molestias.', null],
        [SnippetField::ChiefComplaint, 'Sangrado de encías', 'Refiere sangrado de encías al cepillarse.', null],
        [SnippetField::ChiefComplaint, 'Diente roto', 'Refiere que se le rompió un diente. Pieza ', null],
        [SnippetField::ChiefComplaint, 'Se cayó una curación', 'Refiere que se le cayó una curación. Pieza ', null],
        [SnippetField::ChiefComplaint, 'Hinchazón', 'Refiere hinchazón y dolor. Empezó hace ', null],
        [SnippetField::ChiefComplaint, 'Muela del juicio', 'Refiere dolor e inflamación en la zona de la muela del juicio.', null],
        [SnippetField::ChiefComplaint, 'Reponer dientes', 'Acude para reponer piezas perdidas.', null],
        [SnippetField::ChiefComplaint, 'Alinear los dientes', 'Acude porque quiere alinear sus dientes.', null],
        [SnippetField::ChiefComplaint, 'Mejorar su sonrisa', 'Acude porque quiere mejorar el color y la forma de sus dientes.', null],

        [SnippetField::ClinicalExam, 'Caries', 'Lesión cariosa sin dolor a la percusión. Pieza ', 'caries'],
        [SnippetField::ClinicalExam, 'Caries profunda', 'Caries profunda, con dolor al frío que tarda en pasar. Pieza ', null],
        [SnippetField::ClinicalExam, 'Gingivitis', 'Encías enrojecidas e inflamadas, con sangrado al sondaje. Placa y cálculo supragingival.', 'ging'],
        [SnippetField::ClinicalExam, 'Absceso', 'Aumento de volumen en la encía, doloroso a la palpación, con dolor a la percusión. Pieza ', null],
        [SnippetField::ClinicalExam, 'Pericoronaritis', 'Tercera molar semierupcionada, con la encía que la cubre inflamada y dolorosa. Pieza ', null],
        [SnippetField::ClinicalExam, 'Edéntulo parcial', 'Reborde alveolar con altura y grosor conservados. Ausencia de piezas ', null],
        [SnippetField::ClinicalExam, 'Apiñamiento', 'Apiñamiento dentario anterior superior e inferior.', null],
        [SnippetField::ClinicalExam, 'Higiene deficiente', 'Higiene oral deficiente: placa bacteriana generalizada y cálculo en el sector anteroinferior.', null],

        [SnippetField::Diagnosis, 'Falta radiografía', 'Diagnóstico presuntivo. Se confirma con radiografía.', null],
        [SnippetField::Diagnosis, 'Se explicó al paciente', 'Se explicó el diagnóstico y las alternativas de tratamiento. El paciente entiende y acepta.', null],
        [SnippetField::Diagnosis, 'Pronóstico favorable', 'Pronóstico favorable con el tratamiento indicado.', null],
        [SnippetField::Diagnosis, 'Pronóstico reservado', 'Pronóstico reservado: depende de la respuesta al tratamiento y de la higiene del paciente.', null],

        [SnippetField::Procedures, 'Evaluación', 'Examen clínico completo y odontograma. Se explica el diagnóstico y el plan de tratamiento con su presupuesto.', 'eval'],
        [SnippetField::Procedures, 'Endodoncia: apertura', 'Anestesia infiltrativa y aislamiento absoluto. Apertura cameral, localización de conductos, conductometría e instrumentación. Irrigación con hipoclorito de sodio. Medicación intraconducto y sellado provisional.', 'endo1'],
        [SnippetField::Procedures, 'Endodoncia: obturación', 'Aislamiento absoluto y retiro de la medicación. Irrigación final, secado y obturación de conductos con gutapercha y cemento sellador. Sellado provisional y radiografía de control.', 'endo2'],
        [SnippetField::Procedures, 'Urgencia: alivio del dolor', 'Anestesia infiltrativa, apertura cameral y drenaje. Medicación intraconducto y sellado provisional. Se cita para continuar el tratamiento.', 'urg'],
        [SnippetField::Procedures, 'Blanqueamiento', 'Profilaxis previa y protección gingival. Aplicación del gel blanqueador por ciclos, según el fabricante. Se dan indicaciones.', 'blan'],
        [SnippetField::Procedures, 'Control de ortodoncia', 'Control mensual: cambio de ligaduras y activación del arco. Se refuerza la técnica de cepillado.', 'orto'],
        [SnippetField::Procedures, 'Instalación de brackets', 'Profilaxis, grabado ácido y cementado de brackets superiores e inferiores. Se coloca el arco inicial con ligaduras. Se dan indicaciones de higiene y dieta.', 'brack'],
        [SnippetField::Procedures, 'Colocación de implante', 'Anestesia infiltrativa, incisión y colgajo. Fresado secuencial con irrigación y colocación del implante. Tornillo de cierre y sutura. Se dan indicaciones.', 'impl'],
        [SnippetField::Procedures, 'Retiro de puntos', 'Se retiran los puntos. Herida con buena cicatrización, sin signos de infección.', 'puntos'],
        [SnippetField::Procedures, 'Toma de impresiones', 'Se toman impresiones superior e inferior y registro de mordida. Se envía al laboratorio.', 'impr'],
        [SnippetField::Procedures, 'Tallado para corona', 'Anestesia infiltrativa y tallado de la pieza. Toma de impresión y de color. Se coloca corona provisional.', 'tall'],
        [SnippetField::Procedures, 'Cementado de corona', 'Se prueba la corona: adaptación, contactos y color conformes. Cementado definitivo y ajuste de oclusión.', 'cem'],
        [SnippetField::Procedures, 'Sellantes', 'Profilaxis, aislamiento relativo, grabado ácido y aplicación de sellante en fosas y fisuras. Se verifica la oclusión.', null],
        [SnippetField::Procedures, 'Aplicación de flúor', 'Profilaxis y aplicación de flúor barniz en todas las piezas.', null],

        [SnippetField::Indications, 'Indicaciones post-limpieza', 'Es normal la sensibilidad por uno o dos días. Cepillado 3 veces al día con la técnica indicada y uso de hilo dental. Control en 6 meses.', 'postlimp'],
        [SnippetField::Indications, 'Higiene oral', 'Cepillado 3 veces al día por 2 minutos, con pasta con flúor. Hilo dental una vez al día. Cambiar el cepillo cada 3 meses.', 'hig'],
        [SnippetField::Indications, 'Signos de alarma', 'Comunicarse con el consultorio si hay dolor fuerte que no cede con la medicación, sangrado que no para, hinchazón que aumenta o fiebre.', 'alarma'],
        [SnippetField::Indications, 'Indicaciones post-endodoncia', 'No masticar con ese lado hasta que pase la anestesia. Evitar alimentos duros en esa pieza hasta su restauración final. Es normal la sensibilidad al morder por unos días.', 'postendo'],
        [SnippetField::Indications, 'Indicaciones post-implante', 'Hielo por fuera en intervalos de 10 minutos durante las primeras horas. No enjuagarse ni escupir por 24 horas. Dieta blanda y fría. No fumar por una semana. Cepillado suave desde mañana, sin tocar la zona. Control y retiro de puntos en 7 a 10 días.', 'postimp'],
        [SnippetField::Indications, 'Indicaciones post-blanqueamiento', 'Por 48 horas evitar café, té, vino, gaseosas oscuras, salsas con color y fumar. Es normal la sensibilidad por uno o dos días.', 'postblan'],
        [SnippetField::Indications, 'Indicaciones con brackets', 'Evitar alimentos duros y pegajosos. Cepillarse después de cada comida y usar cepillo interdental. Si un bracket o el alambre molesta, cubrirlo con cera y comunicarse con el consultorio.', 'postorto'],

        [SnippetField::Prescription, 'Amoxicilina 500 mg', 'Amoxicilina 500 mg: 1 cápsula cada 8 horas por 7 días.', 'amoxi'],
        [SnippetField::Prescription, 'Clindamicina 300 mg', 'Clindamicina 300 mg: 1 cápsula cada 8 horas por 7 días.', 'clinda'],
        [SnippetField::Prescription, 'Naproxeno 550 mg', 'Naproxeno 550 mg: 1 tableta cada 12 horas por 3 días, después de los alimentos.', 'napro'],
        [SnippetField::Prescription, 'Clorhexidina 0.12 %', 'Clorhexidina 0.12 % enjuague bucal: enjuagarse con 15 ml por 30 segundos, 2 veces al día por 7 días. No tragar.', 'clorhex'],
    ];

    /**
     * Agrega las opciones del equipo que falten: todas en una instalación nueva; en una base que ya se usa, solo
     * las que se sumaron después. Una que ya está (mismo campo y nombre) no se toca, y si su atajo ya lo usa otra
     * opción del equipo, entra sin atajo.
     *
     * @return int Cuántas se agregaron.
     */
    public function install(bool $onlyAdded = false): int
    {
        return DB::transaction(function () use ($onlyAdded): int {
            $takenShortcuts = TextSnippet::query()->whereNull('user_id')->whereNotNull('shortcut')->pluck('shortcut')
                ->map(fn (string $shortcut) => mb_strtolower($shortcut));
            $added = 0;

            foreach ($onlyAdded ? self::ADDED : [...self::ORIGINAL, ...self::ADDED] as [$field, $label, $body, $shortcut]) {
                $snippet = TextSnippet::firstOrCreate(['user_id' => null, 'field' => $field, 'label' => $label], [
                    'body' => $body,
                    'shortcut' => $shortcut !== null && $takenShortcuts->contains($shortcut) ? null : $shortcut,
                    'status' => SnippetStatus::Active,
                ]);

                if ($snippet->wasRecentlyCreated) {
                    $added++;
                    $takenShortcuts->push($snippet->shortcut);
                }
            }

            return $added;
        });
    }
}
