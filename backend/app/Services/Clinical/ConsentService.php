<?php

namespace App\Services\Clinical;

use App\Enums\PatientRelationship;
use App\Enums\TreatmentKind;
use App\Models\Appointment;
use App\Models\Consent;
use App\Models\ConsentTemplate;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Setting;
use App\Models\Treatment;
use App\Models\User;
use App\Support\Signatures;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Consentimiento informado: el texto de la plantilla con los datos del paciente, firmado con
 * el dedo en la pantalla. Se guarda el texto tal como se firmó; si se retira, queda revocado.
 */
final class ConsentService
{
    /** Datos que se pueden usar en el texto de la plantilla. */
    public const PLACEHOLDERS = [
        '{paciente}' => 'Nombre del paciente',
        '{firmante}' => 'Quien firma (el paciente o su apoderado)',
        '{documento}' => 'Documento de quien firma',
        '{calidad}' => '"en mi calidad de paciente" o "de padre / madre del paciente"',
        '{doctor}' => 'Doctor que realiza el tratamiento',
        '{tratamiento}' => 'Tratamiento',
        '{fecha}' => 'Fecha de la firma',
        '{ciudad}' => 'Distrito o ciudad del consultorio (Ajustes)',
        '{riesgos}' => 'Riesgos propios del paciente según sus antecedentes (anticoagulantes, bifosfonatos, alergias…). Si no lo pones, van solos antes de la fecha',
    ];

    /**
     * Si la cita pide consentimiento: true si ya está firmado, false si falta. Null si no aplica (ningún texto de
     * consentimiento en uso le corresponde).
     */
    public function signedForVisit(Appointment $appointment): ?bool
    {
        return $this->signedForVisits(new Collection([$appointment]))[$appointment->getKey()] ?? null;
    }

    /**
     * Lo mismo para varias citas a la vez (las tarjetas de la agenda), con dos consultas.
     *
     * Un tratamiento con texto propio (una cirugía, un implante) se firma para cada cita: vale lo firmado desde
     * el día en que se agendó. Los demás usan el consentimiento general, que se firma una vez por tratamiento:
     * firmado para limpieza, cubre sus limpiezas siguientes, pero una extracción lo pide de nuevo. Si ninguno de
     * los dos está en uso, la cita no viene.
     *
     * @param  Collection<int, Appointment>  $appointments
     * @return array<int, bool> Por id de la cita.
     */
    public function signedForVisits(Collection $appointments): array
    {
        $templates = ConsentTemplate::query()
            ->where('is_active', true)
            ->where(fn (Builder $query) => $query->whereNull('treatment_id')->orWhereIn('treatment_id', $appointments->pluck('treatment_id')->filter()->unique()->values()->all()))
            ->pluck('treatment_id');
        $ownText = $templates->filter()->flip();
        $general = $templates->containsStrict(null);
        $visits = $appointments->filter(fn (Appointment $appointment) => $general || $ownText->has($appointment->treatment_id));

        if ($visits->isEmpty()) {
            return [];
        }

        $signed = Consent::query()
            ->whereIn('patient_id', $visits->pluck('patient_id')->unique()->values()->all())
            ->whereIn('treatment_id', $visits->pluck('treatment_id')->unique()->values()->all())
            ->whereNull('revoked_at')
            ->get(['patient_id', 'treatment_id', 'signed_at']);

        return $visits->mapWithKeys(function (Appointment $appointment) use ($signed, $ownText): array {
            /** @var CarbonInterface $bookedAt */
            $bookedAt = $appointment->created_at;
            $since = $ownText->has($appointment->treatment_id) ? $bookedAt->copy()->startOfDay() : null;

            return [$appointment->getKey() => $signed->contains(function (Consent $consent) use ($appointment, $since): bool {
                /** @var CarbonInterface|null $signedAt */
                $signedAt = $consent->signed_at;

                return (int) $consent->patient_id === (int) $appointment->patient_id
                    && (int) $consent->treatment_id === (int) $appointment->treatment_id
                    && $signedAt !== null
                    && ($since === null || $signedAt->gte($since));
            })];
        })->all();
    }

