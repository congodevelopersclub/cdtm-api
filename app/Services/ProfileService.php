<?php

namespace App\Services;

use App\Models\{Profile, Skill, Project};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProfileService
{
    /**
     * @param Profile $profile
     * @param array<string, mixed> $validatedData
    */
    public function updateProfile(Profile $profile, array $validatedData): Profile
    {
        DB::transaction(function () use ($validatedData, $profile) {
            // 1. Update basic profile fields
            $profile->update(collect($validatedData)->only(['name', 'bio', 'location', 'headline'])->toArray());

            // 2. Sync skills (only touches this if 'skills' key was sent)
            if (array_key_exists('skills', $validatedData)) {
                $this->syncSkills($profile, $validatedData['skills']);
            }

            // 3. Sync projects (create/update/delete) (only if 'projects' was sent)
            if (array_key_exists('projects', $validatedData)) {
                $this->syncProjects($profile, $validatedData['projects']);
            }
        });

        return $profile->load(['skills', 'projects']);
    }

    /**
     * @param Profile $profile
     * @param array<string, mixed> $validatedData
    */
    public function validateProfile(Profile $profile, array $validatedData): Profile
    {
        if (isset($validatedData['account_status'])) {
            $profile->account_status = $validatedData['account_status'];
            $profile->save();
        }

        return $profile;
    }

    /**
     * @param Profile $profile
     * @param array<string, mixed> $skillsInput
    */
    private function syncSkills(Profile $profile, array $skillsInput): void
    {
        $syncData = [];

        foreach ($skillsInput as $skillInput) {
            $skill = Skill::firstOrCreate(
                ['slug' => Str::slug($skillInput['name'])],
                ['name' => $skillInput['name']]
            );

            $syncData[$skill->id] = [
                'proficiency' => $skillInput['proficiency'] ?? null,
                'years_experience' => $skillInput['years_experience'] ?? null,
            ];
        }

        $profile->skills()->sync($syncData);
    }

    /**
     * @param Profile $profile
     * @param array<string, mixed> $projectsInput
    */
    private function syncProjects(Profile $profile, array $projectsInput): void
    {
        $incomingIds = collect($projectsInput)->pluck('id')->filter()->toArray();

        $profile->projects()
            ->whereNotIn('id', $incomingIds)
            ->delete();

        foreach ($projectsInput as $projectData) {
            $attributes = [
                'title' => $projectData['title'],
                'description' => $projectData['description'] ?? null,
                'link' => $projectData['link'] ?? null,
            ];

            if (isset($projectData['id'])) {
                $profile->projects()
                    ->where('id', $projectData['id'])
                    ->update($attributes);
            } else {
                $profile->projects()->create($attributes);
            }
        }

        $myvar = $this->testing();
    }



    private function testing(): string {

            return 'Hello CDC';
    }

}
