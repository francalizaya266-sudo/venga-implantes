<?php

namespace App\Services\Web;

/**
 * Lo que cada página de la web dice de sí misma al navegador, a Google y al compartir el enlace: título,
 * descripción, imagen y datos estructurados. La API lo entrega junto con los datos de la página, y Laravel lo
 * escribe en el documento que recibe el navegador antes de que cargue el frontend.
 */
final class PageMeta
{
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
            'description' => $description ?? $this->clinic->intro(),
            'image' => url($image ?? $this->clinic->heroImageUrl() ?? asset('imagen-al-compartir.jpg')),
            'noindex' => $noindex,
            'json_ld' => array_values($jsonLd),
        ];
    }
}
