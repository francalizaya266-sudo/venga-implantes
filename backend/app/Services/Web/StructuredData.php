<?php

namespace App\Services\Web;

use App\Models\Doctor;
use App\Models\Faq;
use App\Models\Treatment;
use Illuminate\Support\Collection;

/** Datos estructurados (schema.org) para que Google entienda el consultorio: SEO local. */
final class StructuredData
{
    public function __construct(private readonly ClinicInfo $clinic) {}

    /** El consultorio completo, para el inicio y Contacto: teléfono, dirección, horario, precios desde y redes. */
    public function dentist(): array
    {
        $clinic = $this->clinic;
        $minPrice = Treatment::query()->active()->where('is_public', true)->where('show_price', true)->min('price_from');
        $photo = Doctor::query()->active()->where('is_public', true)->whereNotNull('photo_path')->orderBy('sort_order')->value('photo_path');

        return self::filled([
            ...$this->clinicReference(),
            'email' => $clinic->email(),
            'image' => $photo ? url(PublicImages::url($photo)) : null,
            'priceRange' => $minPrice ? 'Desde S/ '.number_format((float) $minPrice, 0) : null,
            'hasMap' => $clinic->directionsUrl(),
            'openingHoursSpecification' => $clinic->hours()->specification(),
            'sameAs' => array_values($clinic->socials()),
        ]);
    }

    /**
     * Lo justo para que Google sepa de quién es cada una de las demás páginas: el consultorio del inicio, con el mismo
     * identificador, su nombre, su teléfono con +51 y su dirección.
     */
    public function clinicReference(): array
    {
        $clinic = $this->clinic;

        return self::filled([
            '@context' => 'https://schema.org',
            '@type' => 'Dentist',
            '@id' => url('/').'#consultorio',
            'name' => $clinic->name(),
            'url' => url('/'),
            'telephone' => $clinic->internationalPhone(),
            'address' => $clinic->address() ? array_filter([
                '@type' => 'PostalAddress',
                'streetAddress' => $clinic->address(),
                'addressLocality' => $clinic->district(),
                'addressCountry' => 'PE',
            ]) : null,
        ]);
    }

    /** @param  Collection<int, Faq>  $faqs */
    public function faqPage(Collection $faqs): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => $faqs->map(fn (Faq $faq) => [
                '@type' => 'Question',
                'name' => $faq->question,
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => $faq->answer],
            ])->values()->all(),
        ];
    }

    /**
     * Sin lo que no se llenó en Ajustes.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function filled(array $data): array
    {
        return array_filter($data, fn ($value) => $value !== null && $value !== []);
    }

    /** @param  array<string, string>  $items  Nombre => URL, desde el inicio. */
    public function breadcrumbs(array $items): array
    {
        $steps = ['Inicio' => url('/')] + $items;

        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => collect(array_keys($steps))->map(fn (string $name, int $index) => [
                '@type' => 'ListItem',
                'position' => $index + 1,
                'name' => $name,
                'item' => $steps[$name],
            ])->all(),
        ];
    }
}
