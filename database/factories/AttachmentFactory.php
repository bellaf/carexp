<?php

namespace Database\Factories;

use App\Models\Expense;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Attachment>
 */
class AttachmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $expense = Expense::factory();

        return [
            'user_id' => static function (array $attributes): int {
                /** @var Model|null $attachable */
                $attachable = ($attributes['attachable_type'])::query()->find($attributes['attachable_id']);

                return (int) $attachable?->getAttribute('user_id');
            },
            'attachable_type' => Expense::class,
            'attachable_id' => $expense,
            'disk' => 'local',
            'path' => 'attachments/expenses/'.$this->faker->uuid().'.pdf',
            'original_name' => 'receipt.pdf',
            'mime_type' => 'application/pdf',
            'size' => 1024,
        ];
    }
}