    /**
     * El texto que se propone al firmar: el del tratamiento o, si no tiene, el general. Con varios en uso para lo
     * mismo, el que se escribió o se corrigió último (antes ganaba el de más versiones, y uno nuevo nunca salía).
     */
    public function templateFor(?Treatment $treatment): ?ConsentTemplate
    {
        return ConsentTemplate::query()
            ->where('is_active', true)
            ->where(fn ($query) => $query->whereNull('treatment_id')->when($treatment, fn ($q) => $q->orWhere('treatment_id', $treatment->getKey())))
            ->orderByRaw('treatment_id is null')
            ->latest('updated_at')
            ->latest('id')
            ->first();
    }

    /**
     * Para la lista de textos: con varios en uso para el mismo tratamiento (o varios generales), cuál es el que
     * se propone primero al firmar. Null si es el único, o si no está en uso.
     */
    public function isProposedFirst(ConsentTemplate $template): ?bool
    {
        if (! $template->is_active) {
            return null;
        }

        $sameUse = ConsentTemplate::query()
            ->where('is_active', true)
            ->when(
                $template->treatment_id,
                fn (Builder $query, int $treatmentId) => $query->where('treatment_id', $treatmentId),
                fn (Builder $query) => $query->whereNull('treatment_id'),
            )
            ->latest('updated_at')
            ->latest('id')
            ->pluck('id');

        return $sameUse->count() > 1 ? $sameUse->first() === $template->getKey() : null;
    }

    /**
     * Quién firma por defecto: el paciente o, si es menor de edad, su apoderado (el registrado en su ficha o,
     * si no hay, el titular del celular que lo tiene a su cargo).
     *
     * @return array{signer_name: string, signer_document: string|null, signer_relationship: PatientRelationship}
     */
    public function defaultSigner(Patient $patient): array
    {
        if ($patient->isMinor() && filled($patient->guardian_name)) {
            return [
                'signer_name' => $patient->guardian_name,
                'signer_document' => $patient->guardian_document,
                'signer_relationship' => $patient->guardian_relationship ?? PatientRelationship::Parent,
            ];
        }

        $holder = $patient->relationship !== PatientRelationship::Self && $patient->isMinor()
            ? Patient::query()->where('account_id', $patient->account_id)->where('relationship', PatientRelationship::Self)->first()
            : null;

        $signer = $holder ?? $patient;

        return [
            'signer_name' => $signer->full_name,
            'signer_document' => $signer->document_number,
            'signer_relationship' => $holder ? self::inverse($patient->relationship) : PatientRelationship::Self,
        ];
    }

    /**
     * Texto final con los datos de esta firma. Si el paciente tiene riesgos propios para este tratamiento, el texto
     * los suma: donde diga {riesgos} o, si la plantilla no lo usa, antes del párrafo de la fecha.
     */
    public function render(string $body, Patient $patient, array $signer, ?Doctor $doctor, ?Treatment $treatment, CarbonInterface $date): string
    {
        return $this->fill($body, $patient->full_name, $signer, $doctor?->name, $treatment?->name, $date, TreatmentRisks::forConsent($patient, TreatmentKind::for($treatment)));
    }

    /**
     * Cómo queda un texto con datos de ejemplo, para revisarlo antes de guardarlo: lo que va entre llaves ya
     * reemplazado y un riesgo de muestra donde irían los del paciente.
     */
    public function preview(string $body, ?Treatment $treatment = null): string
    {
        return $this->fill(
            $body,
            'María Quispe Mamani',
            ['signer_name' => 'María Quispe Mamani', 'signer_document' => '45678912', 'signer_relationship' => PatientRelationship::Self],
            Doctor::query()->active()->orderBy('sort_order')->value('name') ?? 'Dra. Ana Torres',
            // Un texto general se firma con el tratamiento de ese paciente: el ejemplo lleva uno.
            $treatment?->name ?? 'Extracción de una muela',
            today(),
            ['Por la diabetes, la herida puede tardar más en sanar y hay más riesgo de infección.'],
        );
    }

