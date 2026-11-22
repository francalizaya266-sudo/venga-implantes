<?php

namespace App\Livewire;

use App\Enums\Dentition;
use App\Enums\ToothState;
use App\Exceptions\ClinicalException;
use App\Models\Consultation;
use App\Models\Icd10Code;
use App\Models\Odontogram;
use App\Models\OdontogramCondition;
use App\Models\OdontogramEntry;
use App\Models\Patient;
use App\Models\Treatment;
use App\Services\Clinical\FindingsDictation;
use App\Services\Clinical\OdontogramService;
use App\Services\Clinical\OdontogramSuggestions;
use App\Support\Money;
use App\Support\OdontogramAbbreviations;
use App\Support\Teeth;
use Filament\Notifications\Notification;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Odontograma interactivo. Se toca una pieza y se abre su ficha (sus caras en grande, lo que tiene y lo que
 * se le puede marcar); o se elige un hallazgo (el "pincel") y se toca pieza tras pieza. También se escribe o
 * se dicta: «36 MO caries, 18 ausente». Rojo = por tratar, azul = tratado. En modo lectura permite ver
 * versiones anteriores y qué cambió en cada una.
 *
 * El pincel, la pieza abierta, los filtros y la pantalla completa viven en el navegador
 * (public/js/odontogram.js) y el dibujo se pinta apenas se toca: al servidor solo va cada marca. Debajo, lo
 * que está en rojo se sugiere para el diagnóstico y el plan.
 */
class OdontogramChart extends Component
{
    /**
     * Hallazgos que se muestran como botones, en una fila: lo que no cabe queda detrás de "Más", empezando por los
     * últimos (todo lo demás, en el buscador). En un niño, el sellante pasa adelante y el implante atrás.
     */
    public const FREQUENT = ['caries', 'restauracion', 'extraccion_indicada', 'ausente', 'implante', 'corona', 'tratamiento_pulpar', 'sellante', 'fractura', 'remanente_radicular', 'protesis_fija', 'ortodoncia_fija'];

    /** Los primeros botones de hallazgos se ven siempre, aunque la fila sea angosta. */
    public const ALWAYS_SHOWN_CHIPS = 3;

    /** Lo que la ficha de la pieza ofrece marcar primero, según el tipo de diente; el resto se busca. */
    public const QUICK = [
        'posterior' => ['caries', 'restauracion', 'sellante', 'corona', 'tratamiento_pulpar', 'extraccion_indicada', 'ausente', 'fractura', 'remanente_radicular', 'implante'],
        'anterior' => ['caries', 'restauracion', 'corona', 'tratamiento_pulpar', 'fractura', 'extraccion_indicada', 'ausente', 'remanente_radicular', 'implante', 'giroversion'],
    ];

    /** Nombres cortos para los botones (el nombre completo queda en el título y en la lista). */
    public const CHIP_NAMES = [
        'restauracion' => 'Restauración', 'extraccion_indicada' => 'Extracción', 'ausente' => 'Ausente',
        'corona' => 'Corona', 'implante' => 'Implante', 'ortodoncia_fija' => 'Ortodoncia fija',
    ];

    /** Siglas para hallazgos que no tienen una propia en el catálogo. */
    public const SHORT = [
        'restauracion_temporal' => 'RT', 'erupcion' => 'ERU', 'extruida' => 'EXT', 'intruida' => 'INT',
        'giroversion' => 'GIR', 'migracion' => 'MIG', 'supernumeraria' => 'SN', 'fusion' => 'FUS',
        'geminacion' => 'GEM', 'fractura' => 'FX', 'protesis_fija' => 'PF', 'protesis_removible' => 'PR',
        'protesis_total' => 'PT', 'ortodoncia_fija' => 'AOF', 'ortodoncia_removible' => 'AOR',
        'diastema' => 'DIA', 'transposicion' => 'TRA', 'edentulo_total' => 'EDT',
    ];

    #[Locked]
    public ?int $odontogramId = null;

    #[Locked]
    public ?int $patientId = null;

    #[Locked]
    public bool $editable = false;

    public string $specifications = '';

    public string $observations = '';

    /** Hallazgos escritos o dictados que esperan "Agregar". */
    public string $dictation = '';

    /** Sugerencias ya agregadas en esta pantalla, mientras la atención guarda sus diagnósticos. */
    public array $applied = [];

    /**
     * "Deshacer": por cada cambio de esta pantalla (un toque, "Tratado", quitar, lo dictado, la dentición), qué marcas
     * se agregaron, cómo estaban las que se quitaron o cambiaron y la dentición de antes. Los últimos pasos, del más
     * viejo al más nuevo.
     *
     * @var list<array{label: string, added: list<int>, removed: array<int, array<string, mixed>>, changed: array<int, array<string, mixed>>, dentition?: string|null}>
     */
    #[Locked]
    public array $undo = [];

