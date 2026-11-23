<?php

namespace Tests\Feature\Http\Controllers\Agenda;

use App\Enums\AppointmentStatus;
use App\Models\Account;
use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Setting;
use App\Models\Treatment;
use App\Services\Agenda\DoctorCalendarFeed;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\CreatesAgenda;
use Tests\TestCase;

/** La agenda del doctor en el calendario de su celular: el enlace secreto de "Mi perfil" da sus citas, sin nada clínico. */
class DoctorCalendarControllerTest extends TestCase
{
    use CreatesAgenda, LazilyRefreshDatabase;

    /** Lo que lee el calendario del celular: las líneas largas, ya juntadas. */
    private function lines(TestResponse $response): array
    {
        return explode("\r\n", str_replace("\r\n ", '', (string) $response->getContent()));
    }

    private function linkPath(Doctor $doctor): string
    {
        return (string) parse_url(app(DoctorCalendarFeed::class)->renew($doctor), PHP_URL_PATH);
    }

    public function test_the_secret_link_gives_the_doctor_agenda_without_clinical_data_or_phones(): void
    {
        $this->freezeMonday();
        Setting::set('clinic.name', 'Venga Implantes');
        $doctor = Doctor::factory()->create(['name' => 'Dra. Ana Torres']);
        $rosa = Patient::factory()
            ->for(Account::factory()->state(['phone' => '+51987654321']))
            ->create(['first_name' => 'Rosa María', 'last_name' => 'Huamán Quispe', 'document_number' => '45678912']);
        $cleaning = Treatment::factory()->create(['name' => 'Limpieza dental, con flúor']);

        $pending = Appointment::factory()->for($doctor)->for($rosa)->for($cleaning)->at('2026-11-04 10:00', minutes: 45)
            ->create(['patient_note' => 'Me duele la muela del juicio', 'internal_notes' => 'Alérgica a la penicilina']);
        $confirmed = Appointment::factory()->for($doctor)->for($cleaning)->at('2026-11-05 15:30', minutes: 90)->confirmed()->create();
        $lastWeek = Appointment::factory()->for($doctor)->for($cleaning)->at('2026-10-30 09:00')->create(['status' => AppointmentStatus::Completed]);
        $tooOld = Appointment::factory()->for($doctor)->at('2026-10-20 09:00')->create(['status' => AppointmentStatus::Completed]);
        $cancelled = Appointment::factory()->for($doctor)->at('2026-11-06 09:00')->cancelled()->create();
        $missed = Appointment::factory()->for($doctor)->at('2026-10-31 09:00')->create(['status' => AppointmentStatus::NoShow]);
        $otherDoctor = Appointment::factory()->at('2026-11-04 11:00')->create();

        $response = $this->get($this->linkPath($doctor))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/calendar; charset=UTF-8')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive')
            ->assertHeaderMissing('Set-Cookie');
        $lines = $this->lines($response);

        $this->assertContains('X-WR-CALNAME:Citas · Venga Implantes', $lines);

        // 10:00 a. m. en Lima son las 15:00 en la hora universal que usan los calendarios.
        $this->assertContains("UID:agenda-{$pending->id}@localhost", $lines);
        $this->assertContains('DTSTART:20261104T150000Z', $lines);
        $this->assertContains('DTEND:20261104T154500Z', $lines);
        $this->assertContains('SUMMARY:Rosa Huamán · Limpieza dental\, con flúor', $lines);
        $this->assertContains('DESCRIPTION:Limpieza dental\, con flúor · 45 min · Por confirmar', $lines);
        $this->assertContains('STATUS:TENTATIVE', $lines);
        $this->assertContains('DESCRIPTION:Limpieza dental\, con flúor · 1 h 30 min · Confirmada', $lines);

        // La semana pasada sí; lo de antes, lo cancelado, las faltas y las citas de otros doctores, no.
        foreach ([$pending, $confirmed, $lastWeek] as $appointment) {
            $this->assertContains("UID:agenda-{$appointment->id}@localhost", $lines);
        }

        foreach ([$tooOld, $cancelled, $missed, $otherDoctor] as $appointment) {
            $this->assertNotContains("UID:agenda-{$appointment->id}@localhost", $lines);
        }

        // Nada clínico, ni el celular, ni el documento, ni el nombre completo.
        $this->assertStringNotContainsString('muela', $response->getContent());
        $this->assertStringNotContainsString('penicilina', $response->getContent());
        $this->assertStringNotContainsString('987654321', $response->getContent());
        $this->assertStringNotContainsString('45678912', $response->getContent());
        $this->assertStringNotContainsString('Quispe', $response->getContent());

        foreach (explode("\r\n", $response->getContent()) as $line) {
            $this->assertLessThanOrEqual(75, strlen($line));
        }
    }

    public function test_a_wrong_changed_or_unusable_link_answers_the_same_as_one_that_never_existed(): void
    {
        $this->freezeMonday();
        $doctor = Doctor::factory()->create();
        $withoutLink = Doctor::factory()->create();
        $old = $this->linkPath($doctor);
        $current = $this->linkPath($doctor);
        $nowhere = '/agenda/999999/'.str_repeat('a', DoctorCalendarFeed::TOKEN_LENGTH).'.ics';
        $notFound = $this->get($nowhere)->assertNotFound();

        $this->get($current)->assertOk();

        $refused = [
            'el enlace de antes de cambiarlo' => $old,
            'una clave inventada' => "/agenda/{$doctor->id}/".str_repeat('a', DoctorCalendarFeed::TOKEN_LENGTH).'.ics',
            'la clave de otro doctor' => "/agenda/{$withoutLink->id}/".basename($current, '.ics').'.ics',
            'una clave corta' => "/agenda/{$doctor->id}/abc.ics",
        ];

        foreach ($refused as $case => $path) {
            $response = $this->get($path)->assertNotFound();
            // La página de "no encontrado" repite la dirección pedida; lo demás es idéntico.
            $this->assertSame(str_replace($nowhere, '', (string) $notFound->getContent()), str_replace($path, '', (string) $response->getContent()), $case);
            $this->assertStringNotContainsString('BEGIN:VCALENDAR', (string) $response->getContent(), $case);
        }

        // Quien ya no entra al panel, o un doctor que se dio de baja, deja de ver la agenda en el celular.
        $doctor->user->update(['is_active' => false]);
        $this->get($current)->assertNotFound();

        $doctor->user->update(['is_active' => true]);
        $doctor->update(['is_active' => false]);
        $this->get($current)->assertNotFound();
    }

    public function test_the_link_has_a_limit_of_tries(): void
    {
        $doctor = Doctor::factory()->create();
        $path = $this->linkPath($doctor);

        for ($try = 1; $try <= 30; $try++) {
            $this->get("/agenda/{$doctor->id}/".str_repeat('b', DoctorCalendarFeed::TOKEN_LENGTH).'.ics')->assertNotFound();
        }

        $this->get($path)->assertTooManyRequests();
    }

    public function test_the_secret_is_kept_encrypted_and_never_shown_in_the_doctor_data(): void
    {
        $doctor = Doctor::factory()->create();
        $url = app(DoctorCalendarFeed::class)->renew($doctor);
        $token = basename($url, '.ics');

        $this->assertSame(DoctorCalendarFeed::TOKEN_LENGTH, strlen($token));
        $this->assertNotSame($token, $doctor->getRawOriginal('calendar_token'));
        $this->assertArrayNotHasKey('calendar_token', $doctor->toArray());
    }
}