    /**
     * El texto con cada dato en su lugar.
     *
     * @param  array<string, mixed>  $signer
     * @param  list<string>  $risks
     */
    private function fill(string $body, string $patientName, array $signer, ?string $doctorName, ?string $treatmentName, CarbonInterface $date, array $risks): string
    {
        if ($risks !== [] && ! str_contains($body, '{riesgos}')) {
            $paragraphs = preg_split("/\R\s*\R/", trim($body));
            $dated = collect($paragraphs)->search(fn (string $paragraph) => str_contains($paragraph, '{fecha}'));
            array_splice($paragraphs, $dated === false ? count($paragraphs) : $dated, 0, ['{riesgos}']);
            $body = implode("\n\n", $paragraphs);
        }

        $relationship = $signer['signer_relationship'] instanceof PatientRelationship
            ? $signer['signer_relationship']
            : (PatientRelationship::tryFrom((string) ($signer['signer_relationship'] ?? '')) ?? PatientRelationship::Self);

        $text = strtr($body, [
            '{paciente}' => $patientName,
            '{firmante}' => filled($signer['signer_name'] ?? null) ? $signer['signer_name'] : '________________',
            '{documento}' => filled($signer['signer_document'] ?? null) ? 'documento N.º '.$signer['signer_document'] : 'documento N.º ________',
            '{calidad}' => $relationship === PatientRelationship::Self
                ? 'en mi calidad de paciente'
                : 'en mi calidad de '.mb_strtolower($relationship->getLabel()).' del paciente',
            '{doctor}' => $doctorName ?? 'el doctor tratante',
            '{tratamiento}' => $treatmentName !== null ? mb_strtolower($treatmentName) : 'el tratamiento indicado',
            '{fecha}' => $date->translatedFormat('j \d\e F \d\e Y'),
            '{ciudad}' => (string) (Setting::get('clinic.district') ?: '________'),
            '{riesgos}' => $risks === [] ? '' : "Por los antecedentes del paciente, se me explicó además que:\n".collect($risks)->map(fn (string $risk) => '• '.$risk)->implode("\n"),
        ]);

        // Sin riesgos, {riesgos} no deja un hueco entre párrafos.
        return preg_replace("/\n{3,}/", "\n\n", $text);
    }

    /**
     * Guarda el consentimiento firmado.
     *
     * @param  array{signer_name: string, signer_document?: string|null, signer_relationship: PatientRelationship|string}  $signer
     */
    public function sign(Patient $patient, ConsentTemplate $template, ?Treatment $treatment, array $signer, string $signature, User $by): Consent
    {
        $png = Signatures::png($signature);
        $doctor = $by->doctor;
        $now = now();

        return DB::transaction(function () use ($patient, $template, $treatment, $signer, $png, $doctor, $now) {
            $path = 'clinica/'.$patient->getKey().'/firmas/'.Str::uuid().'.png';
            Storage::disk(ClinicalFileService::DISK)->put($path, $png);

            return Consent::create([
                'patient_id' => $patient->getKey(),
                'consent_template_id' => $template->getKey(),
                'treatment_id' => $treatment?->getKey(),
                'doctor_id' => $doctor?->getKey(),
                'title' => $template->name.($treatment ? ' · '.$treatment->name : ''),
                'body_snapshot' => $this->render($template->body, $patient, $signer, $doctor, $treatment, $now),
                'signer_name' => $signer['signer_name'],
                'signer_document' => $signer['signer_document'] ?? null,
                'signer_relationship' => $signer['signer_relationship'],
                'signature_path' => $path,
                'signed_at' => $now,
            ]);
        });
    }

    /** El paciente retiró su consentimiento: no se borra, queda revocado con la fecha. */
    public function revoke(Consent $consent): void
    {
        if ($consent->revoked_at === null) {
            $consent->update(['revoked_at' => now()]);
        }
    }

    /** Firma lista para mostrarla en la hoja impresa. */
    public function signatureDataUri(Consent $consent): ?string
    {
        return Signatures::dataUri($consent->signature_path);
    }

    /** Si el paciente es "hijo" del titular, el titular es su "padre / madre", y así. */
    private static function inverse(PatientRelationship $relationship): PatientRelationship
    {
        return match ($relationship) {
            PatientRelationship::Child => PatientRelationship::Parent,
            PatientRelationship::Parent => PatientRelationship::Child,
            PatientRelationship::Grandchild => PatientRelationship::Grandparent,
            PatientRelationship::Grandparent => PatientRelationship::Grandchild,
            default => $relationship,
        };
    }
}