    /**
     * Cambiar de dentición con marcas en piezas que la nueva no tiene: se pregunta antes de quitarlas.
     *
     * @var array{dentition: string, teeth: list<int>, marks: int, movable: bool}|null
     */
    #[Locked]
    public ?array $pendingDentition = null;

    /** Sube con cada cambio de dentición pedido: el selector se vuelve a dibujar y muestra la que quedó de verdad. */
    #[Locked]
    public int $dentitionRevision = 0;

    /** Cuántos cambios se pueden deshacer. */
    private const UNDO_STEPS = 10;

    /** Lo que se guarda de cada marca para poder volverla a como estaba. */
    private const UNDO_FIELDS = ['tooth', 'surface', 'odontogram_condition_id', 'state', 'abbreviation', 'to_tooth', 'notes'];

    /** Recibe la atención (odontograma de esa consulta) o el paciente (su último odontograma). */
    public function mount(?Model $record = null, ?int $odontogramId = null, bool $editable = false): void
    {
        $odontogram = match (true) {
            $odontogramId !== null => Odontogram::find($odontogramId),
            $record instanceof Consultation => $record->odontogram,
            $record instanceof Patient => $record->odontograms()->latest('id')->first(),
            default => null,
        };

        $this->patientId = $odontogram?->patient_id ?? ($record instanceof Patient ? $record->getKey() : null);
        abort_if($this->patientId && ! auth()->user()->can('viewClinical', Patient::find($this->patientId)), 403);

        $this->editable = $editable;
        $this->load($odontogram);
    }

    /** En modo lectura: cambia a otra versión del odontograma del paciente. */
    public function showVersion(?int $id): void
    {
        if (! $this->editable) {
            $this->load($id ? Odontogram::where('patient_id', $this->patientId)->find($id) : null);
        }
    }

    private function load(?Odontogram $odontogram): void
    {
        $this->odontogramId = $odontogram?->getKey();
        $this->specifications = (string) $odontogram?->specifications;
        $this->observations = (string) $odontogram?->observations;
        $this->undo = [];
        $this->pendingDentition = null;
        unset($this->odontogram, $this->newEntryIds);
    }

    /** Toque con el pincel en una pieza o superficie: marca el hallazgo (en un puente, de $tooth a $toTooth). */
    public function mark(int $tooth, ?string $surface = null, ?string $code = null, string $state = 'bad', ?string $abbreviation = null, ?int $toTooth = null): void
    {
        $condition = $code ? $this->conditions->firstWhere('code', $code) : null;

        if (! $condition || ! $this->canEdit()) {
            return;
        }

        $this->tracked("{$condition->name} en la {$tooth}", fn () => $this->attempt(fn () => app(OdontogramService::class)->toggle(
            $this->odontogram,
            $condition,
            ToothState::tryFrom($state) ?? ToothState::Bad,
            $tooth,
            $surface,
            $toTooth,
            $abbreviation,
        )));
    }

    /**
     * De la lista o de la ficha: la caries pasa a restauración, la extracción a pieza ausente y lo demás a azul
     * (una marca o todas las de una fila, como las caras de una misma caries).
     * Si con eso queda hecho un procedimiento del plan, la atención lo marca como trabajado hoy.
     *
     * @param  int|list<int>  $entryIds
     */
    public function markTreated(int|array $entryIds, ?string $abbreviation = null): void
    {
        $entries = $this->canEdit() ? $this->odontogram->entries->whereIn('id', (array) $entryIds) : collect();
        $finished = collect();

        $this->tracked('"Tratado" en la '.$entries->pluck('tooth')->unique()->implode(', '), function () use ($entries, $abbreviation, &$finished): void {
            foreach ($entries as $entry) {
                [$tooth, $code, $was] = [$entry->tooth, $entry->condition->code, $entry->abbreviation];
                $this->attempt(fn () => app(OdontogramService::class)->markTreated($entry, $abbreviation));

                $finished = $finished->merge(app(OdontogramSuggestions::class)->finishedItems($this->odontogram->consultation, $tooth, $code, $was));
            }
        });

        if ($finished->isNotEmpty()) {
            $this->dispatch('odontogram-plan-items-worked', items: $finished->pluck('id')->unique()->values()->all());
        }
    }

    /**
     * Agrega las sugerencias elegidas: los procedimientos van al plan y los diagnósticos a la atención.
     *
     * @param  list<string>  $keys
     */
    public function applySuggestions(array $keys): void
    {
        if (! $this->canEdit() || $keys === []) {
            return;
        }

        $result = app(OdontogramSuggestions::class)->apply($this->odontogram->consultation, auth()->user(), $keys);
        $this->applied = array_values(array_unique([...$this->applied, ...$keys]));

        $this->dispatch(
            'odontogram-suggestions-applied',
            diagnoses: $result['diagnoses'],
            planned: $result['added'],
            plannedTotal: $result['added'] ? Money::format($result['total']) : '',
        );
    }

