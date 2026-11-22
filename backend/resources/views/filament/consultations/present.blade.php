@use('App\Enums\PlanItemStatus')
@use('App\Support\Money')
@use('App\Support\Teeth')
{{--
    "Mostrar al paciente": la pantalla que se gira hacia el paciente para explicarle. Su odontograma con lo que hay
    que tratar, sus radiografías y fotos y su plan con precios. Sin antecedentes, notas internas ni menús.
--}}
@php
    $info = app(\App\Services\Web\ClinicInfo::class);
    $logo = $info->logoUrl();
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $patient->short_name }} · {{ $info->name() }}</title>
    <style>
        :root { --brand: #1e4f91; --ink: #0f172a; --muted: #475569; --line: #e2e8f0; --bad: #dc2626; --good: #2563eb; }
        * { box-sizing: border-box; }
        body { margin: 0; font: 18px/1.5 system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; color: var(--ink); background: #f1f5f9; }
        .bar { position: sticky; top: 0; z-index: 2; display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 10px 16px; background: #0f172a; color: #fff; }
        .brand { display: flex; align-items: center; gap: 10px; min-width: 0; font-weight: 700; }
        .brand img { max-height: 36px; max-width: 120px; object-fit: contain; background: #fff; border-radius: 6px; padding: 2px 4px; }
        .bar-actions { display: flex; gap: 8px; flex: none; }
        .bar-actions button, .bar-actions a { font: inherit; font-size: .9rem; font-weight: 700; padding: 8px 14px; border-radius: 8px; border: 0; cursor: pointer; text-decoration: none; background: #334155; color: #fff; }
        .bar-actions button:hover, .bar-actions a:hover { background: #475569; }
        main { max-width: 1120px; margin: 0 auto; padding: 24px 16px 48px; display: grid; gap: 20px; }
        .hello { display: flex; flex-wrap: wrap; align-items: baseline; justify-content: space-between; gap: 4px 16px; }
        .hello h1 { margin: 0; font-size: 2rem; line-height: 1.2; }
        .hello p { margin: 0; color: var(--muted); }
        .card { min-width: 0; background: #fff; border-radius: 16px; padding: 20px 24px; box-shadow: 0 1px 2px rgb(15 23 42 / .06), 0 0 0 1px var(--line); }
        .card h2 { margin: 0 0 12px; font-size: 1.35rem; }
        .card h3 { margin: 20px 0 8px; font-size: 1.05rem; }
        .counts { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 12px; }
        .count { display: inline-flex; align-items: baseline; gap: 6px; padding: 6px 14px; border-radius: 999px; background: #f1f5f9; color: var(--muted); }
        .count b { font-size: 1.25rem; color: var(--ink); }
        .count.is-bad b { color: var(--bad); }
        .count.is-good b { color: var(--good); }
        .drawing { overflow-x: auto; border: 1px solid var(--line); border-radius: 12px; padding: 8px; }
        .drawing svg { display: block; width: 100%; min-width: 640px; height: auto; }
        .legend { display: flex; flex-wrap: wrap; gap: 4px 20px; margin: 10px 0 0; color: var(--muted); font-size: .9rem; }
        .legend i { display: inline-block; width: 12px; height: 12px; border-radius: 3px; margin-right: 6px; vertical-align: -1px; }
        .legend .is-bad { background: var(--bad); }
        .legend .is-good { background: var(--good); }
        .findings { margin: 0; padding: 0; list-style: none; display: grid; gap: 8px; }
        .findings li { display: flex; gap: 14px; align-items: flex-start; padding: 10px 14px; border-radius: 12px; background: #fef2f2; }
        .findings .tooth { flex: none; min-width: 3.25rem; padding: 2px 8px; border-radius: 8px; text-align: center; font-weight: 800; font-variant-numeric: tabular-nums; color: #fff; background: var(--bad); }
        .findings small { display: block; color: var(--muted); font-size: .85rem; }
        .photos { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 12px; }
        .photo { display: flex; flex-direction: column; gap: 6px; padding: 0; border: 0; background: none; font: inherit; text-align: left; cursor: zoom-in; color: inherit; }
        .photo img { width: 100%; aspect-ratio: 4 / 3; object-fit: cover; border-radius: 10px; background: #0f172a; }
        .photo span { font-size: .85rem; color: var(--muted); }
        .plan { margin: 0; padding: 0; list-style: none; }
        .plan li { display: flex; justify-content: space-between; align-items: baseline; gap: 16px; padding: 10px 0; border-bottom: 1px solid var(--line); }
        .plan small { display: block; color: var(--muted); font-size: .85rem; }
        .plan .price { flex: none; font-weight: 700; font-variant-numeric: tabular-nums; }
        .plan .is-done { color: var(--muted); }
        .plan .is-done .price { font-weight: 500; }
        .done { margin-right: 8px; padding: 2px 8px; border-radius: 999px; font-size: .8rem; font-weight: 700; color: #166534; background: #dcfce7; }
        .total { display: flex; justify-content: space-between; margin: 12px 0 0; font-size: 1.35rem; font-weight: 800; }
        .note { margin: 8px 0 0; color: var(--muted); font-size: .95rem; }
        .empty { text-align: center; color: var(--muted); }
        dialog { max-width: min(96vw, 1400px); max-height: 96vh; padding: 0; border: 0; border-radius: 12px; background: #0f172a; color: #fff; }
        dialog::backdrop { background: rgb(15 23 42 / .85); }
        dialog img { display: block; max-width: 100%; max-height: calc(96vh - 56px); margin: 0 auto; }
        dialog p { display: flex; justify-content: space-between; align-items: center; gap: 12px; margin: 0; padding: 10px 14px; font-size: .95rem; }
        dialog button { font: inherit; font-weight: 700; padding: 6px 12px; border: 0; border-radius: 8px; background: #334155; color: #fff; cursor: pointer; }
        @media (max-width: 640px) {
            body { font-size: 16px; }
            .bar-actions #full { display: none; }
            .card { padding: 16px; }
            .hello h1 { font-size: 1.6rem; }
        }
    </style>
</head>
<body>
<div class="bar">
    <div class="brand">
        @if ($logo)
            <img src="{{ $logo }}" alt="">
        @endif
        <span>{{ $info->name() }}</span>
    </div>
    <div class="bar-actions">
        <button type="button" id="full" hidden>Pantalla completa</button>
        <a href="{{ $backUrl }}" id="back">Volver a la atención</a>
    </div>
</div>

<main>
    <header class="hello">
        <h1>{{ $patient->short_name }}</h1>
        <p>{{ ucfirst(now()->translatedFormat('l j \d\e F \d\e Y')) }}</p>
    </header>

    @if ($odontogram)
        <section class="card" aria-labelledby="mouth">
            <h2 id="mouth">Su boca hoy</h2>
            <div class="counts">
                <span class="count is-bad"><b>{{ $counts['bad'] }}</b> {{ $counts['bad'] === 1 ? 'pieza por tratar' : 'piezas por tratar' }}</span>
                @if ($counts['good'] > 0)
                    <span class="count is-good"><b>{{ $counts['good'] }}</b> {{ $counts['good'] === 1 ? 'ya tratada' : 'ya tratadas' }}</span>
                @endif
                @if ($counts['missing'] > 0)
                    <span class="count"><b>{{ $counts['missing'] }}</b> {{ $counts['missing'] === 1 ? 'pieza que falta' : 'piezas que faltan' }}</span>
                @endif
            </div>
            <div class="drawing">
                <x-odontogram-drawing :odontogram="$odontogram" :rows="$rows" :teeth="$teeth" :spans="$spans" />
            </div>
            <p class="legend">
                <span><i class="is-bad"></i>Rojo: lo que hay que tratar</span>
                <span><i class="is-good"></i>Azul: lo que ya está tratado</span>
                <span>Una X azul: la pieza que falta</span>
            </p>

            @if ($toTreat->isNotEmpty())
                <h3>Lo que hay que tratar</h3>
                <ul class="findings">
                    @foreach ($toTreat as $finding)
                        <li>
                            <span class="tooth">{{ $finding['where'] }}</span>
                            <span>
                                <strong>{{ $finding['name'] }}</strong>{{ $finding['surfaces'] ? ' en '.$finding['surfaces'] : '' }}
                                <small>{{ Teeth::name($finding['tooth']) }}</small>
                            </span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    @endif

    @if ($photos->isNotEmpty())
        <section class="card" aria-labelledby="photos">
            <h2 id="photos">Radiografías y fotos</h2>
            <div class="photos">
                @foreach ($photos as $photo)
                    @php($caption = collect([$photo->title ?: $photo->type?->getLabel(), $photo->taken_at?->format('d/m/Y')])->filter()->implode(' · '))
                    <button type="button" class="photo" data-full="{{ $photo->url() }}" data-caption="{{ $caption }}">
                        <img src="{{ $photo->url(thumbnail: true) }}" alt="{{ $caption }}" loading="lazy">
                        <span>{{ $caption }}</span>
                    </button>
                @endforeach
            </div>
        </section>
    @endif

    @if ($plan && $lines->isNotEmpty())
        <section class="card" aria-labelledby="plan">
            <h2 id="plan">Su plan de tratamiento</h2>
            <ol class="plan">
                @foreach ($lines as $line)
                    @php($done = $line['items']->every(fn ($item) => $item->status === PlanItemStatus::Done))
                    <li @class(['is-done' => $done])>
                        <span>
                            {{ $line['label'] }}
                            @if ($line['contents'] !== [])
                                <small>{{ implode(' · ', $line['contents']) }}</small>
                            @endif
                        </span>
                        <span class="price">
                            @if ($done)
                                <span class="done">Hecho</span>
                            @endif
                            {{ Money::format($line['price']) }}
                        </span>
                    </li>
                @endforeach
            </ol>
            <p class="total"><span>Total</span><span>{{ Money::format($plan->total()) }}</span></p>
            @if ($until = $plan->validUntil())
                <p class="note">Precios válidos hasta el {{ $until->format('d/m/Y') }}.</p>
            @endif
            @foreach ($paymentOptions as $option)
                <p class="note">{{ $option }}</p>
            @endforeach
        </section>
    @endif

    @if (! $odontogram && $photos->isEmpty() && ! ($plan && $lines->isNotEmpty()))
        <p class="empty">Todavía no hay odontograma, radiografías ni plan para mostrar.</p>
    @endif
</main>

<dialog id="viewer" aria-label="Radiografía o foto">
    <img src="" alt="">
    <p><span></span><button type="button" autofocus>Cerrar</button></p>
</dialog>

<script>
    (() => {
        const full = document.getElementById('full');
        const viewer = document.getElementById('viewer');

        // Pantalla completa: para girar la pantalla hacia el paciente sin barras del navegador.
        if (document.documentElement.requestFullscreen) {
            full.hidden = false;
            full.addEventListener('click', () => document.fullscreenElement ? document.exitFullscreen() : document.documentElement.requestFullscreen());
            document.addEventListener('fullscreenchange', () => {
                full.textContent = document.fullscreenElement ? 'Salir de pantalla completa' : 'Pantalla completa';
            });
        }

        // Se abrió en otra pestaña: al volver, se cierra y queda la atención como estaba.
        document.getElementById('back').addEventListener('click', (event) => {
            if (history.length === 1) {
                event.preventDefault();
                const href = event.currentTarget.href;
                window.close();
                setTimeout(() => { location.href = href; }, 200);
            }
        });

        // Cada foto en grande, sobre fondo oscuro.
        document.querySelectorAll('.photo').forEach((button) => button.addEventListener('click', () => {
            viewer.querySelector('img').src = button.dataset.full;
            viewer.querySelector('img').alt = button.dataset.caption;
            viewer.querySelector('span').textContent = button.dataset.caption;
            viewer.showModal();
        }));
        viewer.addEventListener('click', () => viewer.close());
    })();
</script>
</body>
</html>
