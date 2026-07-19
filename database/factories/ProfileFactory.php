<?php

namespace Database\Factories;

use App\Models\{Profile, User, Project, Skill};
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Profile>
 */
class ProfileFactory extends Factory
{
    protected $model = Profile::class;
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'headline' => $this->faker->jobTitle(),
            'bio' => $this->faker->paragraph(),
            'avatar_url' => $this->faker->imageUrl(200, 200, 'people'),
            'location' => $this->faker->city(),
            'status' => $this->faker->randomElement(['feelance', 'part-time', 'full-time']),
            'account_status' => $this->faker->randomElement([
                \App\Enums\ProfileAccountStatus::PENDING_VALIDATION,
                \App\Enums\ProfileAccountStatus::VALIDATED,
                \App\Enums\ProfileAccountStatus::REJECTED,
            ]),
        ];
    }

    /**
     * Indicate that the profile is pending validation.
     */
    public function pendingValidation(): static
    {
        return $this->state(fn (array $attributes) => [
            'account_status' => \App\Enums\ProfileAccountStatus::PENDING_VALIDATION,
        ]);
    }

    /**
     * Indicate that the profile is validated.
     */
    public function validated(): static
    {
        return $this->state(fn (array $attributes) => [
            'account_status' => \App\Enums\ProfileAccountStatus::VALIDATED,
        ]);
    }

    /**
     * Indicate that the profile is rejected.
     */
    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'account_status' => \App\Enums\ProfileAccountStatus::REJECTED,
        ]);
    }

    /**
     * Configure the model factory.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (Profile $profile) {
            Project::factory()
                ->count($this->faker->numberBetween(1, 5))
                ->for($profile)
                ->create();

            $skills = Skill::query()
                ->inRandomOrder()
                ->take($this->faker->numberBetween(1, 5))
                ->get();

            $profile->skills()->attach(
                $skills->mapWithKeys(fn (Skill $skill) => [
                    $skill->id => [
                        'proficiency' => $this->faker->numberBetween(1, 10),
                        'years_experience' => $this->faker->numberBetween(1, 10),
                    ],
                ])
            );
        });
    }
}