    /** Marca lo escrito o dictado que se entendió; lo que no, queda en el cuadro para corregirlo. */
    public function addDictated(?string $text = null): void
    {
        if (! $this->canEdit()) {
            return;
        }

        $this->dictation = $text ?? $this->dictation;
        $odontogram = $this->odontogram;
        $pending = [];
        $marked = 0;

        $this->tracked('lo dictado', function () use ($odontogram, &$pending, &$marked): void {
            foreach ($this->dictated as $finding) {
                if ($finding['error']) {
                    $pending[] = $finding['text'];

                    continue;
                }

                foreach ($finding['marks'] as $mark) {
                    $condition = $this->conditions->firstWhere('code', $mark['code']);

                    try {
                        app(OdontogramService::class)->place($odontogram, $condition, ToothState::from($mark['state']), $mark['tooth'], $mark['surface'], $mark['toTooth'], $mark['abbreviation']);
                        $marked++;
                    } catch (ClinicalException $e) {
                        $pending[] = $finding['text'];
                        Notification::make()->warning()->title("{$finding['text']}: {$e->getMessage()}")->send();
                    }
                }
            }
        });

        $this->dictation = implode(', ', array_unique($pending));
        unset($this->odontogram, $this->dictated, $this->newEntryIds);

        if ($marked) {
            Notification::make()->success()->title($marked === 1 ? 'Se marcó 1 hallazgo.' : "Se marcaron {$marked} hallazgos.")->send();
        }
    }

    /** @param  int|list<int>  $entryIds */
    public function remove(int|array $entryIds): void
    {
        if (! $this->canEdit()) {
            return;
        }

        $entries = $this->odontogram->entries()->whereKey((array) $entryIds)->get();
        $this->tracked('quitar la marca de la '.$entries->pluck('tooth')->unique()->implode(', '), fn () => $entries->each->delete());
        unset($this->odontogram, $this->newEntryIds);
    }

    /**
     * "Deshacer" (o Ctrl + Z): las marcas vuelven a como estaban antes del último cambio. Lo que ese cambio mandó
     * al plan o al diagnóstico no se toca.
     */
    public function undoLast(): void
    {
        if (! $this->canEdit() || $this->undo === []) {
            return;
        }

        $step = array_pop($this->undo);
        $odontogramId = $this->odontogramId;
        $renamed = [];

        DB::transaction(function () use ($step, $odontogramId, &$renamed): void {
            if ($step['dentition'] ?? null) {
                Odontogram::query()->whereKey($odontogramId)->update(['dentition' => $step['dentition']]);
            }

            OdontogramEntry::query()->where('odontogram_id', $odontogramId)->whereKey($step['added'])->get()->each->delete();

            foreach ($step['changed'] as $id => $attributes) {
                OdontogramEntry::query()->where('odontogram_id', $odontogramId)->find($id)?->forceFill($attributes)->save();
            }

            foreach ($step['removed'] as $id => $attributes) {
                $renamed[$id] = OdontogramEntry::query()->forceCreate([...$attributes, 'odontogram_id' => $odontogramId])->getKey();
            }
        });

        // Las marcas que volvieron tienen otro id: los pasos anteriores las buscan por el nuevo.
        $this->undo = array_map(fn (array $older): array => [
            ...$older,
            'added' => array_map(fn (int $id): int => $renamed[$id] ?? $id, $older['added']),
            'changed' => collect($older['changed'])->mapWithKeys(fn (array $attributes, int $id): array => [$renamed[$id] ?? $id => $attributes])->all(),
        ], $this->undo);
        $this->dentitionRevision++;
        $this->pendingDentition = null;
        unset($this->odontogram, $this->newEntryIds);
        Notification::make()->success()->title('Se deshizo: '.$step['label'])->send();
    }

    /**
     * Cambiar la dentición (se puede deshacer). Si hay marcas en piezas que la nueva no tiene, primero se pregunta
     * qué hacer con ellas (confirmDentition).
     */
    public function setDentition(string $dentition): void
    {
        $this->dentitionRevision++;
        $this->pendingDentition = null;
        $odontogram = $this->odontogram;

        if (! $this->canEdit() || ! $odontogram || ! ($value = Dentition::tryFrom($dentition)) || $value === $odontogram->dentition) {
            return;
        }

        $outside = app(OdontogramService::class)->entriesOutside($odontogram, $value);

        if ($outside->isEmpty()) {
            $this->changeDentition($odontogram, $value);

            return;
        }

        $teeth = $outside->flatMap(fn (OdontogramEntry $entry) => [$entry->tooth, $entry->to_tooth])->filter()->unique()->sort()->values();

        $this->pendingDentition = [
            'dentition' => $value->value,
            'teeth' => $teeth->all(),
            'marks' => $outside->count(),
            // A adulto, si todas son de leche: cada una tiene su pieza de adulto en el mismo lugar (la 63 es la 23).
            'movable' => $value === Dentition::Permanent && $teeth->every(fn (int $tooth) => Teeth::adultOf($tooth) !== null),
        ];
    }

