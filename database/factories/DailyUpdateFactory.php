<?php

namespace Database\Factories;

use App\Models\DailyUpdate;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DailyUpdate>
 */
class DailyUpdateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'date' => fake()->unique()->dateTimeBetween('-1 year')->format('Y-m-d'),
            'done' => $this->bullets(),
            'up_next' => $this->bullets(),
        ];
    }

    /**
     * Build a bullet list of a few sentences, as the editor would save it.
     */
    protected function bullets(): string
    {
        return '<ul>'.collect(range(1, fake()->numberBetween(2, 4)))
            ->map(fn () => '<li><p>'.fake()->sentence().'</p></li>')
            ->implode('').'</ul>';
    }
}
