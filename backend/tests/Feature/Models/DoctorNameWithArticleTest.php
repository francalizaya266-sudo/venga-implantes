<?php

namespace Tests\Feature\Models;

use App\Models\Doctor;
use Tests\TestCase;

class DoctorNameWithArticleTest extends TestCase
{
    public function test_inside_a_sentence_the_name_goes_with_its_article_and_without_a_title_as_it_is(): void
    {
        $this->assertSame('la Dra. Ana Torres', Doctor::make(['name' => 'Dra. Ana Torres'])->name_with_article);
        $this->assertSame('la Doctora Ana Torres', Doctor::make(['name' => 'Doctora Ana Torres'])->name_with_article);
        $this->assertSame('el Dr. Luis Ramos', Doctor::make(['name' => 'Dr. Luis Ramos'])->name_with_article);
        $this->assertSame('el DR. Luis Ramos', Doctor::make(['name' => 'DR. Luis Ramos'])->name_with_article);
        $this->assertSame('Luis Ramos', Doctor::make(['name' => 'Luis Ramos'])->name_with_article);
        $this->assertSame('Drago Pérez', Doctor::make(['name' => 'Drago Pérez'])->name_with_article);
    }
}
