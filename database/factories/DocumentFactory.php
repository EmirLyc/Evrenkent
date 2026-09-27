<?php

namespace Database\Factories;

use App\Models\Book;
use App\Models\Document;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Document>
 */
class DocumentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'documentable_type' => Book::class,
            'documentable_id' => Book::factory(),
            'title' => fake()->sentence(4),
            'date_label' => fake()->year(),
            'page_count' => fake()->numberBetween(1, 12),
            'file_path' => 'documents/'.fake()->uuid().'.pdf',
            'mime_type' => 'application/pdf',
            'size' => 1024,
            'original_name' => 'belge.pdf',
        ];
    }
}
