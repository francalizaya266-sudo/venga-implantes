<?php

namespace App\Services\Clinical;

use App\Enums\Dentition;
use App\Enums\OdontogramType;
use App\Enums\ToothState;
use App\Exceptions\ClinicalException;
use App\Models\Consultation;
use App\Models\Odontogram;
use App\Models\OdontogramCondition;
use App\Models\OdontogramEntry;
use App\Support\Teeth;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;

/** Odontograma por consulta: se parte del último y cada toque marca o desmarca un hallazgo. */
final class OdontogramService
{
    /** Lo que queda al tratar un hallazgo que cambia: la caries pasa a restauración; la extracción, a pieza ausente. */
    public const TREATED_AS = ['caries' => 'restauracion', 'extraccion_indicada' => 'ausente', 'remanente_radicular' => 'ausente'];

    /** Odontograma de la consulta: copia del último del paciente o, si es el primero, uno inicial según su edad. */
    public function forConsultation(Consultation $consultation): Odontogram
    {
        if ($consultation->odontogram) {
            return $consultation->odontogram;
        }

        $latest = $consultation->patient->odontograms()->with('entries')->latest('id')->first();

        $odontogram = $latest
            ? tap($latest->copyForConsultation($consultation), fn (Odontogram $copy) => $this->growWithAge($copy))
            : Odontogram::create([
                'patient_id' => $consultation->patient_id,
                'consultation_id' => $consultation->getKey(),
                'doctor_id' => $consultation->doctor_id,
                'type' => OdontogramType::Initial,
                'dentition' => $consultation->patient->suggestedDentition(),
            ]);

        $consultation->setRelation('odontogram', $odontogram);

        return $odontogram;
    }

    /**
     * Marca un hallazgo. Si la misma marca ya estaba, la quita: tocar dos veces deshace.
     *
     * @return OdontogramEntry|null La marca creada o cambiada; null si se quitó.
     */
    public function toggle(
        Odontogram $odontogram,
        OdontogramCondition $condition,
        ToothState $state,
        int $tooth,
        ?string $surface = null,
        ?int $toTooth = null,
        ?string $abbreviation = null,
    ): ?OdontogramEntry {
        [$attributes, $existing] = $this->prepare($odontogram, $condition, $state, $tooth, $surface, $toTooth, $abbreviation);

        if ($existing && $existing->state === $attributes['state'] && $existing->abbreviation === $attributes['abbreviation']) {
            $existing->delete();

            return null;
        }

        return $this->save($odontogram, $attributes, $existing);
    }

    /** Marca un hallazgo sin quitar nada (al dictar): si ya estaba igual, lo deja como está. */
    public function place(
        Odontogram $odontogram,
        OdontogramCondition $condition,
        ToothState $state,
        int $tooth,
        ?string $surface = null,
        ?int $toTooth = null,
        ?string $abbreviation = null,
    ): OdontogramEntry {
        [$attributes, $existing] = $this->prepare($odontogram, $condition, $state, $tooth, $surface, $toTooth, $abbreviation);

        return $this->save($odontogram, $attributes, $existing);
    }

