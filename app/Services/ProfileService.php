<?php

namespace App\Services;

use App\Models\{Profile, Skill, Project};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;

class ProfileService
{
    /**
     * @param Profile $profile
     * @param array<string, mixed> $validatedData
    */
    public function updateProfile(Profile $profile, array $validatedData): Profile
    {
        if (array_key_exists('links', $validatedData)) {
            $validatedData['links'] = $validatedData['links'] === '' ? null : $validatedData['links'];
        }

        DB::transaction(function () use ($validatedData, $profile) {
            // 1. Update basic profile fields
            $profile->update(collect($validatedData)->only(['name', 'bio', 'location', 'headline', 'category_id', 'links'])->toArray());

            // 2. Sync skills (only touches this if 'skills' key was sent)
            if (array_key_exists('skills', $validatedData)) {
                $this->syncSkills($profile, $validatedData['skills']);
            }

            // 3. Sync projects (create/update/delete) (only if 'projects' was sent)
            if (array_key_exists('projects', $validatedData)) {
                $this->syncProjects($profile, $validatedData['projects']);
            }
        });

        return $profile->load(['skills', 'projects', 'category']);
    }

    /**
      * @param  array{location?: string|null, category?: string|null, skills?: string|null}  $validatedData
      * @return LengthAwarePaginator<int, Profile>
      */
    // TODO: This query needs to be optmized as soon as possible
    public function search(array $validatedData): LengthAwarePaginator
    {
        return Profile::with(['skills', 'projects', 'category'])
            ->when($validatedData['location'] ?? null, function ($query, $location) {
                $query->where('location', 'like', "%{$location}%");
            })
            ->when($validatedData['category'] ?? null, function ($query, $category) {
                $query->whereHas('category', fn ($q) => $q->where('name', $category));
            })
            ->when($validatedData['skills'] ?? null, function ($query, $skills) {
                $names = array_values(array_unique(array_filter(array_map('trim', explode(',', $skills)), static fn (string $name): bool => $name !== '')));
                if ($names !== []) {
                    $matchingSkills = fn ($q) => $q->whereIn('skills.slug', $names);
                    $query
                        ->whereHas('skills', $matchingSkills)  // at least one match
                        ->withCount(['skills as matched_skills_count' => $matchingSkills])
                        ->orderByDesc('matched_skills_count'); // most matches first
                }
            })
            ->orderBy('profiles.id') // tie-breaker for stable pagination
            ->paginate(20)
            ->withQueryString();
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
    }

}
