<?php

namespace Tests\Feature\Api\V1;

use App\Models\Category;
use App\Models\Profile;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProfileStatsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function test_stats_require_authentication(): void
    {
        $this->getJson('/api/v1/profiles/stats')->assertUnauthorized();
    }

    #[Test]
    public function test_stats_group_profiles(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $backend = Category::factory()->create(['name' => 'Backend']);
        $frontend = Category::factory()->create(['name' => 'Frontend']);
        $php = Skill::factory()->create(['name' => 'PHP']);
        $vue = Skill::factory()->create(['name' => 'Vue']);

        $a = Profile::factory()->validated()->create(['category_id' => $backend->id, 'location' => 'Kinshasa', 'status' => 'full-time']);
        $b = Profile::factory()->validated()->create(['category_id' => $backend->id, 'location' => 'Kinshasa', 'status' => 'part-time']);
        $c = Profile::factory()->pendingValidation()->create(['category_id' => $frontend->id, 'location' => 'Goma', 'status' => 'full-time']);

        $a->skills()->sync([$php->id => ['proficiency' => 4, 'years_experience' => 2]]);
        $b->skills()->sync([$php->id => ['proficiency' => 2, 'years_experience' => 4]]);
        $c->skills()->sync([$vue->id => ['proficiency' => null, 'years_experience' => null]]);

        $response = $this->getJson('/api/v1/profiles/stats')->assertOk();

        $response->assertJsonPath('data.total', 3)
            ->assertJsonPath('data.by_account_status.0', ['label' => 'VALIDATED', 'total' => 2])
            ->assertJsonPath('data.by_stack.0.label', 'Backend')
            ->assertJsonPath('data.by_stack.0.total', 2)
            ->assertJsonPath('data.by_location.0', ['label' => 'Kinshasa', 'total' => 2])
            ->assertJsonPath('data.by_status.0', ['label' => 'full-time', 'total' => 2])
            ->assertJsonPath('data.by_skill.0.label', 'PHP')
            ->assertJsonPath('data.by_skill.0.total', 2)
            ->assertJsonPath('data.by_skill.0.avg_proficiency', 3)
            ->assertJsonPath('data.by_skill.1.avg_proficiency', null)
            ->assertJsonPath('data.by_signup_month.0', ['label' => now()->format('Y-m'), 'total' => 3])
            ->assertJsonPath('data.by_experience_range', [
                ['label' => '2-3', 'total' => 1],
                ['label' => '4-5', 'total' => 1],
                ['label' => 'unknown', 'total' => 1],
            ]);
    }

    #[Test]
    public function test_stats_can_be_filtered_and_validated(): void
    {
        Sanctum::actingAs(User::factory()->create());

        Profile::factory()->validated()->create(['location' => 'Kinshasa']);
        Profile::factory()->pendingValidation()->create(['location' => 'Goma']);

        $this->getJson('/api/v1/profiles/stats?account_status=VALIDATED')
            ->assertOk()
            ->assertJsonPath('data.total', 1);

        $this->getJson('/api/v1/profiles/stats?account_status=nope')->assertUnprocessable();
    }
}