    /**
     * Marca con el pincel (o al dictar). En azul, lo que deja un tratamiento responde a lo rojo que lo pedía, igual
     * que "Tratado": la restauración sobre la caries de esa cara, la pieza ausente sobre la extracción indicada.
     * Así no quedan las dos marcas, una encima de la otra, y se sabe qué quedó tratado.
     *
     * @param  bool  $toggle  Con el pincel, tocar la misma marca la quita; al dictar se deja como está.
     * @return array{entry: OdontogramEntry|null, treated: list<array{code: string, abbreviation: string|null}>, fresh: bool} La marca que quedó (null si se quitó), lo rojo que con eso quedó tratado y si la marca azul es de ahora.
     */
    public function paint(
        Odontogram $odontogram,
        OdontogramCondition $condition,
        ToothState $state,
        int $tooth,
        ?string $surface = null,
        ?int $toTooth = null,
        ?string $abbreviation = null,
        bool $toggle = true,
    ): array {
        [$attributes, $existing] = $this->prepare($odontogram, $condition, $state, $tooth, $surface, $toTooth, $abbreviation);
        $blue = $attributes['state'] === ToothState::Good;
        $answered = $blue ? $this->answeredBy($odontogram, $condition, $tooth, $attributes['surface']) : new EloquentCollection;

        if ($answered->isNotEmpty()) {
            return DB::transaction(function () use ($answered, $condition, $abbreviation): array {
                $entry = null;

                // Si la marca nueva es de toda la pieza (ausente), tratar la primera ya se lleva las demás.
                foreach ($condition->applies_to === 'tooth' ? $answered->take(1) : $answered as $red) {
                    $entry = $this->markTreated($red, $abbreviation);
                }

                return [
                    'entry' => $entry,
                    'treated' => $answered->map(fn (OdontogramEntry $red) => ['code' => self::conditionOf($red)->code, 'abbreviation' => $red->abbreviation])->values()->all(),
                    'fresh' => true,
                ];
            });
        }

        if ($toggle && $existing && $existing->state === $attributes['state'] && $existing->abbreviation === $attributes['abbreviation']) {
            $existing->delete();

            return ['entry' => null, 'treated' => [], 'fresh' => false];
        }

        // La misma marca que estaba en rojo y pasa a azul también quedó tratada.
        $wasRed = $existing !== null && self::stateOf($existing) === ToothState::Bad;
        $treated = $blue && $wasRed ? [['code' => $condition->code, 'abbreviation' => $existing->abbreviation]] : [];

        return ['entry' => $this->save($odontogram, $attributes, $existing), 'treated' => $treated, 'fresh' => $blue && ($existing === null || $wasRed)];
    }

    /**
     * Lo rojo de la pieza que queda tratado al marcar ese hallazgo en azul: la caries de esa cara bajo una
     * restauración; la extracción indicada o el remanente de una pieza que se marca ausente.
     *
     * @return EloquentCollection<int, OdontogramEntry>
     */
    private function answeredBy(Odontogram $odontogram, OdontogramCondition $condition, int $tooth, ?string $surface): EloquentCollection
    {
        $codes = array_keys(self::TREATED_AS, $condition->code, true);

        if ($codes === []) {
            return new EloquentCollection;
        }

        return OdontogramEntry::query()
            ->with('condition')
            ->where('odontogram_id', $odontogram->getKey())
            ->where('tooth', $tooth)
            ->whereNull('to_tooth')
            ->where('state', ToothState::Bad)
            ->whereHas('condition', fn ($query) => $query->whereIn('code', $codes))
            ->when($condition->applies_to === 'surface', fn ($query) => $query->where('surface', $surface))
            ->get();
    }

    /** El hallazgo de la marca, con su tipo (el análisis estático no lo ve). */
    private static function conditionOf(OdontogramEntry $entry): OdontogramCondition
    {
        /** @var OdontogramCondition $condition */
        $condition = $entry->condition;

        return $condition;
    }

    /** El estado de la marca, con su tipo (el análisis estático no lo ve). */
    private static function stateOf(OdontogramEntry $entry): ToothState
    {
        /** @var ToothState $state */
        $state = $entry->state;

        return $state;
    }

    /** Si una marca roja se puede pasar a tratada de un toque. */
    public static function canMarkTreated(OdontogramEntry $entry): bool
    {
        return $entry->state === ToothState::Bad
            && (array_key_exists($entry->condition->code, self::TREATED_AS) || $entry->condition->allows_good_state);
    }

