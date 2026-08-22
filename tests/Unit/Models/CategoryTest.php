<?php

namespace Tests\Unit\Models;

use App\Models\Category;
use App\Models\Profile;
use App\Models\Project;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CategoryTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function test_can_create_category(): void
    {
        $category = Category::factory()->create([
            'name' => 'Backend',
            'slug' => 'backend',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'name' => 'Backend',
            'slug' => 'backend',
            'sort_order' => 1,
        ]);
    }

    #[Test]
    public function test_is_active_is_cast_to_boolean(): void
    {
        $category = Category::factory()->create(['is_active' => 1]);

        $this->assertTrue($category->is_active);
        $this->assertIsBool($category->is_active);
    }

    #[Test]
    public function test_can_soft_delete_category(): void
    {
        $category = Category::factory()->create();

        $category->delete();

        $this->assertSoftDeleted($category);
        $this->assertNull(Category::query()->find($category->id));
        $this->assertNotNull(Category::withTrashed()->find($category->id));
    }

    #[Test]
    public function test_profile_belongs_to_category(): void
    {
        $category = Category::factory()->create();
        $profile = Profile::factory()->create(['category_id' => $category->id]);

        $this->assertTrue($profile->category->is($category));
        $this->assertTrue($category->profiles->contains($profile));
    }

    #[Test]
    public function test_profiles_relation(): void
    {
        $category = Category::factory()->create();

        $relation = $category->profiles();

        $this->assertInstanceOf(HasMany::class, $relation);
        $this->assertSame(Profile::class, $relation->getRelated()::class);
    }

    #[Test]
    public function test_projects_relation(): void
    {
        $category = Category::factory()->create();

        $relation = $category->projects();

        $this->assertInstanceOf(HasMany::class, $relation);
        $this->assertSame(Project::class, $relation->getRelated()::class);
    }
}
