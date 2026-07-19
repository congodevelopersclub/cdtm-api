<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use App\Models\Skill;

class SkillSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $skills = [
            'Java',
            'Fortran',
            'Cobol',
            'Assembly',
            'C',
            'Cpp',
            'Perl',
            'SQL',
            'VB.NET',
            'CSharp',
            'J2EE',
            'ASP.NET',
            'Python',
            'PHP',
            'Laravel',
            'JavaScript',
            'Vue.js',
            'React',
            'MySQL',
            'Docker',
            'Git',
        ];

        foreach ($skills as $skill) {
            Skill::create([
                'name' => $skill,
                'slug' => Str::slug($skill),
            ]);
        }
    }
}
