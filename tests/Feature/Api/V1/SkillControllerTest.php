<?php

namespace Tests\Feature\Api\V1;

use App\Models\Skill;
use PHPUnit\Framework\Attributes\Test;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SkillControllerTest extends TestCase
{
    use RefreshDatabase;
    
    #[Test]
    public function test_can_list_skills(): void
    {
        Skill::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/skills');

        $response->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'slug'],
                ],
            ]);
    }

    #[Test]
    public function test_can_create_skill(): void
    {
        $payload = ['name' => 'Laravel'];

        $response = $this->postJson('/api/v1/skills', $payload);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Laravel')
            ->assertJsonPath('data.slug', 'laravel');

        $this->assertDatabaseHas('skills', [
            'name' => 'Laravel',
            'slug' => 'laravel',
        ]);
    }

    #[Test]
    public function test_creating_skill_requires_name(): void
    {
        $response = $this->postJson('/api/v1/skills', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    #[Test]
    public function test_creating_skill_fails_with_duplicate_slug(): void
    {
        Skill::factory()->create(['slug' => 'php']);

        $response = $this->postJson('/api/v1/skills', [
            'name' => 'PHP',
            'slug' => 'php',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['slug']);
    }

    #[Test]
    public function test_can_show_skill(): void
    {
        $skill = Skill::factory()->create();

        $response = $this->getJson("/api/v1/skills/{$skill->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $skill->id)
            ->assertJsonPath('data.name', $skill->name);
    }

    #[Test]
    public function test_returns_404_for_missing_skill(): void
    {
        $response = $this->getJson('/api/v1/skills/999');

        $response->assertNotFound();
    }

    #[Test]
    public function test_can_update_skill(): void
    {
        $skill = Skill::factory()->create(['name' => 'Old Name']);

        $response = $this->putJson("/api/v1/skills/{$skill->id}", [
            'name' => 'New Name',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'New Name');

        $this->assertDatabaseHas('skills', [
            'id' => $skill->id,
            'name' => 'New Name',
        ]);
    }

    #[Test]
    public function test_can_delete_skill(): void
    {
        $skill = Skill::factory()->create();

        $response = $this->deleteJson("/api/v1/skills/{$skill->id}");

        $response->assertNoContent();

        $this->assertDatabaseMissing('skills', ['id' => $skill->id]);
    }
}