    /** La respuesta a la pregunta: "move" pasa las marcas a la pieza de adulto, "remove" las quita. */
    public function confirmDentition(string $how): void
    {
        $pending = $this->pendingDentition;
        $this->pendingDentition = null;
        $this->dentitionRevision++;

        $odontogram = Odontogram::query()->find($this->odontogramId);

        if (! $this->canEdit() || ! $odontogram || $pending === null || ! in_array($how, ['move', 'remove'], true)) {
            return;
        }

        $this->changeDentition($odontogram, Dentition::from($pending['dentition']), moveToAdult: $how === 'move' && $pending['movable']);
    }

    public function cancelDentition(): void
    {
        $this->pendingDentition = null;
        $this->dentitionRevision++;
    }

    /**
     * La pregunta al cambiar de dentición con marcas que no caben: "5 marcas en la pieza 63, que no existe en adulto".
     *
     * @return array{text: string, name: string, move: string|null, remove: string}|null
     */
    #[Computed]
    public function dentitionQuestion(): ?array
    {
        if ($this->pendingDentition === null) {
            return null;
        }

        ['dentition' => $dentition, 'teeth' => $teeth, 'marks' => $marks, 'movable' => $movable] = $this->pendingDentition;
        $name = self::dentitionName(Dentition::from($dentition));
        $where = count($teeth) === 1
            ? "en la pieza {$teeth[0]}, que no existe"
            : 'en las piezas '.Arr::join($teeth, ', ', ' y ').', que no existen';
        $them = $marks === 1 ? 'la' : 'las';

        return [
            'text' => ($marks === 1 ? '1 marca' : "{$marks} marcas")." {$where} en {$name}. ¿Qué hago con el{$them}?",
            'name' => $name,
            'move' => $movable ? "Pasar{$them} a la ".Arr::join(array_map(fn (int $tooth) => Teeth::adultOf($tooth), $teeth), ', ', ' y ') : null,
            'remove' => "Quitar{$them}",
        ];
    }

    /**
     * Un adulto (13 años o más) con el odontograma de niño o mixto: casi siempre es un error, pero puede tener un
     * diente de leche que no se cayó.
     */
    private static function dentitionWarning(?Odontogram $odontogram, bool $canEdit): ?string
    {
        $patient = $odontogram?->patient;
        $age = $patient?->age;

        if (! $canEdit || ! $odontogram || $age === null || $age < 13 || $odontogram->dentition === Dentition::Permanent) {
            return null;
        }

        return "{$patient->first_name} tiene {$age} años y el odontograma está en ".self::dentitionName($odontogram->dentition).'. Si no tiene dientes de leche, pásalo a adulto.';
    }

    /** "adulto", "niño", "mixta": el nombre corto de la dentición, para las frases. */
    private static function dentitionName(Dentition $dentition): string
    {
        return mb_strtolower(Str::before($dentition->getLabel(), ' ('));
    }

    private function changeDentition(Odontogram $odontogram, Dentition $dentition, bool $moveToAdult = false): void
    {
        $this->tracked('pasar a '.self::dentitionName($dentition), fn () => $this->attempt(
            fn () => app(OdontogramService::class)->changeDentition($odontogram, $dentition, removeOutside: true, moveToAdult: $moveToAdult),
        ));
        unset($this->odontogram, $this->newEntryIds);
    }

    public function updatedSpecifications(): void
    {
        $this->saveNotes();
    }

    public function updatedObservations(): void
    {
        $this->saveNotes();
    }

    #[Computed]
    public function odontogram(): ?Odontogram
    {
        return $this->odontogramId
            ? Odontogram::with(['entries.condition', 'doctor', 'consultation'])->find($this->odontogramId)
            : null;
    }

    /** @return Collection<int, OdontogramCondition> */
    #[Computed]
    public function conditions(): Collection
    {
        return OdontogramCondition::query()->where('is_active', true)->orderBy('sort_order')->get();
    }

    /**
     * Lo que se entendió de lo escrito o dictado, para revisarlo antes de marcar.
     *
     * @return list<array{text: string, summary: string|null, error: string|null, marks: list<array<string, mixed>>}>
     */
    #[Computed]
    public function dictated(): array
    {
        return $this->odontogram && trim($this->dictation) !== ''
            ? app(FindingsDictation::class)->parse($this->dictation, $this->odontogram->dentition, $this->conditions)
            : [];
    }