    /**
     * Pasa una marca roja a tratada: la misma en azul o, si el tratamiento la cambia, la nueva.
     * La caries queda como restauración en esa superficie; la pieza extraída queda ausente y sin sus otras marcas.
     */
    public function markTreated(OdontogramEntry $entry, ?string $abbreviation = null): OdontogramEntry
    {
        $entry->loadMissing(['condition', 'odontogram']);

        if (! self::canMarkTreated($entry)) {
            throw ClinicalException::cannotMarkTreated();
        }

        $code = self::TREATED_AS[$entry->condition->code] ?? null;

        if ($code === null) {
            $entry->update(['state' => ToothState::Good]);

            return $entry;
        }

        $condition = OdontogramCondition::query()->where('code', $code)->firstOrFail();
        $odontogram = $entry->odontogram;

        return DB::transaction(function () use ($entry, $condition, $odontogram, $abbreviation) {
            $replaced = $condition->applies_to === 'tooth'
                ? $odontogram->entries()->where('tooth', $entry->tooth)->whereNull('to_tooth')->get()
                : collect([$entry]);
            $replaced->each->delete();

            $treated = $odontogram->entries()->firstOrNew([
                'odontogram_condition_id' => $condition->getKey(),
                'tooth' => $entry->tooth,
                'surface' => $condition->applies_to === 'surface' ? $entry->surface : null,
                'to_tooth' => null,
            ]);
            $treated->state = ToothState::Good;

            if (in_array($abbreviation, $condition->abbreviations ?? [], true)) {
                $treated->abbreviation = $abbreviation;
            }

            $treated->save();

            return $treated;
        });
    }

    /**
     * Cambia la dentición. Si hay marcas en piezas que la nueva no tiene (la 63 de leche al pasar a adulto), no
     * cambia nada, salvo que se pida qué hacer con ellas: pasarlas a la pieza de adulto del mismo lugar (solo al
     * pasar a adulto) o quitarlas. Todo junto: o se hace entero o no se hace.
     */
    public function changeDentition(Odontogram $odontogram, Dentition $dentition, bool $removeOutside = false, bool $moveToAdult = false): void
    {
        DB::transaction(function () use ($odontogram, $dentition, $removeOutside, $moveToAdult): void {
            if ($moveToAdult && $dentition === Dentition::Permanent) {
                $this->entriesOutside($odontogram, $dentition)->each(fn (OdontogramEntry $entry) => $this->moveToAdultTooth($entry));
            }

            $outside = $this->entriesOutside($odontogram, $dentition);

            if ($outside->isNotEmpty() && ! $removeOutside) {
                throw ClinicalException::teethOutsideDentition();
            }

            $outside->each->delete();
            $odontogram->update(['dentition' => $dentition]);
        });
    }

    /**
     * Las marcas en piezas que esa dentición no tiene (o un tramo que termina en una).
     *
     * @return EloquentCollection<int, OdontogramEntry>
     */
    public function entriesOutside(Odontogram $odontogram, Dentition $dentition): EloquentCollection
    {
        $teeth = Teeth::all($dentition);

        return OdontogramEntry::query()
            ->where('odontogram_id', $odontogram->getKey())
            ->where(fn ($query) => $query->whereNotIn('tooth', $teeth)->orWhere(fn ($span) => $span->whereNotNull('to_tooth')->whereNotIn('to_tooth', $teeth)))
            ->get();
    }

    /**
     * Al empezar una atención: si el paciente creció (cumplió 6 o 13), su odontograma pasa a la dentición que le
     * toca, siempre que no se pierda ninguna marca. De niño a mixta siempre se puede (mixta tiene todas las piezas);
     * a adulto, solo si no hay marcas en piezas de leche. Sin fecha de nacimiento no se toca.
     */
    public function growWithAge(Odontogram $odontogram): void
    {
        $patient = $odontogram->patient;

        if ($patient?->age === null) {
            return;
        }

        $order = [Dentition::Primary->value => 0, Dentition::Mixed->value => 1, Dentition::Permanent->value => 2];
        $current = $order[$odontogram->dentition->value];

        foreach ([$patient->suggestedDentition(), Dentition::Mixed] as $target) {
            if ($order[$target->value] > $current && $this->entriesOutside($odontogram, $target)->isEmpty()) {
                $odontogram->update(['dentition' => $target]);

                return;
            }
        }
    }

