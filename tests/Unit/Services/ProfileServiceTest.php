<?php

namespace Tests\Unit\Services;

use App\Models\Profile;
use App\Models\Project;
use App\Models\Skill;
use App\Services\ProfileService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProfileServiceTest extends TestCase
{
    use RefreshDatabase;

    private ProfileService $profileService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->profileService = new ProfileService();
    }

    #[Test]
    public function it_updates_only_the_allowed_basic_profile_fields(): void
    {
        $profile = Profile::factory()->create([
            'name'     => 'Old Name',
            'email'    => 'old@example.com',
            'bio'      => 'Old bio',
            'location' => 'Old Location',
            'headline' => 'Old Headline',
        ]);

        $result = $this->profileService->updateProfile($profile, [
            'name'          => 'New Name',
            'email'         => 'new@example.com',
            'bio'           => 'New bio',
            'location'      => 'New Location',
            'headline'      => 'New Headline',
            'account_status' => 'SHOULD_NOT_BE_SET', // not in the allowed list
        ]);

        $this->assertSame('New Name', $result->name);
        $this->assertSame('old@example.com', $result->email);
        $this->assertSame('New bio', $result->bio);
        $this->assertSame('New Location', $result->location);
        $this->assertSame('New Headline', $result->headline);

        $this->assertDatabaseHas('profiles', [
            'id'       => $profile->id,
            'name'     => 'New Name',
            'email'    => 'old@example.com',
            'bio'      => 'New bio',
            'location' => 'New Location',
            'headline' => 'New Headline',
        ]);

        // account_status was not in ['name','bio','location','headline'], so it must be untouched
        $this->assertDatabaseMissing('profiles', [
            'id'             => $profile->id,
            'account_status' => 'SHOULD_NOT_BE_SET',
        ]);
    }

    #[Test]
    public function it_returns_the_profile_with_skills_and_projects_eager_loaded(): void
    {
        $profile = Profile::factory()->create();

        $result = $this->profileService->updateProfile($profile, [
            'name' => 'Someone',
        ]);

        $this->assertTrue($result->relationLoaded('skills'));
        $this->assertTrue($result->relationLoaded('projects'));
    }

    #[Test]
    public function it_does_not_touch_skills_when_the_skills_key_is_absent(): void
    {
        $profile = Profile::factory()->create();
        $existingSkill = Skill::factory()->create(['name' => 'PHP', 'slug' => 'php']);
        $profile->skills()->attach($existingSkill->id, ['proficiency' => 'expert', 'years_experience' => 5]);

        $this->profileService->updateProfile($profile, [
            'name' => 'Someone Else',
        ]);

        $this->assertDatabaseHas('profile_skill', [
            'profile_id' => $profile->id,
            'skill_id'   => $existingSkill->id,
        ]);
    }

    #[Test]
    public function it_creates_a_new_skill_and_attaches_it_with_pivot_data_when_skill_does_not_exist(): void
    {
        $profile = Profile::factory()->create();

        $this->profileService->updateProfile($profile, [
            'skills' => [
                ['name' => 'Laravel', 'proficiency' => 'advanced', 'years_experience' => 3],
            ],
        ]);

        $skill = Skill::where('slug', 'laravel')->first();

        $this->assertNotNull($skill);
        $this->assertSame('Laravel', $skill->name);

        $this->assertDatabaseHas('profile_skill', [
            'profile_id'        => $profile->id,
            'skill_id'          => $skill->id,
            'proficiency'       => 'advanced',
            'years_experience'  => 3,
        ]);
    }

    #[Test]
    public function it_reuses_an_existing_skill_matched_by_slug_instead_of_duplicating_it(): void
    {
        $profile = Profile::factory()->create();
        $existingSkill = Skill::factory()->create(['name' => 'Laravel', 'slug' => 'laravel']);

        $this->profileService->updateProfile($profile, [
            'skills' => [
                ['name' => 'Laravel', 'proficiency' => 'expert', 'years_experience' => 7],
            ],
        ]);

        $this->assertSame(1, Skill::where('slug', 'laravel')->count());

        $this->assertDatabaseHas('profile_skill', [
            'profile_id' => $profile->id,
            'skill_id'   => $existingSkill->id,
            'proficiency' => 'expert',
        ]);
    }

    #[Test]
    public function it_removes_skills_that_are_no_longer_present_in_the_incoming_list(): void
    {
        $profile = Profile::factory()->create();
        $skillToKeep = Skill::factory()->create(['name' => 'PHP', 'slug' => 'php']);
        $skillToRemove = Skill::factory()->create(['name' => 'Ruby', 'slug' => 'ruby']);

        $profile->skills()->sync([
            $skillToKeep->id   => ['proficiency' => 'expert', 'years_experience' => 5],
            $skillToRemove->id => ['proficiency' => 'beginner', 'years_experience' => 1],
        ]);

        $this->profileService->updateProfile($profile, [
            'skills' => [
                ['name' => 'PHP', 'proficiency' => 'expert', 'years_experience' => 5],
            ],
        ]);

        $this->assertDatabaseHas('profile_skill', [
            'profile_id' => $profile->id,
            'skill_id'   => $skillToKeep->id,
        ]);

        $this->assertDatabaseMissing('profile_skill', [
            'profile_id' => $profile->id,
            'skill_id'   => $skillToRemove->id,
        ]);
    }

    #[Test]
    public function it_detaches_all_skills_when_an_empty_skills_array_is_sent(): void
    {
        $profile = Profile::factory()->create();
        $skill = Skill::factory()->create();
        $profile->skills()->attach($skill->id, ['proficiency' => 'expert', 'years_experience' => 5]);

        $this->profileService->updateProfile($profile, [
            'skills' => [],
        ]);

        $this->assertDatabaseMissing('profile_skill', [
            'profile_id' => $profile->id,
            'skill_id'   => $skill->id,
        ]);
    }

    #[Test]
    public function it_does_not_touch_projects_when_the_projects_key_is_absent(): void
    {
        $profile = Profile::factory()->create();
        $existingProject = Project::factory()->create(['profile_id' => $profile->id, 'title' => 'Existing']);

        $this->profileService->updateProfile($profile, [
            'name' => 'Someone Else',
        ]);

        $this->assertDatabaseHas('projects', [
            'id' => $existingProject->id,
        ]);
    }

    #[Test]
    public function it_creates_new_projects_that_have_no_id(): void
    {
        $profile = Profile::factory()->create();

        $this->profileService->updateProfile($profile, [
            'projects' => [
                [
                    'title'       => 'New Project',
                    'description' => 'A cool project',
                    'link'        => 'https://example.com',
                ],
            ],
        ]);

        $this->assertDatabaseHas('projects', [
            'profile_id'  => $profile->id,
            'title'       => 'New Project',
            'description' => 'A cool project',
            'link'        => 'https://example.com',
        ]);
    }

    #[Test]
    public function it_updates_an_existing_project_when_an_id_is_provided(): void
    {
        $profile = Profile::factory()->create();
        $project = Project::factory()->create([
            'profile_id' => $profile->id,
            'title'      => 'Old Title',
        ]);

        $this->profileService->updateProfile($profile, [
            'projects' => [
                [
                    'id'          => $project->id,
                    'title'       => 'Updated Title',
                    'description' => 'Updated description',
                ],
            ],
        ]);

        $this->assertDatabaseHas('projects', [
            'id'          => $project->id,
            'title'       => 'Updated Title',
            'description' => 'Updated description',
        ]);

        // No duplicate project should have been created
        $this->assertSame(1, Project::where('profile_id', $profile->id)->count());
    }

    #[Test]
    public function it_deletes_projects_that_are_not_present_in_the_incoming_list(): void
    {
        $profile = Profile::factory()->create();
        $keptProject = Project::factory()->create(['profile_id' => $profile->id, 'title' => 'Keep me', 'description' => 'I should stay']);
        $removedProject = Project::factory()->create(['profile_id' => $profile->id, 'title' => 'Remove me', 'description' => 'I should be deleted']);

        $this->profileService->updateProfile($profile, [
            'projects' => [
                ['id' => $keptProject->id, 'title' => 'Keep me', 'description' => 'I should stay'],
            ],
        ]);

        $this->assertDatabaseHas('projects', ['id' => $keptProject->id]);
        $this->assertDatabaseMissing('projects', ['id' => $removedProject->id]);
    }

    #[Test]
    public function it_deletes_all_projects_when_an_empty_projects_array_is_sent(): void
    {
        $profile = Profile::factory()->create();
        $project = Project::factory()->create(['profile_id' => $profile->id]);

        $this->profileService->updateProfile($profile, [
            'projects' => [],
        ]);

        $this->assertDatabaseMissing('projects', ['id' => $project->id]);
    }

    #[Test]
    public function it_creates_and_deletes_projects_in_the_same_call(): void
    {
        $profile = Profile::factory()->create();
        $existingProject = Project::factory()->create(['profile_id' => $profile->id, 'title' => 'Old One', 'description' => 'I should be updated']);

        $this->profileService->updateProfile($profile, [
            'projects' => [
                ['title' => 'Brand New Project', 'description' => 'Freshly created'],
            ],
        ]);

        $this->assertDatabaseMissing('projects', ['id' => $existingProject->id]);
        $this->assertDatabaseHas('projects', [
            'profile_id' => $profile->id,
            'title'      => 'Brand New Project',
        ]);
        $this->assertSame(1, Project::where('profile_id', $profile->id)->count());
    }

    #[Test]
    public function it_rolls_back_all_changes_when_something_inside_the_transaction_fails(): void
    {
        $profile = Profile::factory()->create(['name' => 'Original Name']);
        $existingProject = Project::factory()->create(['profile_id' => $profile->id, 'title' => 'Untouched']);

        // Force a failure partway through the transaction by sending
        // a project payload missing the required 'title' field, which
        // will throw when the DB layer rejects the null/undefined value.
        try {
            $this->profileService->updateProfile($profile, [
                'name'     => 'Should Not Persist',
                'projects' => [
                    ['description' => 'Missing required title'],
                ],
            ]);

            $this->fail('Expected an exception due to a missing required project field.');
        } catch (\Throwable $e) {
            // expected
        }

        $this->assertDatabaseHas('profiles', [
            'id'   => $profile->id,
            'name' => 'Original Name',
        ]);

        $this->assertDatabaseHas('projects', [
            'id'    => $existingProject->id,
            'title' => 'Untouched',
        ]);
    }

    #[Test]
    public function it_wraps_the_whole_update_in_a_single_database_transaction(): void
    {
        DB::spy();

        $profile = Profile::factory()->create();

        $this->profileService->updateProfile($profile, [
            'name'     => 'Transactional Update',
            'skills'   => [['name' => 'Go', 'proficiency' => 'intermediate']],
            'projects' => [['title' => 'Some Project']],
        ]);

        DB::shouldHaveReceived('transaction')->once();
    }

     #[Test]
    public function it_does_not_save_when_account_status_is_explicitly_null(): void
    {
        $profile = Profile::factory()->create(['account_status' => \App\Enums\ProfileAccountStatus::PENDING_VALIDATION]);
 
        $result = $this->profileService->validateProfile($profile, [
            'account_status' => null,
        ]);
 
        $this->assertSame(\App\Enums\ProfileAccountStatus::PENDING_VALIDATION, $result->account_status);
 
        $this->assertDatabaseHas('profiles', [
            'id'             => $profile->id,
            'account_status' => \App\Enums\ProfileAccountStatus::PENDING_VALIDATION,
        ]);
    }
 
    #[Test]
    public function it_persists_the_account_status_change_immediately_without_a_transaction(): void
    {
        $profile = Profile::factory()->create(['account_status' => \App\Enums\ProfileAccountStatus::PENDING_VALIDATION]);
 
        $this->profileService->validateProfile($profile, [
            'account_status' => \App\Enums\ProfileAccountStatus::VALIDATED,
        ]);
 
        $fresh = Profile::find($profile->id);
 
        $this->assertSame(\App\Enums\ProfileAccountStatus::VALIDATED, $fresh->account_status);
    }
}