    /**
     * Los hallazgos frecuentes en el orden de sus botones: en un niño (dentición de leche o mixta) el sellante va
     * antes, en el lugar del implante.
     *
     * @return list<string>
     */
    private function frequentCodes(?Dentition $dentition): array
    {
        if (in_array($dentition, [null, Dentition::Permanent], true)) {
            return self::FREQUENT;
        }

        $codes = self::FREQUENT;
        [$implant, $sealant] = [array_search('implante', $codes, true), array_search('sellante', $codes, true)];
        [$codes[$implant], $codes[$sealant]] = [$codes[$sealant], $codes[$implant]];

        return $codes;
    }

    /**
     * Otros hallazgos que el doctor marca seguido (en dos pacientes o más): se sugieren junto a los botones.
     *
     * @return Collection<int, OdontogramCondition>
     */
    #[Computed]
    public function favorites(): Collection
    {
        $doctorId = $this->odontogram?->doctor_id;

        if (! $doctorId) {
            return collect();
        }

        $ids = OdontogramEntry::query()
            ->join('odontograms', 'odontograms.id', '=', 'odontogram_entries.odontogram_id')
            ->where('odontograms.doctor_id', $doctorId)
            ->whereIn('odontogram_condition_id', $this->conditions->whereNotIn('code', self::FREQUENT)->modelKeys())
            ->groupBy('odontogram_condition_id')
            ->havingRaw('count(distinct odontograms.patient_id) >= 2')
            ->orderByRaw('count(distinct odontograms.patient_id) desc')
            ->limit(3)
            ->pluck('odontogram_condition_id');

        return $ids->map(fn (int $id) => $this->conditions->firstWhere('id', $id))->filter()->values();
    }

    /** @return Collection<int, Odontogram> */
    #[Computed]
    public function versions(): Collection
    {
        return $this->editable || ! $this->patientId
            ? collect()
            : Odontogram::query()->with('doctor')->where('patient_id', $this->patientId)->latest('id')->get();
    }

    /**
     * Las marcas nuevas en esta versión: las que no estaban igual en la versión anterior del paciente (en la
     * atención de hoy, lo marcado hoy). En el primer odontograma, todas.
     *
     * @return list<int>
     */
    #[Computed]
    public function newEntryIds(): array
    {
        $odontogram = $this->odontogram;

        if (! $odontogram) {
            return [];
        }

        $previous = Odontogram::query()
            ->where('patient_id', $odontogram->patient_id)
            ->where('id', '<', $odontogram->getKey())
            ->latest('id')
            ->first()?->entries()->get()
            ->map(self::signature(...))
            ->flip() ?? collect();

        return $odontogram->entries
            ->reject(fn (OdontogramEntry $entry) => $previous->has(self::signature($entry)))
            ->modelKeys();
    }

    public function canEdit(): bool
    {
        $consultation = $this->odontogram?->consultation;

        return $this->editable && $consultation && auth()->user()->can('update', $consultation);
    }

    public function render(): View
    {
        $odontogram = $this->odontogram;
        $entries = $odontogram?->entries ?? collect();
        $canEdit = $this->canEdit();
        $rows = $odontogram ? Teeth::rows($odontogram->dentition) : [];
        // En el primer odontograma todo es nuevo: resaltarlo no dice nada.
        $newIds = count($this->newEntryIds) < $entries->count() ? $this->newEntryIds : [];
        $teeth = self::teethData($entries, $newIds, $rows);

        $codes = $this->frequentCodes($odontogram?->dentition);
        $frequent = $this->conditions->whereIn('code', $codes)->sortBy(fn ($c) => array_search($c->code, $codes))->values();
        $suggestions = $canEdit ? $this->suggestions() : [];

        return view('livewire.odontogram-chart', [
            'odontogram' => $odontogram,
            'rows' => $rows,
            'teeth' => $teeth,
            'spans' => $entries->filter(fn (OdontogramEntry $e) => $e->to_tooth !== null)->values(),
            'today' => $newIds,
            'groups' => self::groups($entries, $newIds),
            'summary' => collect(['bad', 'good', 'missing', 'today'])->mapWithKeys(fn (string $flag) => [$flag => collect($teeth)->filter(fn (array $tooth) => $tooth['flags'][$flag])->count()])->all(),
            'state' => $odontogram ? ['rows' => $rows, 'teeth' => $this->inspectorData($entries, $newIds, $rows, $suggestions)] : [],
            'preview' => collect($this->dictated)->pluck('marks')->flatten(1)->flatMap(fn (array $mark) => self::spanTeeth($mark['tooth'], $mark['toTooth'], $rows))->unique()->values()->all(),
            'frequent' => $frequent,
            'canEdit' => $canEdit,
            'dentitionWarning' => self::dentitionWarning($odontogram, $canEdit),
            'config' => [
                'editable' => $canEdit,
                'catalog' => $canEdit ? $this->catalog() : [],
                'frequent' => $frequent->pluck('code')->all(),
                'favorites' => $canEdit ? $this->favorites->pluck('code')->all() : [],
                'quick' => self::QUICK,
                'surfaceNames' => collect(['V', 'P', 'L', 'M', 'D', 'O', 'I'])->mapWithKeys(fn (string $s) => [$s => Teeth::surfaceName($s)])->all(),
            ],
            'suggestions' => $suggestions,
        ]);
    }

