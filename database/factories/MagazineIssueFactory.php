<?php

namespace Database\Factories;

use App\Enums\ContentStatus;
use App\Models\Magazine;
use App\Models\MagazineIssue;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MagazineIssue>
 */
class MagazineIssueFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'editor_id' => User::factory(),
            // Sayının dergisi, sayının editörüyle aynı editöre ait (gerçek veride de öyle —
            // bkz. Magazine). editor_id'den sonra gelmeli ki çözülmüş id'yi görsün.
            'magazine_id' => fn (array $attributes) => Magazine::factory()->create(['editor_id' => $attributes['editor_id']])->id,
            'title' => fake()->unique()->words(3, true).' Sayısı',
            'issue_number' => fake()->unique()->numberBetween(1, 999),
            'status' => ContentStatus::Taslak,
        ];
    }
}
