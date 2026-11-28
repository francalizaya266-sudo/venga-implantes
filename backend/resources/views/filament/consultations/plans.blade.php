@use('App\Enums\PlanItemStatus')
@use('App\Enums\TreatmentPlanStatus')
@use('App\Models\TreatmentPlan')
@use('App\Support\Money')
{{-- Su plan de tratamiento, como está hoy: cada procedimiento (o cada paquete, con lo que incluye) con su precio y en qué va.
     En la página del presupuesto va sin encabezado (el estado ya está al costado) y, para quien no ve lo clínico, sin la pieza. --}}
@php
    $withTooth ??= true;
    $showHead ??= true;
    $linkLabel ??= 'Ver en su historia';
@endphp
<div class="visit-plans">
    @foreach ($plans as $plan)
        @php
            $items = $plan->items->reject(fn ($item) => $item->status === PlanItemStatus::Cancelled);
            // De lo que va en varias sesiones se dice cuántas lleva hechas: "Sesión 2 de 2" se leía como terminado.
            $state = fn ($item) => collect([
                match (true) {
                    $item->status === PlanItemStatus::Done || $item->sessions_planned <= 1 => $item->status->getLabel(),
                    $item->sessions_done > 0 => min($item->sessions_done, $item->sessions_planned).' de '.$item->sessions_planned.' sesiones hechas',
                    default => $item->status->getLabel().' · '.$item->sessions_planned.' sesiones',
                },
                $plan->accepted_at !== null && $item->accepted_at === null && $item->status !== PlanItemStatus::Done ? 'falta que lo acepte' : null,
            ])->filter()->implode(' · ');
        @endphp
        <div class="visit-plan" data-plan="{{ $plan->getKey() }}">
            @if ($showHead)
                <div class="visit-plan-head">
                    @if (filled($plan->title) && $plan->title !== 'Plan de tratamiento')
                        <strong>{{ $plan->title }}</strong>
                    @endif
                    <x-filament::badge :color="$plan->status->getColor()" size="sm">{{ $plan->status->getLabel() }}</x-filament::badge>
                </div>
            @endif
            <ul class="visit-plan-items">
                @foreach (TreatmentPlan::lines($items, $withTooth) as $line)
                    @if ($line['package'])
                        <li class="visit-plan-package">
                            <span class="visit-plan-name">{{ $line['label'] }}</span>
                            <span class="visit-plan-price">{{ Money::format($line['price']) }}</span>
                            <ul class="visit-plan-package-items">
                                @foreach ($line['items'] as $item)
                                    <li @class(['is-done' => $item->status === PlanItemStatus::Done])>
                                        <span class="visit-plan-name">{{ $item->label($withTooth) }}@if ($item->quantity > 1) × {{ $item->quantity }}@endif</span>
                                        <span class="visit-plan-state">{{ $state($item) }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </li>
                    @else
                        @php($item = $line['items']->first())
                        <li @class(['is-done' => $item->status === PlanItemStatus::Done])>
                            <span class="visit-plan-name">{{ $line['label'] }}</span>
                            <span class="visit-plan-price">{{ Money::format($line['price']) }}</span>
                            <span class="visit-plan-state">{{ $state($item) }}</span>
                        </li>
                    @endif
                @endforeach
            </ul>
            @if (filled($payment = $payments[$plan->getKey()] ?? null))
                <div class="visit-plan-options">
                    <span>Forma de pago que firmó:</span> {{ $payment }}
                </div>
            @endif
            @if (($options = $paymentOptions[$plan->getKey()] ?? []) !== [])
                <div @class(['visit-plan-options', 'is-pending' => $plan->status === TreatmentPlanStatus::InProgress])>
                    @if ($plan->status === TreatmentPlanStatus::InProgress)
                        <strong>Ya se empezó y falta que firme cómo pagará el paquete.</strong>
                    @endif
                    <span>Formas de pago, se elige al firmar:</span>
                    <ul>
                        @foreach ($options as $option)
                            <li>{{ $option }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
            <div class="visit-plan-foot">
                <span>Total <strong>{{ Money::format($plan->total()) }}</strong> · {{ $plan->progressText() }}</span>
                @if ($url)
                    <x-filament::link :href="$url" size="sm">{{ $linkLabel }}</x-filament::link>
                @endif
            </div>
        </div>
    @endforeach
</div>