    /**
     * Lo que la ficha de cada pieza muestra sin preguntar al servidor: su nombre y lo que tiene, con las caras de
     * una misma caries juntas (como en la lista). Un puente aparece en cada pieza que cubre. Y lo que se sugiere
     * para el diagnóstico y el plan de esa pieza, para agregarlo desde ahí sin bajar a buscarlo.
     *
     * @param  Collection<int, OdontogramEntry>  $entries
     * @param  list<int>  $newIds
     * @param  list<list<int>>  $rows
     * @param  list<array{key: string, tooth: string, surfaces: string|null, findings: list<string>, diagnoses: Collection<int, Icd10Code>, treatment: Treatment|null, planned: bool, diagnosed: bool, optional: bool, done: bool, suggested: bool}>  $suggestions
     * @return array<int, array{name: string, posterior: bool, entries: list<array{ids: list<int>, code: string, name: string, state: string, where: string, treatable: bool, new: bool}>, plan: list<array{key: string, text: string, detail: string, button: string|null}>}>
     */
    private function inspectorData(Collection $entries, array $newIds, array $rows, array $suggestions = []): array
    {
        $byTooth = [];

        foreach (self::groups($entries, $newIds) as $group) {
            $first = $entries->firstWhere('id', $group['ids'][0]);

            foreach (self::spanTeeth($first->tooth, $first->to_tooth, $rows) as $tooth) {
                $byTooth[$tooth][] = [
                    'ids' => $group['ids'],
                    'code' => $group['code'],
                    'name' => $group['name'],
                    'state' => $group['state'],
                    'where' => match (true) {
                        $first->to_tooth !== null => "de la {$first->tooth} a la {$first->to_tooth}",
                        $group['surfaces'] !== null => $group['surfaces'],
                        default => 'toda la pieza',
                    },
                    'treatable' => $group['treatable'],
                    'new' => $group['new'],
                ];
            }
        }

        $plan = collect($suggestions)->groupBy('tooth')->map(fn (Collection $ofTooth) => $ofTooth->map(fn (array $row) => self::cardSuggestion($row))->values()->all());

        return collect(array_merge(...$rows))->mapWithKeys(fn (int $tooth) => [$tooth => [
            'name' => Teeth::name($tooth),
            'posterior' => ! Teeth::isAnterior($tooth),
            'entries' => $byTooth[$tooth] ?? [],
            'plan' => $plan->get((string) $tooth, []),
        ]])->all();
    }

    /**
     * Una sugerencia en la ficha de la pieza: "MO · Restauración con resina", sus códigos y precio, y el botón
     * con lo que falta agregar (las dos cosas, solo el diagnóstico o solo el plan). Sin botón si ya está todo.
     *
     * @param  array{key: string, tooth: string, surfaces: string|null, findings: list<string>, diagnoses: Collection<int, Icd10Code>, treatment: Treatment|null, planned: bool, diagnosed: bool, optional: bool, done: bool, suggested: bool}  $row
     * @return array{key: string, text: string, detail: string, button: string|null}
     */
    private static function cardSuggestion(array $row): array
    {
        $treatment = $row['treatment'];
        $diagnoses = $row['diagnoses'];
        $toPlan = $treatment !== null && ! $row['planned'];

        return [
            'key' => $row['key'],
            'text' => ($row['surfaces'] ? $row['surfaces'].' · ' : '').($treatment->name ?? $diagnoses->first()?->description),
            'detail' => $diagnoses->map(fn (Icd10Code $code) => $code->code.' '.$code->description)
                ->push(match (true) {
                    $toPlan => (float) $treatment->price_from > 0 ? Money::format((float) $treatment->price_from) : 'Sin precio',
                    $row['planned'] => 'Ya está en el plan',
                    default => null,
                })
                ->filter()
                ->implode(' · '),
            'button' => match (true) {
                $row['done'] => null,
                $toPlan && ! $row['diagnosed'] => '+ Diagnóstico y plan',
                $toPlan => '+ Al plan',
                default => '+ Diagnóstico',
            },
        ];
    }

