<?php

namespace Tests\Feature\Api\V1;

use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CategoryControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Sanctum::actingAs(User::factory()->create());
    }

    #[Test]
    public function test_categories_require_authentication(): void
    {
        $this->app['auth']->forgetGuards();

        $this->getJson('/api/v1/categories')->assertUnauthorized();
        $this->postJson('/api/v1/categories', ['name' => 'Backend'])->assertUnauthorized();
    }

    #[Test]
    public function test_can_paginate_categories(): void
    {
        Category::factory()->count(21)->create();

        $response = $this->getJson('/api/v1/categories?page=2');

        $response->assertOk()
            ->assertJsonPath('current_page', 2)
            ->assertJsonCount(1, 'data');
    }

    #[Test]
    public function test_can_create_category_with_explicit_fields(): void
    {
        $response = $this->postJson('/api/v1/categories', [
            'name' => 'DevOps',
            'slug' => 'devops',
            'description' => 'Infrastructure and deployment',
            'is_active' => false,
            'sort_order' => 10,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.slug', 'devops')
            ->assertJsonPath('data.is_active', false)
            ->assertJsonPath('data.sort_order', 10);
    }

    #[Test]
    public function test_can_list_categories(): void
    {
        Category::factory()->create(['name' => 'Backend', 'slug' => 'backend', 'is_active' => true, 'sort_order' => 1]);
        Category::factory()->create(['name' => 'Frontend', 'slug' => 'frontend', 'is_active' => true, 'sort_order' => 2]);
        Category::factory()->create(['name' => 'Inactive', 'slug' => 'inactive', 'is_active' => false, 'sort_order' => 3]);

        $response = $this->getJson('/api/v1/categories');

        $response->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.name', 'Backend')
            ->assertJsonPath('data.1.name', 'Frontend')
            ->assertJsonPath('data.2.name', 'Inactive')
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'slug', 'description', 'is_active', 'sort_order'],
                ],
            ]);
    }

    #[Test]
    public function test_can_create_category(): void
    {
        $payload = [
            'name' => 'Backend',
            'description' => 'Server-side development',
            'sort_order' => 1,
        ];

        $response = $this->postJson('/api/v1/categories', $payload);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Backend')
            ->assertJsonPath('data.slug', 'backend')
            ->assertJsonPath('data.description', 'Server-side development')
            ->assertJsonPath('data.is_active', true)
            ->assertJsonPath('data.sort_order', 1);

        $this->assertDatabaseHas('categories', [
            'name' => 'Backend',
            'slug' => 'backend',
        ]);
    }

    #[Test]
    public function test_creating_category_requires_name(): void
    {
        $response = $this->postJson('/api/v1/categories', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    #[Test]
    public function test_creating_category_fails_with_duplicate_slug(): void
    {
        Category::factory()->create(['slug' => 'backend']);

        $response = $this->postJson('/api/v1/categories', [
            'name' => 'Backend',
            'slug' => 'backend',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['slug']);
    }

    #[Test]
    public function test_can_show_category(): void
    {
        $category = Category::factory()->create(['name' => 'Backend', 'slug' => 'backend']);

        $response = $this->getJson("/api/v1/categories/{$category->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $category->id)
            ->assertJsonPath('data.name', 'Backend');
    }

    #[Test]
    public function test_returns_404_for_missing_category(): void
    {
        $response = $this->getJson('/api/v1/categories/999');

        $response->assertNotFound();
    }

    #[Test]
    public function test_can_update_category(): void
    {
        $category = Category::factory()->create(['name' => 'Old Name', 'slug' => 'old-name']);

        $response = $this->putJson("/api/v1/categories/{$category->id}", [
            'name' => 'New Name',
            'is_active' => false,
            'sort_order' => 5,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'New Name')
            ->assertJsonPath('data.is_active', false)
            ->assertJsonPath('data.sort_order', 5);

        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'name' => 'New Name',
            'is_active' => false,
            'sort_order' => 5,
        ]);
    }

    #[Test]
    public function test_updating_category_fails_with_duplicate_slug(): void
    {
        Category::factory()->create(['slug' => 'backend']);
        $category = Category::factory()->create(['slug' => 'frontend']);

        $response = $this->putJson("/api/v1/categories/{$category->id}", [
            'slug' => 'backend',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['slug']);
    }

    #[Test]
    public function test_returns_404_when_updating_missing_category(): void
    {
        $response = $this->putJson('/api/v1/categories/999', ['name' => 'Missing']);

        $response->assertNotFound();
    }

    #[Test]
    public function test_returns_404_when_deleting_missing_category(): void
    {
        $response = $this->deleteJson('/api/v1/categories/999');

        $response->assertNotFound();
    }

    #[Test]
    public function test_can_update_category_description(): void
    {
        $category = Category::factory()->create(['description' => null]);

        $response = $this->putJson("/api/v1/categories/{$category->id}", [
            'description' => 'Updated description',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.description', 'Updated description');
    }

    #[Test]
    public function test_can_delete_category(): void
    {
        $category = Category::factory()->create();

        $response = $this->deleteJson("/api/v1/categories/{$category->id}");

        $response->assertNoContent();

        $this->assertSoftDeleted('categories', ['id' => $category->id]);
    }
}
