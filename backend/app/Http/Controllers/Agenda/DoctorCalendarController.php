<?php

namespace App\Http\Controllers\Agenda;

use App\Http\Controllers\Controller;
use App\Services\Agenda\DoctorCalendarFeed;
use App\Services\Web\ClinicInfo;
use App\Support\CalendarEvent;
use Illuminate\Http\Response;

/**
 * La agenda del doctor para el calendario de su celular (.ics), con el enlace secreto de "Mi perfil". El celular la
 * pide sin sesión y cada cierto tiempo. Un enlace que no vale da la misma página de "no encontrado" que una dirección
 * que no existe: no se sabe si hubo un enlace ahí. Los buscadores no la guardan.
 */
class DoctorCalendarController extends Controller
{
    public function __invoke(string $doctor, string $token, DoctorCalendarFeed $feed, ClinicInfo $clinic): Response
    {
        $owner = $feed->find((int) $doctor, $token);

        abort_if($owner === null, 404);

        return response(CalendarEvent::forDoctorAgenda($feed->appointments($owner), $clinic), 200, [
            'Content-Type' => 'text/calendar; charset=UTF-8',
            'Content-Disposition' => 'inline; filename="agenda.ics"',
            'Cache-Control' => 'private, no-store',
            'X-Robots-Tag' => 'noindex, nofollow, noarchive',
        ]);
    }
}