    /**
     * Lo que el navegador necesita de cada hallazgo para elegirlo y pintar sin preguntar al servidor.
     *
     * @return array<string, array{name: string, short: string, appliesTo: string, good: bool, bad: bool, abbreviations: list<string>, meanings: array<string, array{0: string, 1: string}>, treatedAs: string}>
     */
    private function catalog(): array
    {
        return $this->conditions->mapWithKeys(fn (OdontogramCondition $condition) => [$condition->code => [
            'name' => $condition->name,
            'short' => self::CHIP_NAMES[$condition->code] ?? $condition->name,
            'appliesTo' => $condition->applies_to,
            'good' => $condition->allows_good_state,
            'bad' => $condition->allows_bad_state,
            'abbreviations' => $condition->abbreviations ?? [],
            'meanings' => array_intersect_key(OdontogramAbbreviations::MEANINGS[$condition->code] ?? [], array_flip($condition->abbreviations ?? [])),
            'treatedAs' => OdontogramService::TREATED_AS[$condition->code] ?? $condition->code,
        ]])->all();
    }

    /**
     * Sugerencias para el diagnóstico y el plan; lo agregado en esta pantalla se ve como hecho.
     *
     * @return list<array{key: string, tooth: string, surfaces: string|null, findings: list<string>, diagnoses: Collection<int, Icd10Code>, treatment: Treatment|null, planned: bool, diagnosed: bool, optional: bool, done: bool, suggested: bool}>
     */
    private function suggestions(): array
    {
        return array_map(
            fn (array $row) => in_array($row['key'], $this->applied, true) ? [...$row, 'done' => true, 'suggested' => false] : $row,
            app(OdontogramSuggestions::class)->rows($this->odontogram, $this->odontogram?->consultation),
        );
    }

    public static function label(OdontogramEntry $entry): string
    {
        return $entry->abbreviation ?? $entry->condition->abbreviation ?? self::SHORT[$entry->condition->code] ?? '';
    }

    /** "Caries (CD · dentina)": el hallazgo con su sigla y lo que significa. */
    public static function findingName(OdontogramEntry $entry): string
    {
        return $entry->condition->name.($entry->abbreviation ? ' ('.OdontogramAbbreviations::withWord($entry->condition->code, $entry->abbreviation).')' : '');
    }

    /**
     * Por pieza: color de cada superficie, hallazgos de pieza completa, siglas y lo que la pieza tiene (algo por
     * tratar, algo tratado, ausente, algo nuevo) para los filtros. Un puente cuenta en cada pieza que cubre.
     *
     * @param  Collection<int, OdontogramEntry>  $entries
     * @param  list<int>  $newIds  Marcas nuevas en esta versión.
     * @param  list<list<int>>  $rows  Las filas del dibujo, para saber qué piezas cubre un puente.
     * @return array<int, array{surfaces: array<string, string>, marks: array<string, string>, labels: list<array{text: string, state: string, meaning: string}>, flags: array{bad: bool, good: bool, missing: bool, today: bool}}>
     */
    public static function teethData(Collection $entries, array $newIds = [], array $rows = []): array
    {
        $teeth = [];
        $empty = ['surfaces' => [], 'marks' => [], 'labels' => [], 'flags' => ['bad' => false, 'good' => false, 'missing' => false, 'today' => false]];

        foreach ($rows ? array_merge(...$rows) : [] as $tooth) {
            $teeth[$tooth] = $empty;
        }

        foreach ($entries->sortBy('id') as $entry) {
            $data = $teeth[$entry->tooth] ?? $empty;
            $state = $entry->state->value;

            if ($entry->surface) {
                $data['surfaces'][$entry->surface] = $state;
            } elseif ($entry->to_tooth === null) {
                $data['marks'][$entry->condition->code] = $state;
            }

            // Una sigla por recuadro aunque se repita en varias caras (CD en mesial y en oclusal).
            $text = $entry->to_tooth === null ? self::label($entry) : '';
            $repeated = collect($data['labels'])->contains(fn (array $label) => $label['text'] === $text && $label['state'] === $state);

            if ($text !== '' && ! $repeated) {
                $data['labels'][] = ['text' => $text, 'state' => $state, 'meaning' => OdontogramAbbreviations::full($entry->condition->code, $entry->abbreviation) ?? $entry->condition->name];
            }

            $teeth[$entry->tooth] = $data;

            foreach (self::spanTeeth($entry->tooth, $entry->to_tooth, $rows) as $tooth) {
                $flags = ($teeth[$tooth] ?? $empty)['flags'];
                $missing = in_array($entry->condition->code, ['ausente', 'edentulo_total'], true);
                $teeth[$tooth] = ($teeth[$tooth] ?? $empty);
                $teeth[$tooth]['flags'] = [
                    'bad' => $flags['bad'] || $state === 'bad',
                    'good' => $flags['good'] || ($state === 'good' && ! $missing),
                    'missing' => $flags['missing'] || $missing,
                    'today' => $flags['today'] || in_array($entry->id, $newIds, true),
                ];
            }
        }

        return $teeth;
    }

