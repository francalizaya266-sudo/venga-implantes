<?php

namespace App\Services\Web;

use App\Models\Doctor;
use App\Models\Faq;
use App\Models\Treatment;
use App\Support\Phone;
use Illuminate\Support\Collection;

/** Datos estructurados (schema.org) para que Google entienda el consultorio: SEO local. */
final class StructuredData
{
    public function __construct(private readonly ClinicInfo $clinic) {}

    public function dentist(): array
    {
        $clinic = $this->clinic;
        // Con el +51 delante, como lo pide Google: el fijo o, si no hay (o no se entiende), el WhatsApp.
        $phone = ($clinic->phone() ? Phone::international($clinic->phone()) : null)
            ?? ($clinic->whatsapp() ? Phone::international($clinic->whatsapp()) : null);
        $minPrice = Treatment::query()->active()->where('is_public', true)->where('show_price', true)->min('price_from');
        $photo = Doctor::query()->active()->where('is_public', true)->whereNotNull('photo_path')->orderBy('sort_order')->value('photo_path');

        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Dentist',
            '@id' => url('/').'#consultorio',
            'name' => $clinic->name(),
            'url' => url('/'),
            'telephone' => $phone,
            'email' => $clinic->email(),
            'image' => $photo ? url(PublicImages::url($photo)) : null,
            'priceRange' => $minPrice ? 'Desde S/ '.number_format((float) $minPrice, 0) : null,
            'address' => $clinic->address() ? array_filter([
                '@type' => 'PostalAddress',
                'streetAddress' => $clinic->address(),
                'addressLocality' => $clinic->district(),
                'addressCountry' => 'PE',
            ]) : null,
            'hasMap' => $clinic->directionsUrl(),
            'openingHoursSpecification' => $clinic->hours()->specification(),
            'sameAs' => array_values($clinic->socials()),
        ], fn ($value) => $value !== null && $value !== []);
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