    /** La marca de una pieza de leche pasa a la de adulto del mismo lugar; si ahí ya estaba igual, se queda esa. */
    private function moveToAdultTooth(OdontogramEntry $entry): void
    {
        $tooth = Teeth::adultOf($entry->tooth);

        if ($tooth === null) {
            return;
        }

        $alreadyThere = OdontogramEntry::query()
            ->where('odontogram_id', $entry->odontogram_id)
            ->where('tooth', $tooth)
            ->where('surface', $entry->surface)
            ->where('odontogram_condition_id', $entry->odontogram_condition_id)
            ->exists();

        $alreadyThere
            ? $entry->delete()
            : $entry->update(['tooth' => $tooth, 'to_tooth' => $entry->to_tooth ? (Teeth::adultOf($entry->to_tooth) ?? $entry->to_tooth) : null]);
    }

    /**
     * Valida la marca y busca si ya estaba (mismo hallazgo en la misma pieza, superficie o tramo).
     *
     * @return array{0: array<string, mixed>, 1: OdontogramEntry|null}
     */
    private function prepare(Odontogram $odontogram, OdontogramCondition $condition, ToothState $state, int $tooth, ?string $surface, ?int $toTooth, ?string $abbreviation): array
    {
        $this->assertTooth($odontogram, $tooth);

        [$surface, $toTooth] = match ($condition->applies_to) {
            'surface' => [$this->surfaceFor($tooth, $surface), null],
            'span' => [null, $this->spanEnd($odontogram, $tooth, $toTooth)],
            default => [null, null],
        };

        $attributes = [
            'odontogram_condition_id' => $condition->getKey(),
            'tooth' => $tooth,
            'surface' => $surface,
            'to_tooth' => $toTooth,
            'state' => $this->allowedState($condition, $state),
            'abbreviation' => in_array($abbreviation, $condition->abbreviations ?? [], true) ? $abbreviation : null,
        ];

        $existing = $odontogram->entries()
            ->where('odontogram_condition_id', $condition->getKey())
            ->where('tooth', $tooth)
            ->where('surface', $surface)
            ->where('to_tooth', $toTooth)
            ->first();

        return [$attributes, $existing];
    }

    /** @param  array<string, mixed>  $attributes */
    private function save(Odontogram $odontogram, array $attributes, ?OdontogramEntry $existing): OdontogramEntry
    {
        if ($existing) {
            $existing->update(['state' => $attributes['state'], 'abbreviation' => $attributes['abbreviation']]);

            return $existing;
        }

        return $odontogram->entries()->create($attributes);
    }

    /** Si el hallazgo solo existe en un color (ej. ausente = azul), se usa ese. */
    private function allowedState(OdontogramCondition $condition, ToothState $state): ToothState
    {
        return match (true) {
            $state === ToothState::Bad && ! $condition->allows_bad_state => ToothState::Good,
            $state === ToothState::Good && ! $condition->allows_good_state => ToothState::Bad,
            default => $state,
        };
    }

    private function assertTooth(Odontogram $odontogram, int $tooth): void
    {
        if (! Teeth::exists($tooth, $odontogram->dentition)) {
            throw ClinicalException::unknownTooth($tooth);
        }
    }

    private function surfaceFor(int $tooth, ?string $surface): string
    {
        if (! in_array($surface, Teeth::surfaces($tooth), true)) {
            throw ClinicalException::needsSurface();
        }

        return $surface;
    }

    private function spanEnd(Odontogram $odontogram, int $tooth, ?int $toTooth): int
    {
        if ($toTooth === null) {
            throw ClinicalException::needsSpan();
        }

        $this->assertTooth($odontogram, $toTooth);

        $sameRow = collect(Teeth::rows($odontogram->dentition))
            ->contains(fn (array $row) => in_array($tooth, $row, true) && in_array($toTooth, $row, true));

        if (! $sameRow) {
            throw ClinicalException::spanAcrossArches();
        }

        return $toTooth;
    }
}