    /**
     * La lista de hallazgos, una fila por hallazgo en cada pieza: las caras de una misma caries van juntas.
     *
     * @param  Collection<int, OdontogramEntry>  $entries
     * @param  list<int>  $newIds
     * @return list<array{ids: list<int>, tooth: int, where: string, surfaces: string|null, name: string, meaning: string|null, state: string, code: string, treatable: bool, new: bool}>
     */
    public static function groups(Collection $entries, array $newIds = []): array
    {
        return $entries
            ->groupBy(fn (OdontogramEntry $e) => implode('|', [$e->tooth, $e->to_tooth, $e->odontogram_condition_id, $e->state->value, $e->abbreviation]))
            ->map(function (Collection $group) use ($newIds) {
                $first = $group->first();
                $surfaces = $group->pluck('surface')->filter()->sortBy(fn (string $s) => array_search($s, ['M', 'O', 'I', 'D', 'V', 'P', 'L'], true))->map(Teeth::surfaceName(...))->values()->all();

                return [
                    'ids' => $group->modelKeys(),
                    'tooth' => $first->tooth,
                    'where' => $first->to_tooth !== null ? "{$first->tooth} a {$first->to_tooth}" : (string) $first->tooth,
                    'surfaces' => $surfaces ? self::sentence($surfaces) : null,
                    'name' => self::findingName($first),
                    'meaning' => OdontogramAbbreviations::full($first->condition->code, $first->abbreviation),
                    'state' => $first->state->value,
                    'code' => $first->condition->code,
                    'treatable' => OdontogramService::canMarkTreated($first),
                    'new' => $group->contains(fn (OdontogramEntry $e) => in_array($e->id, $newIds, true)),
                ];
            })
            ->sortBy([['tooth', 'asc'], ['where', 'asc']])
            ->values()
            ->all();
    }

    /**
     * Las piezas que cubre una marca: la suya o, en un puente, todas las de la fila entre sus dos extremos.
     *
     * @param  list<list<int>>  $rows
     * @return list<int>
     */
    public static function spanTeeth(int $tooth, ?int $toTooth, array $rows): array
    {
        if ($toTooth === null) {
            return [$tooth];
        }

        foreach ($rows as $row) {
            $a = array_search($tooth, $row, true);
            $b = array_search($toTooth, $row, true);

            if ($a !== false && $b !== false) {
                return array_slice($row, min($a, $b), abs($a - $b) + 1);
            }
        }

        return [$tooth];
    }

    /** @param  list<string>  $words */
    private static function sentence(array $words): string
    {
        $last = array_pop($words);

        return $words ? implode(', ', $words).' y '.$last : $last;
    }

    private static function signature(OdontogramEntry $entry): string
    {
        return implode('|', [$entry->odontogram_condition_id, $entry->tooth, $entry->surface, $entry->to_tooth, $entry->state->value, $entry->abbreviation]);
    }

    private function saveNotes(): void
    {
        if ($this->canEdit()) {
            $this->odontogram->update([
                'specifications' => trim($this->specifications) ?: null,
                'observations' => trim($this->observations) ?: null,
            ]);
        }
    }

    /** Hace el cambio y anota cómo estaban las marcas, para "Deshacer". Si no cambió nada, no anota nada. */
    private function tracked(string $label, callable $change): void
    {
        $before = $this->undoSnapshot();
        $dentitionBefore = $this->savedDentition();
        $change();
        $after = $this->undoSnapshot();

        $step = [
            'label' => $label,
            'added' => array_keys(array_diff_key($after, $before)),
            'removed' => array_diff_key($before, $after),
            'changed' => array_filter(array_intersect_key($before, $after), fn (array $attributes, int $id) => $attributes !== $after[$id], ARRAY_FILTER_USE_BOTH),
            'dentition' => $dentitionBefore !== $this->savedDentition() ? $dentitionBefore : null,
        ];

        if ($step['added'] !== [] || $step['removed'] !== [] || $step['changed'] !== [] || $step['dentition'] !== null) {
            $this->undo = array_slice([...$this->undo, $step], -self::UNDO_STEPS);
        }
    }

    /** La dentición tal como está guardada (para "Deshacer" un cambio de dentición). */
    private function savedDentition(): ?string
    {
        return Odontogram::query()->whereKey($this->odontogramId)->toBase()->value('dentition');
    }

    /** @return array<int, array<string, mixed>> Las marcas del odontograma tal como están guardadas, por id. */
    private function undoSnapshot(): array
    {
        return OdontogramEntry::query()
            ->where('odontogram_id', $this->odontogramId)
            ->get()
            ->mapWithKeys(fn (OdontogramEntry $entry) => [$entry->getKey() => Arr::only($entry->getAttributes(), self::UNDO_FIELDS)])
            ->all();
    }

    private function attempt(callable $callback): void
    {
        try {
            $callback();
        } catch (ClinicalException $e) {
            Notification::make()->warning()->title($e->getMessage())->send();
        }

        unset($this->odontogram, $this->newEntryIds);
    }
}
