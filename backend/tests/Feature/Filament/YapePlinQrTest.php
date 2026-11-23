<?php

namespace Tests\Feature\Filament;

use App\Enums\PaymentMethod;
use App\Filament\Pages\Settings;
use App\Filament\Resources\Payments\Pages\ManagePayments;
use App\Models\Charge;
use App\Models\Patient;
use App\Models\Setting;
use App\Models\User;
use App\Services\Web\PublicImages;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/** El QR de Yape y de Plin se sube en Ajustes y sale al cobrar con ese medio, para que el paciente lo escanee. */
class YapePlinQrTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_the_admin_uploads_the_qr_and_the_payment_window_shows_it_only_for_its_method(): void
    {
        Storage::fake(PublicImages::DISK);
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(Settings::class)
            ->fillForm(['payments' => ['yape_qr' => UploadedFile::fake()->image('yape.png', 600, 600), 'yape_holder' => 'Ana Pérez R.']])
            ->call('save')
            ->assertHasNoFormErrors();

        $qr = Setting::get('payments.yape_qr');
        $this->assertStringStartsWith('cobros/', $qr);
        $this->assertStringEndsWith('.png', $qr, 'El QR se guarda tal cual: si se achica o se pasa a JPG, puede no leerse.');
        Storage::disk(PublicImages::DISK)->assertExists($qr);
        $this->assertSame('Ana Pérez R.', Setting::get('payments.yape_holder'));

        $patient = Patient::factory()->create();
        Charge::factory()->for($patient)->create(['amount' => 120]);
        $this->actingAs(User::factory()->reception()->create());

        Livewire::test(ManagePayments::class)
            ->mountAction('registerPayment')
            ->setActionData(['patient_id' => $patient->id, 'method' => PaymentMethod::Yape->value])
            ->assertMountedActionModalSee(['Escanea con Yape', 'A nombre de Ana Pérez R.', PublicImages::url($qr)])
            // Plin no tiene QR cargado, y en efectivo no hace falta.
            ->fillForm(['method' => PaymentMethod::Plin->value])
            ->assertMountedActionModalDontSee('Escanea con')
            ->fillForm(['method' => PaymentMethod::Cash->value])
            ->assertMountedActionModalDontSee('Escanea con');

        // Si se quita en Ajustes, ya no sale al cobrar.
        $this->actingAs(User::factory()->admin()->create());
        Livewire::test(Settings::class)
            ->fillForm(['payments' => ['yape_qr' => null]])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertNull(Setting::get('payments.yape_qr'));
    }
}
