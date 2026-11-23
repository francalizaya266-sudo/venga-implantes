<?php

namespace App\Services\Web;

/**
 * Lo que cada página de la web dice de sí misma al navegador, a Google y al compartir el enlace: título,
 * descripción, imagen y datos estructurados. La API lo entrega junto con los datos de la página, y Laravel lo
 * escribe en el documento que recibe el navegador antes de que cargue el frontend.
 */
final class PageMeta
{
    /** Hasta dónde Google muestra la descripción: más larga, la corta. */
    private const DESCRIPTION_MAX = 160;

    public function __construct(
        private readonly ClinicInfo $clinic,
        private readonly PageTexts $texts,
    ) {}

    /**
     * @param  string|null  $title  El título de la página, sin el nombre del consultorio. Null: el del inicio.
     * @param  string|null  $page  La página con encabezado editable (PageTexts): pone su título si no se dio otro.
     * @param  string|null  $image  La imagen al compartir. Null: la foto del consultorio o la de la marca.
     * @param  list<array<string, mixed>>  $jsonLd  Datos estructurados de schema.org.
     * @return array{title: string, og_title: string, description: string|null, image: string, noindex: bool, json_ld: list<array<string, mixed>>}
     */
    public function for(?string $title = null, ?string $page = null, ?string $description = null, ?string $image = null, bool $noindex = false, array $jsonLd = []): array
    {
        $name = $this->clinic->name();
        $title ??= $page !== null ? $this->texts->plainTitle($page) : null;
        $district = $this->clinic->district();

        return [
            'title' => $title !== null ? $title.' · '.$name : $name.($district ? ' · Dentistas en '.$district : ''),
            'og_title' => $title ?? $name,
            'description' => $description ?? ($page !== null ? $this->pageDescription($page) : null) ?? $this->clinic->intro(),
            'image' => url($image ?? $this->clinic->heroImageUrl() ?? asset('imagen-al-compartir.jpg')),
            'noindex' => $noindex,
            'json_ld' => array_values($jsonLd),
        ];
    }

    /**
     * Una descripción para Google de 150 a 160 letras si se puede: la primera frase va siempre y, de las demás, las
     * que todavía entran sin pasar de 160. Así queda completa con un nombre de consultorio corto o largo.
     */
    public static function describe(string $first, ?string ...$more): string
    {
        $description = $first;

        foreach (array_filter($more) as $sentence) {
            if (mb_strlen($description.' '.$sentence) <= self::DESCRIPTION_MAX) {
                $description .= ' '.$sentence;
            }
        }

        return $description;
    }

    /** Qué hay en cada página y de quién es, con la ciudad: lo que Google muestra bajo el título. */
    private function pageDescription(string $page): ?string
    {
        $name = $this->clinic->name();
        $district = $this->clinic->district();
        $in = $district ? ' en '.$district : '';

        return match ($page) {
            'servicios' => self::describe("Los servicios dentales de {$name}{$in}: para qué sirve cada uno, cuánto dura y desde cuánto cuesta.", 'Reserva tu evaluación en línea.', 'Te confirmamos por WhatsApp.'),
            'equipo' => self::describe("Conoce a los dentistas de {$name}{$in}: su especialidad, sus estudios y su número de colegiatura (COP).", 'Reserva tu cita en línea.', 'Te confirmamos por WhatsApp.'),
            'preguntas' => "Preguntas frecuentes de los pacientes de {$name}{$in}: cómo reservar o cambiar tu cita, formas de pago, tratamientos, precios y cómo llegar.",
            'casos' => self::describe("Antes y después de pacientes de {$name}{$in}: fotos reales que ellos autorizaron mostrar.", 'En tu evaluación te decimos qué puedes esperar.', 'Reserva en línea.'),
            'opiniones' => self::describe("Opiniones de pacientes de {$name}".($district ? ", dentistas en {$district}" : '').', copiadas tal como las escribieron.', 'Lee cómo los atendimos y reserva tu cita en línea.', 'Te confirmamos por WhatsApp.'),
            'promociones' => self::describe("Promociones vigentes de {$name}{$in}: descuentos en tratamientos que se aplican al pagar en el consultorio.", 'Reserva en línea con tu código.', 'Te confirmamos por WhatsApp.'),
            'reservar' => self::describe("Reserva tu cita en {$name}".($district ? ", {$district}" : '').': elige el servicio, el día y la hora que te quedan mejor, sin llamar.', 'Te confirmamos por WhatsApp.', 'Pagas en el consultorio.'),
            default => null,
        };
    }
}
