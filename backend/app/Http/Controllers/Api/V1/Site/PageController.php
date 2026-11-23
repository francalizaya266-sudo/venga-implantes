<?php

namespace App\Http\Controllers\Api\V1\Site;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\Web\ClinicInfo;
use App\Services\Web\PageMeta;
use App\Services\Web\StructuredData;
use App\Support\Phone;

/** Las páginas que no listan nada: Contacto (los datos del consultorio ya van en "site") y la política de privacidad. */
class PageController extends Controller
{
    public function __construct(private readonly PageMeta $meta) {}

    /**
     * @return array{data: array<string, mixed>, meta: array<string, mixed>}
     */
    public function contact(ClinicInfo $clinic, StructuredData $structuredData): array
    {
        return [
            'data' => ['heading' => 'Nuestra ubicación'],
            'meta' => $this->meta->for(
                title: 'Contacto y cómo llegar',
                description: PageMeta::describe(
                    'Cómo llegar a '.$clinic->name().($clinic->fullAddress() ? ': '.$clinic->fullAddress() : '').'.',
                    'Horario de atención, WhatsApp y mapa.',
                    'Escríbenos o reserva tu cita en línea.',
                    'Te esperamos.',
                ),
                jsonLd: [
                    $structuredData->dentist(),
                    $structuredData->breadcrumbs(['Contacto' => route('site.contact')]),
                ],
            ),
        ];
    }

    /**
     * @return array{data: array<string, mixed>, meta: array<string, mixed>}
     */
    public function privacy(ClinicInfo $clinic): array
    {
        $whatsapp = (string) Setting::get('clinic.whatsapp');

        return [
            'data' => [
                'clinic' => $clinic->name(),
                'address' => Setting::get('clinic.address') ?: null,
                'whatsapp' => Phone::isValid($whatsapp) ? Phone::display($whatsapp) : null,
            ],
            'meta' => $this->meta->for(
                title: 'Política de privacidad',
                description: 'Cómo '.$clinic->name().' cuida tus datos personales y de salud (Ley N.º 29733): qué datos usamos, para qué, quién los ve, cuánto los guardamos y tus derechos.',
            ),
        ];
    }
}
