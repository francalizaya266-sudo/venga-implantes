<?php

namespace Tests\Feature\Filament;

use App\Enums\MessageTemplateKey;
use App\Filament\Resources\MessageTemplates\Pages\ManageMessageTemplates;
use App\Models\MessageTemplate;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class NoticeTextsPanelTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_editing_a_notice_explains_only_the_data_that_notice_can_fill(): void
    {
        $this->seed(CatalogSeeder::class);
        $this->actingAs(User::factory()->admin()->create());
        $reminder = MessageTemplate::where('key', MessageTemplateKey::Reminder)->sole();
        $prescription = MessageTemplate::where('key', MessageTemplateKey::Prescription)->sole();

        Livewire::test(ManageMessageTemplates::class)
            ->mountAction(TestAction::make('edit')->table($reminder))
            ->assertMountedActionModalSee(['En este aviso puedes usar:', '{donde}', 'la dirección del consultorio', '{indicaciones}', '{enlace}'])
            ->assertMountedActionModalDontSee(['{receta}', '{cuota}', '{cobrado}']);

        Livewire::test(ManageMessageTemplates::class)
            ->mountAction(TestAction::make('edit')->table($prescription))
            ->assertMountedActionModalSee(['{receta}', 'los medicamentos con sus indicaciones'])
            ->assertMountedActionModalDontSee(['{donde}', '{cuando}', '{enlace}']);
    }
}
