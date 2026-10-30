<?php

namespace App\Services;

use App\Models\{Profile, Skill, Project};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Pagination\LengthAwarePaginator;

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
                $query->whereRaw('LOWER(profiles.location) LIKE ?', ['%' . mb_strtolower((string) $location) . '%']);
            })
            ->when($validatedData['category'] ?? null, function ($query, $category) {
                $query->whereHas(
                    'category',
                    fn ($q) => $q->whereRaw('LOWER(categories.name) LIKE ?', ['%' . mb_strtolower((string) $category) . '%'])
                );
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
     * @param  array{account_status?: string, category_id?: int, location?: string, limit?: int}  $filters
     * @return array{
     *     total: int,
     *     by_account_status: list<array{label: mixed, total: int}>,
     *     by_status: list<array{label: mixed, total: int}>,
     *     by_stack: \Illuminate\Support\Collection<int, array{category_id: mixed, label: mixed, total: int}>,
     *     by_location: list<array{label: mixed, total: int}>,
     *     by_skill: \Illuminate\Support\Collection<int, array{skill_id: mixed, label: mixed, total: int, avg_proficiency: float|null, avg_years_experience: float|null}>,
     *     by_signup_month: \Illuminate\Support\Collection<int, array{label: mixed, total: int}>,
     *     by_experience_range: \Illuminate\Support\Collection<int, array{label: mixed, total: int}>
     * }
     */
    public function stats(array $filters): array
    {
        $limit = (int) ($filters['limit'] ?? 10);

        $base = fn () => Profile::query()
            ->when(isset($filters['account_status']), fn ($q) => $q->where('profiles.account_status', $filters['account_status']))
            ->when(isset($filters['category_id']), fn ($q) => $q->where('profiles.category_id', $filters['category_id']))
            ->when(isset($filters['location']), fn ($q) => $q->where('profiles.location', $filters['location']));

        $grouped = fn (string $column) => $base()
            ->selectRaw("profiles.{$column} as label, count(*) as total")
            ->groupBy("profiles.{$column}")
            ->orderByDesc('total')
            ->orderBy('label');

        $bySkill = $base()
            ->join('profile_skill', 'profile_skill.profile_id', '=', 'profiles.id')
            ->join('skills', 'skills.id', '=', 'profile_skill.skill_id')
            ->selectRaw('skills.id as skill_id, skills.name as label, count(*) as total, avg(profile_skill.proficiency) as avg_proficiency, avg(profile_skill.years_experience) as avg_years_experience')
            ->groupBy('skills.id', 'skills.name')
            ->orderByDesc('total')
            ->orderBy('label')
            ->limit($limit)
            ->toBase()
            ->get()
            ->map(fn ($row) => [
                'skill_id' => $row->skill_id,
                'label' => $row->label,
                'total' => (int) $row->total,
                'avg_proficiency' => $row->avg_proficiency === null ? null : round((float) $row->avg_proficiency, 2),
                'avg_years_experience' => $row->avg_years_experience === null ? null : round((float) $row->avg_years_experience, 2),
            ]);

        $byStack = $base()
            ->leftJoin('categories', 'categories.id', '=', 'profiles.category_id')
            ->selectRaw('profiles.category_id as category_id, categories.name as label, count(*) as total')
            ->groupBy('profiles.category_id', 'categories.name')
            ->orderByDesc('total')
            ->orderBy('label')
            ->toBase()
            ->get()
            ->map(fn ($row) => [
                'category_id' => $row->category_id,
                'label' => $row->label,
                'total' => (int) $row->total,
            ]);

        $monthExpression = match (DB::connection()->getDriverName()) {
            'sqlite' => "strftime('%Y-%m', profiles.created_at)",
            'pgsql' => "to_char(profiles.created_at, 'YYYY-MM')",
            default => "date_format(profiles.created_at, '%Y-%m')",
        };

        $byMonth = $base()
            ->selectRaw("{$monthExpression} as label, count(*) as total")
            ->groupBy('label')
            ->orderBy('label')
            ->toBase()
            ->get()
            ->map(fn ($row) => ['label' => $row->label, 'total' => (int) $row->total])
            ->values();

        // Experience = highest years_experience declared across the profile's skills
        $experience = DB::table('profile_skill')
            ->selectRaw('profile_id, max(years_experience) as years')
            ->groupBy('profile_id');

        $rangeOrder = ['0-1', '2-3', '4-5', '6-10', '10+', 'unknown'];
        $byExperience = $base()
            ->leftJoinSub($experience, 'exp', 'exp.profile_id', '=', 'profiles.id')
            ->selectRaw("case when exp.years is null then 'unknown' when exp.years <= 1 then '0-1' when exp.years <= 3 then '2-3' when exp.years <= 5 then '4-5' when exp.years <= 10 then '6-10' else '10+' end as label, count(*) as total")
            ->groupBy('label')
            ->toBase()
            ->get()
            ->sortBy(fn ($row) => array_search($row->label, $rangeOrder, true))
            ->map(fn ($row) => ['label' => $row->label, 'total' => (int) $row->total])
            ->values();

        $simple = fn ($rows) => $rows->map(fn ($row) => [
            'label' => $row->label instanceof \BackedEnum ? $row->label->value : $row->label,
            'total' => (int) $row->total,
        ])->values()->all();

        return [
            'total' => $base()->count(),
            'by_account_status' => $simple($grouped('account_status')->toBase()->get()),
            'by_status' => $simple($grouped('status')->toBase()->get()),
            'by_stack' => $byStack,
            'by_location' => $simple($grouped('location')->limit($limit)->toBase()->get()),
            'by_skill' => $bySkill,
            'by_signup_month' => $byMonth,
            'by_experience_range' => $byExperience,
        ];
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
