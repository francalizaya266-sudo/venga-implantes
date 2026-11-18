<?php

namespace App\Http\Controllers\Api\V1\Account;

use App\Enums\PatientRelationship;
use App\Enums\Sex;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreHealthRequest;
use App\Models\MedicalBackground;
use App\Models\Patient;
use App\Services\Access\PatientAccess;
use App\Services\Web\PageMeta;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * "Antes de tu cita": el paciente cuenta su salud desde el celular recordado, para que el doctor lo sepa
 * antes de empezar. Solo mientras no haya antecedentes: lo ya registrado no se muestra ni se cambia desde aquí.
 */
class HealthController extends Controller
{
    public function __construct(private readonly PatientAccess $access) {}

    /** Las preguntas, dichas para quien responde: "¿Tienes…?" al titular, "¿Lucía tiene…?" por un familiar. */
    public function show(Request $request, Patient $patient, PageMeta $meta): JsonResponse
    {
        $this->authorizePatient($request, $patient);

        if ($patient->medicalBackground()->exists()) {
            return $this->alreadyKnown($patient);
        }

        $self = $patient->relationship === PatientRelationship::Self;
        $name = $patient->first_name;
        $has = $self ? '¿Tienes' : '¿'.$name.' tiene';
        $asksPregnancy = self::asksPregnancy($patient);

        return response()->json([
            'data' => [
                'patient_id' => $patient->id,
                'title' => $self ? 'Antes de tu cita' : 'Antes de la cita de '.$name,
                'lead' => 'Dos minutos para que el doctor sepa '.($self ? 'cómo estás' : 'cómo está '.$name).' antes de empezar. Solo lo ve el equipo del consultorio.',
                'questions' => [
                    [
                        'choice' => 'has_allergies',
                        'detail' => 'allergies',
                        'question' => $has.' alguna alergia?',
                        'hint' => 'A medicinas, a la anestesia, al látex.',
                        'label' => '¿A qué?',
                        'placeholder' => 'Ej.: penicilina, látex',
                    ],
                    [
                        'choice' => 'takes_medications',
                        'detail' => 'medications',
                        'question' => ($self ? '¿Tomas' : '¿'.$name.' toma').' alguna medicina todos los días?',
                        'hint' => 'Por ejemplo, para la presión, el azúcar o para que la sangre no coagule.',
                        'label' => '¿Cuáles?',
                        'placeholder' => 'Ej.: losartán, metformina',
                    ],
                ],
                'conditions_question' => $has.' alguna de estas?',
                'conditions' => collect(MedicalBackground::CONDITIONS)
                    ->map(fn (string $label, string $key) => ['key' => $key, 'label' => $label])
                    ->values(),
                'also' => array_values(array_filter([
                    $asksPregnancy ? ['key' => 'is_pregnant', 'label' => $self ? 'Estoy embarazada' : 'Está embarazada'] : null,
                    $asksPregnancy ? ['key' => 'is_breastfeeding', 'label' => $self ? 'Estoy dando de lactar' : 'Está dando de lactar'] : null,
                    self::isChild($patient) ? null : ['key' => 'smoker', 'label' => $self ? 'Fumo' : 'Fuma'],
                ])),
                'notes_hint' => 'Opcional. Una operación reciente, un tratamiento que '.($self ? 'llevas' : 'lleva').', miedo al dentista.',
            ],
            'meta' => $meta->for(title: 'Antes de la cita', noindex: true),
        ]);
    }

    public function store(StoreHealthRequest $request, Patient $patient): JsonResponse
    {
        $this->authorizePatient($request, $patient);

        if ($patient->medicalBackground()->exists()) {
            return $this->alreadyKnown($patient);
        }

        $data = $request->validated();
        $asksPregnancy = self::asksPregnancy($patient);

        $patient->medicalBackground()->create([
            'allergies' => $data['has_allergies'] === 'yes' ? trim($data['allergies']) : 'Ninguna',
            'medications' => $data['takes_medications'] === 'yes' ? trim($data['medications']) : 'Ninguna',
            'conditions' => array_values($data['conditions'] ?? []),
            'is_pregnant' => $asksPregnancy && $request->boolean('is_pregnant'),
            'is_breastfeeding' => $asksPregnancy && $request->boolean('is_breastfeeding'),
            'smoker' => self::isChild($patient) ? null : $request->boolean('smoker'),
            'notes' => filled($data['notes'] ?? null) ? trim($data['notes']) : null,
            'filled_by_patient_at' => now(),
        ]);

        return response()->json([
            'message' => 'Gracias. El doctor lo tendrá a la vista y lo repasará '.($patient->relationship === PatientRelationship::Self ? 'contigo' : "con {$patient->first_name}").' en la cita.',
        ], 201);
    }

    private function authorizePatient(Request $request, Patient $patient): void
    {
        abort_unless($this->access->device($request)?->account_id === $patient->account_id, 403);
    }

    /** Ya hay antecedentes: no hay nada que llenar, y la web vuelve a Mi cuenta con este aviso. */
    private function alreadyKnown(Patient $patient): JsonResponse
    {
        return response()->json([
            'message' => 'El consultorio ya tiene los antecedentes de '.$patient->first_name.'. Si algo cambió, cuéntaselo al doctor en la cita.',
            'code' => 'already_known',
        ], 409);
    }

    /** Embarazo y lactancia se preguntan salvo a hombres y a niños. */
    private static function asksPregnancy(Patient $patient): bool
    {
        return $patient->sex !== Sex::Male && ! self::isChild($patient);
    }

    private static function isChild(Patient $patient): bool
    {
        return $patient->age !== null && $patient->age < 12;
    }
}
