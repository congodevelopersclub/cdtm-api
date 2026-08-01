<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'UpdateProfileRequest',
    title: 'Update Profile Request',
    description: 'Request payload for updating a user profile',
    properties: [
        new OA\Property(property: 'name', type: 'string', description: 'The profile name', example: 'John Doe'),
        new OA\Property(property: 'bio', type: 'string', description: 'The profile bio', example: 'Product designer based in Lille.'),
        new OA\Property(property: 'headline', type: 'string', description: 'The profile headline', example: 'Senior Product Designer'),
        new OA\Property(property: 'location', type: 'string', description: 'The profile location', example: 'Lille, France'),
        new OA\Property(
            property: 'skills',
            description: 'Skills linked to this profile',
            type: 'array',
            items: new OA\Items(
                properties: [
                    new OA\Property(property: 'name', type: 'string', description: 'Skill name', example: 'Laravel'),
                    new OA\Property(property: 'proficiency', type: 'integer', description: 'Skill proficiency level (1-5)', example: 4),
                    new OA\Property(property: 'years_experience', type: 'integer', description: 'Years of experience with the skill', example: 3),
                ],
                type: 'object'
            )
        ),
        new OA\Property(
            property: 'projects',
            description: 'Projects linked to this profile',
            type: 'array',
            items: new OA\Items(
                properties: [
                    new OA\Property(property: 'id', type: ['integer','null'], description:'Project ID (nullable for new projects)', example:null),
                    new OA\Property(property:'title', type:'string', description:'Project title', example:'Portfolio Website'),
                    new OA\Property(property:'description', type:['string','null'], description:'Project description (nullable)', example:'A personal portfolio website built with Laravel.'),
                    new OA\Property(property:'link', type:['string','null'], description:'Project link (nullable)', example:'https://portfolio.example.com'),
                ],
                type:'object'
            )
        ),
    ]
)]
class UpdateProfileRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'bio' => ['nullable', 'string', 'max:2000'],
            'headline' => ['nullable', 'string', 'max:2000'],
            'location' => ['nullable', 'string', 'max:255'],

           // Skills
            'skills' => ['sometimes', 'array'],
            'skills.*' => ['array'],
            'skills.*.name' => ['required', 'string', 'max:100'],
            'skills.*.proficiency' => ['nullable', 'integer', 'min:1', 'max:5'],
            'skills.*.years_experience' => ['nullable', 'integer', 'min:0', 'max:60'],

            // Projects
            'projects' => ['sometimes', 'array'],
            'projects.*' => ['array'],
            'projects.*.id' => [
                'nullable', 'integer',
                Rule::exists('projects', 'id')->where('profile_id', $this->route('profile')->id),
            ],
            'projects.*.title' => ['required', 'string', 'max:255'],
            'projects.*.description' => ['nullable', 'string'],
            'projects.*.link' => ['nullable', 'string', 'max:2048'],
        ];
    }
}
