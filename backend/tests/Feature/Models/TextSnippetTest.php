<?php

namespace Tests\Feature\Models;

use App\Enums\SnippetField;
use App\Enums\SnippetStatus;
use App\Models\TextSnippet;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class TextSnippetTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_doctor_sees_shared_and_own_options_with_the_most_used_first(): void
    {
        $doctor = User::factory()->doctor()->create();
        $otherDoctor = User::factory()->doctor()->create();
        $shared = TextSnippet::create(['field' => SnippetField::Procedures, 'label' => 'A compartida', 'body' => '...']);
        $mine = TextSnippet::create(['user_id' => $doctor->id, 'field' => SnippetField::Procedures, 'label' => 'B mía', 'body' => '...']);
        TextSnippet::create(['user_id' => $otherDoctor->id, 'field' => SnippetField::Procedures, 'label' => 'De otro doctor', 'body' => '...']);
        TextSnippet::create(['field' => SnippetField::Indications, 'label' => 'Otro campo', 'body' => '...']);
        TextSnippet::create(['field' => SnippetField::Procedures, 'label' => 'Archivada', 'body' => '...', 'status' => SnippetStatus::Archived]);

        $mine->recordUse($doctor);
        $mine->recordUse($doctor);
        $shared->recordUse($otherDoctor);

        $labels = TextSnippet::query()->availableFor($doctor, SnippetField::Procedures)->pluck('label')->all();

        $this->assertSame(['B mía', 'A compartida'], $labels);
    }

    public function test_options_not_used_yet_show_the_doctors_own_first_and_then_the_shared_ones_as_they_were_created(): void
    {
        $doctor = User::factory()->doctor()->create();
        TextSnippet::create(['field' => SnippetField::Indications, 'label' => 'Zeta, la de todos los días', 'body' => '...']);
        TextSnippet::create(['field' => SnippetField::Indications, 'label' => 'Alfa, la que llegó después', 'body' => '...']);
        TextSnippet::create(['user_id' => $doctor->id, 'field' => SnippetField::Indications, 'label' => 'La mía', 'body' => '...']);

        $labels = TextSnippet::query()->availableFor($doctor, SnippetField::Indications)->pluck('label')->all();

        $this->assertSame(['La mía', 'Zeta, la de todos los días', 'Alfa, la que llegó después'], $labels);
    }
}
